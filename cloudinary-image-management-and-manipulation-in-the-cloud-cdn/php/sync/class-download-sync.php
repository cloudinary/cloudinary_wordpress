<?php
/**
 * Download Sync from Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;

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
	private $plugin;

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
	 * Explicitly defined to allow easier testability.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function rest_can_upload_files( \WP_REST_Request $request ) {

		// This would have been from an ajax call. Therefore verify based on capability.
		return current_user_can( 'upload_files' );
	}

	/**
	 * Handle a failed download by deleting the temp attachment and returning the error in json.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $error         The error text to return.
	 */
	public function handle_failed_download( $attachment_id, $error ) {
		// @todo: Place a handler to catch the error for logging.
		// Delete attachment temp.
		wp_delete_attachment( $attachment_id, true );

		// Send error.
		wp_send_json_error( $error );
	}

	/**
	 * Download attachment from Cloudinary via REST API.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_download_asset( \WP_REST_Request $request ) {

		$attachment_id   = $request->get_param( 'attachment_id' );
		$file_path       = $request->get_param( 'src' );
		$transformations = (array) $request->get_param( 'transformations' );

		$response = $this->import_asset( $attachment_id, $file_path, $transformations );
		if ( is_wp_error( $response ) ) {
			$this->handle_failed_download( $attachment_id, $response->get_error_message() );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare and sync down an asset stored remotely.
	 *
	 * @param $attachment_id
	 *
	 * @return array|\WP_Error
	 */
	public function down_sync( $attachment_id ) {
		$file  = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$path  = wp_parse_url( $file, PHP_URL_PATH );
		$media = $this->plugin->components['media'];
		$parts = explode( '/', $path );
		$parts = array_map(
			function ( $val ) use ( $media ) {
				if ( empty( $val ) ) {
					return false;
				}
				if ( $val === $media->credentials['cloud_name'] ) {
					return false;
				}
				if ( in_array( $val, [ 'image', 'video', 'upload' ], true ) ) {
					return false;
				}
				$transformation_maybe = $media->get_transformations_from_string( $val );
				if ( ! empty( $transformation_maybe ) ) {
					return false;
				}
				if ( substr( $val, 0, 1 ) === 'v' && is_numeric( substr( $val, 1 ) ) ) {
					return false;
				}

				return $val;
			},
			$parts
		);
		// Build public_id.
		$parts     = array_filter( $parts );
		$public_id = implode( '/', $parts );
		// Remove extension.
		$path      = pathinfo( $public_id );
		$public_id = strstr( $public_id, '.' . $path['extension'], true );
		// Save public ID.
		$media->update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $public_id );
		// Check if the asset is in the same folder as the defined Cloudinary folder.
		if ( false !== strpos( $public_id, '/' ) ) {
			$path              = pathinfo( $public_id );
			$asset_folder      = trailingslashit( $path['dirname'] );
			$cloudinary_folder = trailingslashit( $this->plugin->config['settings']['sync_media']['cloudinary_folder'] );
			if ( $asset_folder === $cloudinary_folder ) {
				// The asset folder matches the defined cloudinary folder, flag it as being in a folder sync.
				$media->update_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], true );
			}
		}

		return $this->download_asset( $attachment_id, $file );
	}

	/**
	 * Download an attachment source to the file system.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $source        The optional source to download.
	 *
	 * @return array|\WP_Error
	 */
	public function download_asset( $attachment_id, $source = null ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		if ( empty( $source ) ) {
			$cloudinary_id = $this->media->get_cloudinary_id( $attachment_id );
			$source        = $this->media->cloudinary_url( $attachment_id, array(), array(), $cloudinary_id );
		}
		$file_name = basename( $source );
		try {
			// Prime a file to stream to.
			$upload = wp_upload_bits( $file_name, null, 'temp' );
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
				return $response;
			}
			if ( 200 !== $response['response']['code'] ) {
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
			$old_meta = wp_get_attachment_metadata( $attachment_id );
			ob_start(); // Catch possible errors in WordPress's ID3 module when setting meta for transformed videos.
			$meta            = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
			$captured_errors = ob_get_clean(); // Capture issues.
			// Be sure to record v2 meta.
			if ( ! empty( $old_meta[ Sync::META_KEYS['cloudinary'] ] ) ) {
				$meta[ Sync::META_KEYS['cloudinary'] ] = $old_meta[ Sync::META_KEYS['cloudinary'] ];
			}
			wp_update_attachment_metadata( $attachment_id, $meta );
			// Update the folder synced flag.
			$public_id         = $this->media->get_public_id( $attachment_id );
			$asset_folder      = strpos( $public_id, '/' ) ? dirname( $public_id ) : '/';
			$cloudinary_folder = untrailingslashit( $this->media->get_cloudinary_folder() );
			if ( $asset_folder === $cloudinary_folder ) {
				$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['folder_sync'], true );
			}
			// Generate signatures.
			$this->sync->set_signature_item( $attachment_id, 'options' );
			$this->sync->set_signature_item( $attachment_id, 'cloud_name' );
			$this->sync->set_signature_item( $attachment_id, 'download' );
			$this->sync->set_signature_item( $attachment_id, 'file' );
			$this->sync->set_signature_item( $attachment_id, 'folder' );

		} catch ( \Exception $e ) {
			return new \WP_Error( 'download_error', $e->getMessage() );
		}

		return $upload;
	}

	/**
	 * Download an attachment source to the file system.
	 *
	 * @param int        $attachment_id The attachment ID.
	 * @param string     $file_path     The path of the file.
	 * @param array|null $transformations
	 *
	 * @return array|\WP_Error
	 */
	public function import_asset( $attachment_id, $file_path, $transformations = null ) {

		// Get the image and update the attachment.
		require_once ABSPATH . WPINC . '/class-http.php';
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

		$attachment = wp_prepare_attachment_for_js( $attachment_id );

		// Do transformations.
		if ( 'image' === $attachment['type'] ) {
			// Get the cloudinary_id from public_id not Media::cloudinary_id().
			$cloudinary_id = $this->plugin->components['media']->get_cloudinary_id( $attachment_id );
			// Make sure all sizes have the transformations on for previewing.
			foreach ( $attachment['sizes'] as $name => &$size ) {
				$size['url'] = $this->plugin->components['media']->cloudinary_url( $attachment_id, $name, $transformations, $cloudinary_id );
			}
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
