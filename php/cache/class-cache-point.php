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
	 * Holds a list of pre-found cached urls before querying to find cached items
	 *
	 * @var array.
	 */
	protected $pre_cached = array();
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
	 * Holds the list of items to upload.
	 *
	 * @var array
	 */
	protected $to_upload = array();

	/**
	 * Holds the limit of items to sync per visitor.
	 *
	 * @var int
	 */
	protected $sync_limit;

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
	 * Cache Point constructor.
	 *
	 * @param Cache $cache The plugin ache object.
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
		/**
		 * Filter the on demand synced items limit.
		 *
		 * @hook    cloudinary_on_demand_sync_limit
		 * @default 100
		 *
		 * @param $value {int} The default number of static assets.
		 *
		 * @return {int}
		 */
		$this->sync_limit = apply_filters( 'cloudinary_on_demand_sync_limit', 100 );
		$this->register_post_type();

		add_filter( 'update_post_metadata', array( $this, 'update_meta' ), 10, 4 );
		add_filter( 'get_post_metadata', array( $this, 'get_meta' ), 10, 3 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_meta' ), 10, 4 );
		add_action( 'shutdown', array( $this, 'meta_updates' ) );
		add_action( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 10, 2 );
	}

	/**
	 * Add DNS prefetch link tag for assets.
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for, e.g. 'preconnect' or 'prerender'.
	 *
	 * @return array
	 */
	public function dns_prefetch( $urls, $relation_type ) {

		if ( 'dns-prefetch' === $relation_type && ! empty( $this->active_cache_points ) ) {
			$urls[] = $this->cache->media->base_url;
		}

		return $urls;
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
			$meta = $this->get_meta_cache( $object_id );
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
		if ( ! in_array( $object_id, $this->meta_updates, true ) ) {
			$this->meta_updates[] = $object_id;
		}

		return wp_cache_replace( $object_id, $meta, 'cloudinary_asset' );
	}

	/**
	 * Compiles all metadata and preps upload at shutdown.
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
		// Prep the upload for un-synced items.
		if ( ! empty( $this->to_upload ) ) {
			$api = $this->cache->plugin->get_component( 'api' );
			if ( $api ) {
				$api->background_request( 'upload_cache', array( 'ids' => $this->to_upload ), 'POST' );
			}
		}
	}

	/**
	 * Init the cache_points.
	 */
	public function init() {
		$params = array(
			'post_type'              => self::POST_TYPE_SLUG,
			'post_status'            => array( 'enabled', 'disabled' ),
			'post_parent'            => 0,
			'posts_per_page'         => 100,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
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
	 * @param string $version  The version of the cache point.
	 */
	public function register_cache_path( $url, $src_path, $version ) {
		$this->create_cache_point( $url, $src_path, $version );
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
	 * @param int    $cache_point_id The cache point ID to add to.
	 * @param string $url            The url to add.
	 */
	public function exclude_url( $cache_point_id, $url ) {
		$excludes = get_post_meta( $cache_point_id, self::META_KEYS['excluded_urls'], true );
		if ( empty( $excludes ) ) {
			$excludes = array();
		}
		if ( ! in_array( $url, $excludes, true ) ) {
			$excludes[] = $url;
			update_post_meta( $cache_point_id, self::META_KEYS['excluded_urls'], $excludes );
		}
	}

	/**
	 * Add the url to the cache point's exclude list.
	 *
	 * @param int    $cache_point_id The cache point ID to add to.
	 * @param string $url            The url to add.
	 */
	public function remove_excluded_url( $cache_point_id, $url ) {
		$excludes = get_post_meta( $cache_point_id, self::META_KEYS['excluded_urls'], true );
		if ( ! empty( $excludes ) ) {
			$index = array_search( $url, (array) $excludes, true );
			if ( false !== $index ) {
				unset( $excludes[ $index ] );
				update_post_meta( $cache_point_id, self::META_KEYS['excluded_urls'], $excludes );
			}
		}
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
		$url      = $this->clean_url( $url );
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
	 * @return \WP_Post | null
	 */
	protected function load_cache_point( $url ) {
		if ( ! isset( $this->registered_cache_points[ $url ] ) ) {
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
		}

		return isset( $this->registered_cache_points[ $url ] ) ? $this->registered_cache_points[ $url ] : null;
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
			if ( false !== strpos( $url, $key ) ) {
				$excludes = (array) get_post_meta( $cache_point->ID, self::META_KEYS['excluded_urls'], true );
				if ( ! in_array( $url, $excludes, true ) ) {
					$parent = $cache_point;
				}
				break;
			}
		}

		return $parent;
	}

	/**
	 * Get all cache items for a cache point.
	 *
	 * @param string|int $cache_point_id_url The cache point ID or URL.
	 * @param bool       $id_only            Flag to get ID's only.
	 *
	 * @return \WP_Post[]|int[]
	 */
	public function get_cache_items( $cache_point_id_url, $id_only = false ) {
		$items = array();
		if ( ! is_int( $cache_point_id_url ) ) {
			$cache_point = $this->get_cache_point( $cache_point_id_url );
		} else {
			$cache_point = get_post( $cache_point_id_url );
		}
		if ( ! is_null( $cache_point ) ) {

			$params = array(
				'post_type'              => self::POST_TYPE_SLUG,
				'posts_per_page'         => 100,
				'post_status'            => array( 'enabled', 'disabled' ),
				'post_parent'            => $cache_point->ID,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'paged'                  => 1,
			);
			if ( true === $id_only ) {
				$params['fields'] = 'ids';
			}
			$posts = new \WP_Query( $params );
			do {
				$found = $posts->get_posts();
				$items = array_merge( $items, $found );
				$params['paged'] ++;
				$posts = new \WP_Query( $params );
			} while ( $posts->have_posts() );
		}

		return $items;
	}

	/**
	 * Get a cache point from a url.
	 *
	 * @param Int         $id     The cache point ID to get cache for.
	 * @param string|null $search Optional search.
	 * @param int         $page   The page or results to load.
	 *
	 * @return array
	 */
	public function get_cache_point_cache( $id, $search = null, $page = 1 ) {
		$cache_point = get_post( $id );
		if ( is_null( $cache_point ) ) {
			return array();
		}
		$cached_items = (array) get_post_meta( $cache_point->ID, self::META_KEYS['cached_urls'], true );
		$excluded     = (array) get_post_meta( $cache_point->ID, self::META_KEYS['excluded_urls'], true );
		$cached_items = array_filter( $cached_items );
		$args         = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'posts_per_page' => 20,
			'paged'          => $page,
			'post_parent'    => $id,
			'post_status'    => array( 'enabled', 'disabled' ),
		);
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}
		$posts = new \WP_Query( $args );
		$items = array();
		foreach ( $posts->get_posts() as $post ) {
			$meta = get_post_meta( $post->ID );

			$has = array_intersect_key( $meta[ self::META_KEYS['cached_urls'] ], $cached_items );
			if ( empty( $has ) ) {
				continue; // Not yet uploaded.
			}

			$items[] = array(
				'ID'        => $post->ID,
				'key'       => $post->post_name,
				'local_url' => $meta[ self::META_KEYS['base_url'] ],
				'short_url' => str_replace( $cache_point->post_title, '', $meta[ self::META_KEYS['base_url'] ] ),
				'active'    => ! in_array( $meta[ self::META_KEYS['base_url'] ], $excluded, true ),
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

	/**
	 * Create a new cache point from a url.
	 *
	 * @param string $url      The url to create the cache point for.
	 * @param string $src_path The path to be cached.
	 * @param string $version  The version of the cache point.
	 */
	public function create_cache_point( $url, $src_path, $version ) {
		if ( ! $this->is_registered( $url ) ) {
			$key      = $this->get_key_name( $url );
			$url      = trailingslashit( $url );
			$src_path = str_replace( ABSPATH, '', trailingslashit( $src_path ) );

			// Add meta data.
			$meta = array(
				self::META_KEYS['excluded_urls'] => array(),
				self::META_KEYS['cached_urls']   => array(),
				self::META_KEYS['src_path']      => $src_path,
				self::META_KEYS['url']           => $url,
				self::META_KEYS['version']       => $version,
			);
			// Create new Cache point.
			$params                                = array(
				'post_name'    => $key,
				'post_type'    => self::POST_TYPE_SLUG,
				'post_title'   => $url,
				'post_content' => wp_json_encode( $meta ),
				'post_status'  => 'enabled',
			);
			$post_id                               = wp_insert_post( $params );
			$this->registered_cache_points[ $url ] = get_post( $post_id );
		}
		$this->check_version( $url, $version );
	}

	/**
	 * Check and update the version if needed.
	 *
	 * @param string $url     The url of the cache point.
	 * @param string $version the version.
	 */
	protected function check_version( $url, $version ) {
		$cache_point = $this->get_cache_point( $url );
		if ( ! is_numeric( $cache_point ) ) {
			$prev_version = get_post_meta( $cache_point->ID, self::META_KEYS['version'], true );
			if ( $prev_version !== $version ) {
				update_post_meta( $cache_point->ID, self::META_KEYS['version'], $version );
			}
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
		return md5( trailingslashit( $this->clean_url( $url ) ) );
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
	 * Clean URLs te remove any query arguments and fragments.
	 *
	 * @param string $url The URL to clean.
	 *
	 * @return string
	 */
	public function clean_url( $url ) {
		$default = array(
			'scheme' => '',
			'host'   => '',
			'path'   => '',
		);
		$parts   = wp_parse_args( wp_parse_url( $url ), $default );

		return $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
	}

	/**
	 * Get cached urls from cachepoint cache.
	 *
	 * @param array $urls List of URLS to extract.
	 *
	 * @return array
	 */
	protected function pre_cache_urls( $urls ) {
		foreach ( $urls as $index => $url ) {
			$cache_point = $this->get_cache_point( $url );
			if ( $cache_point ) {
				$cached_urls = get_post_meta( $cache_point->ID, self::META_KEYS['cached_urls'], true );
				if ( isset( $cached_urls[ $url ] ) ) {
					$this->pre_cached[ $url ] = $cached_urls[ $url ];
					unset( $urls[ $index ] );
				}
			}
		}

		return $urls;
	}

	/**
	 * Purge the entire cache for a cache point.
	 *
	 * @param int $id The cache point post ID.
	 *
	 * @return bool
	 */
	public function purge_cache( $id ) {
		$return      = false;
		$cache_point = get_post( $id );
		if ( ! is_null( $cache_point ) ) {
			$items = $this->get_cache_items( $cache_point->ID, true );
			foreach ( $items as $cache_item ) {
				update_post_meta( $cache_item, self::META_KEYS['cached_urls'], array() );
			}
			update_post_meta( $cache_point->ID, self::META_KEYS['cached_urls'], array() );
			$return = true;
		}

		return $return;
	}

	/**
	 * Filter out duplicate urls that have different query and fragments.
	 * We should only have a single base url per asset to prevent creating duplicate base items.
	 *
	 * @param string $url The url to test.
	 *
	 * @return bool
	 */
	protected function filter_duplicate_base( $url ) {
		static $urls = array();
		$clean = $this->clean_url( $url );
		if ( isset( $urls[ $clean ] ) ) {
			return false;
		}
		$urls[ $clean ] = true;

		return true;
	}

	/**
	 * Version a URL.
	 *
	 * @param string $url The url to add a version to.
	 *
	 * @return string
	 */
	protected function version_url( $url ) {
		$url         = $this->clean_url( $url );
		$cache_point = $this->get_cache_point( $url );
		$version     = get_post_meta( $cache_point->ID, self::META_KEYS['version'], true );

		return add_query_arg( 'version', $version, $url );
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

		$urls        = $this->pre_cache_urls( array_map( array( $this, 'version_url' ), $urls ) );
		$found_posts = $this->pre_cached;
		if ( ! empty( $urls ) ) {
			$queried_items = $this->query_cached_items( $urls );
			if ( ! empty( $queried_items ) ) {
				$found_posts += $queried_items;
			}
		}
		$missing = array_diff( $urls, array_keys( $found_posts ) );
		$missing = array_filter( $missing, array( $this, 'filter_duplicate_base' ) );
		if ( ! empty( $missing ) ) {
			$this->prepare_cache( $missing );
		}

		// Remove urls that are local to improve replace performance.
		$found_posts = array_filter(
			$found_posts,
			function ( $key, $value ) {
				return $key !== $value;
			},
			ARRAY_FILTER_USE_BOTH
		);

		return $found_posts;
	}

	/**
	 * Add item to be synced later.
	 *
	 * @param int $id The cloudinary_asset post type ID to sync.
	 */
	protected function prepare_for_sync( $id ) {
		if ( count( $this->to_upload ) < $this->sync_limit ) {
			$this->to_upload[] = $id;
		}
	}

	/**
	 * Query cached items that are not cached in the cache point meta (purged, new, evaluated).
	 * This will add items to the to_upload to re-evaluate, and re-upload if needed.
	 *
	 * @param array $urls The urls to query.
	 *
	 * @return array
	 */
	public function query_cached_items( $urls ) {
		$clean_urls = array_map( array( $this, 'clean_url' ), $urls );
		$keys       = array_map( array( $this, 'get_key_name' ), $urls );
		$params     = array(
			'post_type'              => self::POST_TYPE_SLUG,
			'post_name__in'          => array_unique( $keys ),
			'posts_per_page'         => 100,
			'post_status'            => array( 'enabled', 'disabled' ),
			'post_parent__in'        => $this->get_active_cache_points( true ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'paged'                  => 1,
		);
		$posts      = new \WP_Query( $params );
		do {
			$all         = $posts->get_posts();
			$found_posts = array();
			foreach ( $all as $index => $post ) {
				$meta     = get_post_meta( $post->ID );
				$excludes = get_post_meta( $post->post_parent, self::META_KEYS['excluded_urls'], true );
				if ( in_array( $meta[ self::META_KEYS['base_url'] ], $excludes, true ) ) {
					// Add it as local, since this is being ignored.
					$found_posts[ $meta[ self::META_KEYS['base_url'] ] ] = $meta[ self::META_KEYS['base_url'] ];
					continue;
				}
				$indexes = array_keys( $clean_urls, $meta[ self::META_KEYS['base_url'] ], true );
				if ( empty( $indexes ) ) {
					continue; // Shouldn't happen, but bail in case.
				}
				foreach ( $indexes as $key ) {
					$url = $urls[ $key ];

					if ( ! isset( $meta[ self::META_KEYS['cached_urls'] ][ $url ] )
						 || $url === $meta[ self::META_KEYS['cached_urls'] ][ $url ]
							&& $meta[ self::META_KEYS['last_updated'] ] < time() - MINUTE_IN_SECONDS * 10 ) {
						// Send to upload prep.
						$this->prepare_for_sync( $post->ID );
						$meta[ self::META_KEYS['cached_urls'] ][ $url ] = $url;
						update_post_meta( $post->ID, self::META_KEYS['cached_urls'], $meta[ self::META_KEYS['cached_urls'] ] );
					}
					$found_posts[ $url ] = $meta[ self::META_KEYS['cached_urls'] ][ $url ];
				}
			}
			$params['paged'] ++;
			$posts = new \WP_Query( $params );
		} while ( $posts->have_posts() );

		return $found_posts;
	}

	/**
	 * Prepare a list of urls to be cached.
	 *
	 * @param array $urls List of urls to cache.
	 */
	public function prepare_cache( $urls ) {

		foreach ( $urls as $url ) {
			$base_url    = $this->clean_url( $url );
			$cache_point = $this->get_cache_point( $base_url );
			if ( is_null( $cache_point ) || $this->exists( $base_url ) ) {
				continue;
			}

			$file = $this->url_to_path( $url );
			if ( is_null( $file ) ) {
				$this->exclude_url( $cache_point->ID, $url );
				continue;
			}
			$meta = array(
				self::META_KEYS['base_url']     => $base_url,
				self::META_KEYS['cached_urls']  => array(
					$url => $url,
				),
				self::META_KEYS['src_file']     => $file,
				self::META_KEYS['last_updated'] => time(),
			);

			$args = array(
				'post_type'    => self::POST_TYPE_SLUG,
				'post_title'   => $base_url,
				'post_content' => wp_json_encode( $meta ),
				'post_name'    => $this->get_key_name( $base_url ), // Has the name for uniqueness, and length.
				'post_status'  => 'enabled',
				'post_parent'  => $cache_point->ID,
			);

			$id = wp_insert_post( $args );
			$this->prepare_for_sync( $id );
		}
	}

	/**
	 * Check if the post exists to prevent creating duplicates.
	 *
	 * @param string $url The url to test.
	 *
	 * @return bool
	 */
	public function exists( $url ) {

		$cache_name = $this->get_key_name( $url );
		$args       = array(
			'post_type'      => self::POST_TYPE_SLUG,
			'post_status'    => array( 'enabled', 'disabled' ),
			'posts_per_page' => 1,
			'name'           => $cache_name,
		);
		$query      = new \WP_Query( $args );

		return (bool) $query->found_posts;
	}

	/**
	 * Register the cache point type.
	 */
	protected function register_post_type() {
		$args            = array(
			'label'               => __( 'Cloudinary Asset', 'cloudinary' ),
			'description'         => __( 'Post type to represent a non-media library asset.', 'cloudinary' ),
			'labels'              => array(),
			'supports'            => false,
			'hierarchical'        => true,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'capability_type'     => 'page',
		);
		$this->post_type = register_post_type( self::POST_TYPE_SLUG, $args );
	}
}
