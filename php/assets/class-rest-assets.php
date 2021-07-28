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

		$endpoints['purge_all'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_purge_all' ),
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

		$asset         = (int) $request->get_param( 'cachePoint' );
		$transient_key = '_purge_cache' . $asset;
		$parents       = $this->assets->get_asset_parents();
		if ( empty( $parents ) ) {
			return rest_ensure_response( true );
		}

		$tracker = get_transient( $transient_key );
		foreach ( $parents as $parent ) {
			if ( $asset && $asset !== $parent->ID ) {
				continue;
			}
			$tracker['time']           = time();
			$tracker['current_parent'] = $asset;
			set_transient( $transient_key, $tracker, MINUTE_IN_SECONDS );
			$this->assets->purge_parent( $parent->ID );
		}
		delete_transient( $transient_key );

		return rest_ensure_response( true );
	}

	/**
	 * Purges a cache which forces the entire point to re-evaluate cached items when requested.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function rest_purge_all( $request ) {

		$count         = $request->get_param( 'count' );
		$parent        = (int) $request->get_param( 'parent' );
		$transient_key = '_purge_cache' . $parent;
		$query_args    = array(
			'post_type'              => Assets::POST_TYPE_SLUG,
			'posts_per_page'         => 1,
			'paged'                  => 1,
			'post_status'            => array( 'inherit', 'draft', 'publish' ),
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( ! empty( $parent ) ) {
			$query_args['post_parent'] = $parent;
		}
		$query = new \WP_Query( $query_args );

		$result  = array(
			'total'   => $query->found_posts,
			'pending' => $query->found_posts,
			'percent' => empty( $query->found_posts ) ? 100 : 0,
		);
		$tracker = get_transient( $transient_key );

		if ( ! empty( $tracker ) && isset( $tracker['time'] ) ) {
			$result['percent'] = ( $tracker['total'] - $result['pending'] ) / $tracker['total'] * 100;
		}
		if ( empty( $count ) && ! empty( $query->found_posts ) ) {
			if ( empty( $result['time'] ) ) {
				set_transient( $transient_key, $result, MINUTE_IN_SECONDS );
				$this->assets->plugin->get_component( 'api' )->background_request( 'purge_cache', array( 'cachePoint' => $parent ) );
			}
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get cached files for an cache point.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_get_caches( $request ) {
		$url          = $request->get_param( 'ID' );
		$parent       = $this->assets->get_asset_parent( $url );
		$search       = $request->get_param( 'search' );
		$page         = $request->get_param( 'page' );
		$current_page = $page ? $page : 1;
		$data         = $this->get_assets( $parent->ID, $search, $current_page );

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
	 * @param int         $id     The cache point ID to get cache for.
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
			'post_type'              => Assets::POST_TYPE_SLUG,
			'posts_per_page'         => 20,
			'paged'                  => $page,
			'post_parent'            => $id,
			'post_status'            => array( 'inherit', 'draft' ),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
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
		$total_items = $posts->found_posts;
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
