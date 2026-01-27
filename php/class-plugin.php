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
use Cloudinary\Delivery\Lazy_Load;
use Cloudinary\Delivery\Responsive_Breakpoints;
use Cloudinary\Assets as CLD_Assets;
use Cloudinary\Integrations\Elementor;
use Cloudinary\Integrations\WPML;
use Cloudinary\Media\Gallery;
use Cloudinary\Sync\Storage;
use Cloudinary\UI\State;
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
	 * @var     Admin|CLD_Assets|Connect|Dashboard|Deactivation|Delivery|Extensions|Gallery|Lazy_Load|Media|Meta_Box|Relate|Report|Responsive_Breakpoints|REST_API|State|Storage|SVG|Sync|URL[]|WPML|Elementor|null
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
	 * @var Settings
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
	 * The plugin file.
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Holds the list of keys.
	 */
	const KEYS = array(
		'notices' => 'cloudinary_notices',
	);

	/**
	 * Plugin_Base constructor.
	 */
	public function __construct() {
		$this->setup_endpoints();
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
	public function plugins_loaded() {
		Cron::get_instance();
		$this->components['admin']                  = new Admin( $this );
		$this->components['state']                  = new State( $this );
		$this->components['connect']                = new Connect( $this );
		$this->components['deactivation']           = new Deactivation( $this );
		$this->components['sync']                   = new Sync( $this );
		$this->components['media']                  = new Media( $this );
		$this->components['gallery']                = new Gallery( $this );
		$this->components['api']                    = new REST_API( $this );
		$this->components['storage']                = new Storage( $this );
		$this->components['report']                 = new Report( $this );
		$this->components['delivery']               = new Delivery( $this );
		$this->components['lazy_load']              = new Lazy_Load( $this );
		$this->components['responsive_breakpoints'] = new Responsive_Breakpoints( $this );
		$this->components['assets']                 = new CLD_Assets( $this );
		$this->components['dashboard']              = new Dashboard( $this );
		$this->components['extensions']             = new Extensions( $this );
		$this->components['svg']                    = new SVG( $this );
		$this->components['relate']                 = new Relate( $this );
		$this->components['metabox']                = new Meta_Box( $this );
		$this->components['url']                    = new URL( $this );
		$this->components['wpml']                   = new WPML( $this );
		$this->components['elementor']              = new Elementor( $this );
		$this->components['special_offer']          = new Special_Offer( $this );
	}

	/**
	 * Get a plugin component.
	 *
	 * @param mixed $component The component.
	 *
	 * @return Admin|CLD_Assets|Connect|Dashboard|Deactivation|Delivery|Extensions|Gallery|Lazy_Load|Media|Meta_Box|Relate|Report|Responsive_Breakpoints|REST_API|State|Storage|SVG|Sync|URL|null
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
			'pages' => array(),
		);

		foreach ( $parts as $slug => $part ) {
			if ( file_exists( $this->dir_path . "ui-definitions/settings-{$slug}.php" ) ) {
				$parts[ $slug ] = include $this->dir_path . "ui-definitions/settings-{$slug}.php"; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}

		$structure = array(
			'version'    => $this->version,
			'page_title' => __( 'Cloudinary', 'cloudinary' ),
			'menu_title' => __( 'Cloudinary', 'cloudinary' ),
			'capability' => Utils::user_can( 'manage_settings' ) ? 'exist' : false,
			'icon'       => 'dashicons-cloudinary',
			'slug'       => $this->slug,
			'settings'   => $parts['pages'],
			'sidebar'    => include CLDN_PATH . 'ui-definitions/settings-sidebar.php',
		);

		return $structure;
	}

	/**
	 * Setup settings.
	 */
	public function setup_settings() {
		$params         = $this->get_settings_page_structure();
		$this->settings = new Settings( $this->slug, $params );
		$components     = array_filter( $this->components, array( $this, 'is_setting_component' ) );
		$this->init_component_settings( $components );

		// Setup connection.
		$connection = $this->get_component( 'connect' )->is_connected();
		if ( false === $connection ) {
			$count      = sprintf( ' <span class="update-plugins count-%d"><span class="update-count">%d</span></span>', 1, number_format_i18n( 1 ) );
			$main_title = $this->settings->get_param( 'menu_title' ) . $count;
			$this->settings->set_param( 'menu_title', $main_title );
			$this->settings->set_param( 'connect_count', $count );
		} else {
			$this->settings->set_param( 'connected', true );
			/**
			 * Action indicating that the cloudinary is connected.
			 *
			 * @hook  cloudinary_connected
			 * @since 3.0.0
			 *
			 * @param $plugin {Plugin} The core plugin object.
			 */
			do_action( 'cloudinary_connected', $this );
		}
		/**
		 * Action indicating that the Settings are initialised.
		 *
		 * @hook  cloudinary_init_settings
		 * @since 2.7.5
		 *
		 * @param $plugin {Plugin} The core plugin object.
		 */
		do_action( 'cloudinary_init_settings', $this );

		// Register with admin.
		$this->components['admin']->register_page( $this->slug, $this->settings->get_params() );
	}

	/**
	 * Init component settings objects.
	 *
	 * @param Settings_Component[] $components of components to init settings for.
	 */
	private function init_component_settings( $components ) {
		$version = get_option( Connect::META_KEYS['version'] );
		foreach ( $components as $slug => $component ) {
			/**
			 * Component that implements Settings.
			 *
			 * @var  Component\Settings $component
			 */
			$component->init_settings( $this->settings );

			// Upgrade settings if needed.
			if ( $version < $this->version ) {
				$component->upgrade_settings( $version, $this->version );
			}
		}
		// Update settings version, if needed.
		if ( $version < $this->version ) {
			update_option( Connect::META_KEYS['version'], $this->version );
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
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_enqueue_styles' ), 11 );
		add_action( 'init', array( $this, 'init' ) );
		// Move to 100 and 200 to allow other plugins/systems to add cloudinary filters and actions that are fired within the init hooks.
		add_action( 'init', array( $this, 'setup' ), 100 );
		add_action( 'init', array( $this, 'register_assets' ), 200 );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'plugin_row_meta', array( $this, 'force_visit_plugin_site_link' ), 10, 4 );
		add_action( 'admin_print_footer_scripts', array( $this, 'print_script_data' ), 1 );
		add_action( 'wp_print_footer_scripts', array( $this, 'print_script_data' ), 1 );

		add_action( 'cloudinary_version_upgrade', array( Utils::class, 'install' ) );
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
	 * @return bool If the component is an asset implemented object or not.
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
	 * Init the plugin properties.
	 *
	 * @return void
	 */
	public function init() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin              = get_plugin_data( CLDN_CORE );
		$location            = $this->locate_plugin();
		$this->slug          = ! empty( $plugin['TextDomain'] ) ? $plugin['TextDomain'] : $location['dir_basename'];
		$this->version       = $plugin['Version'];
		$this->dir_path      = $location['dir_path'];
		$this->template_path = $this->dir_path . 'php/templates/';
		$this->dir_url       = $location['dir_url'];
		$this->plugin_file   = pathinfo( dirname( CLDN_CORE ), PATHINFO_BASENAME ) . '/' . wp_basename( CLDN_CORE );
	}

	/**
	 * Setup hooks
	 *
	 * @since  0.1
	 */
	public function setup() {
		$this->set_config();

		if ( $this->settings->get_param( 'connected' ) ) {
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
		 * Action indicating that the Cloudinary is ready and setup.
		 *
		 * @hook  cloudinary_ready
		 * @since 3.0.0
		 *
		 * @param $plugin {Plugin} The core plugin object.
		 */
		do_action( 'cloudinary_ready', $this );
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
				$this->components['admin']->add_admin_notice( 'cld_general', $notice['message'], $notice['type'], $notice['dismissible'], $notice['duration'], $notice['icon'] );
			}
		}
	}

	/**
	 * Setup the Cloudinary endpoints.
	 */
	protected function setup_endpoints() {

		/**
		 * The Cloudinary API URL.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_API' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_API', 'api.cloudinary.com' );
		}

		/**
		 * The Cloudinary endpoint for the Core.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_CORE' ) ) {
			// The %s stands for the version of Core. Use the constant CLOUDINARY_ENDPOINTS_CORE_VERSION to set it.
			define( 'CLOUDINARY_ENDPOINTS_CORE', 'https://unpkg.com/cloudinary-core@%s/cloudinary-core-shrinkwrap.min.js' );
		}

		/**
		 * The Cloudinary Core version.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_CORE_VERSION' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_CORE_VERSION', '2.6.3' );
		}

		/**
		 * The Cloudinary endpoint to submit the deactivation feedback.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_DEACTIVATION' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_DEACTIVATION', 'https://analytics-api.cloudinary.com/wp_deactivate_reason' );
		}

		/**
		 * The Cloudinary Gallery widget lib cdn url.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_GALLERY' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_GALLERY', 'https://product-gallery.cloudinary.com/all.js' );
		}

		/**
		 * The Cloudinary endpoint for the Media Library.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_MEDIA_LIBRARY' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_MEDIA_LIBRARY', 'https://media-library.cloudinary.com/global/all.js' );
		}

		/**
		 * The Cloudinary endpoint for the Preview Image.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE', 'https://res.cloudinary.com/demo/image/upload/' );
		}

		/**
		 * The Cloudinary endpoint for the Preview Video.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_PREVIEW_VIDEO' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_PREVIEW_VIDEO', 'https://res.cloudinary.com/demo/video/upload/' );
		}

		/**
		 * The Cloudinary endpoint for the Video Player Embed.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_EMBED' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_EMBED', 'https://player.cloudinary.com/embed/' );
		}

		/**
		 * The Cloudinary endpoint for the Video Player Script.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_SCRIPT' ) ) {
			// The %s stands for the version of Video Player. Use the constant CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_VERSION to set it.
			define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_SCRIPT', 'https://unpkg.com/cloudinary-video-player@%s/dist/cld-video-player.min.js' );
		}

		/**
		 * The Cloudinary endpoint for the Video Player Style.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_STYLE' ) ) {
			// The %s stands for the version of Video Player. Use the constant CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_VERSION to set it.
			define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_STYLE', 'https://unpkg.com/cloudinary-video-player@%s/dist/cld-video-player.min.css' );
		}

		/**
		 * The Cloudinary Video Player version.
		 */
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_VERSION' ) ) {
			define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_VERSION', '3.0.2' );
		}
	}

	/**
	 * Autoload for classes that are in the same namespace as $this.
	 *
	 * @param string $class Class name.
	 *
	 * @return void
	 */
	public function autoload( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
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
		$directory = __DIR__ . DIRECTORY_SEPARATOR . '../php';
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
		$dir_basename = wp_basename( CLDN_PATH );

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
	 * Add Script data.
	 *
	 * @param string      $slug   The slug to add.
	 * @param mixed       $value  The value to set.
	 * @param string|null $handle $the optional script handle to add data for.
	 */
	public function add_script_data( $slug, $value, $handle = null ) {
		if ( null === $handle ) {
			$handle = $this->slug;
		}
		$this->settings->set_param( '@script' . $this->settings->separator . $handle . $this->settings->separator . $slug, $value );
	}

	/**
	 * Output script data if set.
	 */
	public function print_script_data() {
		if ( ! isset( $this->settings ) || ! method_exists( $this->settings, 'get_param' ) ) {
			return;
		}

		$handles = $this->settings->get_param( '@script' );

		if ( ! empty( $handles ) ) {
			foreach ( $handles as $handle => $data ) {
				// We should never be using multiple handles. This is just for cases where data needs to be added where the main script is not loaded.
				$json = wp_json_encode( $data );
				wp_add_inline_script( $handle, 'var cldData = ' . $json, 'before' );
			}
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
	public function force_visit_plugin_site_link( $plugin_meta, $plugin_file, $plugin_data, $status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
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
