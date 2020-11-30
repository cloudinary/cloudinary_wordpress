<?php
/**
 * Settings class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Settings\Setting;
use WP_Screen;

/**
 * Settings Class.
 */
class Settings {

	/**
	 * The single instance of the class.
	 *
	 * @var Settings
	 */
	protected static $instance = null;
	/**
	 * The setting to test.
	 *
	 * @var Setting[]
	 */
	protected $settings;

	/**
	 * List of Pages.
	 *
	 * @var Setting[]
	 */
	protected $pages = array();

	/**
	 * Holds the current Page.
	 *
	 * @var Setting
	 */
	protected $current_page;

	/**
	 * Holds the current tab.
	 *
	 * @var Setting
	 */
	protected $current_tab;

	/**
	 * Initiate the settings object.
	 */
	protected function __construct() {
		$this->settings = new Setting( '_settings' );
		add_action( 'admin_menu', array( $this, 'register_admin' ) );
	}

	/**
	 * Register the page.
	 */
	public function register_admin() {
		$render_function = array( $this, 'render' );
		foreach ( $this->settings->get_settings() as $setting ) {
			// Setup the main page.
			$page_handle                 = add_menu_page( $setting->get_param( 'page_title' ), $setting->get_param( 'menu_title' ), $setting->get_param( 'capability' ), $setting->get_slug(), $render_function, $setting->get_param( 'icon' ) );
			$this->pages[ $page_handle ] = $setting;
			$setting->set_param( 'page_handle', $page_handle );
			// Setup the Child pages.
			foreach ( $setting->get_settings() as $sub_setting ) {
				if ( 'page' !== $sub_setting->get_param( 'type' ) ) {
					continue;
				}
				$sub_setting->set_param( 'header', $setting->get_param( 'header' ) );
				$sub_setting->set_param( 'footer', $setting->get_param( 'footer' ) );
				$capability                  = $sub_setting->get_param( 'capability', $setting->get_param( 'capability' ) );
				$page_handle                 = add_submenu_page( $setting->get_slug(), $sub_setting->get_param( 'page_title', $setting->get_param( 'page_title' ) ), $sub_setting->get_param( 'menu_title', $setting->get_param( 'menu_title' ) ), $capability, $sub_setting->get_slug(), $render_function, $sub_setting->get_param( 'position' ) );
				$this->pages[ $page_handle ] = $sub_setting;
				$sub_setting->set_param( 'page_handle', $page_handle );
			}
		}
	}

	/**
	 * Render a page.
	 */
	public function render() {
		echo $this->get_active_page()->get_component()->render(); // phpcs:ignore
	}

	/**
	 * Get the currently active page.
	 *
	 * @return Setting
	 */
	public function get_active_page() {
		$screen = get_current_screen();
		$page   = $this->settings;
		if ( $screen instanceof WP_Screen && isset( $this->pages[ $screen->id ] ) ) {
			$page = $this->pages[ $screen->id ];
		}

		return $page;
	}

	/**
	 * Register a setting.
	 *
	 * @param string $slug   The new setting slug.
	 * @param array  $params The setting parameters.
	 *
	 * @return Setting
	 */
	protected function register_setting( $slug, $params = array() ) {
		$setting = $this->settings->add_setting( $slug, $params );
		$setting->set_param( 'type', 'page' );

		return $setting;
	}

	/**
	 * Create a new setting on the Settings object.
	 *
	 * @param string $slug   The setting slug.
	 * @param array  $params The settings params.
	 *
	 * @return Setting
	 */
	public static function create_setting( $slug, $params = array() ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		$params['option_name'] = ''; // Root option.

		return self::$instance->register_setting( $slug, $params );
	}

	/**
	 * Initialise the registered settings by loading the data and registering the settings with WordPress.
	 *
	 * @param string $slug The setting slug to initialise.
	 */
	public static function init_setting( $slug ) {
		if ( ! is_null( self::$instance ) && self::$instance->settings->has_setting( $slug ) ) {
			$self     = self::$instance;
			$settings = $self->settings->get_setting( $slug );
			$settings->set_param( 'option_name', $settings->get_slug() );
			$settings->setup_component();
			$settings->load_value();
		}
	}
}
