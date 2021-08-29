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
use WP_REST_Server;
use WP_REST_Request;

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
	 * Holds notices object.
	 *
	 * @var Settings
	 */
	protected $notices;

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
	protected $component;

	/**
	 * Option name for settings based internal data.
	 *
	 * @var string
	 */
	const SETTINGS_DATA = '_settings_version';

	/**
	 * Slug for notices
	 *
	 * @var string
	 */
	const NOTICE_SLUG = '_cld_notices';

	/**
	 * Initiate the settings object.
	 *
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'cloudinary_init_settings', array( $this, 'init_settings' ) );
		add_action( 'admin_init', array( $this, 'init_setting_save' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( $this, 'build_menus' ) );
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		$notice_params = array(
			'storage' => 'transient',
		);
		$notices       = new Settings( self::NOTICE_SLUG, $notice_params );
		$this->notices = $notices->add( 'cld_general', array() );
		add_action( 'shutdown', array( $notices, 'save' ) );
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
		$connected   = $this->settings->get_param( 'connected' );
		// Setup the Child page handles.
		foreach ( $page['settings'] as $slug => $sub_page ) {
			if ( empty( $sub_page ) ) {
				continue;
			}
			// Check if the page contains settings that require connection.
			if ( ! empty( $sub_page['requires_connection'] ) && empty( $connected ) ) {
				continue;
			}
			$render_slug = $page['slug'] . '_' . $slug;
			if ( ! isset( $first ) ) {
				$render_slug = $page['slug'];
				$first       = true;
			}
			if ( ! apply_filters( "cloudinary_settings_enabled_{$slug}", true ) ) {
				continue;
			}
			$capability       = ! empty( $sub_page['capability'] ) ? $sub_page['capability'] : $page['capability'];
			$page_title       = ! empty( $sub_page['page_title'] ) ? $sub_page['page_title'] : $page['page_title'];
			$menu_title       = ! empty( $sub_page['menu_title'] ) ? $sub_page['menu_title'] : $page_title;
			$position         = ! empty( $sub_page['position'] ) ? $sub_page['position'] : 50;
			$page_handle      = add_submenu_page(
				$page['slug'],
				$page_title,
				$menu_title,
				$capability,
				$render_slug,
				$render_function,
				$position
			);
			$sub_page['slug'] = $slug;
			$this->set_param( $page_handle, $sub_page );
			// Dynamically call to set active setting.
			add_action( "load-{$page_handle}", array( $this, $page_handle ) );
		}
	}

	/**
	 * Dynamically set the active page.
	 *
	 * @param string $name      The name called (page in this case).
	 * @param array  $arguments Arguments passed to call.
	 */
	public function __call( $name, $arguments ) {
		if ( $this->has_param( $name ) ) {
			$page = $this->get_param( $name );
			$this->settings->set_param( 'active_setting', $page['slug'] );
		}
	}

	/**
	 * Render a page.
	 */
	public function render() {
		wp_enqueue_script( $this->plugin->slug );
		$screen = get_current_screen();
		$page   = $this->get_param( $screen->id );

		$this->set_param( 'active_slug', $page['slug'] );
		$setting         = $this->init_components( $page, $screen->id );
		$this->component = $setting->get_component();
		include $this->plugin->dir_path . 'ui-definitions/components/page.php';
	}

	/**
	 * Get the component.
	 *
	 * @return Component
	 */
	public function get_component() {
		return $this->component;
	}

	/**
	 * Initialise UI components.
	 *
	 * @param array  $template The template structure.
	 * @param string $slug     The slug of the template ti init.
	 *
	 * @return Setting|null
	 */
	public function init_components( $template, $slug ) {
		if ( ! empty( $template['requires_connection'] ) && ! $this->settings->get_param( 'connected' ) ) {
			return null;
		}
		$setting = $this->settings->add( $slug, array(), $template );
		foreach ( $template as $index => $component ) {
			if ( ! self::filter_template( $index ) ) {
				continue;
			}
			if ( ! isset( $component['type'] ) ) {
				$component['type'] = 'frame';
			}
			if ( ! isset( $component['setting'] ) ) {
				$component['setting'] = $this->init_components( $component, $slug . '.' . $component['type'] . '_' . $index );
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
	 * Init the plugin settings.
	 */
	public function init_settings() {
		$this->settings = $this->plugin->settings;
	}

	/**
	 * Register settings with WordPress.
	 */
	public function init_setting_save() {
		$settings_slug = $this->settings->get_slug();
		$args          = array(
			'_cld_nonce'             => FILTER_SANITIZE_STRING,
			'_wp_http_referer'       => FILTER_SANITIZE_URL,
			'cloudinary-active-slug' => FILTER_SANITIZE_STRING,
			$settings_slug           => array(
				'flags' => FILTER_REQUIRE_ARRAY,
			),
		);
		$saving        = filter_input_array( INPUT_POST, $args, false );

		if ( ! empty( $saving ) && ! empty( $saving[ $settings_slug ] ) && wp_verify_nonce( $saving['_cld_nonce'], 'cloudinary-settings' ) ) {
			$referer = $saving['_wp_http_referer'];
			wp_parse_str( wp_parse_url( $referer, PHP_URL_QUERY ), $query );

			$errors = array();
			$updated = false;
			foreach ( $saving[ $settings_slug ] as $key => $value ) {
				$current = $this->settings->get_value( $key );
				if( $current === $value ){
					continue;
				}
				$capture_setting = $this->settings->get_setting( $key );
				$value           = $capture_setting->get_component()->sanitize_value( $value );
				$result          = $this->settings->set_pending( $key, $value );
				if ( is_wp_error( $result ) ) {
					$errors[] = $result;
					break;
				}
				$updated = true;
			}
			if ( empty( $errors ) && true === $updated ) {
				$this->add_admin_notice( 'success_notice', __( 'Settings updated successfully', 'cloudinary' ), 'success' );
				$this->settings->save();
				$slug      = $saving['cloudinary-active-slug'];
				$new_value = $this->settings->get_value( $slug );
				/**
				 * Action to announce the saving of a setting.
				 *
				 * @hook   cloudinary_save_settings_{$slug}
				 * @hook   cloudinary_save_settings
				 * @since  2.7.6
				 *
				 * @param $new_value {int}     The new setting value.
				 */
				do_action( "cloudinary_save_settings_{$slug}", $new_value );
				do_action( 'cloudinary_save_settings', $new_value );
			}
			foreach ( $errors as $error ) {
				$this->add_admin_notice( $error->get_error_code(), $error->get_error_message(), $error->get_error_data() );
			}

			wp_safe_redirect( $referer );
			exit;
		}
	}

	/**
	 * Set an error/notice for a setting.
	 *
	 * @param string $error_code    The error code/slug.
	 * @param string $error_message The error text/message.
	 * @param string $type          The error type.
	 * @param bool   $dismissible   If notice is dismissible.
	 * @param int    $duration      How long it's dismissible for.
	 * @param string $icon          Optional icon.
	 */
	public function add_admin_notice( $error_code, $error_message, $type = 'error', $dismissible = true, $duration = 0, $icon = null ) {

		// Format message array into paragraphs.
		if ( is_array( $error_message ) ) {
			$message       = implode( "\n\r", $error_message );
			$error_message = wpautop( $message );
		}

		$icons = array(
			'success' => 'dashicons-yes-alt',
			'created' => 'dashicons-saved',
			'updated' => 'dashicons-saved',
			'error'   => 'dashicons-no-alt',
			'warning' => 'dashicons-warning',
		);

		if ( null === $icon && ! empty( $icons[ $type ] ) ) {
			$icon = $icons[ $type ];
		}
		$notices = $this->notices->get_value();
		// Set new notice.
		$params    = array(
			'type'     => 'notice',
			'level'    => $type,
			'message'  => $error_message,
			'code'     => $error_code,
			'dismiss'  => $dismissible,
			'duration' => $duration,
			'icon'     => $icon,
		);
		$notices[] = $params;
		$this->notices->set_pending( $notices );
		$this->notices->set_value( $notices );
	}

	/**
	 * Render the notices.
	 */
	public function render_notices() {
		$notices = $this->notices->get_value();
		if ( ! empty( $notices ) ) {
			$notice = $this->init_components( $notices, self::NOTICE_SLUG );
			$notice->get_component()->render( true );
			$this->notices->set_pending( array() );
		}
	}

	/**
	 * Get admin notices.
	 *
	 * @return Setting[]
	 */
	public function get_admin_notices() {
		$setting_notices = get_settings_errors();
		foreach ( $setting_notices as $key => $notice ) {
			$this->add_admin_notice( $notice['code'], $notice['message'], $notice['type'], true );
		}

		return $setting_notices;
	}
}
