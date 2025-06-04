<?php
/**
 * Storage management options.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Component\Notice;
use Cloudinary\Delivery;
use Cloudinary\Sync;
use Cloudinary\Utils;

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
	 * Hold the Delivery instance.
	 *
	 * @var Delivery
	 */
	protected $delivery;

	/**
	 * The delay in seconds before local assets get deleted if Cloudinary only storage.
	 */
	const DELETE_DELAY = MINUTE_IN_SECONDS;

	/**
	 * Filter constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'cloudinary_register_sync_types', array( $this, 'setup' ), 20 );
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
	 * Generate a signature for this sync type.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function generate_signature( $attachment_id ) {
		return $this->settings['offload'] . $this->media->get_public_id( $attachment_id ) . $this->is_ready();
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
				if ( ! $this->sync->can_sync( $attachment_id, 'storage' ) ) {
					return;
				}
				$this->remove_local_assets( $attachment_id );
				break;
			case 'dual_low':
				$transformations = $this->media->get_transformation_from_meta( $attachment_id );
				// Only low res image items.
				if ( ! $this->media->is_preview_only( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
					// Add low quality transformations.
					$transformations[] = array( 'quality' => 'auto:low' );
				}
				$url = $this->media->cloudinary_url( $attachment_id, array(), $transformations, null, true );
				break;
			case 'dual_full':
				$exists = get_attached_file( $attachment_id );
				if ( ! empty( $previous_state ) && ! file_exists( $exists ) ) {
					$url = $this->media->raw_cloudinary_url( $attachment_id );
				}
				break;
		}

		// start applying default transformations again.
		remove_filter( 'cloudinary_apply_default_transformations', '__return_false' );

		// Remove the delay meta.
		delete_post_meta( $attachment_id, Sync::META_KEYS['delay'] );

		// If we have a URL, it means we have a new source to pull from.
		if ( ! empty( $url ) ) {
			// Ensure that we dont delete assets if the last state didn't have any.
			if ( 'cld' !== $previous_state ) {
				$this->remove_local_assets( $attachment_id );
			}
			$date = get_post_datetime( $attachment_id );
			$url  = remove_query_arg( '_i', $url );
			$this->download->download_asset( $attachment_id, $url, $date->format( 'Y/m' ) );
		}

		$this->sync->set_signature_item( $attachment_id, 'storage' );
		$this->sync->set_signature_item( $attachment_id, 'breakpoints' );
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['storage'], $this->settings['offload'] ); // Save the state.
	}

	/**
	 * Remove all local files from an asset/attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function remove_local_assets( $attachment_id ) {
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
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function status( $attachment_id ) {
		$note = __( 'Syncing', 'cloudinary' );
		switch ( $this->settings['offload'] ) {
			case 'cld':
				$note         = __( 'Removing asset copy from local storage', 'cloudinary' );
				$delayed      = get_post_meta( $attachment_id, Sync::META_KEYS['delay'], true );
				$diff         = time() - $delayed;
				$remaining    = self::DELETE_DELAY - $diff;
				$hr_remaining = human_time_diff( time() - $remaining, time() );
				if ( self::DELETE_DELAY > $diff ) {
					// translators: %s is time remaining.
					$note = sprintf( __( 'Local asset removal in %s', 'cloudinary' ), $hr_remaining );
				}
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
		if ( ! empty( $this->settings ) && 'cld' === $this->settings['offload'] ) {
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
	 * Check if component is ready to run.
	 *
	 * @return bool
	 */
	public function is_ready() {
		return $this->sync && $this->media && $this->connect && $this->download;
	}

	/**
	 * Validates the storage mechanism.
	 * Returning a false on the validate method within a sync type bypasses the sync method and sets the signature.
	 *
	 * @param int $attachment_id The attachment ID to validate.
	 *
	 * @return bool
	 */
	public function validate( $attachment_id ) {
		$valid = false;

		if ( $this->delivery->is_deliverable( $attachment_id ) ) {
			$valid = true;

			if ( 'cld' === $this->settings['offload'] ) {
				// In cld mode, we want to delay the deletion.
				$delayed = get_post_meta( $attachment_id, Sync::META_KEYS['delay'], true );
				$now     = time();
				if ( empty( $delayed ) ) {
					update_post_meta( $attachment_id, Sync::META_KEYS['delay'], $now );
					$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
					update_post_meta( $attachment_id, '_' . md5( $file ), $file );
				}
				$valid = file_exists( get_attached_file( $attachment_id ) );
			} else {
				// Remove the delay meta.
				delete_post_meta( $attachment_id, Sync::META_KEYS['delay'] );
				$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
				delete_post_meta( $attachment_id, '_' . md5( $file ), $file );
			}
		}

		return $valid;
	}

	/**
	 * Validates the Stats mechanism.
	 *
	 * @param int $attachment_id The attachment ID to validate.
	 *
	 * @return bool
	 */
	public function validate_stats( $attachment_id ) {
		return $this->delivery->is_deliverable( $attachment_id );
	}

	/**
	 * Filter the ability to sync based on the delay.
	 *
	 * @param bool   $can           Flag if can sync.
	 * @param int    $attachment_id The attachment ID.
	 * @param string $type          The sync type.
	 *
	 * @return bool|mixed
	 */
	public function delay_cld_only( $can, $attachment_id, $type ) {
		if ( 'storage' === $type && 'cld' === $this->settings['offload'] ) {
			$delayed = get_post_meta( $attachment_id, Sync::META_KEYS['delay'], true );
			$can     = self::DELETE_DELAY < time() - $delayed;
		}

		return $can;
	}

	/**
	 * Generate the signature for the size.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return false|string
	 */
	public function size_signature( $attachment_id ) {
		$fields                  = array(
			'image_optimization',
			'image_format',
			'image_quality',
			'image_freeform',
			'video_optimization',
			'video_format',
			'video_quality',
			'video_freeform',
		);
		$settings                = $this->plugin->settings->get_value( ...$fields );
		$settings['local_size']  = get_post_meta( $attachment_id, Sync::META_KEYS['local_size'], true );
		$settings['remote_size'] = get_post_meta( $attachment_id, Sync::META_KEYS['remote_format'], true );

		return empty( $settings['local_size'] ) ? false : wp_json_encode( $settings );
	}

	/**
	 * Sync the file size differences.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $public_id     Optional public ID.
	 */
	public function size_sync( $attachment_id, $public_id = null ) {
		if ( empty( $this->media ) ) {
			$this->setup();
		}

		if ( is_null( $public_id ) ) {
			$public_id = $this->media->get_public_id( $attachment_id );
		}

		if ( is_null( $public_id ) ) {
			return;
		}

		$args      = array(
			/** This filter is documented in wp-includes/class-wp-http-streams.php */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'headers'   => array(
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
			),
		);
		$url       = $this->media->cloudinary_url( $attachment_id, null, null, $public_id );
		$request   = wp_remote_head( $url, $args );
		$has_error = wp_remote_retrieve_header( $request, 'X-Cld-Error' );
		if ( ! empty( $has_error ) && false !== strpos( $has_error, 'deny' ) ) {
			// Deny failure. Log and exit.
			update_post_meta( $attachment_id, Sync::META_KEYS['sync_error'], __( 'Restricted file type', 'cloudinary' ) );

			return;
		}
		$remote_size   = wp_remote_retrieve_header( $request, 'Content-Length' );
		$remote_format = wp_remote_retrieve_header( $request, 'Content-Type' );
		$local_size    = get_post_meta( $attachment_id, Sync::META_KEYS['local_size'], true );
		if ( empty( $local_size ) ) {
			$url        = $this->media->cloudinary_url( $attachment_id, null, null, $public_id, true );
			$request    = wp_remote_head( $url, $args );
			$local_size = wp_remote_retrieve_header( $request, 'Content-Length' );

			update_post_meta( $attachment_id, Sync::META_KEYS['local_size'], $local_size );
		}
		update_post_meta( $attachment_id, Sync::META_KEYS['remote_size'], $remote_size );
		update_post_meta( $attachment_id, Sync::META_KEYS['remote_format'], $remote_format );

		$this->sync->set_signature_item( $attachment_id, 'size' );
	}

	/**
	 * Create a unique file name based on file hash meta entry.
	 *
	 * @param string $filename The proposed file name.
	 * @param string $ext      The file extension.
	 * @param string $dir      The file directory.
	 *
	 * @return string
	 */
	public function unique_filename( $filename, $ext, $dir ) {
		$dirs = wp_get_upload_dir();

		$path      = str_replace( $dirs['basedir'] . '/', '', $dir );
		$base      = array(
			'base'  => Utils::pathinfo( $filename, PATHINFO_FILENAME ),
			'count' => null,
			'ext'   => $ext,
		);
		$count     = 1;
		$file_path = path_join( $path, implode( '', $base ) );

		while ( 20 > $count ) {
			// Full size check.
			$exists = $this->media->get_id_from_sync_key( $file_path );
			if ( empty( $exists ) ) {
				// Scaled size check.
				$exists = $this->media->get_id_from_sync_key( Utils::make_scaled_url( $file_path ) );
				// Check for not synced items.
				if ( empty( $exists ) && ! file_exists( $dir . DIRECTORY_SEPARATOR . $filename ) ) {
					break;
				}
			}

			$base['count'] = '-' . $count;
			$file_path     = path_join( $path, implode( '', $base ) );
			$filename      = implode( '', $base );
			++$count;
		}

		return $filename;
	}

	/**
	 * Ensure metadata for CLD only.
	 *
	 * @param array $data          The meta data array.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function ensure_metadata( $data, $attachment_id ) {
		if ( defined( 'REST_REQUEST' ) && true === REST_REQUEST ) {
			if ( isset( $data['sizes'] ) && ! empty( $data['original_image'] ) && 'cld' === $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['storage'], true ) ) {
				$data['file'] = path_join( dirname( $data['file'] ), $data['original_image'] );
				unset( $data['original_image'] );
			}
		}

		return $data;
	}

	/**
	 * Conditionally remove editors in post context to prevent users editing images in WP.
	 *
	 * @param array $editors List of available editors.
	 *
	 * @return array
	 */
	public function disable_editors_maybe( $editors ) {

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && 'post' === $screen->base ) {
				$editors = array();
			}
		}

		return $editors;
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
			$this->delivery = $this->plugin->get_component( 'delivery' );

			$defaults       = array(
				'offload' => 'dual_full',
			);
			$settings       = $this->media->get_settings()->get_value( 'sync_media' );
			$this->settings = wp_parse_args( $settings, $defaults );
			$structure      = array(
				'generate' => array( $this, 'generate_signature' ),
				'validate' => array( $this, 'validate' ),
				'priority' => 15,
				'sync'     => array( $this, 'sync' ),
				'state'    => 'info syncing',
				'note'     => array( $this, 'status' ),
			);
			$this->sync->register_sync_type( 'storage', $structure );

			$structure = array(
				'generate' => array( $this, 'size_signature' ),
				'validate' => array( $this, 'validate_stats' ),
				'priority' => 16,
				'sync'     => array( $this, 'size_sync' ),
				'state'    => 'info syncing',
				'note'     => __( 'Calculating stats', 'cloudinary' ),
				'required' => false,
			);
			$this->sync->register_sync_type( 'size', $structure );

			add_filter( 'cloudinary_can_sync_asset', array( $this, 'delay_cld_only' ), 10, 3 );
			add_filter( 'wp_unique_filename', array( $this, 'unique_filename' ), 10, 3 );
			add_filter( 'wp_get_attachment_metadata', array( $this, 'ensure_metadata' ), 10, 2 );

			// Remove editors to prevent users from manually editing images in WP when CLD only (since files don't exist).
			if ( 'cld' === $this->settings['offload'] ) {
				add_filter( 'wp_image_editors', array( $this, 'disable_editors_maybe' ) );
			}
		}
	}

	/**
	 * Is storage set ot be full quality.
	 *
	 * @return bool
	 */
	public function is_local_full() {
		$sync_media = $this->media->get_settings()->get_value( 'sync_media' );

		return ! empty( $sync_media['offload'] ) && 'dual_full' === $sync_media['offload'];
	}

	/**
	 * Get the settings for Storage.
	 *
	 * @param string $setting The Setting to get.
	 *
	 * @return array|string|null
	 */
	public function get_settings( $setting = null ) {
		if ( is_null( $setting ) ) {
			return $this->settings;
		}

		if ( isset( $this->settings[ $setting ] ) ) {
			return $this->settings[ $setting ];
		}

		return null;
	}
}
