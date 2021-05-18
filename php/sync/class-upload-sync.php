<?php
/**
 * Upload Sync to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;

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
					'cloudinary-push' => __( 'Push to Cloudinary', 'cloudinary' ),
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
		if ( $this->media->is_media( $post->ID ) && current_user_can( 'delete_post', $post->ID ) ) {
			$action_url = add_query_arg(
				array(
					'action'   => 'cloudinary-push',
					'media[]'  => $post->ID,
					'_wpnonce' => wp_create_nonce( 'bulk-media' ),
				),
				'upload.php'
			);
			if ( ! $this->media->is_local_media( $post->ID ) ) {
				return $actions;
			}
			if ( ! $this->sync->is_syncable( $post->ID ) ) {
				return $actions;
			}
			if ( ! $this->plugin->components['sync']->is_synced( $post->ID ) ) {
				$actions['cloudinary-push'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					$action_url,
					esc_attr__( 'Push to Cloudinary', 'cloudinary' ),
					esc_html__( 'Push to Cloudinary', 'cloudinary' )
				);
			} else {
				if ( file_exists( get_attached_file( $post->ID ) ) ) {
					$actions['cloudinary-push'] = sprintf(
						'<a href="%s" aria-label="%s">%s</a>',
						$action_url,
						esc_attr__( 'Re-sync to Cloudinary', 'cloudinary' ),
						esc_html__( 'Re-sync to Cloudinary', 'cloudinary' )
					);
				}
			}
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
						'dual_full' !== $this->plugin->settings->find_setting( 'offload' )->get_value() &&
						$this->plugin->components['sync']->is_synced( $post_id )
					) {
						continue;
					}

					// Clean up for previous syncs and start over.
					$this->sync->delete_cloudinary_meta( $post_id );
					$this->sync->set_signature_item( $post_id, 'file', '' );
					$this->media->delete_post_meta( $post_id, Sync::META_KEYS['public_id'] );
					$this->sync->add_to_sync( $post_id );
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
	 * Upload an asset to Cloudinary.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array|\WP_Error
	 */
	public function upload_asset( $attachment_id ) {

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

		$type       = $this->sync->get_sync_type( $attachment_id );
		$options    = $this->media->get_upload_options( $attachment_id );
		$public_id  = $options['public_id'];
		$try_remote = 'cloud_name' !== $type;

		// Add the suffix before uploading.
		if ( $this->media->get_public_id( $attachment_id ) === $public_id ) {
			// Only apply the saved suffix if the public_id is the same. This is to allow filtered ID's a change to have a suffix removed.
			$options['public_id'] .= $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['suffix'], true );
		} else {
			// If the public_id has been changed, remove the saved suffix.
			$this->media->delete_post_meta( $attachment_id, Sync::META_KEYS['suffix'] );
		}

		// Run the upload Call.
		$result = $this->connect->api->upload( $attachment_id, $options, array(), $try_remote );

		remove_filter( 'cloudinary_doing_upload', '__return_true' );

		if ( ! is_wp_error( $result ) ) {

			// Check that this wasn't an existing.
			if ( ! empty( $result['existing'] ) ) {
				// Check to see if this is the same image.
				$version = $this->media->get_cloudinary_version( $attachment_id );
				if ( $version !== $result['version'] ) {
					// New image with the same name.
					// Add a suffix and try again.
					$suffix = '_' . $attachment_id . substr( strrev( uniqid() ), 0, 5 );
					$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['suffix'], $suffix );

					return $this->upload_asset( $attachment_id );
				}
			}

			// Set folder Synced.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], $this->media->is_folder_synced( $attachment_id ) );
			// Set public_id.
			update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );
			// Set version.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['version'], $result['version'] );
			// Set the delivery type.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['delivery'], $result['type'] );
			// Set traceable sync keys.
			update_post_meta( $attachment_id, '_' . md5( $options['public_id'] ), true );
			update_post_meta( $attachment_id, '_' . md5( 'base_' . $options['public_id'] ), true );
			// Update signature for all that use the same method.
			$this->sync->sync_signature_by_type( $attachment_id, $type );
			// Update options and public_id as well (full sync).
			$this->sync->set_signature_item( $attachment_id, 'options' );
			$this->sync->set_signature_item( $attachment_id, 'public_id' );

			$this->update_breakpoints( $attachment_id, $result );
			$this->update_content( $attachment_id );
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
		// dynamic sync type..
		$type    = $this->sync->get_sync_type( $attachment_id );
		$options = $this->media->get_upload_options( $attachment_id );
		$result  = $this->connect->api->context( $options );

		if ( ! is_wp_error( $result ) ) {
			$this->sync->set_signature_item( $attachment_id, $type );
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

	/**
	 * Trigger an update on content that contains the same attachment ID to allow filters to capture and process.
	 *
	 * @param int $attachment_id The attachment id to find and init an update.
	 */
	public function update_content( $attachment_id ) {
		// Search and update link references in content.
		$content_search = new \WP_Query(
			array(
				's'              => 'wp-image-' . $attachment_id,
				'fields'         => 'ids',
				'posts_per_page' => 1000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		); // phpcs:ignore WordPress.WP.PostsPerPage
		if ( ! empty( $content_search->found_posts ) ) {
			$content_posts = array_unique( $content_search->get_posts() ); // ensure post only gets updated once.
			foreach ( $content_posts as $content_id ) {
				wp_update_post( array( 'ID' => $content_id ) ); // Trigger an update, internal filters will filter out remote URLS.
			}
		}
	}
}
