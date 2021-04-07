<?php
/**
 * Handles cache point management.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Cache;

use Cloudinary\Cache;
use Cloudinary\Cache\Cache_Controller;

/**
 * Class Cache Point.
 *
 * Handles managing cache points.
 */
class Cache_Point {

	/**
	 * The plugin instance.
	 *
	 * @var Cache
	 */
	protected $cache;

	/**
	 * Holds the list of cache_points.
	 *
	 * @var \WP_Post[]
	 */
	protected $cache_points = array();

	/**
	 * Holds the list of cache_point urls.
	 *
	 * @var array()
	 */
	protected $cache_point_urls = array();

	/**
	 * Post type.
	 *
	 * @var \WP_Post_Type
	 */
	protected $post_type;

	/**
	 * Holds the post type.
	 */
	const POST_TYPE_SLUG = 'cloudinary_asset';

	/**
	 * Cache Point constructor.
	 *
	 * @param Cache $cache The plugin ache object.
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
		$this->register_post_type();
	}

	/**
	 * Init the cache_points.
	 */
	public function init() {
		$params = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'post_status'    => array( 'pending', 'publish' ),
			'post_parent'    => 0,
			'posts_per_page' => 100,
		);
		$query  = new \WP_Query( $params );
		foreach ( $query->get_posts() as $post ) {
			$this->cache_points[ $post->post_title ] = $post;
		}
		do_action( 'cloudinary_cache_init_cache_points' );
	}

	/**
	 * Register a cache path.
	 *
	 * @param string $url      The URL to register.
	 * @param string $src_path The source path to register.
	 */
	public function register_cache_path( $url, $src_path ) {
		if ( ! isset( $this->cache_points[ trailingslashit( $url ) ] ) ) {
			$src_path = str_replace( ABSPATH, '', $src_path );
			$this->create_cache_point( $url, $src_path );
		}
	}

	/**
	 * Get all active cache_points.
	 *
	 * @param bool $ids_only Flag to get only the ids.
	 *
	 * @return int[]|\WP_Post[]
	 */
	public function get_active_cache_points( $ids_only = false ) {
		$return = array_filter(
			$this->cache_points,
			function ( $post ) {
				return 'publish' === $post->post_status;
			}
		);

		if ( $ids_only ) {
			$return = array_map(
				function ( $post ) {
					return $post->ID;
				},
				$return
			);
		}

		return $return;
	}

	/**
	 * Convert a URl to a path.
	 *
	 * @param string $url The URL to convert.
	 *
	 * @return string
	 */
	public function url_to_path( $url ) {
		if ( strpos( $url, '?' ) ) {
			$url = strstr( $url, '?', true );
		}
		$src_path = $this->cache->file_system->get_src_path( $url );
		if ( $this->cache->file_system->is_dir( $src_path ) ) {
			$src_path = trailingslashit( $src_path );
		}

		return $src_path;
	}

	/**
	 * Get a cache point from a url.
	 *
	 * @param string $url The cache point url to get.
	 *
	 * @return \WP_Post
	 */
	public function get_cache_point( $url ) {
		$key = $this->get_key_name( $url );
		if ( isset( $this->cache_points[ $key ] ) ) {
			return $this->cache_points[ $key ];
		}

		foreach ( $this->cache_points as $key => $cache_point ) {
			if ( false !== strpos( $url, $key ) ) {
				return $cache_point;
			}
		}

		return null;
	}

	/**
	 * Create a new cache point from a url.
	 *
	 * @param string $url      The url to create the cache point for.
	 * @param string $src_path The path to be cached.
	 */
	public function create_cache_point( $url, $src_path ) {
		$key      = $this->get_key_name( $url );
		$url      = trailingslashit( $url );
		$src_path = trailingslashit( $src_path );

		$params  = array(
			'post_name'    => $key,
			'post_type'    => self::POST_TYPE_SLUG,
			'post_title'   => $url,
			'post_content' => $src_path,
			'post_status'  => 'publish',
		);
		$post_id = wp_insert_post( $params );

		$this->cache_points[ $url ] = get_post( $post_id );
	}

	/**
	 * Get a key name for a cache point.
	 *
	 * @param string $url The url to get the key name for.
	 *
	 * @return string
	 */
	protected function get_key_name( $url ) {
		return md5( trailingslashit( $url ) );
	}

	/**
	 * Checks to see if a url is cacheable.
	 *
	 * @param string $url The URL to check if it can sync.
	 *
	 * @return bool
	 */
	public function can_cache_url( $url ) {
		$changed = str_replace( array_keys( $this->cache_points ), '', $url );

		return $changed !== $url;
	}

	/**
	 * Convert a list of local URLS to Cached.
	 *
	 * @param array $urls List of local URLS to get cached versions.
	 *
	 * @return array
	 */
	public function get_cached_urls( $urls ) {
		$urls        = array_filter( array_unique( $urls ), array( $this, 'can_cache_url' ) );
		$keys        = array_map( array( $this, 'get_key_name' ), $urls );
		$params      = array(
			'post_type'       => self::POST_TYPE_SLUG,
			'post_name__in'   => $keys,
			'posts_per_page'  => - 1,
			'post_parent__in' => $this->get_active_cache_points( true ),
		);
		$posts       = new \WP_Query( $params );
		$all         = $posts->get_posts();
		$found_posts = array();
		foreach ( $all as $post ) {
			$create_time  = get_post_datetime( $post, 'date' )->getTimestamp();
			$current_time = current_datetime()->getTimestamp();
			$cached_url   = $post->post_content;
			$local_url    = $post->post_title;
			// Check if it's still local after 2 min.
			if ( $local_url === $cached_url && $create_time < $current_time - 60 ) {
				// Set bach to the uploader.
				$cached_url = $this->get_cache_upload_url( $post );
			}
			$found_posts[ $local_url ] = $cached_url;
		}
		$missing = array_diff( $urls, array_keys( $found_posts ) );

		if ( ! empty( $missing ) ) {
			$found_posts = array_merge( $found_posts, $this->prepare_cache( $missing ) );
		}

		return $found_posts;
	}

	/**
	 * Prepare a list of urls to be cached.
	 *
	 * @param array $urls  List of urls to cache.
	 * @param int   $limit Limit the number of items to be cached at once.
	 *
	 * @return array
	 */
	public function prepare_cache( $urls, $limit = 5 ) {
		$urls        = array_slice( $urls, 0, $limit );
		$cached_urls = array();
		foreach ( $urls as $url ) {
			$cached_point = $this->get_cache_point( $url );
			if ( is_null( $cached_point ) ) {
				continue;
			}
			$args = array(
				'post_type'    => 'cloudinary_asset',
				'post_title'   => $url,
				'post_content' => $url,
				'post_name'    => $this->get_key_name( $url ), // Has the name for uniqueness, and length.
				'post_status'  => 'publish',
				'post_parent'  => $cached_point->ID,
			);

			$id                  = wp_insert_post( $args );
			$cached_urls[ $url ] = $this->get_cache_upload_url( $id );
		}

		return $cached_urls;
	}

	/**
	 * Get the cache upload URL.
	 *
	 * @param int|\WP_Post $post The post or post ID.
	 *
	 * @return string
	 */
	public function get_cache_upload_url( $post ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		return $this->post_type->get_rest_controller()->get_cache_upload_url( $post->ID );
	}

	/**
	 * Register the cache point type.
	 */
	protected function register_post_type() {
		$args            = array(
			'label'                 => __( 'Post Type', 'cloudinary' ),
			'labels'                => array(),
			'supports'              => false,
			'hierarchical'          => true,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => true,
			'can_export'            => false,
			'has_archive'           => false,
			'exclude_from_search'   => false,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => 'page',
			'show_in_rest'          => true,
			'rest_base'             => 'cld-asset',
			'rest_controller_class' => '\Cloudinary\Cache\Cache_Controller',
		);
		$this->post_type = register_post_type( self::POST_TYPE_SLUG, $args );
	}
}
