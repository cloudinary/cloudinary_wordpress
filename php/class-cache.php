<?php
/**
 * Cloudinary Logger, to collect logs and debug data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Media;
use Cloudinary\Connect;

/**
 * Plugin report class.
 */
class Cache implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * @var Media
	 */
	protected $media;

	/**
	 * @var Connect
	 */
	protected $connect;

	/**
	 * @var array
	 */
	public $file_cache_default = array(
		'version' => null,
		'files'   => array(),
	);

	/**
	 * Holds the meta keys to be used.
	 */
	const META_KEYS = array(
		'queue'        => '_cloudinary_cache_queue',
		'url'          => '_cloudinary_cache_url',
		'cached'       => '_cloudinary_cached',
		'plugin_files' => '_cloudinary_plugin_files',
	);

	/**
	 * Report constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin  = $plugin;
		$this->media   = $this->plugin->get_component( 'media' );
		$this->connect = $this->plugin->get_component( 'connect' );
		$this->register_hooks();
		add_filter( 'template_include', array( $this, 'frontend_rewrite' ), PHP_INT_MAX );

		add_action( 'cloudinary_settings_save_setting_cache_theme', array( $this, 'clear_theme_cache' ), 10 );
		add_action( 'cloudinary_settings_save_setting_cache_plugins', array( $this, 'clear_theme_cache' ), 10 );
		add_action( 'cloudinary_settings_save_setting_cache_wordpress', array( $this, 'clear_wp_cache' ), 10 );
	}

	/**
	 * Invalidate Theme file cache.
	 *
	 * @param $new
	 *
	 * @return mixed|string
	 */
	public function clear_theme_cache( $new ) {
		if ( 'off' === $new ) {
			$theme    = wp_get_theme();
			$main_key = md5( $theme->get_stylesheet_directory() );
			delete_option( $main_key );
			if ( $theme->parent() ) {
				$parent_key = md5( $theme->parent()->get_stylesheet_directory() );
				delete_option( $parent_key );
			}
		} else {
			$this->get_theme_paths();
		}

		return $new;
	}

	/**
	 * Invalidate Plugin cache.
	 */
	public function clear_plugin_cache( $new ) {
		if ( 'off' === $new ) {
			$plugins        = get_plugins();
			$active_plugins = (array) get_option( 'active_plugins', array() );
			foreach ( $active_plugins as $plugin ) {
				$plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $plugin );
				$folder_key    = md5( $plugin_folder );
				delete_option( $folder_key );
			}
		} else {
			$this->get_plugin_paths();
		}

		return $new;
	}

	/** Invalidate the WP cache.
	 *
	 * @param $new
	 */
	public function clear_wp_cache( $new ) {
		if ( 'off' === $new ) {
			$admin    = md5( ABSPATH . 'wp-admin' );
			$includes = md5( ABSPATH . 'wp-includes' );
			delete_option( $admin );
			delete_option( $includes );
		} else {
			$this->get_wp_paths();
		}

		return $new;
	}

	/**
	 * Get the file paths for the plugins.
	 *
	 * @return array
	 */
	protected function get_plugin_paths() {
		$paths          = array();
		$plugins        = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		foreach ( $active_plugins as $plugin ) {
			$key = 'plugin_cache_' . basename( dirname( $plugin ) );
			if ( 'off' === $this->plugin->settings->get_value( $key ) ) {
				continue;
			}
			$plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $plugin );
			$files         = $this->get_folder_files( $plugin_folder, $plugins[ $plugin ]['Version'], 'plugins_url', false );
			$paths         = array_merge( $paths, $files );
		}

		return $paths;
	}

	/**
	 * Get the theme file paths.
	 *
	 * @return array
	 */
	protected function get_theme_paths() {

		$theme = wp_get_theme();
		$paths = $this->get_folder_files(
			$theme->get_stylesheet_directory(),
			$theme->get( 'Version' ),
			function ( $file ) use ( $theme ) {
				return $theme->get_stylesheet_directory_uri() . $file;
			}
		);
		if ( $theme->parent() ) {
			$parent = $theme->parent();
			$paths  += $this->get_folder_files(
				$parent->get_stylesheet_directory(),
				$parent->get( 'Version' ),
				function ( $file ) use ( $parent ) {
					return $parent->get_stylesheet_directory_uri() . $file;
				}
			);
		}

		return $paths;
	}

	/**
	 * Get the file paths for WordPress.
	 *
	 * @return array
	 */
	protected function get_wp_paths() {
		$version = get_bloginfo( 'version' );
		$paths   = $this->get_folder_files( ABSPATH . 'wp-admin', $version, 'admin_url' );
		$paths   += $this->get_folder_files( ABSPATH . 'wp-includes', $version, 'includes_url' );

		return $paths;
	}

	/**
	 * Get the paths for scanning.
	 *
	 * @return array
	 */
	protected function get_paths() {
		$paths = array();
		if ( 'on' === $this->plugin->settings->get_value( 'cache_plugins' ) ) {
			$paths += $this->get_plugin_paths();
		}

		if ( 'on' === $this->plugin->settings->get_value( 'cache_theme' ) ) {
			$paths += $this->get_theme_paths();
		}

		if ( 'on' === $this->plugin->settings->get_value( 'cache_wordpress' ) ) {
			$paths += $this->get_wp_paths();
		}

		return $paths;
	}

	/**
	 * @param $template
	 *
	 * @return string
	 */
	public function frontend_rewrite( $template ) {
		$paths = $this->get_paths();

		if ( empty ( $paths ) ) {
			return $template;
		}

		ob_start();
		include $template;
		$html = ob_get_clean();

		$paths = array_filter(
			$paths,
			function ( $path, $url ) use ( $html ) {
				return strpos( $html, $url );
			},
			ARRAY_FILTER_USE_BOTH
		);
		preg_match_all( '#(' . implode( '|', array_keys( $paths ) ) . ')\b([-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $html, $result );
		if ( empty( $result[0] ) ) {
			return $template;
		}

		$sources  = array(
			'url' => array(),
			'cld' => array(),
		);

		foreach ( $result[0] as $index => $url ) {
			$file_location = $paths[ $result[1][ $index ] ];
			$path_query    = wp_parse_url( $url, PHP_URL_QUERY );
			$file_query    = wp_parse_url( $file_location, PHP_URL_QUERY );
			parse_str( $file_query, $query );
			$file_source = remove_query_arg( 'ver', $file_location );
			if ( ! empty( $path_query ) ) {
				parse_str( $path_query, $query );
			}

			$cloudinary_url = $this->get_cached_url( $url, $query['ver'], $file_source );
			if ( ! empty( $cloudinary_url ) ) {
				$sources['url'][] = $url;
				$sources['cld'][] = $cloudinary_url;
			}
		}

		// Replace all sources.
		$html = str_replace( $sources['url'], $sources['cld'], $html );

		// Push to output stream.
		file_put_contents( "php://output", $html ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		return 'php://output';
	}

	/**
	 * Get and sanitize files from a folder.
	 *
	 * @param string $folder       The folder to get from.
	 * @param string $version      The version.
	 * @param string $callback     The callback for sanitizing the url.
	 * @param bool   $strip_folder Flag for stripping the basename.
	 *
	 * @return array
	 */
	protected function get_folder_files( $folder, $version, $callback = 'home_url', $strip_folder = true ) {
		if ( ! is_callable( $callback ) ) {
			$callback = 'home_url';
		}
		$folder_key   = md5( $folder );
		$folder_cache = get_option( $folder_key, $this->file_cache_default );
		if ( empty( $folder_cache['files'] ) || $folder_cache['version'] !== $version ) {
			$folder_cache['files']   = array();
			$folder_cache['version'] = $version;
			$found                   = $this->get_files( $folder );
			foreach ( $found as $file ) {
				$strip_length                  = $strip_folder ? strlen( $folder ) : strlen( dirname( $folder ) . '/' );
				$file_part                     = substr( $file, $strip_length );
				$url                           = call_user_func( $callback, wp_normalize_path( $file_part ) );
				$folder_cache['files'][ $url ] = $file . '?ver=' . $version;
			}
			// Add files cache.
			update_option( $folder_key, $folder_cache, false );
		}

		return $folder_cache['files'];
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Register the sync endpoint.
	 *
	 * @param array $endpoints The endpoint to add to.
	 *
	 * @return mixed
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['cache_site'] = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'start_cache' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		return $endpoints;
	}

	/**
	 * Get files from a folder.
	 *
	 * @param $path
	 *
	 * @return array
	 */
	public function get_files( $path ) {
		$exclude      = array(
			'node_modules',
			'vendor',
		);
		$excluded_ext = array(
			'php',
			'json',
			'map',
			'scss',
			'md',
			'txt',
			'xml',
		);
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$files  = list_files( $path, PHP_INT_MAX, $exclude );
		$return = array_filter(
			$files,
			function ( $file ) use ( $excluded_ext ) {
				return ! in_array( pathinfo( $file, PATHINFO_EXTENSION ), $excluded_ext, true );
			}
		);

		sort( $return );

		return $return;
	}

	/**
	 * Get a cached URL.
	 *
	 * @param $local_url
	 * @param $version
	 *
	 * @return string
	 */
	public function get_cached_url( $local_url, $version, $file_location ) {
		$location_path    = wp_parse_url( $local_url, PHP_URL_PATH );
		$parts            = explode( '/', trim( $location_path, '/' ) );
		$storage_location = $parts[0];
		$option_key       = self::META_KEYS['cached'] . '_' . $storage_location;
		$cache            = get_option( $option_key, array() );
		if ( empty( $cache[ $local_url ] ) || $cache[ $local_url ]['ver'] !== $version ) {
			$cache[ $local_url ]        = array(
				'ver' => $version,
			);
			$cache[ $local_url ]['url'] = $this->sync_static( $file_location );
			if ( ! empty( $cache[ $local_url ]['url'] ) ) {
				update_option( $option_key, $cache, false );
			}
		}

		return $cache[ $local_url ]['url'];
	}

	/**
	 * @param $file
	 *
	 * @return mixed
	 */
	protected function sync_static( $file ) {

		$folder    = $this->media->get_cloudinary_folder() . $this->plugin->settings->get_value( 'cache_folder' );
		$file_path = $folder . '/' . substr( $file, strlen( ABSPATH ) );
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
			return null;
		}

		$url = $data['secure_url'];
		if ( ! empty( $data['eager'] ) ) {
			$url = $data['eager'][0]['secure_url'];
		}

		return $url;
	}

	/**
	 * Setup the settings.
	 */
	public function setup() {
		$media_settings = $this->plugin->settings->get_setting( 'media' );

		$plugins = get_plugins();
		$active  = wp_get_active_and_valid_plugins();
		$toggles = array();
		foreach ( $active as $plugin ) {
			$path      = basename( dirname( $plugin ) ) . '/' . basename( $plugin );
			$details   = $plugins[ $path ];
			$toggles[] = array(
				'type'        => 'on_off',
				'slug'        => 'plugin_cache_' . basename( dirname( $plugin ) ),
				'description' => $details['Name'],
				'default'     => 'off',
			);
		}

		$settings = array(
			'type'        => 'page',
			'menu_title'  => __( 'Site Cache', 'cloudinary' ),
			'option_name' => 'cloudinary_cache_site',
			'priority'    => 9,
			'slug'        => 'site_cache',
			array(
				'type'  => 'panel',
				'title' => __( 'Cache Settings', 'cloudinary' ),
				array(
					'type'         => 'on_off',
					'slug'         => 'enable_site_cache',
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
						'enable_site_cache' => true,
					),
					array(
						'type'        => 'on_off',
						'slug'        => 'cache_plugins',
						'title'       => __( 'Plugins', 'cloudinary' ),
						'description' => __( 'Deliver assets in active plugins.', 'cloudinary' ),
						'default'     => 'off',
					),
					array(
						'type'      => 'group',
						'condition' => array(
							'cache_plugins' => true,
						),
						$toggles,
					),
					array(
						'type'        => 'on_off',
						'slug'        => 'cache_theme',
						'title'       => __( 'Theme', 'cloudinary' ),
						'description' => __( 'Deliver assets in active theme.', 'cloudinary' ),
						'default'     => 'off',
					),
					array(
						'type'        => 'on_off',
						'slug'        => 'cache_wordpress',
						'title'       => __( 'WordPress', 'cloudinary' ),
						'description' => __( 'Deliver assets in for WordPress.', 'cloudinary' ),
						'default'     => 'off',
					),
					array(
						'type'    => 'text',
						'slug'    => 'cache_folder',
						'title'   => __( 'Cache folder', 'cloudinary' ),
						'default' => wp_parse_url( get_site_url(), PHP_URL_HOST ),
					),
					array(
						'type' => 'cache_status',
					),
				),

			),
			array(
				'type' => 'submit',
			),
		);

		$cache_setting = $this->plugin->settings->create_setting( 'site_cache', $settings );
		$media_settings->add_setting( $cache_setting );
	}
}
