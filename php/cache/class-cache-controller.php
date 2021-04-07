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
					'methods'             => \WP_REST_Server::READABLE,
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
	 * Retrieves a single post.
	 *
	 * @since 4.7.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {

		$post = $this->get_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		// Get the server.
		$server = rest_get_server();
		$server->remove_header( 'Content-Type' );

		// Get the file path.
		$src_url = $post->post_title;
		if ( false !== strpos( $src_url, '?' ) ) {
			$src_url = strstr( $src_url, '?', true );
		}
		$cache_point = $this->get_post( $post->post_parent );
		$base_path   = ABSPATH . $cache_point->post_content;
		$file        = wp_normalize_path( str_replace( $cache_point->post_title, $base_path, $src_url ) );

		// Check if the file is different.
		if ( $post->post_title !== $post->post_content ) {
			// Lets do a check on the file.
			$modified_time = get_post_datetime( $post, 'modified' )->getTimestamp();
			$file_time     = $this->cache->file_system->wp_file_system->mtime( $file );
			if ( $modified_time > $file_time ) {
				$server->send_header( 'Location', $post->post_content );
				exit;
			}
		}
		$mime = wp_check_filetype( $file, wp_get_mime_types() );
		$server->send_header( 'Content-Type', $mime['type'] );
		$server->send_header( 'Content-Length', $this->cache->file_system->wp_file_system->size( $file ) );
		$handle = fopen( $file, 'r' ); // phpcs:ignore
		fpassthru( $handle );
		$cache_url = $this->cache->sync_static( $file, $src_url );
		if ( ! is_wp_error( $cache_url ) ) {
			$details = array(
				'ID'           => $post->ID,
				'post_content' => $cache_url,
			);
			wp_update_post( $details );
		}
		exit;
	}

}
