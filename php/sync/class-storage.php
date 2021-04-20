<?php
/**
 * Storage management options.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

namespace Cloudinary\Sync;

use Cloudinary\Component\Notice;
use Cloudinary\Sync;

/**
 * Class Filter.
 *
 * Handles filtering of HTML content.
 */
class Storage implements Notice {

	/**
	 * Holds the Plugin instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Plugin Instance of the plugin.
	 */
	protected $plugin;


	/**
	 * Holds the Plugin Media instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Media Instance of the media object.
	 */
	protected $media;

	/**
	 * Holds the Sync instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Sync Instance of the plugin.
	 */
	protected $sync;

	/**
	 * Holds the Download Sync instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Sync\Download_Sync Instance of the plugin.
	 */
	protected $download;

	/**
	 * Holds the Connect instance.
	 *
	 * @since   2.2.0
	 *
	 * @var     \Cloudinary\Connect Instance of the plugin.
	 */
	protected $connect;

	/**
	 * Holds an array of the storage settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Filter constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'cloudinary_register_sync_types', array( $this, 'setup' ), 20 );
		// Add File validation sync.
		add_filter( 'cloudinary_sync_base_struct', array( $this, 'add_file_folder_validators' ) );
		// Add sync storage checks.
		add_filter( 'cloudinary_render_field', array( $this, 'maybe_disable_connect' ), 10, 2 );
	}

	/**
	 * Disable the cloudinary_url input if media is offloaded and warn the user to sync items.
	 *
	 * @param array  $field The field settings.
	 * @param string $slug  The settings slug.
	 *
	 * @return array
	 */
	public function maybe_disable_connect( $field, $slug ) {

		if ( 'connect' === $slug && 'cloudinary_url' === $field['slug'] ) {
			$field['description'] = __( 'Please ensure all media is fully synced before changing the environment variable URL.', 'cloudinary' );
			if ( 'dual_full' !== $this->settings['offload'] ) {
				$field['suffix']      = null;
				$field['description'] = sprintf(
				// translators: Placeholders are <a> tags.
					__( 'You can’t currently change your environment variable as your storage setting is set to "Cloudinary only". Update your %1$s storage settings %2$s and sync your assets to WordPress storage to enable this setting.', 'cloudinary' ),
					sprintf(
						'<a href="%s">',
						add_query_arg( 'page', 'cld_sync_media', admin_url( 'admin.php' ) )
					),
					'</a>'
				);
				$field['disabled'] = true;
			}
		}

		return $field;
	}

	/**
	 * Add a validators for the file and folder sync type to allow skipping the upload if of-storage is on.
	 *
	 * @param array $sync_types The array of sync types.
	 *
	 * @return array
	 */
	public function add_file_folder_validators( $sync_types ) {

		if ( isset( $sync_types['file'] ) && ! isset( $sync_types['file']['validate'] ) ) {
			$sync_types['file']['validate'] = array( $this, 'validate_file_folder_sync' );
		}
		if ( isset( $sync_types['folder'] ) && ! isset( $sync_types['folder']['validate'] ) ) {
			$sync_types['folder']['required'] = array( $this, 'validate_file_folder_sync' );
		}

		return $sync_types;
	}

	/**
	 * Validator and Required check to skip file and folder required for of-storage.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function validate_file_folder_sync( $attachment_id ) {

		$return = true;
		// Check if this is not a Cloudinary URL.
		if ( 'cld' === $this->settings['offload'] ) {
			$file   = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$return = ! $this->media->is_cloudinary_url( $file );
		}

		return $return;
	}

	/**
	 * Generate a signature for this sync type.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function generate_signature( $attachment_id ) {
		$file_exists = true;
		if ( 'cld' !== $this->settings['offload'] ) {
			$attachment_file = get_attached_file( $attachment_id );
			if ( ! file_exists( $attachment_file ) ) {
				$file_exists = $attachment_file;
			}
		}

		return $this->settings['offload'] . $this->media->get_public_id( $attachment_id ) . $file_exists;
	}

	/**
	 * Process the storage sync for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function sync( $attachment_id ) {

		// Get the previous state of the attachment.
		$previous_state = $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['storage'], true );

		// don't apply the default transformations here.
		add_filter( 'cloudinary_apply_default_transformations', '__return_false' );

		switch ( $this->settings['offload'] ) {
			case 'cld':
				$this->remove_local_assets( $attachment_id );
				update_post_meta( $attachment_id, '_wp_attached_file', $this->media->cloudinary_url( $attachment_id ) );
				break;
			case 'dual_low':
				$transformations = $this->media->get_transformation_from_meta( $attachment_id );
				// Only low res image items.
				if ( ! $this->media->is_preview_only( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
					// Add low quality transformations.
					$transformations[] = array( 'quality' => 'auto:low' );
				}
				$url = $this->media->cloudinary_url( $attachment_id, '', $transformations );
				break;
			case 'dual_full':
				$exists = get_attached_file( $attachment_id );
				if ( ! empty( $previous_state ) && ! file_exists( $exists ) ) {
					// Only do this is it's changing a state.
					$transformations = $this->media->get_transformation_from_meta( $attachment_id );
					$url             = $this->media->cloudinary_url( $attachment_id, '', $transformations );
				}
				break;
		}

		// start applying default transformations again.
		remove_filter( 'cloudinary_apply_default_transformations', '__return_false' );

		// If we have a URL, it means we have a new source to pull from.
		if ( ! empty( $url ) ) {
			// Ensure that we dont delete assets if the last state didn't have any.
			if ( 'cld' !== $previous_state ) {
				$this->remove_local_assets( $attachment_id );
			}
			$date = get_post_datetime( $attachment_id );
			$this->download->download_asset( $attachment_id, $url, $date->format( 'Y/m' ) );
		}

		$this->sync->set_signature_item( $attachment_id, 'storage' );
		$this->sync->set_signature_item( $attachment_id, 'breakpoints' );
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['storage'], $this->settings['offload'] ); // Save the state.
		// If bringing media back to WordPress, we need to trigger content update to allow unfiltered Cloudinary URL's to be filtered.
		if ( ! empty( $previous_state ) && 'cld' !== $this->settings['offload'] ) {
			$this->sync->managers['upload']->update_content( $attachment_id );
		}
	}

	/**
	 * Remove all local files from an asset/attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	protected function remove_local_assets( $attachment_id ) {
		// Delete local versions of images.
		$meta         = wp_get_attachment_metadata( $attachment_id );
		$backup_sizes = '';
		if ( ! empty( $meta['backup_sizes'] ) ) {
			// Replace backup sizes.
			$meta['sizes'] = $meta['backup_sizes'];
		}

		if ( ! empty( $meta['sizes'] ) ) {
			$backup_sizes = $meta['sizes'];
		}

		if ( empty( $meta['file'] ) ) {
			$meta['file'] = get_the_guid( $attachment_id );
		}

		return wp_delete_attachment_files( $attachment_id, $meta, $backup_sizes, get_attached_file( $attachment_id ) );
	}

	/**
	 * Get the current status of the sync.
	 *
	 * @return string
	 */
	public function status() {
		$note = __( 'Syncing', 'cloudinary' );
		switch ( $this->settings['offload'] ) {
			case 'cld':
				$note = __( 'Removing asset copy from local storage', 'cloudinary' );
				break;
			case 'dual_low':
				$note = __( 'Syncing low resolution asset to local storage', 'cloudinary' );
				break;
			case 'dual_full':
				$note = __( 'Syncing asset to local storage', 'cloudinary' );
				break;
		}

		return $note;
	}

	/**
	 * Get notices to display in admin.
	 *
	 * @return array
	 */
	public function get_notices() {
		$notices = array();
		if ( 'cld' === $this->settings['offload'] ) {
			$storage         = $this->connect->get_usage_stat( 'storage', 'used_percent' );
			$transformations = $this->connect->get_usage_stat( 'transformations', 'used_percent' );
			$bandwidth       = $this->connect->get_usage_stat( 'bandwidth', 'used_percent' );
			if ( 100 <= $storage || 100 <= $transformations || 100 <= $bandwidth ) {

				$notices[] = array(
					'message'     => sprintf(
					// translators: Placeholders are <a> tags.
						__( 'You have reached one or more of your quota limits. Your Cloudinary media will soon stop being delivered. Your current storage setting is "Cloudinary only" and this will therefore result in broken links to media assets. To prevent any issues upgrade your account or change your %1$s storage settings.%2$s', 'cloudinary' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=cld_sync_media' ) ) . '">',
						'</a>'
					),
					'type'        => 'error',
					'dismissible' => true,
				);
			}
		}

		return $notices;
	}

	/**
	 * Add a deactivate class to the deactivate link to trigger a warning if storage is only on Cloudinary.
	 *
	 * @param array $actions The actions for the plugin.
	 *
	 * @return array
	 */
	public function tag_deactivate_link( $actions ) {
		if ( 'cld' === $this->settings['offload'] ) {
			$actions['deactivate'] = str_replace( '<a ', '<a class="cld-deactivate" ', $actions['deactivate'] );
		} else {
			$actions['deactivate'] = str_replace( '<a ', '<a class="cld-deactivate-link" ', $actions['deactivate'] );
		}

		return $actions;
	}

	/**
	 * Check if component is ready to run.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return $this->sync && $this->media && $this->connect && $this->download;
	}

	/**
	 * Setup hooks for the filters.
	 */
	public function setup() {

		$this->sync     = $this->plugin->get_component( 'sync' );
		$this->connect  = $this->plugin->get_component( 'connect' );
		$this->media    = $this->plugin->get_component( 'media' );
		$this->download = $this->sync->managers['download'] ? $this->sync->managers['download'] : new Download_Sync( $this->plugin );

		if ( $this->is_ready() ) {
			$defaults       = array(
				'offload' => 'dual_full',
			);
			$settings       = $this->media->get_settings()->get_value( 'sync_media' );
			$this->settings = wp_parse_args( $settings, $defaults );
			$structure      = array(
				'generate' => array( $this, 'generate_signature' ),
				'priority' => 15,
				'sync'     => array( $this, 'sync' ),
				'state'    => 'info syncing',
				'note'     => array( $this, 'status' ),
			);
			$this->sync->register_sync_type( 'storage', $structure );

			// Tag the deactivate button.
			$plugin_file = pathinfo( dirname( CLDN_CORE ), PATHINFO_BASENAME ) . '/' . basename( CLDN_CORE );
			add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'tag_deactivate_link' ) );
		}
	}
}
