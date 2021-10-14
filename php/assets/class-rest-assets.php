<?php
/**
 * Handles cloudinary_assets REST features.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Assets;

use Cloudinary\Assets;
use Cloudinary\Utils;
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

		$asset_parent  = (int) $request->get_param( 'asset_parent' );
		$transient_key = '_purge_cache' . $asset_parent;
		$parents       = $this->assets->get_asset_parents();
		if ( empty( $parents ) ) {
			return rest_ensure_response( true );
		}

		$tracker = get_transient( $transient_key );
		foreach ( $parents as $parent ) {
			if ( $asset_parent && $asset_parent !== $parent->ID ) {
				continue;
			}
			$tracker['time']           = time();
			$tracker['current_parent'] = $asset_parent;
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
		global $wpdb;
		$parent_url = $request->get_param( 'parent' );
		$count      = $request->get_param( 'count' );
		$clean      = $this->assets->clean_path( $parent_url );
		$parent     = $this->assets->get_param( $clean );
		$result     = array(
			'total'   => 0,
			'pending' => count( $this->assets->get_asset_parents() ),
			'percent' => 0,
		);
		if ( $parent instanceof \WP_Post ) {
			$data   = array(
				'public_id'  => null,
				'post_state' => 'enable',
			);
			$where  = array(
				'parent_path' => $clean,
				'sync_type'   => 'asset',
			);
			$format = array(
				'%s',
				'%s',
			);
			$wpdb->update( Utils::get_relationship_table(), $data, $where, $format, $format ); // phpcs:ignore WordPress.DB
			$result['total']   = 0;
			$result['pending'] = 0;
			$result['percent'] = 100;
		} elseif ( false === $count ) {
			$data   = array(
				'public_id'  => null,
				'post_state' => 'enable',
			);
			$where  = array(
				'sync_type' => 'asset',
			);
			$format = array(
				'%s',
				'%s',
			);
			$wpdb->update( Utils::get_relationship_table(), $data, $where, $format, array( '%s' ) ); // phpcs:ignore WordPress.DB
			$result['total']   = 0;
			$result['pending'] = 0;
			$result['percent'] = 100;
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
		global $wpdb;
		$ids   = $request['ids'];
		$state = $request['state'];
		foreach ( $ids as $id ) {
			$where = array(
				'post_id'    => $id,
				'post_state' => 'asset',
			);
			if ( 'delete' === $state ) {
				$data = array(
					'public_id'  => null,
					'post_state' => 'enable',
				);
				$wpdb->update( Utils::get_relationship_table(), $data, $where ); // phpcs:ignore WordPress.DB
				continue;
			}

			$data = array(
				'post_state' => strtolower( $state ),
			);
			$wpdb->update( Utils::get_relationship_table(), $data, $where ); // phpcs:ignore WordPress.DB
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
		global $wpdb;
		$cache_point = get_post( $id );

		$wpdb->cld_table = Utils::get_relationship_table();
		$cache           = wp_cache_get( $id, 'cld_query' );
		$limit           = 20;
		$start           = 0;
		if ( $page > 1 ) {
			$start = $limit * $page - 1;
		}
		if ( empty( $cache ) ) {
			$prepare        = $wpdb->prepare(
				"SELECT COUNT( id ) as total FROM $wpdb->cld_table WHERE parent_path = %s AND primary_url = sized_url AND sync_type = 'asset';",
				$cache_point->post_title
			);
			$cache['total'] = (int) $wpdb->get_var( $prepare ); // phpcs:ignore WordPress.DB
			$prepare        = $wpdb->prepare(
				"SELECT * FROM $wpdb->cld_table WHERE public_id IS NOT NULL && parent_path = %s AND primary_url = sized_url AND sync_type = 'asset' limit %d,%d;",
				$cache_point->post_title,
				$start,
				$limit
			);
			$cache['items'] = $wpdb->get_results( $prepare, ARRAY_A ); // phpcs:ignore WordPress.DB
			wp_cache_set( $id, $cache, 'cld_query' );
		}

		$default = array(
			'items'        => array(),
			'total'        => $cache['total'],
			'total_pages'  => 1,
			'current_page' => 1,
			'nav_text'     => __( 'No items cached.', 'cloudinary' ),
		);
		if ( is_null( $cache_point ) ) {
			return $default;
		}
		$items = array();
		foreach ( $cache['items'] as $item ) {
			$parts   = explode( $item['parent_path'], $item['primary_url'] );
			$url     = './' . $parts[1];
			$items[] = array(
				'ID'        => $item['post_id'],
				'key'       => $item['id'],
				'local_url' => $item['primary_url'],
				'short_url' => $url,
				'active'    => 'enable' === $item['post_state'],
			);
		}
		$total_items = $cache['total'];
		$pages       = ceil( $total_items / $limit );
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
