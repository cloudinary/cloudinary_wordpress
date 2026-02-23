<?php
/**
 * Handles cloudinary_assets REST features.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Assets;

use Cloudinary\Assets;
use Cloudinary\Connect\Api;
use Cloudinary\Relate;
use Cloudinary\Sync;
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
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
			'args'                => array(),
		);
		$endpoints['disable_cache_items'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
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
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
			'args'                => array(),
		);

		$endpoints['purge_all'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_purge_all' ),
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
			'args'                => array(),
		);

		$endpoints['save_asset'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_save_asset' ),
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
			'args'                => array(),
		);

		return $endpoints;
	}

	/**
	 * Update an assets transformations.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function rest_save_asset( $request ) {
		$media         = $this->assets->media;
		$attachment_id = $request->get_param( 'ID' );
		$type          = $media->get_resource_type( $attachment_id );

		$return = array();

		// Save transformations if present.
		$transformations = $request->get_param( 'transformations' );

		if ( isset( $transformations ) ) {
			$result = $this->save_transformation_type( $attachment_id, $transformations, $type, 'transformations' );
			$return = array_merge( $return, $result );
		}

		// Save text overlay even if empty (allow clearing).
		$text_overlay = $request->get_param( 'textOverlay' );

		if ( isset( $text_overlay ) && array_key_exists( 'transformation', (array) $text_overlay ) ) {
			$result = $this->save_transformation_type( $attachment_id, $text_overlay['transformation'], $type, 'text_overlay', $text_overlay );
			$return = array_merge( $return, $result );
		}

		// Save image overlay even if empty (allow clearing).
		$image_overlay = $request->get_param( 'imageOverlay' );

		if ( isset( $image_overlay ) && array_key_exists( 'transformation', (array) $image_overlay ) ) {
			$result = $this->save_transformation_type( $attachment_id, $image_overlay['transformation'], $type, 'image_overlay', $image_overlay );
			$return = array_merge( $return, $result );
		}

		return rest_ensure_response( $return );
	}

	/**
	 * Shared helper to save a transformation type (main, text overlay, image overlay).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $transformation Transformation string.
	 * @param string $type Resource type.
	 * @param string $save_type Type to save (transformations, text_overlay, image_overlay).
	 * @param array  $full_data Full data for the transformation type.
	 * @return array
	 */
	protected function save_transformation_type( $attachment_id, $transformation, $type, $save_type, $full_data = null ) {
		$media                = $this->assets->media;
		$transformation_array = $media->get_transformations_from_string( $transformation, $type );
		$cleaned              = Api::generate_transformation_string( $transformation_array, $type );

		if ( ! empty( $full_data ) ) {
			$full_data['transformation'] = $cleaned;
		}

		switch ( $save_type ) {
			case 'transformations':
				Relate::update_transformations( $attachment_id, $cleaned );
				break;
			case 'text_overlay':
			case 'image_overlay':
				Relate::update_transformations_overlay( $attachment_id, $full_data, $save_type );
				break;
		}

		$result = array( $save_type => $cleaned );

		if ( $cleaned !== $transformation ) {
			$result['note'] = __( 'There are incorrect transformations, please set correct ones.', 'cloudinary' );
		}

		return $result;
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
			// Remove parent.
			wp_delete_post( $parent->ID );
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
	public function rest_can_manage_assets() {
		return Utils::user_can( 'manage_assets' );
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
				'post_id'   => $id,
				'sync_type' => 'asset',
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
			$search_ext = null;
			if ( ! empty( $search ) ) {
				if ( is_numeric( $search ) ) {
					$search_ext = $wpdb->prepare( ' AND post_id = %d', (int) $search );
				} else {
					$search_ext = " AND sized_url LIKE '%%{$search}%%'";
				}
			}

			$cache          = array();
			$prepare        = $wpdb->prepare(
				"SELECT COUNT( id ) as total FROM $wpdb->cld_table WHERE parent_path = %s AND post_state != 'inherit' {$search_ext};", // phpcs:ignore WordPress.DB.PreparedSQL
				$cache_point->post_title
			);
			$cache['total'] = (int) $wpdb->get_var( $prepare ); // phpcs:ignore WordPress.DB
			$prepare        = $wpdb->prepare(
				"SELECT * FROM $wpdb->cld_table WHERE public_id IS NOT NULL && parent_path = %s AND post_state != 'inherit' {$search_ext} limit %d,%d;", // phpcs:ignore WordPress.DB.PreparedSQL
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
			$items[] = $this->assets->build_item( $item );
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
