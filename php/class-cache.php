<?php
/**
 * Cloudinary Logger, to collect logs and debug data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use \Cloudinary\Cache\File_System;

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
	protected $media;

	/**
	 * File System
	 *
	 * @var File_System
	 */
	protected $file_system;

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
	 * WP DB Class.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * Holds the table name for caching items.
	 *
	 * @var string
	 */
	protected $cache_table;

	/**
	 * Holds the table name for storing cache points.
	 *
	 * @var string
	 */
	protected $cache_point_table;

	/**
	 * Holds the folder in which to store cached items in Cloudinary.
	 *
	 * @var string
	 */
	public $cache_folder;

	/**
	 * Holds the meta keys to be used.
	 */
	const META_KEYS = array(
		'queue'           => '_cloudinary_cache_queue',
		'url'             => '_cloudinary_cache_url',
		'cached'          => '_cloudinary_cached',
		'plugin_files'    => '_cloudinary_plugin_files',
		'upload_error'    => '_cloudinary_upload_errors',
		'uploading_cache' => '_cloudinary_uploading_cache',
		'content_folders' => '_cloudinary_content_folders',
		'has_table'       => '_cloudinary_has_table',
		'cache_table'     => 'cld_cache',
		'cache_point'     => 'cld_cache_points',
	);

	/**
	 * Holds the timeout in seconds, for when to resync the list of found files for a path.
	 */
	const FILE_LOOKUP_TIMEOUT = 60;

	/**
	 * Site Cache constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		global $wpdb;
		$this->wpdb = $wpdb;
		parent::__construct( $plugin );
		$this->file_system = new File_System( $plugin );
		if ( $this->file_system->enabled() ) {
			$this->cache_folder      = wp_parse_url( get_site_url(), PHP_URL_HOST );
			$this->cache_table       = $this->wpdb->prefix . self::META_KEYS['cache_table'];
			$this->cache_point_table = $this->wpdb->prefix . self::META_KEYS['cache_point'];
			$this->media             = $this->plugin->get_component( 'media' );
			$this->connect           = $this->plugin->get_component( 'connect' );
			$this->api               = $this->plugin->get_component( 'api' );
			$this->register_hooks();
		}
	}

	/**
	 * Rewrites urls in admin.
	 */
	public function admin_rewrite() {
		ob_start( array( $this, 'html_rewrite' ) );
	}

	/**
	 * Clear the plugin Settings cache.
	 *
	 * @param string $plugin The plugin to invalidate cache for.
	 */
	public function invalidate_plugin_cache( $plugin ) {

	}

	/**
	 * Invalidate cache of src files.
	 *
	 * @param array|string $files File/Files to invalidate.
	 */
	public function invalidate( $files ) {

	}

	/**
	 * Get the file paths for the plugins.
	 *
	 * @param string $plugin  The plugin path.
	 * @param string $version The plugin version.
	 * @param array  $types   The types to get.
	 * @param bool   $rebuild Flag to rebuild paths.
	 *
	 * @return array
	 */
	protected function get_plugin_data( $plugin, $version, $types, $rebuild = false ) {
		$plugin_path = $this->file_system->wp_plugins_dir() . dirname( $plugin );
		$cache_point = $this->get_cache_data( $plugin_path );
		$paths       = $cache_point['data'];
		if ( true === $rebuild || empty( $paths ) || $version !== $paths['version'] || $types !== $paths['types'] ) {
			$paths = array(
				'type'              => 'plugin',
				'plugin'            => $plugin,
				'version'           => $version,
				'types'             => $types,
				'unique_extensions' => array(),
				'files_fetched'     => time(),
				'path'              => $plugin_path,
			);

			$paths['files']             = $this->get_plugin_paths( $plugin, $types );
			$paths['unique_extensions'] = File_System::get_unique_extensions( $paths['files'] );
			$cache_point['data']        = $paths;
			$this->set_cache_data( $plugin_path, $cache_point );
		}

		return $paths;
	}

	/**
	 * Get the file paths for the plugins.
	 *
	 * @param string $plugin The plugin path.
	 * @param array  $types  The types to get.
	 *
	 * @return array
	 */
	protected function get_plugin_paths( $plugin, $types ) {
		$all_files = get_plugin_files( $plugin );

		$filtered_files = File_System::filter_file_types( $all_files, $types );
		$trim_len       = strlen( dirname( $plugin ) ) + 1;

		return array_map(
			function ( $file ) use ( $trim_len ) {
				return substr( $file, $trim_len );
			},
			$filtered_files
		);
	}

	/**
	 * Get the file paths for the theme.
	 *
	 * @param \WP_Theme|string $theme   The theme name or object.
	 * @param array            $types   The types to get.
	 * @param bool             $rebuild Flag to rebuild paths.
	 *
	 * @return array
	 */
	protected function get_theme_data( $theme, $types, $rebuild = false ) {
		if ( is_string( $theme ) ) {
			$theme = wp_get_theme( $theme );
		}
		$theme_path  = $theme->get_stylesheet_directory();
		$cache_point = $this->get_cache_data( $theme_path );
		$paths       = $cache_point['data'];
		if ( true === $rebuild || empty( $paths ) || $theme->get( 'Version' ) !== $paths['version'] || $types !== $paths['types'] ) {
			$paths                      = array(
				'title'             => ! empty( $theme->get( 'Name' ) ) ? $theme->get( 'Name' ) : basename( $theme_path ),
				'type'              => 'theme',
				'theme'             => basename( $theme_path ),
				'version'           => $theme->get( 'Version' ),
				'types'             => $types,
				'unique_extensions' => array(),
				'files_fetched'     => time(),
				'path'              => $theme_path,
			);
			$files                      = $this->get_theme_paths( $theme, $types );
			$paths['files']             = File_System::filter_min( $files );
			$paths['unique_extensions'] = File_System::get_unique_extensions( $paths['files'] );
			$cache_point['data']        = $paths;
			$this->set_cache_data( $theme_path, $cache_point );
		}

		return $paths;
	}

	/**
	 * Get files for the theme.
	 *
	 * @param \WP_Theme $theme The theme to get paths for.
	 * @param array     $types The file types to get.
	 *
	 * @return  array
	 */
	protected function get_theme_paths( $theme, $types ) {
		$all_files = $theme->get_files( $types, 1000, false );

		return array_keys( $all_files );
	}

	/**
	 * Get the file paths for a folder.
	 *
	 * @param string $path    The folder path.
	 * @param array  $types   The types to get.
	 * @param bool   $rebuild Flag to rebuild paths.
	 *
	 * @return array
	 */
	protected function get_folder_data( $path, $types, $rebuild = false ) {
		$cache_point = $this->get_cache_data( $path );
		$paths       = $cache_point['data'];
		$version     = get_bloginfo( 'version' );
		if ( true === $rebuild || ! empty( $paths ) || $version !== $paths['version'] || $types !== $paths['types'] ) {
			$paths                      = array(
				'version'           => $version,
				'type'              => $path,
				'types'             => $types,
				'unique_extensions' => array(),
				'files_fetched'     => time(),
				'path'              => $path,
			);
			$paths['files']             = $this->file_system->get_files( $path, $types );
			$paths['unique_extensions'] = File_System::get_unique_extensions( $paths['files'] );
			$cache_point['data']        = $paths;
			$this->set_cache_data( $path, $cache_point );
		}

		return $paths;
	}

	/**
	 * Get the cached data for a path.
	 *
	 * @param string $path The path to get the cache data for.
	 *
	 * @return array
	 */
	protected function get_cache_data( $path ) {
		if ( empty( $this->cache_points[ $path ] ) ) {
			$cache_point = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->cache_point_table} WHERE cache_point = %s", $path ), ARRAY_A ); // phpcs:ignore
			if ( is_null( $cache_point ) ) {
				$cache_point = array(
					'cache_point' => $path,
					'mode'        => 'disabled',
				);
				$this->wpdb->insert( $this->cache_point_table, $cache_point );
			} else {
				$cache_point['data']    = json_decode( $cache_point['data'], true );
				$cache_point['exclude'] = json_decode( $cache_point['exclude'], true );
			}
			$this->cache_points[ $path ] = $cache_point;
		}

		// Override manual settings.
		return $this->cache_points[ $path ];
	}

	/**
	 * Set the cache data for the path.
	 *
	 * @param string $path The path to set data for.
	 * @param array  $data The data to set.
	 */
	protected function set_cache_data( $path, $data ) {

		if ( $this->cache_points[ $path ] !== $data ) {
			$this->cache_points[ $path ] = $data;
			$data['data']                = wp_json_encode( $data['data'] );
			$data['exclude']             = wp_json_encode( $data['exclude'] );
			$this->wpdb->update( $this->cache_point_table, $data, array( 'cache_point' => $path ), '' );
		}
	}

	/**
	 * Get urls for cache point files.
	 *
	 * @param string $cache_point_slug The cache point to get urls for.
	 * @param array  $excludes         List of files to exclude.
	 *
	 * @return array
	 */
	protected function get_path_urls( $cache_point_slug, $excludes = array() ) {
		$cache_point = $this->get_cache_data( $cache_point_slug );
		$data        = $cache_point['data'];
		if ( empty( $data['files_fetched'] ) || $data['files_fetched'] >= time() + self::FILE_LOOKUP_TIMEOUT ) {
			switch ( $data['type'] ) {
				case 'plugin':
					$data = $this->get_plugin_data( $data['plugin'], $data['version'], $data['types'], true );
					break;
				case 'theme':
					$data = $this->get_theme_data( $data['theme'], $data['types'], true );
					break;
				default:
					$data = $this->get_folder_data( $data['path'], $data['types'], false );
					break;
			}
		}

		if ( ! empty( $excludes ) ) {
			$data['files'] = array_diff( $excludes, $data['files'] );
		}
		$urls = $this->file_system->get_file_urls( $cache_point['cache_point'], $data['files'], $data['version'] );

		return $urls;
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

		$base_url = md5( filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL ) );
		$paths    = apply_filters( 'cloudinary_get_cache_paths', array() );
		if ( empty( $paths ) ) {
			return $html;
		}
		$sources = get_transient( $base_url );
		if ( empty( $sources ) ) {
			$sources = $this->build_sources( $paths, $html );
			$expire  = 60;
			if ( is_array( $sources ) && ! empty( $sources['upload_pending'] ) ) {
				// Set to a short since it's still syncing.
				$expire = 5;
			}
			set_transient( $base_url, $sources, $expire );
		}

		// Replace all sources if we have some URLS.
		if ( ! empty( $sources['url'] ) ) {
			$html = str_replace( $sources['url'], $sources['cld'], $html );
		}

		return $html;
	}

	/**
	 * Build sources for a set of paths and HTML.
	 *
	 * @param array  $paths The paths to get sources from.
	 * @param string $html  The html to build against.
	 *
	 * @return array[]|null
	 */
	protected function build_sources( $paths, $html ) {

		// Remove all paths that are not present in the page.
		$paths = array_filter(
			$paths,
			function ( $url ) use ( $html ) {
				return strpos( $html, $url );
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( empty( $paths ) ) {
			// Bail since there are not paths to look for to be cached.
			return null;
		}

		// Get just the URLS to get.
		$urls_on_page = array_keys( $paths );
		// Get all instances of paths from the page with version suffix.
		preg_match_all( '#' . implode( '|', $urls_on_page ) . '\b([-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $html, $result );
		$result[0] = array_filter( $result[0], 'wp_http_validate_url' );
		// Bail if not found.
		if ( empty( $result[0] ) ) {
			return null;
		}
		// Clean out duplicates.
		$found_urls = array_unique( $result[0] );
		$sources    = array();
		$urls       = $this->get_cached_urls( $found_urls );
		$missing    = array_diff( $found_urls, array_keys( $urls ) );
		if ( ! empty( $missing ) ) {
			$this->prep_cache_items( $missing, $paths );
			$sources['upload_pending'] = true;
		}
		$urls = array_filter( $urls );

		$sources['url'] = array_keys( $urls );
		$sources['cld'] = array_values( $urls );

		return $sources;
	}

	/**
	 * Prepare uncached URLS to be synced by inserting them in the cache table, without cached URLS.
	 *
	 * @param array $items Local urls to be added.
	 * @param array $paths Array of source paths of files to be cached.
	 */
	protected function prep_cache_items( $items, $paths ) {

		$sources = array();
		foreach ( $items as $url ) {
			$clean_url = remove_query_arg( 'ver', $url );
			$sources[] = $url;
			$sources[] = remove_query_arg( 'ver', $paths[ $clean_url ] );
			$sources[] = pathinfo( $clean_url, PATHINFO_EXTENSION );
		}

		$list_prepare = implode( ',', array_fill( 0, count( $items ), '(%s,%s,%s)' ) );
		$query        = "INSERT INTO {$this->cache_table} ( local_url, src_path, type ) VALUES {$list_prepare}";
		$this->wpdb->query( $this->wpdb->prepare( $query, $sources ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {

		add_filter( 'template_include', array( $this, 'frontend_rewrite' ), PHP_INT_MAX );
		add_action( 'admin_init', array( $this, 'admin_rewrite' ), 0 );
		add_action( 'deactivate_plugin', array( $this, 'invalidate_plugin_cache' ) );
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'shutdown', array( $this, 'start_sync' ) );
	}

	/**
	 * Register the sync endpoint.
	 *
	 * @param array $endpoints The endpoint to add to.
	 *
	 * @return mixed
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['upload_cache'] = array(
			'method'   => \WP_REST_Server::ALLMETHODS,
			'callback' => array( $this, 'upload_cache' ),
			'args'     => array(),
		);

		return $endpoints;
	}

	/**
	 * Start uploading files to cloudinary cache.
	 */
	public function upload_cache() {
		if ( empty( get_transient( self::META_KEYS['uploading_cache'] ) ) ) {
			set_transient( self::META_KEYS['uploading_cache'], true, 60 ); // Flag a transient to prevent multiple background uploads.
			$to_upload = $this->get_uncached_items();
			foreach ( $to_upload as &$upload ) {
				set_transient( self::META_KEYS['uploading_cache'], true, 60 ); // Flag a transient to prevent multiple background uploads.
				$upload['cached_url'] = $this->sync_static( $upload['src_path'], $upload['src_path'] );
				if ( ! is_wp_error( $upload['cached_url'] ) && ! empty( $upload['cached_url'] ) ) {
					$upload['timestamp'] = time();
					$this->set_cached_url( $upload );
				}
			}
		}
	}

	/**
	 * Get all cached urls for local urls.
	 *
	 * @param array $local_urls List of local URLs to get.
	 * @param bool  $purge      Flag to purge expired items.
	 *
	 * @return array
	 */
	public function get_cached_urls( $local_urls, $purge = false ) {

		if ( $purge ) {
			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->cache_table} WHERE timestamp <= %d", time() - 60 ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		$list_prepare = array_fill( 0, count( $local_urls ), '%s' );
		$list_prepare = join( ',', $list_prepare );

		$query  = "SELECT * FROM {$this->cache_table} WHERE local_url in( {$list_prepare} );";
		$found  = $this->wpdb->get_results( $this->wpdb->prepare( $query, $local_urls ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$return = array();
		foreach ( $found as $row ) {
			$return[ $row['local_url'] ] = $row['cached_url'];
		}

		return $return;
	}

	/**
	 * Get or count all un-cached urls for syncing.
	 *
	 * @param bool $count Count only flag.
	 *
	 * @return array
	 */
	public function get_uncached_items( $count = false ) {

		$type = 'id, src_path, local_url';
		if ( $count ) {
			$type = 'COUNT(id)';
		}
		$query = "SELECT {$type} FROM {$this->cache_table} WHERE cached_url IS NULL";

		return $this->wpdb->get_results( $this->wpdb->prepare( $query ), ARRAY_A );  // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Load cached URLS from a source file.
	 *
	 * @param string $file Source file to load cache for.
	 *
	 * @return array|null
	 */
	protected function load_cache( $file ) {
		return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->cache_table} WHERE src_path = %s", $file ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 *  Update changes and start sync.
	 */
	public function start_sync() {
		if ( ! empty( $this->get_uncached_items( true ) ) ) {
			$this->api->background_request( 'upload_cache' );
		}
	}

	/**
	 * Get cached url item.
	 *
	 * @param array $item The cache data array.
	 */
	public function set_cached_url( $item ) {
		$id = $item['id'];
		unset( $item['id'] );
		$this->wpdb->update( $this->cache_table, $item, array( 'id' => $id ) );
	}

	/**
	 * Upload a static file.
	 *
	 * @param string $file The file path to upload.
	 * @param string $url  The file URL to upload.
	 *
	 * @return string|\WP_Error
	 */
	protected function sync_static( $file, $url ) {

		$errored = get_option( self::META_KEYS['upload_error'], array() );
		if ( isset( $errored[ $file ] ) && 3 <= $errored[ $file ] ) {
			// Dont try again.
			return new \WP_Error( 'upload_error' );
		}

		$file_path = $this->cache_folder . '/' . substr( $file, strlen( ABSPATH ) );
		$public_id = dirname( $file_path ) . '/' . pathinfo( $file, PATHINFO_FILENAME );
		$type      = $this->media->get_file_type( $file );
		$options   = array(
			'file'          => $file,
			'resource_type' => 'auto',
			'public_id'     => wp_normalize_path( $public_id ),
		);

		if ( 'image' === $type ) {
			$options['eager'] = 'f_auto,q_auto';
		}

		$data = $this->connect->api->upload( 0, $options, array(), false );
		if ( is_wp_error( $data ) ) {
			$errored[ $file ] = isset( $errored[ $file ] ) ? $errored[ $file ] + 1 : 1;
			update_option( self::META_KEYS['upload_error'], $errored );

			return null;
		}

		$url = $data['secure_url'];
		if ( ! empty( $data['eager'] ) ) {
			$url = $data['eager'][0]['secure_url'];
		}

		return $url;
	}

	/**
	 * Get the plugins table structure.
	 *
	 * @return array|mixed
	 */
	protected function get_plugins_table() {

		$default_filters   = array(
			__( 'Images', 'cloudinary' ) => array(
				'jpg',
				'png',
				'svg',
			),
			__( 'CSS', 'cloudinary' )    => array(
				'css',
			),
			__( 'JS', 'cloudinary' )     => array(
				'js',
			),
		);
		$file_type_filters = apply_filters( 'cloudinary_plugin_asset_cache_filters', $default_filters );

		$types = array();
		foreach ( $file_type_filters as $filter ) {
			$types = array_merge( $types, $filter );
		}

		$plugins     = get_plugins();
		$active      = wp_get_active_and_valid_plugins();
		$rows        = array();
		$cache_paths = array();
		foreach ( $active as $plugin_path ) {
			$dir    = basename( dirname( $plugin_path ) );
			$plugin = $dir . '/' . basename( $plugin_path );
			if ( ! isset( $plugins[ $plugin ] ) ) {
				continue;
			}
			$details              = $plugins[ $plugin ];
			$slug                 = sanitize_file_name( $plugin );
			$paths                = $this->get_plugin_data( $plugin, $details['Version'], $types );
			$paths['title']       = $details['Name'];
			$rows[ $slug ]        = $paths;
			$cache_paths[ $slug ] = $paths['path'];
		}

		return array(
			'slug'         => 'plugin_files',
			'type'         => 'folder_table',
			'title'        => __( 'Plugin', 'cloudinary' ),
			'root_paths'   => $rows,
			'cache_points' => $cache_paths,
			'filters'      => $file_type_filters,
		);

	}

	/**
	 * Get the settings structure for the theme table.
	 *
	 * @return array
	 */
	protected function get_theme_table() {
		$default_filters   = array(
			__( 'Images', 'cloudinary' ) => array(
				'jpg',
				'png',
				'svg',
			),
			__( 'CSS', 'cloudinary' )    => array(
				'css',
			),
			__( 'JS', 'cloudinary' )     => array(
				'js',
			),
		);
		$file_type_filters = apply_filters( 'cloudinary_folder_asset_cache_filters', $default_filters );

		$types = array();
		foreach ( $file_type_filters as $filter ) {
			$types = array_merge( $types, $filter );
		}
		$theme  = wp_get_theme();
		$themes = array(
			$theme,
		);
		if ( $theme->parent() ) {
			$themes[] = $theme->parent();
		}
		$cache_paths = array();
		// Active Theme.
		foreach ( $themes as $theme ) {
			$paths                          = $this->get_theme_data( $theme, $types );
			$rows[ $paths['theme'] ]        = $paths;
			$cache_paths[ $paths['theme'] ] = $paths['path'];
		}

		return array(
			'slug'         => 'theme_files',
			'type'         => 'folder_table',
			'title'        => __( 'Theme', 'cloudinary' ),
			'root_paths'   => $rows,
			'cache_points' => $cache_paths,
			'filters'      => $file_type_filters,
		);
	}

	/**
	 * Get the settings structure for the WordPress table.
	 *
	 * @return array
	 */
	protected function get_wp_table() {

		$default_filters   = array(
			__( 'Images', 'cloudinary' ) => array(
				'jpg',
				'png',
				'svg',
			),
			__( 'CSS', 'cloudinary' )    => array(
				'css',
			),
			__( 'JS', 'cloudinary' )     => array(
				'js',
			),
		);
		$file_type_filters = apply_filters( 'cloudinary_folder_asset_cache_filters', $default_filters );

		$types = array();
		foreach ( $file_type_filters as $filter ) {
			$types = array_merge( $types, $filter );
		}

		$rows        = array();
		$cache_paths = array();
		// Admin folder.
		$path                    = rtrim( $this->file_system->wp_admin_dir(), '/' );
		$data                    = $this->get_folder_data( $path, $types );
		$data['title']           = 'Admin';
		$rows['wp_admin']        = $data;
		$cache_paths['wp_admin'] = $data['path'];

		// Includes folder.
		$path                       = rtrim( $this->file_system->wp_includes_dir(), '/' );
		$data                       = $this->get_folder_data( $path, $types );
		$data['title']              = 'Includes';
		$rows['wp_includes']        = $data;
		$cache_paths['wp_includes'] = $data['path'];

		return array(
			'slug'         => 'wordpress_files',
			'type'         => 'folder_table',
			'title'        => __( 'WordPress', 'cloudinary' ),
			'root_paths'   => $rows,
			'cache_points' => $cache_paths,
			'filters'      => $file_type_filters,
		);
	}

	/**
	 * Setup the cache object.
	 */
	public function setup() {
		$this->create_table_maybe();
		$this->add_setting_tabs();
	}

	/**
	 * Adds the individual setting tabs.
	 */
	protected function add_setting_tabs() {
		$this->add_plugin_settings();
		$this->add_theme_settings();
		$this->add_wp_settings();
		if ( 'on' == $this->settings->get_value( 'enable_full_site_cache' ) ) {
			$placeholder = $this->settings->create_setting( 'cache_all_holder', array( 'type' => 'data' ) );
			$this->settings->get_setting( 'cache_plugins' )->set_parent( $placeholder );
			$this->settings->get_setting( 'cache_themes' )->set_parent( $placeholder );
			$this->settings->get_setting( 'cache_wordpress' )->set_parent( $placeholder );

			return;
		}
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
	 *
	 * @return array
	 */
	public function add_cache_paths( $setting, $cache_point_setting, $all_cache_setting ) {

		$settings     = $this->settings->find_setting( $setting );
		$cache_points = $settings->find_setting( $cache_point_setting )->get_param( 'cache_points' );
		$paths        = array();
		foreach ( $cache_points as $slug => $cache_point ) {
			// All on or Plugin is on.
			if ( 'on' == $this->settings->get_value( 'enable_full_site_cache' ) || 'on' === $settings->get_value( $all_cache_setting ) || 'on' === $settings->get_value( $slug ) ) {
				$paths += $this->get_path_urls( $cache_point );
				continue;
			}

			// Plugin is off.
			if ( 'off' === $settings->get_value( $slug ) ) {
				continue;
			}

			// Plugin has some.
			$excludes = $settings->get_value( $slug . '_files' );
			$paths   += $this->get_path_urls( $cache_point, $excludes );

		}

		return $paths;
	}

	/**
	 * Add the plugin cache settings page.
	 */
	protected function add_plugin_settings() {
		if ( ! $this->is_cache_setting_enabled( 'cache_plugins_enable' ) ) {
			$this->settings->remove_setting( 'cache_plugins' );

			return;
		}
		$plugins_setup = $this->get_plugins_table();
		$params        = array(
			'type' => 'frame',
			array(
				'type'       => 'panel',
				'title'      => __( 'Plugins', 'cloudinary' ),
				'content'    => __( 'Deliver assets in active plugins.', 'cloudinary' ),
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
					'type'        => 'on_off',
					'slug'        => 'cache_all_plugins',
					'description' => __( 'Deliver assets from all plugin folders', 'cloudinary' ),
					'default'     => 'on',
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'cache_all_plugins' => false,
					),
					$plugins_setup,
				),
			),
			array(
				'type' => 'submit',
			),
		);
		$this->settings->create_setting( 'plugins_settings', $params, $this->settings->get_setting( 'cache_plugins' ) );
		add_filter( 'cloudinary_get_cache_paths', array( $this, 'add_plugin_cache_paths' ) );
	}

	/**
	 * Add Plugin paths for caching.
	 *
	 * @param array $paths The current paths to add to.
	 *
	 * @return array
	 */
	public function add_plugin_cache_paths( $paths ) {

		$paths += $this->add_cache_paths( 'cache_plugins', 'plugin_files', 'cache_all_plugins' );

		return $paths;
	}

	/**
	 * Add Theme Settings page.
	 */
	protected function add_theme_settings() {
		if ( ! $this->is_cache_setting_enabled( 'cache_theme_enable' ) ) {
			$this->settings->remove_setting( 'cache_themes' );

			return;
		}
		$theme_setup = $this->get_theme_table();
		$params      = array(
			'type' => 'frame',
			array(
				'type'    => 'panel',
				'title'   => __( 'Themes', 'cloudinary' ),
				'content' => __( 'Deliver assets in custom folders.', 'cloudinary' ),
				array(
					'type'        => 'on_off',
					'slug'        => 'cache_all_themes',
					'description' => __( 'Deliver all assets from active theme.', 'cloudinary' ),
					'default'     => 'on',
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'cache_all_themes' => false,
					),
					$theme_setup,
				),
			),
			array(
				'type' => 'submit',
			),
		);

		$this->settings->create_setting( 'theme_settings', $params, $this->settings->get_setting( 'cache_themes' ) );
		add_filter( 'cloudinary_get_cache_paths', array( $this, 'add_theme_cache_paths' ) );
	}

	/**
	 * Add Theme paths for caching.
	 *
	 * @param array $paths The current paths to add to.
	 *
	 * @return array
	 */
	public function add_theme_cache_paths( $paths ) {

		$paths += $this->add_cache_paths( 'cache_themes', 'theme_files', 'cache_all_themes' );

		return $paths;
	}

	/**
	 * Add WP Settings page.
	 */
	protected function add_wp_settings() {
		if ( ! $this->is_cache_setting_enabled( 'cache_wordpress_enable' ) ) {
			$this->settings->remove_setting( 'cache_wordpress' );

			return;
		}
		$wordpress_setup = $this->get_wp_table();
		$params          = array(
			'type' => 'frame',
			array(
				'type'    => 'panel',
				'title'   => __( 'WordPress', 'cloudinary' ),
				'content' => __( 'Deliver assets in WordPress core.', 'cloudinary' ),
				array(
					'type'        => 'on_off',
					'slug'        => 'cache_all_wp',
					'description' => __( 'Deliver all assets from WordPress.', 'cloudinary' ),
					'default'     => 'on',
				),
				array(
					'type'      => 'group',
					'condition' => array(
						'cache_all_wp' => false,
					),
					$wordpress_setup,
				),
			),
			array(
				'type' => 'submit',
			),
		);

		$this->settings->create_setting( 'wordpress_settings', $params, $this->settings->get_setting( 'cache_wordpress' ) );
		add_filter( 'cloudinary_get_cache_paths', array( $this, 'add_wp_cache_paths' ) );
	}

	/**
	 * Add Theme paths for caching.
	 *
	 * @param array $paths The current paths to add to.
	 *
	 * @return array
	 */
	public function add_wp_cache_paths( $paths ) {

		$paths += $this->add_cache_paths( 'cache_wordpress', 'wordpress_files', 'cache_all_wp' );

		return $paths;
	}

	/**
	 * Maybe create table if needed.
	 */
	protected function create_table_maybe() {
		$created = get_option( self::META_KEYS['has_table'], false );
		if ( ! $created ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$collate = $this->wpdb->get_charset_collate();
			$query   = "CREATE TABLE {$this->wpdb->prefix}cld_cache (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
local_url varchar(255) DEFAULT NULL,
cached_url varchar(255) DEFAULT NULL,
src_path varchar(1024) DEFAULT NULL,
type varchar(5) DEFAULT NULL,
timestamp bigint(20) DEFAULT NULL,
PRIMARY KEY (id),
KEY src_path (src_path),
KEY local_url (local_url),
KEY type (type),
KEY timestamp (timestamp)
) {$collate};
CREATE TABLE {$this->wpdb->prefix}cld_cache_points (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
cache_point varchar(255) DEFAULT NULL,
mode varchar(45) DEFAULT NULL,
exclude longtext DEFAULT NULL,
data longtext DEFAULT NULL,
PRIMARY KEY (id),
UNIQUE KEY cache_point (cache_point)
) {$collate};";

			if ( dbDelta( $query ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbdelta
				update_option( self::META_KEYS['has_table'], true );
			}
		}
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
				'cache_extras'    => array(
					'page_title' => __( 'Site Cache', 'cloudinary' ),
					array(
						'type'  => 'panel',
						'title' => __( 'Cache Settings', 'cloudinary' ),
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
							'type'      => 'group',
							'condition' => array(
								'enable_full_site_cache' => false,
							),
							array(
								'type'        => 'on_off',
								'slug'        => 'cache_plugins_enable',
								'title'       => __( 'Plugins', 'cloudinary' ),
								'description' => __( 'Deliver assets in active plugins.', 'cloudinary' ),
								'default'     => 'off',
							),
							array(
								'type'        => 'on_off',
								'slug'        => 'cache_theme_enable',
								'title'       => __( 'Theme', 'cloudinary' ),
								'description' => __( 'Deliver assets in active theme.', 'cloudinary' ),
								'default'     => 'off',
							),
							array(
								'type'        => 'on_off',
								'slug'        => 'cache_wordpress_enable',
								'title'       => __( 'WordPress', 'cloudinary' ),
								'description' => __( 'Deliver assets for WordPress.', 'cloudinary' ),
								'default'     => 'off',
							),
						),
					),
					array(
						'type' => 'submit',
					),
				),
				'cache_plugins'   => array(
					'page_title' => __( 'Plugins', 'cloudinary' ),
				),
				'cache_themes'    => array(
					'page_title' => __( 'Themes', 'cloudinary' ),
				),
				'cache_wordpress' => array(
					'page_title' => __( 'WordPress', 'cloudinary' ),
				),
			),
		);

		return $args;
	}
}
