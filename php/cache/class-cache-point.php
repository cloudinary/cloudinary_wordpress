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
	 * Holds the list of active cache_points.
	 *
	 * @var \WP_Post[]
	 */
	protected $active_cache_points = array();

	/**
	 * Holds the list of registered cache_points.
	 *
	 * @var \WP_Post[]
	 */
	protected $registered_cache_points = array();

	/**
	 * Holds the list of cache points requiring meta updates.
	 *
	 * @var array
	 */
	public $meta_updates = array();

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
		add_filter( 'update_post_metadata', array( $this, 'update_meta' ), 10, 4 );
		add_filter( 'get_post_metadata', array( $this, 'get_meta' ), 10, 3 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_meta' ), 10, 4 );
		add_action( 'shutdown', array( $this, 'meta_updates' ) );
	}

	/**
	 * Update our cache point meta data.
	 *
	 * @param null|bool $check      The check to allow short circuit of get_metadata.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 *
	 * @return bool|null
	 */
	public function update_meta( $check, $object_id, $meta_key, $meta_value ) {

		if ( self::POST_TYPE_SLUG === get_post_type( $object_id ) ) {
			$check = true;
			$meta  = $this->get_meta_cache( $object_id );
			if ( ! isset( $meta[ $meta_key ] ) || $meta_value !== $meta[ $meta_key ] ) {
				$meta[ $meta_key ] = $meta_value;
				$check             = $this->set_meta_cache( $object_id, $meta );
			}
		}

		return $check;
	}

	/**
	 * Delete our cache point meta data.
	 *
	 * @param null|bool $check      The check to allow short circuit of get_metadata.
	 * @param int       $object_id  The object ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The meta value.
	 *
	 * @return bool
	 */
	public function delete_meta( $check, $object_id, $meta_key, $meta_value ) {

		if ( self::POST_TYPE_SLUG === get_post_type( $object_id ) ) {
			$check = false;
			$meta  = $this->get_meta_cache( $object_id );
			if ( isset( $meta[ $meta_key ] ) && $meta[ $meta_key ] === $meta_value || is_null( $meta_value ) ) {
				unset( $meta[ $meta_key ] );
				$check = $this->set_meta_cache( $object_id, $meta );
			}
		}

		return $check;
	}

	/**
	 * Get our cache point meta data.
	 *
	 * @param null|bool $check     The check to allow short circuit of get_metadata.
	 * @param int       $object_id The object ID.
	 * @param string    $meta_key  The meta key.
	 *
	 * @return mixed
	 */
	public function get_meta( $check, $object_id, $meta_key ) {

		if ( self::POST_TYPE_SLUG === get_post_type( $object_id ) ) {
			$meta  = $this->get_meta_cache( $object_id );
			$value = array();
			if ( empty( $meta_key ) ) {
				$value = $meta;
			} elseif ( isset( $meta[ $meta_key ] ) ) {
				$value[] = $meta[ $meta_key ];
			} else {
				$value = '';
			}

			return $value;
		}

		return $check;
	}

	/**
	 * Get meta data for a cache point.
	 *
	 * @param int $object_id The post ID.
	 *
	 * @return mixed
	 */
	protected function get_meta_cache( $object_id ) {
		$meta = wp_cache_get( $object_id, 'cloudinary_asset' );
		if ( ! $meta ) {
			$post = get_post( $object_id );
			$meta = json_decode( $post->post_content, true );
			wp_cache_add( $object_id, $meta, 'cloudinary_asset' );
		}

		return $meta;
	}

	/**
	 * Set meta data for a cache point.
	 *
	 * @param int   $object_id The post ID.
	 * @param mixed $meta      The meta to set.
	 *
	 * @return bool
	 */
	protected function set_meta_cache( $object_id, $meta ) {
		$this->meta_updates[] = $object_id;

		return wp_cache_replace( $object_id, $meta, 'cloudinary_asset' );
	}

	/**
	 * Compiles all metadata to be saved at shutdown.
	 */
	public function meta_updates() {
		foreach ( $this->meta_updates as $id ) {
			$meta   = $this->get_meta_cache( $id );
			$params = array(
				'ID'           => $id,
				'post_content' => wp_json_encode( $meta ),
			);
			wp_update_post( $params );
		}
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
			$this->registered_cache_points[ $post->post_title ] = $post;
		}
		do_action( 'cloudinary_cache_init_cache_points' );

	}

	/**
	 * Checks if the cache point is registered.
	 *
	 * @param string $url the URL to check.
	 *
	 * @return bool
	 */
	protected function is_registered( $url ) {
		$url = trailingslashit( $url );

		return isset( $this->registered_cache_points[ $url ] );
	}

	/**
	 * Register a cache path.
	 *
	 * @param string $url      The URL to register.
	 * @param string $src_path The source path to register.
	 */
	public function register_cache_path( $url, $src_path ) {
		$this->create_cache_point( $url, $src_path );
		$this->activate_cache_point( $url );
	}

	/**
	 * Enable a cache path.
	 *
	 * @param string $url The path to enable.
	 */
	public function activate_cache_point( $url ) {
		$url = trailingslashit( $url );
		if ( $this->is_registered( $url ) ) {
			$cache_point                       = $this->registered_cache_points[ $url ];
			$this->active_cache_points[ $url ] = $cache_point;
			// Init the metadata.
			$this->get_meta_cache( $cache_point->ID );
		}
	}

	/**
	 * Add the url to the cache point's exclude list.
	 *
	 * @param \WP_Post $cache_point The cache point to add to.
	 * @param string   $url         The url to add.
	 */
	protected function exclude_url( $cache_point, $url ) {
		$excludes = get_post_meta( $cache_point->ID, 'excluded_urls', true );
		if ( empty( $excludes ) ) {
			$excludes = array();
		}
		$excludes[] = $url;
		update_post_meta( $cache_point->ID, 'excluded_urls', $excludes );
		$args = array(
			'ID'           => $cache_point->ID,
			'post_content' => wp_json_encode( $cache_point->post_content_filtered ),
		);
		wp_update_post( $args );
	}

	/**
	 * Checks if the file url is valid (exists).
	 *
	 * @param string $url The url to test.
	 *
	 * @return bool
	 */
	protected function is_valid_url( $url ) {
		static $validated_urls = array();
		if ( isset( $validated_urls[ $url ] ) ) {
			return $validated_urls[ $url ];
		}
		$validated_urls[ $url ] = ! is_null( $this->url_to_path( $url ) );

		return $validated_urls[ $url ];
	}

	/**
	 * Set cache path status.
	 *
	 * @param string $url    The path to disable.
	 * @param string $status The state to set.
	 */
	public function set_cache_path_state( $url, $status ) {
		$cache_point = $this->get_cache_point( $url );
		if ( $cache_point && $cache_point->post_status !== $status ) {
			$args = array(
				'ID'          => $cache_point->ID,
				'post_status' => $status,
			);
			wp_update_post( $args );
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
		$return = $this->active_cache_points;
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
	 * Load a cache point from a url.
	 *
	 * @param string $url The cache point url to get.
	 *
	 * @return \WP_Post
	 */
	protected function load_cache_point( $url ) {

		$key         = $this->get_key_name( $url );
		$url         = trailingslashit( $url );
		$cache_point = null;
		$params      = array(
			'name'           => $key,
			'post_type'      => self::POST_TYPE_SLUG,
			'posts_per_page' => 1,
		);
		$found       = get_posts( $params );
		if ( ! empty( $found ) ) {
			$cache_point                           = array_shift( $found );
			$this->registered_cache_points[ $url ] = $cache_point;
		}

		return $cache_point;
	}

	/**
	 * Get a cache point from a url.
	 *
	 * @param string $url The cache point url to get.
	 *
	 * @return \WP_Post
	 */
	public function get_cache_point( $url ) {
		// Lets check if the cache_point is a file.
		if ( pathinfo( $url, PATHINFO_EXTENSION ) ) {
			return $this->get_parent_cache_point( $url );
		}
		$url         = trailingslashit( $url );
		$cache_point = null;
		if ( isset( $this->active_cache_points[ $url ] ) ) {
			$cache_point = $this->active_cache_points[ $url ];
		} else {
			$cache_point = $this->load_cache_point( $url );
		}

		return $cache_point;
	}

	/**
	 * Get the parent cache point for a file URL.
	 *
	 * @param string $url The url of the file.
	 *
	 * @return \WP_Post|null
	 */
	protected function get_parent_cache_point( $url ) {
		$parent = null;
		foreach ( $this->active_cache_points as $key => $cache_point ) {
			$excludes = get_post_meta( $cache_point->ID, 'excluded_urls', true );
			if ( false !== strpos( $url, $key ) && ! in_array( $url, $excludes, true ) ) {
				$parent = $cache_point;
				break;
			}
		}

		return $parent;
	}

	/**
	 * Get a cache point from a url.
	 *
	 * @param Int         $id     The cache point ID to get cache for.
	 * @param string|null $search Optional search.
	 * @param int         $page   The page or results to load.
	 *
	 * @return \WP_Post[]
	 */
	public function get_cache_point_cache( $id, $search = null, $page = 1 ) {

		$cache_point = get_post( $id );
		if ( is_null( $cache_point ) ) {
			return array();
		}
		$args = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'posts_per_page' => 20,
			'paged'          => $page,
			'post_parent'    => $id,
			'post_status'    => array( 'draft', 'publish' ),
		);
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}
		$posts = new \WP_Query( $args );

		$items = array();
		foreach ( $posts->get_posts() as $post ) {
			$meta    = get_post_meta( $post->ID );
			$items[] = array(
				'ID'         => $post->ID,
				'key'        => $post->post_name,
				'local_url'  => $meta['local_url'],
				'cached_url' => $meta['cached_url'],
				'short_url'  => str_replace( $cache_point->post_title, '', $meta['local_url'] ),
				'active'     => 'publish' === $post->post_status && 'publish' === $cache_point->post_status,
			);
		}
		// translators: The current page and total pages.
		$description = sprintf( __( 'Page %1$d of %2$d', 'cloudinary' ), $page, $posts->max_num_pages );

		// translators: The number of files.
		$totals = sprintf( _n( '%d cached file', '%d cached files', $posts->found_posts, 'cloudinary' ), $posts->found_posts );

		$return = array(
			'items'        => $items,
			'total'        => $posts->found_posts,
			'total_pages'  => $posts->max_num_pages,
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

	/**
	 * Create a new cache point from a url.
	 *
	 * @param string $url      The url to create the cache point for.
	 * @param string $src_path The path to be cached.
	 */
	public function create_cache_point( $url, $src_path ) {
		if ( ! $this->is_registered( $url ) ) {
			$key      = $this->get_key_name( $url );
			$url      = trailingslashit( $url );
			$src_path = str_replace( ABSPATH, '', trailingslashit( $src_path ) );

			// Add meta data.
			$meta = array(
				'excluded_urls' => array(),
				'src_path'      => $src_path,
				'url'           => $url,
			);
			// Create new Cache point.
			$params                                = array(
				'post_name'    => $key,
				'post_type'    => self::POST_TYPE_SLUG,
				'post_title'   => $url,
				'post_content' => wp_json_encode( $meta ),
				'post_status'  => 'publish',
			);
			$post_id                               = wp_insert_post( $params );
			$this->registered_cache_points[ $url ] = get_post( $post_id );
		}
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
		return ! is_null( $this->get_parent_cache_point( $url ) );
	}

	/**
	 * Convert a list of local URLS to Cached.
	 *
	 * @param array $urls List of local URLS to get cached versions.
	 *
	 * @return array|null
	 */
	public function get_cached_urls( $urls ) {
		$active_ids = $this->get_active_cache_points( true );
		if ( empty( $active_ids ) ) {
			return null;
		}
		$urls = array_filter( $urls, array( $this, 'can_cache_url' ) );
		if ( empty( $urls ) ) {
			return null;
		}

		$keys        = array_map( array( $this, 'get_key_name' ), $urls );
		$params      = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'post_name__in'  => $keys,
			'posts_per_page' => - 1,
			'post_status'    => array( 'draft', 'publish' ),
		);
		$posts       = new \WP_Query( $params );
		$all         = $posts->get_posts();
		$found_posts = array();
		foreach ( $all as $index => $post ) {
			if ( 'draft' === $post->post_status ) {
				// Add it as local.
				unset( $urls[ array_search( $post->post_title, $urls ) ] );
				continue;
			}

			$meta       = get_post_meta( $post->ID );
			$cached_url = $meta['cached_url'];
			// Check if it's still local after 2 min.
			if ( $meta['local_url'] === $meta['cached_url'] && $meta['last_updated'] < time() - 20 ) {
				// Set bach to the uploader.
				$cached_url = $this->get_cache_upload_url( $post );
			}

			$found_posts[ $meta['local_url'] ] = $cached_url;
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
			$cache_point = $this->get_cache_point( $url );
			if ( is_null( $cache_point ) ) {
				continue;
			}
			if ( ! $this->is_valid_url( $url ) ) {
				$this->exclude_url( $cache_point, $url );
				continue;
			}

			$src_url = $url;
			if ( false !== strpos( $src_url, '?' ) ) {
				$src_url = strstr( $src_url, '?', true );
			}
			$base_url  = get_post_meta( $cache_point->ID, 'url', true );
			$base_path = get_post_meta( $cache_point->ID, 'src_path', true );
			$base_path = ABSPATH . $base_path;
			$file      = wp_normalize_path( str_replace( $base_url, $base_path, $src_url ) );

			$meta = array(
				'local_url'    => $url,
				'cached_url'   => $url,
				'src_file'     => $file,
				'last_updated' => time(),
			);

			$args = array(
				'post_type'    => self::POST_TYPE_SLUG,
				'post_title'   => $meta['local_url'],
				'post_content' => wp_json_encode( $meta ),
				'post_name'    => $this->get_key_name( $meta['local_url'] ), // Has the name for uniqueness, and length.
				'post_status'  => 'publish',
				'post_parent'  => $cache_point->ID,
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
	 * Get the cache update URL.
	 *
	 * @return string
	 */
	public function get_cache_update_url() {

		return $this->post_type->get_rest_controller()->get_cache_state_url();
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
			'public'                => false,
			'show_ui'               => false,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
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
