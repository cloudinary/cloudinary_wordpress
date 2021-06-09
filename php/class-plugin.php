<?php
/**
 * Bootstraps the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Assets;
use Cloudinary\Component\Config;
use Cloudinary\Component\Notice;
use Cloudinary\Component\Setup;
use Cloudinary\Settings\Setting;
use Cloudinary\Sync\Storage;
use WP_REST_Request;
use WP_REST_Server;
use const E_USER_WARNING;
use const WPCOM_IS_VIP_ENV;

/**
 * Main plugin bootstrap file.
 */
final class Plugin {

	/**
	 * Holds the components of the plugin
	 *
	 * @since   0.1
	 *
	 * @var     Media[]|Sync[]|Settings_Page[]|REST_API[]|Connect[]
	 */
	public $components;
	/**
	 * Plugin config.
	 *
	 * @var array
	 */
	public $config = array();

	/**
	 * The core Settings object.
	 *
	 * @var Setting
	 */
	public $settings;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	public $dir_path;

	/**
	 * Plugin templates path.
	 *
	 * @var string
	 */
	public $template_path;

	/**
	 * Plugin directory URL.
	 *
	 * @var string
	 */
	public $dir_url;

	/**
	 * Directory in plugin containing autoloaded classes.
	 *
	 * @var string
	 */
	protected $autoload_class_dir = 'php';

	/**
	 * Autoload matches cache.
	 *
	 * @var array
	 */
	protected $autoload_matches_cache = array();

	/**
	 * Holds the list of hooks.
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	public $hooks;

	/**
	 * Plugin_Base constructor.
	 */
	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin              = get_plugin_data( CLDN_CORE );
		$location            = $this->locate_plugin();
		$this->slug          = ! empty( $plugin['TextDomain'] ) ? $plugin['TextDomain'] : $location['dir_basename'];
		$this->version       = $plugin['Version'];
		$this->dir_path      = $location['dir_path'];
		$this->template_path = $this->dir_path . 'php/templates/';
		$this->dir_url       = $location['dir_url'];
		spl_autoload_register( array( $this, 'autoload' ) );
		$this->register_hooks();
	}

	/**
	 * Initiate the plugin resources.
	 *
	 * Priority is 9 because WP_Customize_Widgets::register_settings() happens at
	 * after_setup_theme priority 10. This is especially important for plugins
	 * that extend the Customizer to ensure resources are available in time.
	 */
	public function init() {
		$this->components['connect']      = new Connect( $this );
		$this->components['deactivation'] = new Deactivation( $this );
		$this->components['sync']         = new Sync( $this );
		$this->components['media']        = new Media( $this );
		$this->components['api']          = new REST_API( $this );
		$this->components['storage']      = new Storage( $this );
		$this->components['report']       = new Report( $this );
		$this->components['beta']         = new Beta( $this );
	}

	/**
	 * Get a plugin component.
	 *
	 * @param mixed $component The component.
	 *
	 * @return Report|Connect|Media|REST_API|Settings_Page|Sync|Cache|null
	 */
	public function get_component( $component ) {
		$return = null;
		if ( isset( $this->components[ $component ] ) ) {
			$return = $this->components[ $component ];
		}

		return $return;
	}

	/**
	 * Get the core settings page structure for settings.
	 *
	 * @return array
	 */
	private function get_settings_page_structure() {

		$parts = array(
			'header' => array(),
			'pages'  => array(),
			'footer' => array(),
		);

		foreach ( $parts as $slug => $part ) {
			if ( file_exists( $this->dir_path . "ui-definitions/settings-{$slug}.php" ) ) {
				$parts[ $slug ] = include $this->dir_path . "ui-definitions/settings-{$slug}.php";
			}
		}

		$structure = array(
			'version'     => $this->version,
			'page_title'  => __( 'Cloudinary', 'cloudinary' ),
			'menu_title'  => __( 'Cloudinary', 'cloudinary' ),
			'capability'  => 'manage_options',
			'icon'        => 'dashicons-cloudinary',
			'option_name' => $this->slug,
			'page_header' => $parts['header'],
			'page_footer' => $parts['footer'],
			'pages'       => $parts['pages'],
		);

		return $structure;
	}

	/**
	 * Setup settings.
	 */
	public function setup_settings() {
		$params         = $this->get_settings_page_structure();
		$this->settings = \Cloudinary\Settings::create_setting( $this->slug, $params );
		$components     = array_filter( $this->components, array( $this, 'is_setting_component' ) );
		$this->init_component_settings( $components );
		$this->register_component_settings( $components );

		// Init settings.
		\Cloudinary\Settings::init_setting( $this->slug );

		// Add count notice if not connected.
		if ( ! $this->get_component( 'connect' )->is_connected() ) {
			$count      = sprintf( ' <span class="update-plugins count-%d"><span class="update-count">%d</span></span>', 1, number_format_i18n( 1 ) );
			$main_title = $this->settings->get_param( 'menu_title' ) . $count;
			$this->settings->set_param( 'menu_title', $main_title );
			$this->settings->set_param( 'connect_count', $count );

			// Set the Getting Started title.
			$connect       = $this->settings->find_setting( 'dashboard' );
			$connect_title = $connect->get_param( 'menu_title' ) . $count;
			$connect->set_param( 'menu_title', $connect_title );
		}
	}

	/**
	 * Init component settings objects.
	 *
	 * @param Settings_Component[] $components of components to init settings for.
	 */
	private function init_component_settings( $components ) {
		foreach ( $components as $slug => $component ) {
			/**
			 * Component that implements Settings.
			 *
			 * @var  Component\Settings $component
			 */
			$component->init_settings( $this->settings );
		}
	}

	/**
	 * Register settings.
	 *
	 * @param Settings_Component[] $components Array of components to register settings for.
	 */
	private function register_component_settings( $components ) {
		foreach ( $components as $slug => $component ) {
			/**
			 * Component that implements Settings.
			 *
			 * @var  Component\Settings $component
			 */
			$component->register_settings( $this->settings );
		}
	}

	/**
	 * Register Hooks for the plugin.
	 */
	public function set_config() {
		$this->setup_settings();
		$components = array_filter( $this->components, array( $this, 'is_config_component' ) );

		foreach ( $components as $slug => $component ) {
			$component->get_config();
		}
	}

	/**
	 * Register Hooks for the plugin.
	 */
	public function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_enqueue_styles' ), 11 );
		add_action( 'init', array( $this, 'setup' ), 10 );
		add_action( 'init', array( $this, 'register_assets' ), 10 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'plugin_row_meta', array( $this, 'force_visit_plugin_site_link' ), 10, 4 );
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'wp_enqueue_editor', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['dismiss_notice'] = array(
			'method'   => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'rest_dismiss_notice' ),
			'args'     => array(),
		);

		return $endpoints;
	}

	/**
	 * Register scripts and enqueue styles.
	 */
	public function register_enqueue_styles() {
		wp_enqueue_style( 'cloudinary' );
		$components = array_filter( $this->components, array( $this, 'is_active_asset_component' ) );

		// Enqueue components.
		array_map(
			function ( $component ) {
				/**
				 * Component that implements Component\Assets.
				 *
				 * @var  Component\Assets $component
				 */
				$component->enqueue_assets();
			},
			$components
		);
	}

	/**
	 * Enqueue the core scripts and styles as needed.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'cloudinary' );
	}

	/**
	 * Register Assets
	 *
	 * @since  0.1
	 */
	public function register_assets() {
		// Register Main.
		wp_register_script( 'cloudinary', $this->dir_url . 'js/cloudinary.js', array( 'jquery', 'wp-util' ), $this->version, true );
		wp_register_style( 'cloudinary', $this->dir_url . 'css/cloudinary.css', null, $this->version );

		$components = array_filter( $this->components, array( $this, 'is_asset_component' ) );
		array_map(
			function ( $component ) {
				/**
				 * Component that implements Component\Assets.
				 *
				 * @var  Component\Assets $component
				 */
				$component->register_assets();
			},
			$components
		);
	}

	/**
	 * Check if component is an asset implementing component.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component is an asset impmented object or not.
	 */
	private function is_asset_component( $component ) {
		return $component instanceof Assets;
	}

	/**
	 * Check if an asset component is active.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component is an asset impmented object or not.
	 */
	private function is_active_asset_component( $component ) {
		return $this->is_asset_component( $component ) && $component->is_active();
	}

	/**
	 * Check if component is a setup implementing component.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component implements Setup.
	 */
	private function is_setup_component( $component ) {
		return $component instanceof Setup;
	}

	/**
	 * Check if component is a config implementing component.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component implements Config.
	 */
	private function is_config_component( $component ) {
		return $component instanceof Config;
	}

	/**
	 * Check if component is a settings implementing component.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component implements Setting.
	 */
	private function is_setting_component( $component ) {
		return $component instanceof Settings_Component;
	}

	/**
	 * Check if component is a notice implementing component.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component implements Notice.
	 */
	private function is_notice_component( $component ) {
		return $component instanceof Notice;
	}

	/**
	 * Setup hooks
	 *
	 * @since  0.1
	 */
	public function setup() {
		$this->set_config();

		/**
		 * Component that implements Component\Setup.
		 *
		 * @var  Component\Setup $component
		 */
		foreach ( $this->components as $key => $component ) {
			if ( ! $this->is_setup_component( $component ) ) {
				continue;
			}

			$component->setup();
		}

	}

	/**
	 * Set a transient with the duration using a token as an identifier.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function rest_dismiss_notice( WP_REST_Request $request ) {
		$token    = $request->get_param( 'token' );
		$duration = $request->get_param( 'duration' );

		set_transient( $token, true, $duration );
	}

	/**
	 * Load admin notices where needed.
	 *
	 * @since  0.1
	 */
	public function admin_notices() {

		$setting = Utils::get_active_setting();
		/**
		 * An array of classes that implement the Notice interface.
		 *
		 * @var $components Notice[]
		 */
		$components = array_filter( $this->components, array( $this, 'is_notice_component' ) );
		$default    = array(
			'message'     => '',
			'type'        => 'error',
			'dismissible' => false,
			'duration'    => 10, // Default dismissible duration is 10 Seconds for save notices etc...
			'icon'        => null,
		);

		foreach ( $components as $component ) {
			$notices = $component->get_notices();
			foreach ( $notices as $notice ) {
				$notice = wp_parse_args( $notice, $default );
				$setting->add_admin_notice( 'cld_general', $notice['message'], $notice['type'], $notice['dismissible'], $notice['duration'], $notice['icon'] );
			}
		}
	}

	/**
	 * Autoload for classes that are in the same namespace as $this.
	 *
	 * @param string $class Class name.
	 *
	 * @return void
	 */
	public function autoload( $class ) {
		// Assume we're using namespaces (because that's how the plugin is structured).
		$namespace = explode( '\\', $class );
		$root      = array_shift( $namespace );

		// If a class ends with "Trait" then prefix the filename with 'trait-', else use 'class-'.
		$class_trait = preg_match( '/Trait$/', $class ) ? 'trait-' : 'class-';

		// If we're not in the plugin's namespace then just return.
		if ( 'Cloudinary' !== $root ) {
			return;
		}

		// Class name is the last part of the FQN.
		$class_name = array_pop( $namespace );

		// Remove "Trait" from the class name.
		if ( 'trait-' === $class_trait ) {
			$class_name = str_replace( '_Trait', '', $class_name );
		}

		// For file naming, the namespace is everything but the class name and the root namespace.
		$namespace = trim( implode( DIRECTORY_SEPARATOR, $namespace ) );

		// Get the path to our files.
		$directory = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '../php';
		if ( ! empty( $namespace ) ) {
			$directory .= DIRECTORY_SEPARATOR . strtolower( $namespace );
		}

		// Because WordPress file naming conventions are odd.
		$file = strtolower( str_replace( '_', '-', $class_name ) );

		$file = $directory . DIRECTORY_SEPARATOR . $class_trait . $file . '.php';

		if ( file_exists( $file ) ) {
			require_once $file; // phpcs:ignore
		}
	}

	/**
	 * Version of plugin_dir_url() which works for plugins installed in the plugins directory,
	 * and for plugins bundled with themes.
	 *
	 * @return array
	 */
	public function locate_plugin() {

		$dir_url      = plugin_dir_url( CLDN_CORE );
		$dir_path     = CLDN_PATH;
		$dir_basename = basename( CLDN_PATH );

		return compact( 'dir_url', 'dir_path', 'dir_basename' );
	}

	/**
	 * Relative Path
	 *
	 * Returns a relative path from a specified starting position of a full path
	 *
	 * @param string $path  The full path to start with.
	 * @param string $start The directory after which to start creating the relative path.
	 * @param string $sep   The directory separator.
	 *
	 * @return string
	 */
	public function relative_path( $path, $start, $sep ) {
		$path = explode( $sep, untrailingslashit( $path ) );
		if ( count( $path ) > 0 ) {
			foreach ( $path as $p ) {
				array_shift( $path );
				if ( $p === $start ) {
					break;
				}
			}
		}

		return implode( $sep, $path );
	}

	/**
	 * Return whether we're on WordPress.com VIP production.
	 *
	 * @return bool
	 */
	public function is_wpcom_vip_prod() {
		return ( defined( '\WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV );
	}

	/**
	 * Call trigger_error() if not on VIP production.
	 *
	 * @param string $message Warning message.
	 * @param int    $code    Warning code.
	 */
	public function trigger_warning( $message, $code = E_USER_WARNING ) {
		if ( ! $this->is_wpcom_vip_prod() ) {
			// @phpcs:disable
			trigger_error( esc_html( get_class( $this ) . ': ' . $message ), $code );
			// @phpcs:enable
		}
	}

	/**
	 * Force Visit Plugin Site Link
	 *
	 * If the plugin slug is set and the current user can install plugins, only the "View Details" link is shown.
	 * This method forces the "Visit plugin site" link to appear.
	 *
	 * @see wp-admin/includes/class-wp-plugins-list-table.php
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata.
	 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $status      Status of the plugin.
	 *
	 * @return array
	 */
	public function force_visit_plugin_site_link( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( 'Cloudinary' === $plugin_data['Name'] ) {
			$plugin_site_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $plugin_data['PluginURI'] ),
				__( 'Visit plugin site', 'cloudinary' )
			);
			if ( ! in_array( $plugin_site_link, $plugin_meta, true ) ) {
				$plugin_meta[] = $plugin_site_link;
			}
		}

		return $plugin_meta;
	}
}
