<?php
/**
 * Cloudinary Logger, to collect logs and debug data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Cache\Cache_Point;
use Cloudinary\Cache\File_System;
use Cloudinary\Component\Setup;
use Cloudinary\Settings\Setting;

/**
 * Plugin report class.
 */
class Cache extends Settings_Component implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media component.
	 *
	 * @var Media
	 */
	public $media;

	/**
	 * File System
	 *
	 * @var File_System
	 */
	public $file_system;

	/**
	 * Holds the Connect component.
	 *
	 * @var Connect
	 */
	protected $connect;

	/**
	 * Holds the Rest API component.
	 *
	 * @var REST_API
	 */
	protected $api;

	/**
	 * Holds the setting slugs for the file paths that are selected.
	 *
	 * @var array
	 */
	public $cache_data_keys = array();

	/**
	 * Holds the retrieved cache points for recall to minimize DB hits.
	 *
	 * @var array
	 */
	protected $cache_points = array();

	/**
	 * Holds the folder in which to store cached items in Cloudinary.
	 *
	 * @var string
	 */
	public $cache_folder;

	/**
	 * Holds the Cache Point object.
	 *
	 * @var Cache_Point
	 */
	public $cache_point;

	/**
	 * Holds the meta keys to be used.
	 */
	const META_KEYS = array(
		'upload_error'    => '_cloudinary_upload_errors',
		'uploading_cache' => '_cloudinary_uploading_cache',
		'has_table'       => '_cloudinary_has_table',
		'cache_table'     => 'cld_cache',
		'cache_point'     => 'cld_cache_points',
		'upload_method'   => '_cloudinary_upload_method',
	);

	/**
	 * Site Cache constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->file_system = new File_System( $plugin );
		if ( $this->file_system->enabled() ) {
			$this->cache_folder = wp_parse_url( get_site_url(), PHP_URL_HOST );
			$this->media        = $this->plugin->get_component( 'media' );
			$this->connect      = $this->plugin->get_component( 'connect' );
			$this->api          = $this->plugin->get_component( 'api' );
			$this->register_hooks();
		}
	}

	/**
	 * Rewrites urls in admin.
	 */
	public function admin_rewrite() {
		ob_start( array( $this, 'html_rewrite' ) );
		add_action(
			'shutdown',
			function () {
				ob_get_flush();
			},
			- 1
		);
	}

	/**
	 * Rewrite urls on frontend.
	 *
	 * @param string $template The frontend template being loaded.
	 *
	 * @return string
	 */
	public function frontend_rewrite( $template ) {
		$bypass = filter_input( INPUT_GET, 'bypass_cache', FILTER_SANITIZE_STRING );

		if ( ! empty( $bypass ) ) {
			return $template;
		}
		ob_start( array( $this, 'html_rewrite' ) );
		include $template;

		return CLDN_PATH . 'php/cache/template.php';
	}

	/**
	 * Rewrite HTML by replacing local URLS with Remote URLS.
	 *
	 * @param string $html The HTML to rewrite.
	 *
	 * @return string
	 */
	public function html_rewrite( $html ) {

		$sources = $this->build_sources( $html );
		// Replace all sources if we have some URLS.
		if ( ! empty( $sources ) && ! empty( $sources['url'] ) ) {
			$html = str_replace( $sources['url'], $sources['cld'], $html );
		}

		return $html;
	}

	/**
	 * Find the URLs from HTML.
	 *
	 * @param string $html The HTML to find urls from.
	 *
	 * @return array
	 */
	public function find_urls( $html ) {
		$types = $this->get_filetype_filters();

		// Get all instances of paths from the page with version suffix. Keep it loose as to catch relative urls as well.
		// wp_extract_urls() can also do this, however, we want to get only of the types specified.
		preg_match_all( '/(?<=["\'\(\s])[^"\'\(\s;\)]+?\.{1}(' . implode( '|', $types ) . ')([-a-zA-Z0-9@:;%_\+.~\#?&=]+)?/i', $html, $urls );
		$urls = array_unique( $urls[0] );

		return $urls;
	}

	/**
	 * Convert relative urls to full urls.
	 *
	 * @param string $html          The html to convert.
	 * @param string $relative_path The relative path to do the conversion against.
	 *
	 * @return array
	 */
	public function convert_relative_urls( $html, $relative_path ) {

		$extracted = $this->extract_relative_urls( $html, $relative_path );

		return str_replace( array_keys( $extracted ), $extracted, $html );
	}

	/**
	 * Extracts relative urls form HTML.
	 *
	 * @param string $html          The html to extract from.
	 * @param string $relative_path The relative path of the HTML location.
	 *
	 * @return array
	 */
	public function extract_relative_urls( $html, $relative_path ) {
		$urls      = $this->find_urls( $html );
		$mapped    = array_combine( $urls, $urls );
		$extracted = array_map(
			function ( $file ) use ( $relative_path ) {
				$file           = ltrim( $file, '/' ); // Remove the leading slash.
				$file_parts     = explode( '/', $file );
				$relative_path  = untrailingslashit( $relative_path ); // Remove the trailing slash.
				$relative_parts = explode( '/', $relative_path );
				foreach ( $file_parts as $part ) {
					if ( '.' === $part || '..' === $part ) {
						array_shift( $file_parts );
						if ( '..' === $part ) {
							// Drop the end of the relative path.
							array_pop( $relative_parts );
						}
						continue;
					}
					break;
				}
				$relative_parts = array_merge( $relative_parts, $file_parts );

				return implode( '/', $relative_parts );
			},
			$mapped
		);

		return $extracted;
	}

	/**
	 * Build sources for a set of paths and HTML.
	 *
	 * @param string $html The html to build against.
	 *
	 * @return array[]|null
	 */
	protected function build_sources( $html ) {

		$found_urls = $this->find_urls( $html );

		// Bail if not found.
		if ( empty( $found_urls ) ) {
			return null;
		}
		// Extract CSS files to look internally for relative URLS.
		$css_relatives = array_filter(
			$found_urls,
			function ( $file ) {
				return 'css' === pathinfo( strstr( $file, '?', true ), PATHINFO_EXTENSION );
			}
		);

		// Remove the URLs that have relative url's in the CSS.
		$found_urls = array_diff( $found_urls, $css_relatives );
		preg_match_all( '/<link([^>]*)>/s', $html, $found_tags );
		$tag_args = array_map( 'shortcode_parse_atts', $found_tags[1] );

		// Extract relative URLs.
		$tag_replace = array();
		foreach ( $css_relatives as $css_relative ) {
			$path      = $this->cache_point->url_to_path( strstr( $css_relative, '?', true ) );
			$css       = $this->file_system->wp()->get_contents( $path );
			$extracted = $this->extract_relative_urls( $css, dirname( $css_relative ) );

			// Find tags with relatives and replace with style tags.
			foreach ( $tag_args as $index => $args ) {

				if ( empty( $args ) || ! isset( $args['href'] ) ) {
					continue;
				}
				if ( $args['href'] === $css_relative ) {
					$css = str_replace( array_keys( $extracted ), $extracted, $css );
					$css = str_replace( "\n", '', $css );
					$css = str_replace( "\r\n", '', $css );

					$tag_replace[ $found_tags[0][ $index ] ] = '<style>' . $css . '</style>';
				}
			}

			$found_urls = array_merge( $found_urls, array_values( $extracted ) );
		}

		$found_urls  = array_unique( $found_urls );
		$found_posts = $this->cache_point->get_cached_urls( $found_urls );
		if ( empty( $found_posts ) ) {
			return null;
		}
		// Find tags with relatives and replace with style tags.
		foreach ( $tag_replace as &$tag_replaced ) {
			$tag_replaced = str_replace( array_keys( $found_posts ), $found_posts, $tag_replaced );
		}
		$found_posts = array_merge( $found_posts, $tag_replace );

		// Clean locals/pending.
		$found_posts = array_filter(
			$found_posts,
			function ( $key, $value ) {
				return $key != $value;
			},
			ARRAY_FILTER_USE_BOTH
		);

		$sources        = array();
		$sources['url'] = array_keys( $found_posts );
		$sources['cld'] = array_values( $found_posts );

		return $sources;
	}

	/**
	 * Register any hooks that this component needs.
	 */
	protected function register_hooks() {
		$this->cache_point = new Cache_Point( $this );
		if ( ! $this->bypass_cache() ) {
			add_filter( 'template_include', array( $this, 'frontend_rewrite' ), PHP_INT_MAX );
			add_action( 'admin_init', array( $this, 'admin_rewrite' ), 0 );
		}
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'cloudinary_upload_cache', array( $this, 'upload_cache' ) );
		add_action( 'http_request_args', array( $this, 'prevent_caching_internal_requests' ), 10, 5 );
	}

	/**
	 * Prevent internal background requests from getting new cached items created.
	 *
	 * @param array  $args The request structure.
	 * @param string $url  The URL being requested.
	 *
	 * @return array
	 */
	public function prevent_caching_internal_requests( $args, $url ) {
		$home    = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$request = strtolower( wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $home === $request ) {
			$args['headers']['x-cld-cache'] = time();
		}

		return $args;
	}

	/**
	 * Check if the cache needs to be bypassed.
	 */
	public function bypass_cache() {

		$bypass = filter_input( INPUT_SERVER, 'HTTP_X_CLD_CACHE', FILTER_SANITIZE_NUMBER_INT );

		/**
		 * Filter to allow bypassing the cache.
		 *
		 * @hook    cloudinary_bypass_cache
		 * @default false
		 *
		 * @param $bypass {bool} True to bypass, false to not.
		 *
		 * @return  {bool}
		 */
		return apply_filters( 'cloudinary_bypass_cache', ! is_null( $bypass ) );
	}

	/**
	 * Hook into the Cron event and process unsynced items.
	 *
	 * @param array $args The args passed to the cron event.
	 */
	public function upload_cache( $args ) {
		foreach ( (array) $args as $post_id ) {
			$meta        = get_post_meta( $post_id );
			$post        = get_post( $post_id );
			$cached_urls = get_post_meta( $post->post_parent, 'cached_urls', true );
			if ( empty( $cached_urls ) ) {
				$cached_urls = array();
			}

			foreach ( $meta['cached_urls'] as $url => &$cached_url ) {
				if ( $url !== $cached_url ) {
					continue;
				}
				$result = $this->sync_static( $meta['src_file'], $meta['base_url'] );
				if ( is_wp_error( $result ) ) {
					// If error, log it, and set item to draft.
					update_post_meta( $post_id, 'upload_error', $result );
					$params = array(
						'ID'          => $post_id,
						'post_status' => 'disabled',
					);
					wp_update_post( $params );
					continue;
				}
				$cached_url          = $result;
				$cached_urls[ $url ] = $cached_url;
			}
			update_post_meta( $post_id, 'cached_urls', $meta['cached_urls'] );
			update_post_meta( $post_id, 'last_updated', time() );
			// Update cache point, cache.
			update_post_meta( $post->post_parent, 'cached_urls', $cached_urls );
		}
	}

	/**
	 * Register the sync endpoint.
	 *
	 * @param array $endpoints The endpoint to add to.
	 *
	 * @return mixed
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
			'callback'            => array( $this, 'rest_disable_items' ),
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
			'callback'            => array( $this, 'rest_purge_cache_point' ),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
			'args'                => array(),
		);

		return $endpoints;
	}

	/**
	 * Purges a cachepoint which forces the entire point to re-evaluate cached items when requested.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function rest_purge_cache_point( $request ) {

		$cache_point = $request->get_param( 'cachePoint' );
		$result      = $this->cache_point->purge_cache( $cache_point );

		return rest_ensure_response( $result );
	}

	/**
	 * Get cached files for an cache point.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_caches( $request ) {
		$id           = $request->get_param( 'ID' );
		$search       = $request->get_param( 'search' );
		$page         = $request->get_param( 'page' );
		$current_page = $page ? $page : 1;
		$data         = $this->cache_point->get_cache_point_cache( $id, $search, $current_page );

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
	 * Change the status of a cache_point.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function rest_disable_items( $request ) {
		$ids   = $request['ids'];
		$state = $request['state'];
		foreach ( $ids as $id ) {
			$item         = get_post( $id );
			$cached_items = get_post_meta( $item->post_parent, 'cached_urls', true );
			$item_meta    = get_post_meta( $id );
			if ( 'delete' === $state ) {
				if ( isset( $cached_items[ $item_meta['local_url'] ] ) ) {
					unset( $cached_items[ $item_meta['local_url'] ] );
					update_post_meta( $item->post_parent, 'cached_urls', $cached_items );
				}
			} elseif ( 'draft' === $state ) {
				$this->cache_point->exclude_url( $item->post_parent, $item_meta['base_url'] );
			} elseif ( 'publish' === $state ) {
				$this->cache_point->remove_excluded_url( $item->post_parent, $item_meta['base_url'] );
			}
		}

		return $ids;
	}

	/**
	 * Gets the upload method: url or file upload by determining if the site is accessible from the outside.
	 *
	 * @return string
	 */
	protected function get_upload_method() {
		$method = get_transient( self::META_KEYS['upload_method'] );
		if ( empty( $method ) ) {
			$test_url = $this->media->base_url . '/image/fetch/' . $this->plugin->dir_url . 'no_file.svg';
			$request  = wp_remote_head( $test_url );
			$result   = wp_remote_retrieve_header( $request, 'x-cld-error' );
			$method   = 'url';
			if ( false !== strpos( $result, 'ERR_DNS_FAIL' ) ) {
				$method = 'direct';
			}
			set_transient( self::META_KEYS['upload_method'], $method, DAY_IN_SECONDS );
		}

		return $method;
	}

	/**
	 * Upload a static file.
	 *
	 * @param string $file The file path to upload.
	 * @param string $url  The file URL to upload.
	 *
	 * @return string|\WP_Error
	 */
	public function sync_static( $file, $url ) {

		$file_path   = $this->cache_folder . '/' . substr( $file, strlen( ABSPATH ) );
		$public_id   = dirname( $file_path ) . '/' . pathinfo( $file, PATHINFO_FILENAME );
		$type        = $this->media->get_file_type( $file );
		$method      = $this->get_upload_method();
		$upload_file = $this->cache_point->clean_url( $url );
		if ( 'direct' === $method ) {
			if ( function_exists( 'curl_file_create' ) ) {
				$upload_file = curl_file_create( $file ); // phpcs:ignore
				$upload_file->setPostFilename( $file );
			} else {
				$upload_file = '@' . $upload_file;
			}
		}
		$options = array(
			'file'          => $upload_file,
			'resource_type' => 'auto',
			'public_id'     => wp_normalize_path( $public_id ),
		);

		if ( 'image' === $type ) {
			$options['eager'] = 'f_auto,q_auto:eco';
		}
		$data = $this->connect->api->upload_cache( $options );

		if ( isset( $temp_name ) ) {
			$this->file_system->wp()->delete( $temp_name );
		}
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$url = $data['secure_url'];
		if ( ! empty( $data['eager'] ) ) {
			$url = $data['eager'][0]['secure_url'];
		}

		return $url;
	}

	/**
	 * Get the file type filters that is used to determine what kind of files get cached.
	 *
	 * @return array
	 */
	protected function get_filetype_filters() {
		$default_filters = array(
			'jpg',
			'gif',
			'png',
			'svg',
			'eot',
			'woff2',
			'woff',
			'ttf',
			'css',
			'js',
		);

		/**
		 * Filter types of files that can be cached.
		 *
		 * @hook    cloudinary_plugin_asset_cache_filters
		 * @default array()
		 *
		 * @param $default_filters {array} The types of files to be filtered.
		 *
		 * @return  {array}
		 */
		return apply_filters( 'cloudinary_plugin_asset_cache_filters', $default_filters );
	}

	/**
	 * Get the plugins table structure.
	 *
	 * @return array|mixed
	 */
	protected function get_plugins_table() {

		$plugins = get_plugins();
		$active  = wp_get_active_and_valid_plugins();
		$rows    = array();
		foreach ( $active as $plugin_path ) {
			$dir    = basename( dirname( $plugin_path ) );
			$plugin = $dir . '/' . basename( $plugin_path );
			if ( ! isset( $plugins[ $plugin ] ) ) {
				continue;
			}
			$slug          = sanitize_file_name( $plugin );
			$plugin_url    = plugins_url( $plugin );
			$details       = $plugins[ $plugin ];
			$rows[ $slug ] = array(
				'title'    => $details['Name'],
				'url'      => dirname( $plugin_url ),
				'src_path' => dirname( $plugin_path ),
			);
		}

		return array(
			'slug'       => 'plugin_files',
			'type'       => 'folder_table',
			'title'      => __( 'Plugin', 'cloudinary' ),
			'root_paths' => $rows,
		);

	}

	/**
	 * Get the settings structure for the theme table.
	 *
	 * @return array
	 */
	protected function get_theme_table() {

		$theme  = wp_get_theme();
		$themes = array(
			$theme,
		);
		if ( $theme->parent() ) {
			$themes[] = $theme->parent();
		}
		// Active Theme.
		foreach ( $themes as $theme ) {
			$theme_location = $theme->get_stylesheet_directory();
			$theme_slug     = basename( dirname( $theme_location ) ) . '/' . basename( $theme_location );
			$slug           = sanitize_file_name( $theme_slug );
			$rows[ $slug ]  = array(
				'title'    => $theme->get( 'Name' ),
				'url'      => $theme->get_stylesheet_directory_uri(),
				'src_path' => $theme->get_stylesheet_directory(),
			);
		}

		return array(
			'slug'       => 'theme_files',
			'type'       => 'folder_table',
			'title'      => __( 'Theme', 'cloudinary' ),
			'root_paths' => $rows,
		);
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_wp_table() {

		$rows = array();
		// Admin folder.
		$rows['wp_admin'] = array(
			'title'    => __( 'WordPress Admin', 'cloudinary' ),
			'url'      => admin_url(),
			'src_path' => $this->file_system->wp_admin_dir(),
		);
		// Includes folder.
		$rows['wp_includes'] = array(
			'title'    => __( 'WordPress Includes', 'cloudinary' ),
			'url'      => includes_url(),
			'src_path' => $this->file_system->wp_includes_dir(),
		);

		return array(
			'slug'       => 'wordpress_files',
			'type'       => 'folder_table',
			'title'      => __( 'WordPress', 'cloudinary' ),
			'root_paths' => $rows,
		);
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_content_table() {

		$rows               = array();
		$uploads            = wp_get_upload_dir();
		$rows['wp_content'] = array(
			'title'    => __( 'Uploads', 'cloudinary' ),
			'url'      => $uploads['baseurl'],
			'src_path' => $uploads['basedir'],
		);

		return array(
			'slug'       => 'content_files',
			'type'       => 'folder_table',
			'title'      => __( 'Content', 'cloudinary' ),
			'root_paths' => $rows,
		);
	}

	/**
	 * Setup the cache object.
	 */
	public function setup() {
		$this->setup_setting_tabs();
		$this->cache_point->init();
	}

	/**
	 * Adds the individual setting tabs.
	 */
	protected function setup_setting_tabs() {
		$cache_settings = $this->get_cache_settings();
		foreach ( $cache_settings as $setting ) {
			$callback = $setting->get_param( 'callback' );
			if ( is_callable( $callback ) ) {
				call_user_func( $callback ); // Init the settings.
			}
		}
	}

	/**
	 * Get all the Cache settings.
	 *
	 * @return Setting[]
	 */
	public function get_cache_settings() {
		static $settings = array();
		if ( empty( $settings ) ) {
			$main_setting = $this->settings->get_setting( 'cache_paths' );
			foreach ( $main_setting->get_settings() as $slug => $setting ) {
				$settings[ $slug ] = $setting;
			}
		}

		return $settings;
	}

	/**
	 * Check to see if cache setting is enabled.
	 *
	 * @param string $cache_setting The setting slug to check.
	 *
	 * @return bool
	 */
	protected function is_cache_setting_enabled( $cache_setting ) {

		return 'on' == $this->settings->get_value( 'enable_full_site_cache' ) || 'on' == $this->settings->get_value( $cache_setting );

	}

	/**
	 * Add paths for caching.
	 *
	 * @param string $setting             The setting to get paths from.
	 * @param string $cache_point_setting The setting with the cache points.
	 * @param string $all_cache_setting   The setting to define all on.
	 */
	public function add_cache_paths( $setting, $cache_point_setting, $all_cache_setting ) {

		$settings     = $this->settings->find_setting( $setting );
		$cache_points = $settings->find_setting( $cache_point_setting )->get_param( 'root_paths', array() );
		foreach ( $cache_points as $slug => $cache_point ) {
			$enable_full = $this->settings->get_value( 'enable_full_site_cache' );
			$enable_all  = $settings->get_value();
			// All on or Plugin is on.
			if ( 'on' == $enable_full || 'on' === $enable_all[ $all_cache_setting ] || ( isset( $enable_all[ $slug ] ) && 'on' === $enable_all[ $slug ] ) ) {
				$this->cache_point->register_cache_path( $cache_point['url'], $cache_point['src_path'] );
			}
		}
	}

	/**
	 * Add the plugin cache settings page.
	 */
	protected function add_plugin_settings() {

		$plugins_setup = $this->get_plugins_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Plugins', 'cloudinary' ),
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'cache_all_plugins',
				'description' => __( 'Deliver assets from all plugin folders', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
				),
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'cache_all_plugins' => false,
				),
				$plugins_setup,
			),
		);
		$this->settings->create_setting( 'plugins_settings', $params, $this->settings->get_setting( 'cache_plugins' ) );
		add_action( 'cloudinary_cache_init_cache_points', array( $this, 'add_plugin_cache_paths' ) );
	}

	/**
	 * Add Plugin paths for caching.
	 */
	public function add_plugin_cache_paths() {

		$this->add_cache_paths( 'cache_plugins', 'plugin_files', 'cache_all_plugins' );
	}

	/**
	 * Add Theme Settings page.
	 */
	protected function add_theme_settings() {

		$theme_setup = $this->get_theme_table();
		$params      = array(
			'type'        => 'panel',
			'title'       => __( 'Themes', 'cloudinary' ),
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'cache_all_themes',
				'description' => __( 'Deliver all assets from active theme.', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
				),
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'cache_all_themes' => false,
				),
				$theme_setup,
			),
		);

		$this->settings->create_setting( 'theme_settings', $params, $this->settings->get_setting( 'cache_themes' ) );
		add_action( 'cloudinary_cache_init_cache_points', array( $this, 'add_theme_cache_paths' ) );
	}

	/**
	 * Add Theme paths for caching.
	 */
	public function add_theme_cache_paths() {

		$this->add_cache_paths( 'cache_themes', 'theme_files', 'cache_all_themes' );
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_wp_settings() {

		$wordpress_setup = $this->get_wp_table();
		$params          = array(
			'type'        => 'panel',
			'title'       => __( 'WordPress', 'cloudinary' ),
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'cache_all_wp',
				'description' => __( 'Deliver all assets from WordPress core.', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
				),
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'cache_all_wp' => false,
				),
				$wordpress_setup,
			),
		);

		$this->settings->create_setting( 'wordpress_settings', $params, $this->settings->get_setting( 'cache_wordpress' ) );
		add_action( 'cloudinary_cache_init_cache_points', array( $this, 'add_wp_cache_paths' ) );
	}

	/**
	 * Add Theme paths for caching.
	 */
	public function add_wp_cache_paths() {

		$this->add_cache_paths( 'cache_wordpress', 'wordpress_files', 'cache_all_wp' );
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_content_settings() {

		$content_setup = $this->get_content_table();
		$params        = array(
			'type'        => 'panel',
			'title'       => __( 'Content', 'cloudinary' ),
			'collapsible' => 'closed',
			'attributes'  => array(
				'header' => array(
					'class' => array(
						'full-width',
					),
				),
				'wrap'   => array(
					'class' => array(
						'full-width',
					),
				),
			),
			array(
				'type'        => 'on_off',
				'slug'        => 'cache_all_content',
				'description' => __( 'Deliver all content assets from WordPress Media Library.', 'cloudinary' ),
				'default'     => 'off',
				'master'      => array(
					'enable_full_site_cache',
				),
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'cache_all_content' => false,
				),
				$content_setup,
			),
		);

		$this->settings->create_setting( 'content_settings', $params, $this->settings->get_setting( 'cache_content' ) );
		add_action( 'cloudinary_cache_init_cache_points', array( $this, 'add_content_cache_paths' ) );
	}

	/**
	 * Add Content paths for caching.
	 */
	public function add_content_cache_paths() {
		if ( ! is_admin() ) {
			// Exclude content replacement in admin.
			$this->add_cache_paths( 'cache_content', 'content_files', 'cache_all_content' );
		};
	}

	/**
	 * Enabled method for version if settings are enabled.
	 *
	 * @param bool $enabled Flag to enable.
	 *
	 * @return bool
	 */
	public function is_enabled( $enabled ) {
		return ! is_null( $this->file_system ) && $this->connect->is_connected();
	}

	/**
	 * Returns the setting definitions.
	 *
	 * @return array|null
	 */
	public function settings() {
		if ( ! $this->file_system->enabled() ) {
			return null;
		}

		$args = array(
			'type'       => 'page',
			'menu_title' => __( 'Site Cache', 'cloudinary' ),
			'tabs'       => array(
				'main_cache_page' => array(
					'page_title' => __( 'Site Cache', 'cloudinary' ),
					array(
						'slug'       => 'cache_paths',
						'type'       => 'panel',
						'title'      => __( 'Cache Settings', 'cloudinary' ),
						'attributes' => array(
							'header' => array(
								'class' => array(
									'full-width',
								),
							),
							'wrap'   => array(
								'class' => array(
									'full-width',
								),
							),
						),
						array(
							'type'         => 'on_off',
							'slug'         => 'enable_full_site_cache',
							'title'        => __( 'Full CDN', 'cloudinary' ),
							'tooltip_text' => __(
								'Deliver all assets from Cloudinary.',
								'cloudinary'
							),
							'description'  => __( 'Enable caching site assets.', 'cloudinary' ),
							'default'      => 'off',
						),
						array(
							'slug'     => 'cache_plugins',
							'type'     => 'frame',
							'callback' => array( $this, 'add_plugin_settings' ),
						),
						array(
							'slug'     => 'cache_themes',
							'type'     => 'frame',
							'callback' => array( $this, 'add_theme_settings' ),
						),
						array(
							'slug'     => 'cache_wordpress',
							'type'     => 'frame',
							'callback' => array( $this, 'add_wp_settings' ),
						),
						array(
							'slug'     => 'cache_content',
							'type'     => 'frame',
							'callback' => array( $this, 'add_content_settings' ),
						),
					),
					array(
						'type'       => 'submit',
						'attributes' => array(
							'wrap' => array(
								'class' => array(
									'full-width',
								),
							),
						),
					),
				),
			),
		);

		return $args;
	}
}
