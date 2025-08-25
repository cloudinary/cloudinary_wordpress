<?php
/**
 * Extensions class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;

/**
 * Class Extensions
 */
class Extensions extends Settings_Component implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the core plugin settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Holds various keys used.
	 */
	const KEYS = array(
		'extension' => '@extension',
		'register'  => '@register',
	);

	/**
	 * Extensions constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		add_action( 'cloudinary_connected', array( $this, 'init' ) );
	}

	/**
	 * Init component on connection.
	 */
	public function init() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'cloudinary_init_settings', array( $this, 'register_extensions' ) );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['extension'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_toggle_extension' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		return $endpoints;
	}

	/**
	 * Admin permission callback.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @return bool
	 */
	public function rest_can_manage_options() {
		return Utils::user_can( 'manage_extensions' );
	}

	/**
	 * Handles the enable and disable of an extension..
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_toggle_extension( \WP_REST_Request $request ) {
		$extension = $request->get_param( 'extension' );
		$value     = true === $request->get_param( 'enabled' ) ? 'on' : 'off';

		$extension_setting = $this->settings->get_setting( $extension );
		$saved             = $extension_setting->save_value( $value );

		$return = array(
			// Translators: placeholder is number of active extensions.
			'active_settings' => $this->get_active_count_text(),
		);

		return rest_ensure_response( $return );
	}

	/**
	 * Get the active extensions count.
	 *
	 * @return string
	 */
	public function get_active_count_text() {
		$active = 0;
		foreach ( $this->settings->get_value( $this->settings_slug ) as $value ) {
			if ( 'on' === $value ) {
				++$active;
			}
		}

		// Translators: placeholders are number of active extensions.
		return sprintf( _n( '%s Active extension', '%s Active extensions', $active, 'cloudinary' ), $active );
	}

	/**
	 * Get a list of internal extensions.
	 *
	 * @return array[]
	 */
	protected function get_internal_extensions() {
		$internal = array(
			'media-library' => array(
				'name'        => __( 'Cloudinary DAM', 'cloudinary' ),
				'description' => sprintf(
					// translators: The Link for Learn more.
					__( 'Cloudinary’s Digital Asset Management solutions is designed to meet the unique needs of today focusing on flexibility, intelligent automation features, and delivery at scale. %1$sLearn More%2$s.', 'cloudinary' ),
					'<br><a href="https://cloudinary.com/products/digital_asset_management" target="_blank">',
					'</a>'
				),
				'icon'        => $this->plugin->dir_url . 'css/images/dam-icon.svg',
				'handler'     => '\\Cloudinary\\Media_Library',
				'default'     => 'off',
			),
		);

		return $internal;
	}

	/**
	 * Register extensions.
	 */
	public function register_extensions() {
		$extensions = $this->get_internal_extensions();

		$panel = array(
			'type'        => 'panel',
			'slug'        => 'extensions',
			'title'       => __( 'Extensions', 'cloudinary' ),
			'description' => $this->get_active_count_text(),
			'collapsible' => 'closed',
			'attributes'  => array(
				'description' => array(
					'data-text' => 'active_settings',
				),
			),
		);

		$this->settings->set_param( 'sidebar' . $this->settings->separator . $this->settings_slug, $panel );

		/**
		 * Filter to register extensions.
		 *
		 * @hook  cloudinary_register_extensions
		 * @since 3.0.0
		 *
		 * @param $extensions {array) The list of extensions to register.
		 * @param $plugin     {Plugin} The core plugin object.
		 */
		$extensions = apply_filters( 'cloudinary_register_extensions', $extensions, $this->plugin );
		foreach ( $extensions as $slug => $extension ) {

			$instance = null;
			if ( isset( $extension['handler'] ) ) {
				$try = array( $extension['handler'], 'get_instance' );
				if ( is_callable( $try ) ) {
					$instance = call_user_func( $try );
				}

				// Add to plugin components.
				$this->plugin->components[ 'extension_' . $slug ] = $instance;
			}

			$extension_slug    = $this->settings_slug . $this->settings->separator . $slug;
			$extension_setting = $this->settings->add(
				$extension_slug,
				isset( $extension['default'] ) ? $extension['default'] : 'off',
				array(
					'type'       => 'on_off',
					'slug'       => 'enable',
					'instance'   => $instance,
					'attributes' => array(
						'data-extension' => $slug,
					),
				)
			);

			// Create panel in sidebar.
			$params = array(
				'type'       => 'info_box',
				'icon'       => $extension['icon'],
				'slug'       => $slug,
				'title'      => $extension['name'],
				'text'       => $extension['description'],
				'attributes' => array(
					'wrap' => array(
						'class' => array(
							'extension-item',
						),
					),
				),
				$extension_setting,
			);
			$this->settings->set_param( 'sidebar.extensions.' . $slug, $params );
		}

		// Add page re-loader button.
		$params = array(
			'type'       => 'tag',
			'element'    => 'a',
			'content'    => __( 'Reload page', 'cloudinary' ),
			'attributes' => array(
				'style' => 'width:100%; display:none; text-align:center;',
				'id'    => 'page-reloader',
				'class' => array(
					'button',
					'button-small',
				),
				'href'  => add_query_arg( 'reload', '1' ),
			),
		);
		$this->settings->set_param( 'sidebar.extensions.settings', $params );
		// Set settings to own.
		$this->settings = $this->settings->get_setting( $this->settings_slug );
	}

	/**
	 * Setup Extensions.
	 */
	public function setup() {
		$data = array(
			'url'   => Utils::rest_url( REST_API::BASE . '/extension' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		);
		foreach ( $this->settings->get_settings() as $setting ) {
			if ( 'on' === $setting->get_value() && $setting->has_param( 'instance' ) ) {
				$setting->get_param( 'instance' )->setup();
			}
		}
		$this->plugin->add_script_data( 'extensions', $data );
	}

	/**
	 * Init the settings.
	 *
	 * @param Settings $settings The core settings for the plugin.
	 */
	public function init_settings( $settings ) {
		parent::init_settings( $settings );
		// Register setting point.
		$settings->add( $this->settings_slug );
	}
}
