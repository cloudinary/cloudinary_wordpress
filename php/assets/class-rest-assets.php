<?php
/**
 * Handles cloudinary_assets REST features.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Assets;

use Cloudinary\Assets;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_HTTP_Response;

/**
 * Class Rest Assets.
 *
 * Handles managing assets.
 */
class Rest_Assets {

	/**
	 * Holds the assets instance.
	 *
	 * @var Assets
	 */
	protected $assets;

	/**
	 * Holds the meta keys.
	 *
	 * @var array
	 */
	const META_KEYS = array(
		'excluded_urls' => 'excluded_urls',
		'cached_urls'   => 'cached_urls',
		'src_path'      => 'src_path',
		'url'           => 'url',
		'base_url'      => 'base_url',
		'src_file'      => 'src_file',
		'last_updated'  => 'last_updated',
		'upload_error'  => 'upload_error',
		'version'       => 'version',
	);

	/**
	 * Rest_Assets constructor.
	 *
	 * @param Assets $assets The assets instance.
	 */
	public function __construct( $assets ) {
		$this->assets = $assets;
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Register the endpoints.
	 *
	 * @param array $endpoints The endpoint to add to.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['show_cache']          = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_get_caches' ),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
			'args'                => array(),
		);
		$endpoints['disable_cache_items'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
			'callback'            => array( $this, 'rest_handle_state' ),
			'args'                => array(
				'ids'   => array(
					'type'        => 'array',
					'default'     => array(),
					'description' => __( 'The list of IDs to update.', 'cloudinary' ),
				),
				'state' => array(
					'type'        => 'string',
					'default'     => 'draft',
					'description' => __( 'The state to update.', 'cloudinary' ),
				),
			),
		);
		$endpoints['purge_cache']         = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_purge' ),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
			'args'                => array(),
		);

		return $endpoints;
	}

	/**
	 * Purges a cache which forces the entire point to re-evaluate cached items when requested.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function rest_purge( $request ) {

		$asset      = $request->get_param( 'cachePoint' );
		$query_args = array(
			'post_type'      => Assets::POST_TYPE_SLUG,
			'posts_per_page' => 20,
			'paged'          => 1,
			'post_parent'    => $asset,
			'post_status'    => array( 'inherit', 'draft' ),
			'fields'         => 'ids',
		);
		$query      = new \WP_Query( $query_args );
		do {
			$posts = $query->get_posts();
			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id );
			}
			// Paginate.
			$query_args = $query->query_vars;
			$query_args['paged'] ++;
			$query = new \WP_Query( $query_args );
		} while ( $query->have_posts() );

		$deleted = wp_delete_post( $asset );

		return rest_ensure_response( $deleted );
	}

	/**
	 * Get cached files for an cache point.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_get_caches( $request ) {
		$id           = $request->get_param( 'ID' );
		$search       = $request->get_param( 'search' );
		$page         = $request->get_param( 'page' );
		$current_page = $page ? $page : 1;
		$data         = $this->get_assets( $id, $search, $current_page );

		return rest_ensure_response( $data );
	}

	/**
	 * Admin permission callback.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @return bool
	 */
	public function rest_can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle the state of a cache_point.
	 * Active : post_status = inherit.
	 * Inactive : post_status = draft.
	 * Deleted : delete post.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function rest_handle_state( $request ) {
		$ids   = $request['ids'];
		$state = $request['state'];
		foreach ( $ids as $id ) {
			if ( Assets::POST_TYPE_SLUG !== get_post_type( $id ) ) {
				continue;
			}
			if ( 'delete' === $state ) {
				wp_delete_post( $id );
				continue;
			}

			$args = array(
				'ID'          => $id,
				'post_status' => 'disable' === $state ? 'draft' : 'inherit',
			);
			wp_update_post( $args );
		}

		return $ids;
	}

	/**
	 * Get assets for a cache point.
	 *
	 * @param Int         $id     The cache point ID to get cache for.
	 * @param string|null $search Optional search.
	 * @param int         $page   The page or results to load.
	 *
	 * @return array
	 */
	public function get_assets( $id, $search = null, $page = 1 ) {
		$cache_point = get_post( $id );
		if ( is_null( $cache_point ) ) {
			return array();
		}
		$args = array(
			'post_type'      => Assets::POST_TYPE_SLUG,
			'posts_per_page' => 20,
			'paged'          => $page,
			'post_parent'    => $id,
			'post_status'    => array( 'inherit', 'draft' ),
		);
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}
		$posts = new \WP_Query( $args );
		$items = array();
		foreach ( $posts->get_posts() as $post ) {
			$items[] = array(
				'ID'        => $post->ID,
				'key'       => $post->post_name,
				'local_url' => $post->post_title,
				'short_url' => str_replace( $cache_point->post_title, '', $post->post_title ),
				'active'    => 'inherit' === $post->post_status,
			);
		}
		$total_items = count( $items );
		$pages       = ceil( $total_items / 20 );
		// translators: The current page and total pages.
		$description = sprintf( __( 'Page %1$d of %2$d', 'cloudinary' ), $page, $pages );

		// translators: The number of files.
		$totals = sprintf( _n( '%d cached file', '%d cached files', $total_items, 'cloudinary' ), $total_items );

		$return = array(
			'items'        => $items,
			'total'        => $total_items,
			'total_pages'  => $pages,
			'current_page' => $page,
			'nav_text'     => $totals . ' | ' . $description,
		);
		if ( empty( $items ) ) {
			if ( ! empty( $search ) ) {
				$return['nav_text'] = __( 'No items found.', 'cloudinary' );
			} else {
				$return['nav_text'] = __( 'No items cached.', 'cloudinary' );
			}
		}

		return $return;
	}
}
