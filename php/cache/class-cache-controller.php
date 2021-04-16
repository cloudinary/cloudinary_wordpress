<?php
/**
 * Cache endpoint controller.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Cache;

use function Cloudinary\get_plugin_instance;

/**
 * Class Cache Point.
 *
 * Handles managing cache points.
 */
class Cache_Controller extends \WP_REST_Posts_Controller {

	/**
	 * Holds the cache object.
	 *
	 * @var \Cloudinary\Cache
	 */
	protected $cache;

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
		$this->cache = get_plugin_instance()->get_component( 'cache' );
	}

	/**
	 * Register the controller routes.
	 */
	public function register_routes() {

		$get_item_args = array(
			'context' => $this->get_context_param( array( 'default' => 'view' ) ),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id'   => array(
						'description' => __( 'Unique identifier for the object.', 'cloudinary' ),
						'type'        => 'integer',
					),
					'path' => array(
						'description' => __( 'Path to get files for.', 'cloudinary' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $get_item_args,
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/(?P<filename>[\W\w]+)',
			array(
				'args'   => array(
					'id'       => array(
						'description' => __( 'Unique identifier for the object.', 'cloudinary' ),
						'type'        => 'integer',
					),
					'filename' => array(
						'description' => __( 'Filename for this item.', 'cloudinary' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => \WP_REST_Server::ALLMETHODS,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $get_item_args,
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

	}

	/**
	 * Get an upload url.
	 *
	 * @param int $id The post ID.
	 *
	 * @return string
	 */
	public function get_cache_upload_url( $id ) {
		$post     = $this->get_post( $id );
		$filename = basename( $post->post_title );

		return rest_url( "{$this->namespace}/{$this->rest_base}/{$id}/{$filename}" );
	}

	/**
	 * Get the disable items url.
	 *
	 * @return string
	 */
	public function get_cache_state_url() {

		return rest_url( "{$this->namespace}/{$this->rest_base}/state" );
	}

	/**
	 * Retrieves a single post.
	 *
	 * @since 4.7.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		// Get the server.
		$server = rest_get_server();
		$server->remove_header( 'Content-Type' );

		$post = $this->get_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$meta   = get_post_meta( $post->ID );
		$direct = $request->get_param( 'uploading' );
		if ( empty( $direct ) ) {
			$mime     = wp_check_filetype( $meta['src_file'], wp_get_mime_types() );
			$streamed = false;
			if ( 'image' === strstr( $mime['type'], '/', true ) ) {
				// Stream images to the browser before uploading.
				$server->send_header( 'Content-Type', $mime['type'] );
				$server->send_header( 'Content-Length', $this->cache->file_system->wp_file_system->size( $meta['src_file'] ) );
				$handle = fopen( $meta['src_file'], 'r' );  // phpcs:ignore
				fpassthru( $handle );
				fclose( $handle ); // phpcs:ignore
				$streamed = true;
			}
			$url    = $this->get_cache_upload_url( $post->ID );
			$params = array(
				'timeout'   => 0.01,
				'method'    => 'GET',
				'blocking'  => false,
				'sslverify' => false,
				'headers'   => array(),
				'body'      => array(
					'uploading' => true,
				),
			);

			wp_safe_remote_request( $url, $params );
			if ( ! $streamed ) {
				$server->send_header( 'Location', $post->post_title );
			}
			exit;
		}

		// Check if the file is different.
		if ( $meta['local_url'] !== $meta['cached_url'] ) {
			// Lets do a check on the file.
			$file_time = $this->cache->file_system->wp_file_system->mtime( $meta['src_file'] );
			if ( $meta['last_updated'] > $file_time ) {
				// All cool.
				exit;
			}
		}

		$cache_url = $this->cache->sync_static( $meta['src_file'], $meta['cached_url'] );
		if ( is_wp_error( $cache_url ) ) {
			// If error, log it, and set item to draft.
			update_post_meta( $post->ID, 'upload_error', $cache_url );
			$params = array(
				'ID'          => $post->ID,
				'post_status' => 'draft',
			);
			wp_update_post( $params );
			exit;
		}

		update_post_meta( $post->ID, 'cached_url', $cache_url );
		exit;
	}
}
