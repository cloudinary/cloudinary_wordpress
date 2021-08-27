<?php
/**
 * Settings class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Traits\Params_Trait;
use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;
use WP_Screen;

/**
 * Settings Class.
 */
class Admin {

	use Params_Trait;

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds main settings object.
	 *
	 * @var Settings
	 */
	protected $settings;
	/**
	 * Holds the pages.
	 *
	 * @var array
	 */
	protected $pages;

	/**
	 * Holds the current page component.
	 *
	 * @var Component
	 */
	public static $component;

	/**
	 * Option name for settings based internal data.
	 *
	 * @var string
	 */
	const SETTINGS_DATA = '_settings_version';

	/**
	 * Initiate the settings object.
	 *
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_init', array( $this, 'init_setting_save' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( $this, 'build_menus' ) );
	}

	/**
	 * Check settings version to allow settings to update or upgrade.
	 *
	 * @param string $slug The slug for the settings set to check.
	 */
	protected static function check_version( $slug ) {
		$key              = '_' . $slug . self::SETTINGS_DATA;
		$settings_version = get_option( $key, 2.4 );
		$plugin_version   = get_plugin_instance()->version;
		if ( version_compare( $settings_version, $plugin_version, '<' ) ) {
			// Allow for updating.
			do_action( "{$slug}_settings_upgrade", $settings_version, $plugin_version );
			// Update version.
			update_option( $key, $plugin_version );
		}
	}

	/**
	 * Register the page.
	 */
	public function build_menus() {
		foreach ( $this->pages as $page ) {
			$this->register_admin( $page );
		}
	}

	/**
	 * Register the page.
	 *
	 * @param array $page The page array to create pages.
	 */
	public function register_admin( $page ) {

		$render_function = array( $this, 'render' );

		// Setup the main page.
		$page_handle = add_menu_page(
			$page['page_title'],
			$page['menu_title'],
			$page['capability'],
			$page['slug'],
			'',
			$page['icon']
		);

		// Setup the Child page handles.
		foreach ( $page['settings'] as $slug => $sub_page ) {
			if ( empty( $sub_page ) ) {
				continue;
			}
			$render_slug = $slug;
			if ( ! isset( $first ) ) {
				$render_slug = $page['slug'];
				$first       = true;
			}
			if ( ! apply_filters( "cloudinary_settings_enabled_{$slug}", true ) ) {
				continue;
			}
			$capability  = ! empty( $sub_page['capability'] ) ? $sub_page['capability'] : $page['capability'];
			$page_title  = ! empty( $sub_page['page_title'] ) ? $sub_page['page_title'] : $page['page_title'];
			$menu_title  = ! empty( $sub_page['menu_title'] ) ? $sub_page['menu_title'] : $page_title;
			$position    = ! empty( $sub_page['position'] ) ? $sub_page['position'] : 50;
			$page_handle = add_submenu_page(
				$page['slug'],
				$page_title,
				$menu_title,
				$capability,
				$render_slug,
				$render_function,
				$position
			);

			$this->set_param( $page_handle, $sub_page );
		}
	}

	/**
	 * Render a page.
	 */
	public function render() {
		wp_enqueue_script( $this->plugin->slug );
		$screen = get_current_screen();
		$page   = $this->get_param( $screen->id );
		if ( ! empty( $page['settings'] ) ) {
			$page['settings'][] = array(
				'type' => 'submit',
			);
		}
		$setting         = $this->init_settings( $page, $screen->id );
		self::$component = $setting->get_component();
		include $this->plugin->dir_path . 'ui-definitions/components/page.php';
	}

	/**
	 * Initialise UI components.
	 *
	 * @param array  $template The template structure.
	 * @param string $slug     The slug of the template ti init.
	 *
	 * @return Setting
	 */
	public function init_settings( $template, $slug ) {
		$settings = get_plugin_instance()->settings;
		$setting  = $settings->add( $slug, array(), $template );
		foreach ( $template as $index => $component ) {
			if ( ! self::filter_template( $index ) ) {
				continue;
			}
			if ( ! isset( $component['type'] ) ) {
				$component['type'] = 'frame';
			}
			if ( ! isset( $component['setting'] ) ) {
				$component['setting'] = $this->init_settings( $component, $slug . '.' . $component['type'] . '_' . $index );
			} else {
				$setting->add( $component['setting'] );
			}
		}

		return $setting;
	}

	/**
	 * Filter out non-setting params.
	 *
	 * @param numeric-string $key The key to filter out.
	 *
	 * @return bool
	 */
	public static function filter_template( $key ) {
		return is_numeric( $key ) || 'settings' === $key;
	}

	/**
	 * Register a setting page.
	 *
	 * @param string $slug   The new page slug.
	 * @param array  $params The page parameters.
	 */
	public function register_page( $slug, $params = array() ) {
		// Register the page.
		$this->pages[ $slug ] = $params;
	}

	/**
	 * Register settings with WordPress.
	 */
	public function init_setting_save() {
		$this->settings = $this->plugin->settings;
		$args           = array(
			'_wpnonce'            => FILTER_SANITIZE_STRING,
			'_wp_http_referer'    => FILTER_SANITIZE_URL,
			'cloudinary_settings' => array(
				'flags' => FILTER_REQUIRE_ARRAY,
			),
		);
		$saving         = filter_input_array( INPUT_POST, $args, false );
		if ( ! empty( $saving ) && ! empty( $saving['cloudinary_settings'] ) && wp_verify_nonce( $saving['_wpnonce'], 'cloudinary-settings' ) ) {
			$referer = $saving['_wp_http_referer'];
			wp_parse_str( wp_parse_url( $referer, PHP_URL_QUERY ), $query );

			$slug = $query['page'];
			foreach ( $saving['cloudinary_settings'] as $key => $value ) {
				$capture_setting = $this->settings->get_setting( $key );
				$value           = $capture_setting->get_component()->sanitize_value( $value );
				$this->settings->set_pending( $key, $value );
			}

			$this->settings->save();
			wp_safe_redirect( $referer );
			exit;
		}
	}

	/**
	 * Register the setting with WordPress.
	 *
	 * @param string $option_name The option name so save.
	 */
	protected function register_setting( $option_name ) {

		$args = array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'prepare_sanitizer' ),
			'show_in_rest'      => false,
		);
		register_setting( $option_name, $option_name, $args );
		add_filter( 'pre_update_site_option_' . $option_name, array( $this, 'set_notices' ), 10, 3 );
		add_filter( 'pre_update_option_' . $option_name, array( $this, 'set_notices' ), 10, 3 );
	}
}
