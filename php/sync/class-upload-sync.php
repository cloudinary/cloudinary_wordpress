<?php
/**
 * Upload Sync to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;
use Cloudinary\Utils;

/**
 * Class Upload_Sync.
 *
 * Push media to Cloudinary on upload.
 */
class Upload_Sync {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Plugin Instance of the global plugin.
	 */
	protected $plugin;

	/**
	 * The Push_Sync object.
	 *
	 * @var \Cloudinary\Sync\Push_Sync
	 */
	private $pusher;

	/**
	 * Holds the main Sync Class.
	 *
	 * @var \Cloudinary\Sync
	 */
	protected $sync;

	/**
	 * Holds the Connect Class.
	 *
	 * @var \Cloudinary\Connect
	 */
	protected $connect;

	/**
	 * Holds the Media Class.
	 *
	 * @var \Cloudinary\Media
	 */
	protected $media;

	/**
	 * This feature is enabled.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Upload_Sync constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin  The plugin.
	 * @param bool               $enabled Is this feature enabled.
	 * @param object             $pusher  An object that implements `push_attachments`. Default: null.
	 */
	public function __construct( \Cloudinary\Plugin $plugin, $enabled = false, $pusher = null ) {
		$this->plugin  = $plugin;
		$this->pusher  = $pusher;
		$this->enabled = $enabled;
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		// Hook into auto upload sync.
		add_filter( 'cloudinary_on_demand_sync_enabled', array( $this, 'auto_sync_enabled' ), 10, 2 );
		// Handle bulk and inline actions.
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
		// Add inline action.
		add_filter( 'media_row_actions', array( $this, 'add_inline_action' ), 10, 2 );

		// Add Bulk actions.
		add_filter(
			'bulk_actions-upload',
			function ( $actions ) {
				$cloudinary_actions = array(
					'cloudinary-push'     => __( 'Sync with Cloudinary', 'cloudinary' ),
					'cloudinary-clean-up' => __( 'Fix Cloudinary Sync Errors', 'cloudinary' ),
				);

				return array_merge( $cloudinary_actions, $actions );
			}
		);
	}

	/**
	 * Add an inline action for manual sync.
	 *
	 * @param array    $actions All actions.
	 * @param \WP_Post $post    The current post object.
	 *
	 * @return array
	 */
	public function add_inline_action( $actions, $post ) {
		if (
			$this->sync->is_syncable( $post->ID )
			&& $this->media->is_uploadable_media( $post->ID )
			&& $this->media->is_media( $post->ID )
			&& Utils::user_can( 'manage_assets', 'delete_post', 'bulk_actions', $post->ID )
			&& $this->media->plugin->get_component( 'delivery' )->is_deliverable( $post->ID )
		) {
			$action_url = add_query_arg(
				array(
					'action'   => 'cloudinary-push',
					'media[]'  => $post->ID,
					'_wpnonce' => wp_create_nonce( 'bulk-media' ),
				),
				'upload.php'
			);
			if ( ! $this->media->is_uploadable_media( $post->ID ) ) {
				return $actions;
			}
			if ( ! $this->sync->is_syncable( $post->ID ) ) {
				return $actions;
			}
			if ( ! $this->plugin->components['sync']->is_synced( $post->ID ) ) {
				$actions['cloudinary-push'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					$action_url,
					esc_attr__( 'Sync and deliver from Cloudinary', 'cloudinary' ),
					esc_html__( 'Sync and deliver from Cloudinary', 'cloudinary' )
				);
			} elseif ( file_exists( get_attached_file( $post->ID ) ) ) {
				$actions['cloudinary-push'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					$action_url,
					esc_attr__( 'Re-sync to Cloudinary', 'cloudinary' ),
					esc_html__( 'Re-sync to Cloudinary', 'cloudinary' )
				);
			}
		}

		if (
			$this->sync->is_syncable( $post->ID )
			&& ! empty( get_post_meta( $post->ID, Sync::META_KEYS['sync_error'], true ) )
		) {
			$actions['cloudinary-clean-up'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				add_query_arg(
					array(
						'action'   => 'cloudinary-clean-up',
						'media[]'  => $post->ID,
						'_wpnonce' => wp_create_nonce( 'bulk-media' ),
					),
					admin_url( 'upload.php' )
				),
				esc_attr__( 'Fix Cloudinary Sync Error', 'cloudinary' ),
				esc_html__( 'Fix Cloudinary Sync Error', 'cloudinary' )
			);
		}

		return $actions;
	}

	/**
	 * Handles bulk actions for attachments.
	 *
	 * @param string $location The location to redirect after.
	 * @param string $action   The action to handle.
	 * @param array  $post_ids Post ID's to action.
	 *
	 * @return string
	 */
	public function handle_bulk_actions( $location, $action, $post_ids ) {

		switch ( $action ) {
			case 'cloudinary-push':
				foreach ( $post_ids as $post_id ) {
					if ( ! $this->sync->is_syncable( $post_id ) ) {
						continue;
					}

					// It's required to perform a new sync that Cloudinary and WordPress storage is set.
					if (
						$this->plugin->components['sync']->been_synced( $post_id ) &&
						'dual_full' !== $this->plugin->settings->find_setting( 'offload' )->get_value()
					) {
						continue;
					}

					// Clean up for previous syncs and start over.
					if ( ! $this->media->is_cloudinary_url( get_post_meta( $post_id, '_wp_attached_file', true ) ) ) {
						$this->sync->delete_cloudinary_meta( $post_id );
						$this->sync->set_signature_item( $post_id, 'file', '' );
						$this->sync->set_signature_item( $post_id, 'edit', '' );
						$this->sync->set_signature_item( $post_id, 'cld_asset' );
						$this->media->delete_post_meta( $post_id, Sync::META_KEYS['public_id'] );
						$this->sync->add_to_sync( $post_id );
					}
				}
				break;

			case 'cloudinary-clean-up':
				foreach ( $post_ids as $post_id ) {
					if ( ! $this->sync->is_syncable( $post_id ) ) {
						continue;
					}
					delete_post_meta( $post_id, Sync::META_KEYS['sync_error'] );
				}
				break;
		}

		return $location;
	}

	/**
	 * Check if auto-sync is enabled.
	 *
	 * @param bool $enabled Flag to determine if autosync is enabled.
	 * @param int  $post_id The post id currently processing.
	 *
	 * @return bool
	 */
	public function auto_sync_enabled( $enabled, $post_id ) {
		if ( $this->plugin->components['settings']->is_auto_sync_enabled() ) {
			$enabled = true;
		}

		// Check if it was synced before to allow re-sync for changes.
		if ( ! empty( $this->plugin->components['sync']->get_signature( $post_id ) ) ) {
			$enabled = true;
		}

		return $enabled;
	}

	/**
	 * Setup this component.
	 */
	public function setup() {
		if ( empty( $this->pusher ) ) {
			$this->pusher  = $this->plugin->components['sync']->managers['push'];
			$this->sync    = $this->plugin->components['sync'];
			$this->connect = $this->plugin->components['connect'];
			$this->media   = $this->plugin->components['media'];
		}
		$this->register_hooks();
	}

	/**
	 * Filter the original image to return the full edited rather than the original source.
	 *
	 * @param string $original_image The original image path.
	 * @param int    $attachment_id  The attachment ID.
	 *
	 * @return string
	 */
	public function filter_backup_original( $original_image, $attachment_id ) {
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		if ( ! empty( $backup_sizes ) && ! empty( $backup_sizes['full-orig'] ) ) {
			// wp_get_original_image_path will always return the original.
			// So we need to determine the the current file is an edit or not.
			$attached_file = get_attached_file( $attachment_id, true );
			// The original will never be a -scaled.
			// If we scale the attached file and the original, they should match. Meaning the attached file is not an edit.
			if ( Utils::make_scaled_url( $original_image ) !== Utils::make_scaled_url( $attached_file ) ) {
				// Since attached file is an edit, we want to upload the edited file, not the original.
				$original_image = $attached_file;
			}
		}

		return $original_image;
	}

	/**
	 * Upload an edited asset.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array|\WP_Error
	 */
	public function edit_upload( $attachment_id ) {
		$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['relationship'] );
		$this->sync->set_signature_item( $attachment_id, 'delivery', 'reset' );

		return $this->upload_asset( $attachment_id, 'edit' );
	}

	/**
	 * Upload an asset to Cloudinary.
	 *
	 * @param int         $attachment_id The attachment ID.
	 * @param string|null $type          Optional Sync type.
	 * @param string|null $suffix        An optional suffix.
	 *
	 * @return array|\WP_Error
	 */
	public function upload_asset( $attachment_id, $type = null, $suffix = null ) {

		add_filter( 'cloudinary_doing_upload', '__return_true' );

		add_filter(
			'cloudinary_is_folder_synced',
			function ( $is_synced, $post_id ) use ( $attachment_id ) {
				if ( $post_id === $attachment_id ) {
					return true;
				}

				return $is_synced;
			},
			10,
			2
		);

		$options = $this->media->get_upload_options( $attachment_id, 'upload' );
		if ( empty( $type ) ) {
			$type = $this->sync->get_sync_type( $attachment_id );
		}
		// Add suffix.
		$options['public_id'] .= $suffix;

		// Run the upload Call.
		switch ( $type ) {
			case 'cloud_name':
			case 'folder':
				$result = $this->connect->api->copy( $attachment_id, $options );
				break;
			case 'edit':
				$file                 = get_attached_file( $attachment_id, true );
				$options['public_id'] = ltrim( path_join( dirname( $options['public_id'] ), Utils::pathinfo( $file, PATHINFO_FILENAME ) ), './' ) . $suffix;
				add_filter( 'wp_get_original_image_path', array( $this, 'filter_backup_original' ), 10, 2 );
				$options['overwrite'] = true; // It's safe to do this, since an edited file will be massively unique due to the -e{timestamp} suffix.
				$result               = $this->connect->api->upload( $attachment_id, $options, array(), false );
				remove_filter( 'wp_get_original_image_path', array( $this, 'filter_backup_original' ), 10 );
				break;
			default:
				$result = $this->connect->api->upload( $attachment_id, $options, array() );
				break;
		}
		remove_filter( 'cloudinary_doing_upload', '__return_true' );

		if ( ! is_wp_error( $result ) ) {

			// Check that this wasn't an existing.
			if ( ! empty( $result['existing'] ) ) {
				// Add a suffix and try again.
				$suffix = '_' . $attachment_id . substr( strrev( uniqid() ), 0, 5 );

				return $this->upload_asset( $attachment_id, $type, $suffix );
			}

			// Set folder Synced.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], $this->media->is_folder_synced( $attachment_id ) );
			// Set public_id.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $result['public_id'] );
			// Set version.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['version'], $result['version'] );
			// Set the delivery type.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['delivery'], $result['type'] );

			// Set the raw url.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['raw_url'], $result['secure_url'] );

			// Create a trackable key in post meta to allow getting the attachment id from URL with transformations.
			update_post_meta( $attachment_id, '_' . md5( $options['public_id'] ), true );

			// Create a trackable key in post meta to allow getting the attachment id from URL.
			update_post_meta( $attachment_id, '_' . md5( 'base_' . $options['public_id'] ), true );

			// Update signature for all that use the same method.
			$this->sync->sync_signature_by_type( $attachment_id, $type );
			// Update options and public_id as well (full sync).
			$this->sync->set_signature_item( $attachment_id, 'options' );
			$this->sync->set_signature_item( $attachment_id, 'public_id' );

			$this->update_breakpoints( $attachment_id, $result );
			delete_post_meta( $attachment_id, Sync::META_KEYS['sync_error'] );
		} else {
			update_post_meta( $attachment_id, Sync::META_KEYS['sync_error'], $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Update an assets context..
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array|\WP_Error
	 */
	public function context_update( $attachment_id ) {

		$options = $this->media->get_upload_options( $attachment_id, 'upload' );
		$result  = $this->connect->api->context( $options );

		if ( ! is_wp_error( $result ) ) {
			$this->sync->set_signature_item( $attachment_id, 'options' );
			update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $options['public_id'] );
		}

		return $result;
	}

	/**
	 * Perform an explicit update to Cloudinary.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array|\WP_Error|bool
	 */
	public function explicit_update( $attachment_id ) {
		// Explicit update.
		$type = $this->sync->get_sync_type( $attachment_id );
		$args = $this->media->get_breakpoint_options( $attachment_id );
		if ( ! empty( $args ) ) {
			$result = $this->connect->api->explicit( $args );
			if ( ! is_wp_error( $result ) ) {
				$this->update_breakpoints( $attachment_id, $result );
			}
		} else {
			$this->update_breakpoints( $attachment_id, array() );
			$result = true;
		}
		$this->sync->set_signature_item( $attachment_id, $type );

		return $result;
	}

	/**
	 * Update breakpoints for an asset.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $breakpoints   Structure of the breakpoints.
	 */
	public function update_breakpoints( $attachment_id, $breakpoints ) {

		if ( 'on' === $this->plugin->settings->get_value( 'enable_breakpoints' ) ) {
			if ( ! empty( $breakpoints['responsive_breakpoints'] ) ) { // Images only.
				$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['breakpoints'], $breakpoints['responsive_breakpoints'][0]['breakpoints'] );
			} elseif ( wp_attachment_is_image( $attachment_id ) ) {
				$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['breakpoints'] );
			}
			$this->sync->set_signature_item( $attachment_id, 'breakpoints' );
		}
	}
}
