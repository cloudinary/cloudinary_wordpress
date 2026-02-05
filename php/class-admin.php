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
use WP_REST_Response;

/**
 * Admin Class.
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
	 * Holds the page section.
	 *
	 * @var string
	 */
	protected $section = 'page';

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
		if ( ! defined( 'REST_REQUEST' ) || true !== REST_REQUEST ) {
			add_action( 'shutdown', array( $notices, 'save' ) );
		}
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
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_dismiss_notice' ),
			'args'                => array(),
			'permission_callback' => function () {
				return Utils::user_can( 'manage_settings' );
			},
		);

		$endpoints['save_settings'] = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_save_settings' ),
			'args'                => array(),
			'permission_callback' => function () {
				return Utils::user_can( 'manage_settings' );
			},
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
	 * Set a transient with the duration using a token as an identifier.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_save_settings( WP_REST_Request $request ) {
		$data     = $request->get_params();
		$settings = $this->settings;
		$data     = array_filter(
			$data,
			function ( $key ) use ( $settings ) {
				return $settings->get_setting( $key, false );
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $data as $submission => $package ) {
			$this->save_settings( $submission, $package );
		}
		$results = $this->notices->get_value();
		$this->notices->delete();

		return rest_ensure_response( $results );
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
			$page['capability'], // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			$page['slug'],
			'',
			$page['icon'],
			'81.5'
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
			// Add section page if defined.
			if ( ! empty( $sub_page['section'] ) ) {
				$this->set_param( $sub_page['section'], $sub_page );
				// Section pages are more like tabs, so skip menu page registrations.
				continue;
			}
			$capability = ! empty( $sub_page['capability'] ) ? $sub_page['capability'] : $page['capability'];
			$page_title = ! empty( $sub_page['page_title'] ) ? $sub_page['page_title'] : $page['page_title'];
			$menu_title = ! empty( $sub_page['menu_title'] ) ? $sub_page['menu_title'] : $page_title;
			$position   = ! empty( $sub_page['position'] ) ? $sub_page['position'] : 50;
			if ( isset( $sub_page['disconnected_title'] ) && ! $this->settings->get_param( 'connected' ) ) {
				$page_title = $sub_page['disconnected_title'];
				$menu_title = $sub_page['disconnected_title'];
			}
			$page_handle      = add_submenu_page(
				$page['slug'],
				$page_title,
				$menu_title,
				$capability, // phpcs:ignore WordPress.WP.Capabilities.Undetermined
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
			$section = Utils::get_sanitized_text( 'section' );
			if ( $section && $this->has_param( $section ) ) {
				$this->section = $section;
				$this->set_param( 'current_section', $this->get_param( $section ) );
			}
			if ( 'page' === $this->section && ! $this->settings->get_param( 'connected' ) && 'help' !== $page['slug'] ) {
				$args = array(
					'page'    => $this->plugin->slug,
					'section' => 'wizard',
				);
				$url  = add_query_arg( $args, 'admin.php' );
				wp_safe_redirect( $url );
				exit;
			}
		}
	}

	/**
	 * Render a page.
	 */
	public function render() {
		wp_enqueue_script( $this->plugin->slug );
		$screen = get_current_screen();
		$page   = $this->get_param( $screen->id );
		// Check if a section page was set, and replace page structure with the section.
		if ( $this->has_param( 'current_section' ) ) {
			$page = $this->get_param( 'current_section' );
		}

		$this->set_param( 'active_slug', isset( $page['slug'] ) ? $page['slug'] : '' );
		$setting         = $this->init_components( $page, $screen->id );
		$this->component = $setting->get_component();
		$template        = $this->section;

		$file = $this->plugin->dir_path . 'ui-definitions/components/page.php';
		if ( file_exists( $this->plugin->dir_path . 'ui-definitions/components/' . $template . '.php' ) ) {
			// If the section has a defined template, use that instead eg. wizard.
			$file = $this->plugin->dir_path . 'ui-definitions/components/' . $template . '.php';
		}
		include $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
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
			// Add setting components directly.
			if ( $component instanceof Setting ) {
				$setting->add( $component );
				continue;
			}

			if ( ( ! is_array( $component ) || ! isset( $component['slug'] ) ) && ! self::filter_template( $index ) ) {
				continue;
			}

			if ( ! isset( $component['type'] ) ) {
				$component['type'] = 'frame';
			}
			$component_slug = $index;
			if ( isset( $component['slug'] ) ) {
				$component_slug = $component['slug'];
			}
			if ( ! isset( $component['setting'] ) ) {
				$component['setting'] = $this->init_components( $component, $slug . $this->settings->separator . $component_slug );
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

		$submission = Utils::get_sanitized_text( 'cloudinary-active-slug', INPUT_POST );
		if ( ! $submission ) {
			return; // Bail.
		}

		$args = array(
			'_cld_nonce'       => array(
				'filter'  => FILTER_CALLBACK,
				'options' => 'sanitize_text_field',
			),
			'_wp_http_referer' => FILTER_SANITIZE_URL,
			$submission        => array(
				'flags' => FILTER_REQUIRE_ARRAY,
			),
		);

		$saving = filter_input_array( INPUT_POST, $args, false );
		if ( ! empty( $saving ) && ! empty( $saving[ $submission ] ) && wp_verify_nonce( $saving['_cld_nonce'], 'cloudinary-settings' ) ) {
			$referer = $saving['_wp_http_referer'];

			$data = $saving[ $submission ];
			$this->save_settings( $submission, $data );
			wp_safe_redirect( $referer );
			exit;
		}
	}

	/**
	 * Save a settings set.
	 *
	 * @param string $submission The settings slug to save.
	 * @param array  $data       The data to save.
	 */
	protected function save_settings( $submission, $data ) {
		$page    = $this->settings->get_setting( $submission );
		$errors  = array();
		$pending = false;
		foreach ( $data as $key => $value ) {
			$slug    = $submission . $page->separator . $key;
			$current = $this->settings->get_value( $slug );
			if ( $current === $value ) {
				continue;
			}
			$capture_setting = $this->settings->get_setting( $key );
			$value           = $capture_setting->get_component()->sanitize_value( $value );
			$result          = $this->settings->set_pending( $key, $value, $current );
			if ( is_wp_error( $result ) ) {
				$this->add_admin_notice( $result->get_error_code(), $result->get_error_message(), $result->get_error_data() );
				break;
			}
			$pending = true;
		}

		if ( empty( $errors ) && true === $pending ) {
			$results = $this->settings->save();
			if ( ! empty( $results ) ) {
				$this->add_admin_notice( 'error_notice', __( 'Settings updated successfully', 'cloudinary' ), 'success' );
			}
		} else {
			$this->add_admin_notice( 'error_notice', __( 'No changes to save', 'cloudinary' ), 'success' );
		}
		// Flush cache.
		do_action( 'cloudinary_flush_cache' );
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
		$params                 = array(
			'type'     => 'notice',
			'level'    => $type,
			'message'  => $error_message,
			'code'     => $error_code,
			'dismiss'  => $dismissible,
			'duration' => $duration,
			'icon'     => $icon,
		);
		$notices[ $error_code ] = $params;
		$this->notices->set_pending( $notices );
		$this->notices->set_value( $notices );
	}

	/**
	 * Render the notices.
	 */
	public function render_notices() {

		$notices = $this->notices->get_value();
		if ( ! empty( $notices ) ) {
			sort( $notices );
			$notice = $this->init_components( $notices, self::NOTICE_SLUG );
			$notice->get_component()->render( true );
			$this->notices->delete();
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
