<?php
/**
 * Cloudinary Logger, to collect logs and debug data.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;

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
	 * Report constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		$this->register_hooks();
		add_action( 'wp', array( $this, 'start_cache' ) );
	}

	/**
	 * Holds the meta keys to be used.
	 */
	const META_KEYS = array(
		'queue'  => '_cloudinary_cache_queue',
		'url'    => '_cloudinary_cache_url',
		'cached' => '_cloudinary_cached',
	);

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	public function rest_endpoints( $endpoints ) {

		$endpoints['cache_site'] = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'start_cache' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		return $endpoints;
	}

	public function setup() {
		$media_settings = $this->plugin->settings->get_setting( 'media' );
		$settings       = array(
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

	public function start_cache() {
		if ( ! empty( get_option( self::META_KEYS['url'] ) ) ) {
			return;
		}
		$default = array(
			'theme'     => array(),
			'plugins'   => array(),
			'wordpress' => array(),
		);
		$files   = get_option( self::META_KEYS['queue'], $default );
		$sync    = $this->plugin->get_component( 'sync' );
		// Expensive to run.
		if ( empty( $files['plugins'] ) && 'on' === $this->plugin->settings->get_value( 'cache_plugins' ) ) {
			$folders = wp_get_active_and_valid_plugins();
			foreach ( $folders as $folder ) {
				$path              = dirname( $folder );
				$found             = $this->get_files( $path );
				$files['plugins']  = array_merge( $files['plugins'], $found );
				$files['_updated'] = true;
			}
			$sync->managers['queue']->add_to_queue( $files['plugins'], 'static' );
		}
		if ( empty( $files['theme'] ) && 'on' === $this->plugin->settings->get_value( 'cache_theme' ) ) {
			$found             = $this->get_files( get_template_directory() );
			$files['theme']    = array_merge( $files['theme'], $found );
			$files['_updated'] = true;
			$sync->managers['queue']->add_to_queue( $files['theme'], 'static' );
		}
		if ( empty( $files['wordpress'] ) && 'on' === $this->plugin->settings->get_value( 'cache_wordpress' ) ) {
			$found              = $this->get_files( ABSPATH . 'wp-includes' );
			$files['wordpress'] = array_merge( $files['wordpress'], $found );
			$found              = $this->get_files( ABSPATH . 'wp-admin' );
			$files['wordpress'] = array_merge( $files, $found );
			$files['_updated']  = true;
			$sync->managers['queue']->add_to_queue( $files['wordpress'], 'static' );
		}

		if ( ! empty( $files['_updated'] ) ) {
			unset( $files['_updated'] );
			update_option( self::META_KEYS['queue'], $files, false );
			$sync->managers['queue']->start_threads( 'static' );
		}

	}

	/**
	 * Get files from a folder.
	 *
	 * @param $path
	 *
	 * @return array
	 */
	public function get_files( $path ) {
		$exclude = array(
			'node_modules',
			'vendor',
		);
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$files  = list_files( $path, PHP_INT_MAX, $exclude );
		$return = array_filter(
			$files,
			function ( $file ) {
				return 'php' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			}
		);

		return $return;
	}

}
