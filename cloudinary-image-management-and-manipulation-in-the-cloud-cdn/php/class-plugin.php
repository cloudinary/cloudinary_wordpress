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

/**
 * Main plugin bootstrap file.
 */
class Plugin {

	/**
	 * Holds the components of the plugin
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	public $components;
	/**
	 * Plugin config.
	 *
	 * @var array
	 */
	public $config = array();

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
		$this->components['settings'] = new Settings_Page( $this );
		$this->components['connect']  = new Connect( $this );
		$this->components['sync']     = new Sync( $this );
		$this->components['api']      = new REST_API( $this );
		$this->components['media']    = new Media( $this );
	}

	/**
	 * Register Hooks for the plugin.
	 */
	public function set_config() {
		$components = array_filter( $this->components, array( $this, 'is_config_component' ) );

		foreach ( $components as $slug => $component ) {
			/**
			 * Component that implements Component\Config.
			 *
			 * @var  Component\Config $component
			 */
			$this->config[ $slug ] = $component->get_config();
		}
	}

	/**
	 * Register Hooks for the plugin.
	 */
	public function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 11 );
		add_action( 'init', array( $this, 'setup' ), 10 );
		add_action( 'init', array( $this, 'register_assets' ), 10 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'plugin_row_meta', array( $this, 'force_visit_plugin_site_link' ), 10, 4 );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_assets() {
		// Enqueue Main.
		wp_enqueue_script( 'cloudinary', $this->dir_url . 'js/cloudinary.js', array( 'jquery', 'wp-util' ), $this->version, true );
		wp_enqueue_style( 'cloudinary', $this->dir_url . 'css/cloudinary.css', null, $this->version );
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
	 * Register Assets
	 *
	 * @since  0.1
	 */
	public function register_assets() {
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
	 * Check if component is a notice implementing component.
	 *
	 * @since  0.1
	 *
	 * @param object $component The component to check.
	 *
	 * @return bool If the component implements Config.
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
	 * Load admin notices where needed.
	 *
	 * @since  0.1
	 */
	public function admin_notices() {
		/**
		 * @var $components Notice[]
		 *
		 * An array of classes that implement the Notice interface.
		 */
		$components = array_filter( $this->components, array( $this, 'is_notice_component' ) );
		$default    = array(
			'message'     => '',
			'type'        => 'error',
			'dismissible' => true,
		);
		foreach ( $components as $component ) {
			$notices = $component->get_notices();
			foreach ( $notices as $notice ) {
				if ( ! empty( $notice ) && ! empty( $notice['message'] ) ) {
					$notice = wp_parse_args( $notice, $default );
					if ( true === $notice['dismissible'] ) {
						$html = sprintf(
							'<div class="notice-%1$s settings-error notice is-dismissible"><p><strong>%2$s</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%3$s</span></button></div>',
							esc_attr( $notice['type'] ),
							$notice['message'],
							__( 'Dismiss this notice.', 'cloudinary' )
						);
					} else {
						$html = sprintf(
							'<div class="notice-%1$s settings-error notice"><p><strong>%2$s</strong></p></div>',
							esc_attr( $notice['type'] ),
							$notice['message']
						);
					}
					echo wp_kses_post( $html );
				}
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
			$class_name = str_replace( 'Trait', '', $class_name );
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

		$dir_url      = CLDN_URL;
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
		return ( defined( '\WPCOM_IS_VIP_ENV' ) && \WPCOM_IS_VIP_ENV );
	}

	/**
	 * Call trigger_error() if not on VIP production.
	 *
	 * @param string $message Warning message.
	 * @param int    $code    Warning code.
	 */
	public function trigger_warning( $message, $code = \E_USER_WARNING ) {
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
			$plugin_site_link = sprintf( '<a href="%s">%s</a>',
				esc_url( $plugin_data['PluginURI'] ),
				__( 'Visit plugin site' )
			);
			if ( ! in_array( $plugin_site_link, $plugin_meta, true ) ) {
				$plugin_meta[] = $plugin_site_link;
			}
		}

		return $plugin_meta;
	}
}
