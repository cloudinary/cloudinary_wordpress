<?php
/**
 * Download Sync from Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;
use Cloudinary\Utils;

/**
 * Class Download_Sync.
 *
 * Pull media from Cloudinary on insert.
 */
class Download_Sync {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Plugin Instance of the global plugin.
	 */
	protected $plugin;

	/**
	 * Holds the Media component.
	 *
	 * @var \Cloudinary\Media
	 */
	protected $media;

	/**
	 * Holds the Sync component.
	 *
	 * @var \Cloudinary\Sync
	 */
	protected $sync;

	/**
	 * Download_Sync constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->register_hooks();
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {
		$endpoints['asset'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_download_asset' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_upload_files' ),
		);

		return $endpoints;
	}

	/**
	 * Admin permission callback.
	 *
	 * The user must be allowed to download assets, and to edit the attachment the asset is
	 * downloaded into: the endpoint replaces that attachment's file, and deletes the
	 * attachment if the download fails.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function rest_can_upload_files( \WP_REST_Request $request ) {

		// This would have been from an ajax call. Therefore verify based on capability.
		if ( ! Utils::user_can( 'manage_assets', 'upload_files', 'download' ) ) {
			return false;
		}

		$attachment_id = absint( $request->get_param( 'attachment_id' ) );

		return $attachment_id
			&& 'attachment' === get_post_type( $attachment_id )
			&& current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Handle a failed download by deleting the temp attachment and returning the error in json.
	 *
	 * Only an attachment that is still waiting for its first download is deleted: it has no
	 * file yet, and the current user is allowed to delete it.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $error         The error text to return.
	 */
	public function handle_failed_download( $attachment_id, $error ) {
		$attachment_id = absint( $attachment_id );
		// The error can come from a response header of the remote server.
		$error  = sanitize_text_field( (string) $error );
		$delete = $this->is_pending_download( $attachment_id ) && current_user_can( 'delete_post', $attachment_id );

		$message = sprintf(
			'Cloudinary: failed to import asset into attachment %d (public_id: %s). %s Error: %s',
			$attachment_id,
			(string) $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['public_id'], true ),
			$delete ? 'Deleting the attachment.' : 'Keeping the attachment.',
			$error
		);
		Utils::log( $message, 'download-sync-failed' );
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( $delete ) {
			$this->delete_local_attachment( $attachment_id );
		}

		// Send error. The media modal expects the admin-ajax response format.
		wp_send_json_error( $error );
	}

	/**
	 * Delete an attachment in WordPress only.
	 *
	 * Deleting an attachment normally also deletes its asset on Cloudinary. A failed import must
	 * leave that asset alone: it's the source the user picked in the media library, not a copy
	 * made by the import.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	protected function delete_local_attachment( $attachment_id ) {
		$callback = array( $this->sync->managers['delete'], 'delete_asset' );
		$priority = has_action( 'delete_attachment', $callback );
		if ( false !== $priority ) {
			remove_action( 'delete_attachment', $callback, $priority );
		}

		wp_delete_attachment( $attachment_id, true );

		if ( false !== $priority ) {
			add_action( 'delete_attachment', $callback, $priority );
		}
	}

	/**
	 * Check if an attachment is still waiting for its first download, i.e. has no file yet.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	protected function is_pending_download( $attachment_id ) {
		return 'attachment' === get_post_type( $attachment_id ) && empty( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}

	/**
	 * Download attachment from Cloudinary via REST API.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_download_asset( \WP_REST_Request $request ) {

		$attachment_id   = absint( $request->get_param( 'attachment_id' ) );
		$file_path       = $request->get_param( 'src' );
		$transformations = (array) $request->get_param( 'transformations' );

		$response = $this->import_asset( $attachment_id, $file_path, $transformations );
		if ( is_wp_error( $response ) ) {
			$this->handle_failed_download( $attachment_id, $response->get_error_message() );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Download an attachment source to the file system.
	 *
	 * @param int         $attachment_id The attachment ID.
	 * @param string      $source        The optional source to download.
	 * @param string|null $date          The date of the attachment to set storage folders.
	 *
	 * @return array|\WP_Error
	 */
	public function download_asset( $attachment_id, $source = null, $date = null ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		if ( empty( $source ) ) {
			$source = wp_get_attachment_url( $attachment_id );
		}

		/**
		 * Raw attachment metadata; may include keys beyond core's documented shape.
		 *
		 * @var array<string, mixed>|false $meta
		 */
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $meta['file'] ) ) {
			$file_name = isset( $meta['original_image'] ) ? wp_basename( $meta['original_image'] ) : wp_basename( $meta['file'] );
		} else {
			$file_name = wp_basename( strtok( $source, '?' ) );
		}
		$upload   = array();
		$attached = false;
		try {
			// Prime a file to stream to.
			$upload = wp_upload_bits( $file_name, null, 'temp', $date );
			if ( ! empty( $upload['error'] ) ) {
				return new \WP_Error( 'download_error', $upload['error'] );
			}
			// Stream file to primed file.
			$response = wp_safe_remote_get(
				$source,
				array(
					'timeout'  => 300, // phpcs:ignore
					'stream'   => true,
					'filename' => $upload['file'],
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_delete_file( $upload['file'] );

				return $response;
			}
			if ( 200 !== $response['response']['code'] ) {
				// The error body has been streamed into the primed file, which is never attached. Remove it.
				wp_delete_file( $upload['file'] );

				$header_error = wp_remote_retrieve_header( $response, 'x-cld-error' );
				if ( ! empty( $header_error ) ) {
					$error = $header_error;
				} else {
					$error = __( 'Could not download the Cloudinary asset.', 'cloudinary' );
				}

				return new \WP_Error( 'download_error', $error );
			}

			// Prepare the asset.
			update_attached_file( $attachment_id, $upload['file'] );
			$attached = true;

			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

			// Update the folder synced flag.
			$public_id         = $this->media->get_public_id( $attachment_id );
			$asset_folder      = strpos( $public_id, '/' ) ? dirname( $public_id ) : '/';
			$cloudinary_folder = untrailingslashit( $this->media->get_cloudinary_folder() );
			if ( $asset_folder === $cloudinary_folder ) {
				$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], true );
			}

			// Generate signatures.
			$this->sync->set_signature_item( $attachment_id, 'cloud_name' );
			$this->sync->set_signature_item( $attachment_id, 'download' );
			$this->sync->set_signature_item( $attachment_id, 'file' );
			$this->sync->set_signature_item( $attachment_id, 'folder' );
			if ( $this->sync->can_sync( $attachment_id ) ) {
				$this->sync->add_to_sync( $attachment_id ); // Update storage and other sync types.
			}
		} catch ( \Exception $e ) {
			// Remove the primed file, unless it already became the attachment's file.
			if ( ! $attached && ! empty( $upload['file'] ) ) {
				wp_delete_file( $upload['file'] );
			}

			return new \WP_Error( 'download_error', $e->getMessage() );
		}

		return $upload;
	}

	/**
	 * Download an attachment source to the file system.
	 *
	 * @param int        $attachment_id   The attachment ID.
	 * @param string     $file_path       The path of the file.
	 * @param array|null $transformations The transformations.
	 *
	 * @return array|\WP_Error
	 */
	public function import_asset( $attachment_id, $file_path, $transformations = null ) {

		// Get the image and update the attachment.
		$http_class = ABSPATH . WPINC . '/class-http.php';
		// Since WP 5.9.
		if ( file_exists( ABSPATH . WPINC . '/class-wp-http.php' ) ) {
			$http_class = ABSPATH . WPINC . '/class-wp-http.php';
		}
		if ( ! class_exists( 'WP_Http' ) ) {
			require_once $http_class; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Fetch the asset.
		try {
			// Prime a file to stream to.
			$upload = $this->download_asset( $attachment_id, $file_path );

		} catch ( \Exception $e ) {
			return new \WP_Error( 'download_error', $e->getMessage() );
		}

		// download_asset() returns its errors rather than throwing them.
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		$attachment = wp_prepare_attachment_for_js( $attachment_id );

		// Do transformations.
		if ( 'image' === $attachment['type'] ) {
			// Get the cloudinary_id from public_id not Media::cloudinary_id().
			$cloudinary_id = $this->plugin->components['media']->get_cloudinary_id( $attachment_id );

			// don't apply the default transformations here.
			add_filter( 'cloudinary_apply_default_transformations', '__return_false' );

			// Make sure all sizes have the transformations on for previewing.
			foreach ( $attachment['sizes'] as $name => &$size ) {
				$size['url'] = $this->plugin->components['media']->cloudinary_url( $attachment_id, $name, $transformations, $cloudinary_id );
			}

			// start applying default transformations again.
			remove_filter( 'cloudinary_apply_default_transformations', '__return_false' );
		}
		// Prepare response.
		$response = array(
			'success' => true,
			'data'    => $attachment,
		);

		return $response;
	}

	/**
	 * Setup this component.
	 */
	public function setup() {
		// Setup components.
		$this->media = $this->plugin->components['media'];
		$this->sync  = $this->plugin->components['sync'];
	}
}
