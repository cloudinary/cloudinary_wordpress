<?php
/**
 * Cloudinary Setting.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Settings;

use Cloudinary\Settings;
use Cloudinary\UI\Component;

/**
 * Class Setting.
 *
 * Setting for Cloudinary.
 */
class Setting {

	/**
	 * The setting params.
	 *
	 * @var array
	 */
	protected $params = array();

	/**
	 * Root settings.
	 *
	 * @var Setting|null
	 */
	protected $root_setting;

	/**
	 * Parent of the setting.
	 *
	 * @var Setting|Settings|null
	 */
	protected $parent;

	/**
	 * Holds this settings child settings.
	 *
	 * @var Setting[]
	 */
	protected $settings = array();

	/**
	 * Setting slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Setting Value.
	 *
	 * @var mixed
	 */
	protected $value = null;

	/**
	 * This settings component object.
	 *
	 * @var Component
	 */
	protected $component;

	/**
	 * Holds a list of dynamic setting params.
	 *
	 * @var array
	 */
	protected $setting_params;

	/**
	 * Setting constructor.
	 *
	 * @param string       $slug   The setting slug.
	 * @param array        $params The setting params.
	 * @param null|Setting $parent $the parent setting.
	 */
	public function __construct( $slug, $params = array(), $parent = null ) {
		$this->slug           = sanitize_file_name( $slug );
		$this->setting_params = $this->get_dynamic_param_keys();
		$root                 = $this;

		if ( ! is_null( $parent ) ) {
			$root = $parent->get_root_setting();
			$this->set_parent( $parent );
		}
		$this->root_setting = $root;
		$this->register_with_root();
		if ( ! empty( $params ) ) {
			$this->setup_setting( $params );
		}
	}

	/**
	 * Register with the root settings.
	 */
	protected function register_with_root() {
		if ( ! $this->is_root_setting() ) {
			// Add to root index.
			$root  = $this->get_root_setting();
			$index = $root->get_param( 'index', array() );
			if ( ! isset( $index[ $this->get_slug() ] ) ) {
				$index[ $this->get_slug() ] = $this;
				$root->set_param( 'index', $index );
			}
		}
	}

	/**
	 * Get the dynamic params and callbacks list. This allows to create a list of specific settings using a key param.
	 *
	 * @return array
	 */
	protected function get_dynamic_param_keys() {
		$default_setting_params = array(
			'components'  => array( $this, 'add_child_settings' ),
			'settings'    => array( $this, 'add_child_settings' ),
			'pages'       => array( $this, 'add_child_pages' ),
			'tabs'        => array( $this, 'add_tab_pages' ),
			'page_header' => array( $this, 'add_header' ),
			'page_footer' => array( $this, 'add_footer' ),
		);
		/**
		 * Filters the list of params that indicate a child setting to allow registering dynamically.
		 *
		 * @param array $setting_params The array of params.
		 *
		 * @return array
		 */
		$setting_params = apply_filters( 'cloudinary_get_setting_params', $default_setting_params, $this );

		return $setting_params;
	}

	/**
	 * Get all the param keys.
	 *
	 * @return array
	 */
	public function get_param_keys() {
		return array_keys( $this->params );
	}

	/**
	 * Sets the params recursively.
	 *
	 * @param array $parts The parts to set.
	 * @param array $param The param being set.
	 * @param mixed $value The value to set.
	 *
	 * @return mixed
	 */
	protected function set_param_array( $parts, $param, $value ) {
		$new = $param;
		$key = array_shift( $parts );
		if ( ! empty( $parts ) ) {
			$param = isset( $param[ $key ] ) ? $param[ $key ] : array();
			$value = $this->set_param_array( $parts, $param, $value );
		}
		$new[ $key ] = $value;

		return $new;

	}

	/**
	 * Set a parameter and value to the setting.
	 *
	 * @param string $param Param key to set.
	 * @param mixed  $value The value to set.
	 *
	 * @return $this
	 */
	public function set_param( $param, $value ) {
		$parts = explode( ':', $param );
		$param = array_shift( $parts );
		if ( ! empty( $parts ) ) {
			if ( ! isset( $this->params[ $param ] ) ) {
				$this->params[ $param ] = array();
			}
			$value = $this->set_param_array( $parts, $this->params[ $param ], $value );
		}

		$this->params[ $param ] = $value;

		if ( is_null( $value ) ) {
			$this->remove_param( $param );
		}

		// Update priority sorting if set.
		if ( 'priority' === $param && $this->has_parent() ) {
			$parent = $this->get_parent();
			$parent->remove_setting( $this->get_slug() );
			$parent->add_setting( $this );
		}

		return $this;
	}

	/**
	 * Remove a parameter.
	 *
	 * @param string $param Param key to set.
	 *
	 * @return $this
	 */
	public function remove_param( $param ) {
		unset( $this->params[ $param ] );

		return $this;
	}

	/**
	 * Get a param from a chained lookup.
	 *
	 * @param string $param_slug The slug to get.
	 *
	 * @return mixed
	 */
	protected function get_array_param( $param_slug ) {
		$parts = explode( ':', $param_slug );
		$param = $this->params;
		while ( ! empty( $parts ) ) {
			if ( ! is_array( $param ) ) {
				$param = null; // Set to null to indicate invalid.
				break;
			}
			$part  = array_shift( $parts );
			$param = isset( $param[ $part ] ) ? $param[ $part ] : null;
		}

		return $param;
	}

	/**
	 *
	 * Check if a param exists.
	 *
	 * @param string $param_slug The param to check.
	 *
	 * @return bool
	 */
	public function has_param( $param_slug ) {
		$param = $this->get_array_param( $param_slug );

		return ! is_null( $param );
	}

	/**
	 * Get params param.
	 *
	 * @param string $param   The param to get.
	 * @param mixed  $default The default value for this param is a value is not found.
	 *
	 * @return string|array|bool|Setting
	 */
	public function get_param( $param, $default = null ) {
		$value = $this->get_array_param( $param );

		return ! is_null( $value ) ? $value : $default;
	}

	/**
	 * Get the whole params.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Check if setting has a parent.
	 *
	 * @return bool
	 */
	public function has_parent() {
		return ! empty( $this->parent );
	}

	/**
	 * Check if setting has settings.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return ! empty( $this->settings );
	}

	/**
	 * Check if setting has settings.
	 *
	 * @param string $setting_slug The setting slug to check.
	 *
	 * @return bool
	 */
	public function has_setting( $setting_slug ) {
		$setting_slugs = $this->get_setting_slugs();

		return in_array( $setting_slug, $setting_slugs, true );
	}

	/**
	 * Get the parent setting.
	 *
	 * @return Setting|null
	 */
	public function get_parent() {
		$parent = null;
		if ( $this->has_parent() ) {
			$parent = $this->parent;
		}

		return $parent;
	}

	/**
	 * Get all settings settings.
	 *
	 * @return Setting[]
	 */
	public function get_settings() {
		$settings = array_filter( $this->settings, array( $this, 'is_public' ) );

		return $settings;
	}

	/**
	 * Get all slugs of settings.
	 *
	 * @return array
	 */
	public function get_setting_slugs() {
		return array_keys( $this->get_settings() );
	}

	/**
	 * Get a setting setting.
	 *
	 * @param string $slug   The setting slug to get.
	 * @param bool   $create Optional flag to create if a setting is not found. Default: true.
	 *
	 * @return Setting|null
	 */
	public function get_setting( $slug, $create = true ) {
		$setting = null;
		$parts   = explode( ':', $slug );
		$slug    = sanitize_file_name( trim( array_shift( $parts ) ) );
		if ( $this->has_settings() ) {
			if ( $this->has_setting( $slug ) ) {
				if ( ! empty( $parts ) ) {
					return $this->settings[ $slug ]->get_setting( implode( ':', $parts ) );
				}

				return $this->settings[ $slug ];
			}
			$setting = $this->find_setting_recursively( $slug );
		}
		if ( is_null( $setting ) && true === $create ) {
			$setting = $this->create_setting( $slug, null, $this ); // return a dynamic setting.
		}

		return $setting;
	}

	/**
	 * Checks if a setting exists.
	 *
	 * @param string $slug Slug of setting to check.
	 *
	 * @return bool
	 */
	public function setting_exists( $slug ) {
		$exists  = false;
		$setting = $this->get_root_setting()->get_setting( $slug, false );
		if ( ! $this->is_private_slug( $slug ) && ! is_null( $setting ) && $setting->get_param( 'is_setup', false ) ) {
			$exists = true;
		}

		return $exists;
	}

	/**
	 * Find a setting from the root.
	 *
	 * @param string $slug The setting slug to get.
	 *
	 * @return Setting
	 */
	public function find_setting( $slug ) {
		if ( ! $this->is_root_setting() ) {
			return $this->get_root_setting()->find_setting( $slug );
		}
		$parts = explode( ':', $slug );
		$index = $this->get_param( 'index', array() );

		$slug = sanitize_file_name( trim( array_shift( $parts ) ) );
		if ( isset( $index[ $slug ] ) ) {
			if ( ! empty( $parts ) ) {
				return $index[ $slug ]->get_setting( implode( ':', $parts ) );
			}

			return $index[ $slug ];
		}

		return $this->get_setting( $slug );
	}

	/**
	 * Recursively find a setting.
	 *
	 * @param string $slug The setting slug to get.
	 *
	 * @return Setting|null
	 */
	protected function find_setting_recursively( $slug ) {
		$found = null;
		// loop through settings to find it.
		foreach ( $this->get_settings() as $sub_setting ) {
			if ( $sub_setting->has_settings() ) {
				if ( $sub_setting->has_setting( $slug ) ) {
					$found = $sub_setting->get_setting( $slug );
					break;
				}
				$found = $sub_setting->find_setting_recursively( $slug );
				if ( ! is_null( $found ) ) {
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Register a setting.
	 *
	 * @param array $params The setting params.
	 *
	 * @return $this
	 */
	public function setup_setting( array $params ) {
		if ( true === $this->get_param( 'is_setup' ) ) {
			return $this;
		}

		$default        = array(
			'priority' => 10,
		);
		$params         = wp_parse_args( $params, $default );
		$dynamic_params = array_filter( $params, array( $this, 'is_setting_param' ), ARRAY_FILTER_USE_KEY );
		foreach ( $params as $param => $value ) {

			if ( $this->is_setting_param( $param ) ) {
				continue;
			}
			// Set params.
			$this->set_param( $param, $value );
		}

		// Register dynamics.
		$this->register_dynamic_settings( $dynamic_params );

		// Load data.
		$this->load_value();

		// Mark as setup.
		$this->set_param( 'is_setup', true );

		return $this;
	}

	/**
	 * Register the setting with WordPress.
	 */
	protected function register_setting() {
		$option_group = $this->get_option_name();
		$root_group   = $this->get_root_setting()->get_option_name();
		if ( ! $this->is_root_setting() ) { // Dont save the core setting.
			$args = array(
				'type'              => 'array',
				'description'       => $this->get_param( 'description' ),
				'sanitize_callback' => array( $this, 'prepare_sanitizer' ),
				'show_in_rest'      => false,
			);
			register_setting( $option_group, $option_group, $args );
			add_filter( 'pre_update_site_option_' . $option_group, array( $this, 'set_notices' ), 10, 3 );
			add_filter( 'pre_update_option_' . $option_group, array( $this, 'set_notices' ), 10, 3 );
			$this->set_param( 'setting_registered', true );
		}
	}

	/**
	 * Prepare the setting option group to be sanitized by each component.
	 *
	 * @param array $data Array of values to sanitize.
	 *
	 * @return array
	 */
	public function prepare_sanitizer( $data ) {
		foreach ( $data as $slug => $value ) {
			$setting = $this->find_setting( $slug );

			$current_value = $setting->get_value();
			$new_value     = $setting->get_component()->sanitize_value( $value );
			/**
			 * Filter the value before saving a setting.
			 *
			 * @param mixed   $new_value     The new setting value.
			 * @param mixed   $current_value The setting current value.
			 * @param Setting $setting       The setting object.
			 */
			$new_value = apply_filters( "cloudinary_settings_save_setting_{$slug}", $new_value, $current_value, $setting );
			$new_value = apply_filters( 'cloudinary_settings_save_setting', $new_value, $current_value, $setting );

			$data[ $slug ] = $new_value;
		}

		return $data;
	}

	/**
	 * Set notices on successful update of settings.
	 *
	 * @param mixed  $value        The new Value.
	 * @param mixed  $old_value    The old value.
	 * @param string $setting_slug The setting key.
	 *
	 * @return mixed
	 */
	public function set_notices( $value, $old_value, $setting_slug ) {
		if ( $setting_slug !== $this->get_option_name() ) {
			return $value;
		}
		static $set_errors = array();
		if ( ! isset( $set_errors[ $setting_slug ] ) ) {
			if ( $value !== $old_value ) {
				if ( is_wp_error( $value ) ) {
					add_settings_error( $setting_slug, 'setting_notice', $value->get_error_message(), 'error' );
					$value = $old_value;
				} else {
					$notice = $this->get_param( 'success_notice', __( 'Settings updated successfully', 'cloudinary' ) );
					add_settings_error( $setting_slug, 'setting_notice', $notice, 'updated' );
				}
			}
			$set_errors[ $setting_slug ] = true;
		}

		return $value;
	}

	/**
	 * Register dynamic settings from params.
	 *
	 * @param array $params Array of params to create dynamic settings from.
	 */
	protected function register_dynamic_settings( $params ) {
		foreach ( $params as $param => $value ) {

			// Dynamic array based without a key slug.
			if ( is_int( $param ) && is_array( $value ) ) {
				$slug = isset( $value['slug'] ) ? $value['slug'] : $this->slug . '_' . $param;
				$this->create_setting( $slug, $value, $this );
				continue;
			}

			$callback = $this->get_setting_param_callback( $param );
			$callable = is_callable( $callback );
			if ( $callable ) {
				call_user_func( $callback, $value );
			}
		}
	}

	/**
	 * Get a callback to handle a dynamic child setting creation.
	 *
	 * @param string $param Param name to get callback for.
	 *
	 * @return string
	 */
	protected function get_setting_param_callback( $param ) {
		return $this->is_setting_param( $param ) ? $this->setting_params[ $param ] : '__return_null';
	}

	/**
	 * Checks if a param is a dynamic child setting array.s
	 *
	 * @param string $param Param to check for.
	 *
	 * @return bool
	 */
	protected function is_setting_param( $param ) {
		return isset( $this->setting_params[ $param ] ) || is_int( $param );
	}

	/**
	 * Create child tabs.
	 *
	 * @param array $tab_pages Array of tabs to create.
	 */
	public function add_tab_pages( $tab_pages ) {

		$this->set_param( 'has_tabs', true );
		foreach ( $tab_pages as $tab_page => $params ) {
			$params['type']        = 'page';
			$params['option_name'] = $this->build_option_name( sanitize_file_name( $tab_page ) );
			$this->create_setting( $tab_page, $params, $this );
		}

	}

	/**
	 * Add a page header.
	 *
	 * @param array $params The header config.
	 */
	public function add_header( $params ) {
		$this->add_param_setting( 'page_header', $params );
	}

	/**
	 * Add a page footer.
	 *
	 * @param array $params The footer config.
	 */
	public function add_footer( $params ) {
		$this->add_param_setting( 'page_footer', $params );
	}

	/**
	 * Add a setting as a param.
	 *
	 * @param string $param  The param slug to add.
	 * @param array  $params The setting parameters.
	 */
	public function add_param_setting( $param, $params ) {
		$params['type'] = $param;
		$slug           = $this->get_slug() . '_' . $param;
		$setting        = new Setting( $slug, $params, $this );
		$this->set_param( $param, $setting );
	}

	/**
	 * Create child pages on this setting.
	 *
	 * @param array $pages Page setting params.
	 */
	public function add_child_pages( $pages ) {

		foreach ( $pages as $slug => $params ) {
			$params['option_name'] = $this->build_option_name( sanitize_file_name( $slug ) );
			$params['type']        = 'page';
			$this->create_setting( $slug, $params, $this );
		}
	}

	/**
	 * Get a specific attribute from the setting.
	 *
	 * @param string $attribute_point The attribute point to get.
	 *
	 * @return mixed
	 */
	public function get_attributes( $attribute_point ) {
		$return     = array();
		$attributes = $this->get_param( 'attributes', array() );
		if ( isset( $attributes[ $attribute_point ] ) ) {
			$return = $attributes[ $attribute_point ];
		}

		return $return;
	}

	/**
	 * Setup the settings component.
	 *
	 * @return $this
	 */
	public function setup_component() {
		if ( ! $this->has_component() ) {
			$this->component = Component::init( $this );
			$this->component->setup();
		}

		return $this;
	}

	/**
	 *  Register Settings with WordPress.
	 */
	public function register_settings() {
		// Register WordPress Settings only if has a capture component.
		if ( $this->is_capture() ) {
			if ( $this->has_param( 'option_name' ) && $this->has_settings() ) {
				$this->register_setting();
			}
			foreach ( $this->get_settings() as $setting ) {
				$setting->register_settings();
			}
		}
	}

	/**
	 * Register sub settings.
	 *
	 * @param array       $settings The array of sub settings.
	 * @param null|string $type     Forced type of the child settings.
	 */
	public function add_child_settings( $settings, $type = null ) {
		foreach ( $settings as $setting => $params ) {
			if ( ! is_null( $type ) ) {
				if ( 'page' === $type ) {
					$params['option_name'] = $this->build_option_name( sanitize_file_name( $setting ) );
				}
				$params['type'] = $type;
			}
			$this->create_setting( $setting, $params, $this );
		}
	}

	/**
	 * Get the settings Component.
	 *
	 * @return \Cloudinary\UI\Component
	 */
	public function get_component() {
		if ( is_null( $this->component ) ) {
			$this->setup_component();
		}

		return $this->component;
	}

	/**
	 * Render the settings Component.
	 *
	 * @param bool $echo Flag to echo output.
	 *
	 * @return string
	 */
	public function render_component( $echo = false ) {
		return $this->get_component()->render( $echo );
	}

	/**
	 * Get the setting slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the option slug.
	 *
	 * @return string
	 */
	public function get_option_name() {
		$option_slug = null;
		if ( $this->has_param( 'option_name' ) ) {
			return $this->get_param( 'option_name' );
		} elseif ( $this->has_parent() ) {
			$option_slug = $this->get_option_parent()->get_option_name();
		}

		if ( is_null( $option_slug ) && $this->has_parent() && ! $this->get_parent()->has_parent() ) {
			// Set an auto option slug if the parent is the root item.
			$option_slug = $this->get_parent()->get_slug() . '_' . $this->get_slug();
		}

		return $option_slug;
	}

	/**
	 * Get the option slug.
	 *
	 * @return Setting
	 */
	public function get_option_parent() {

		if ( $this->has_param( 'option_name' ) ) {
			return $this;
		} elseif ( $this->has_parent() ) {
			return $this->get_parent()->get_option_parent();
		}

		return $this->get_root_setting();
	}

	/**
	 * Get values from option children.
	 *
	 * @return array
	 */
	public function get_option_children() {
		$value = array();
		if ( $this->has_settings() ) {
			foreach ( $this->get_settings() as $setting ) {
				if ( $setting->has_param( 'option_name' ) ) {
					$value[ $setting->get_slug() ] = $setting->get_value();
				} elseif ( $setting->has_settings() ) {
					$value = $setting->get_option_children();
				}
			}
		}

		return $value;
	}

	/**
	 * Build a new option name.
	 *
	 * @param string $slug The slug to build for.
	 *
	 * @return string
	 */
	protected function build_option_name( $slug ) {

		$option_path = array(
			$this->get_option_name(),
			$slug,
		);
		$option_path = array_unique( $option_path );

		return implode( '_', array_filter( $option_path ) );
	}

	/**
	 * Set the settings value.
	 *
	 * @param mixed $value The value to set.
	 *
	 * @return $this
	 */
	public function set_value( $value ) {
		$this->value = $value;

		return $this;
	}

	/**
	 * Save the value of a setting to the first lower options slug.
	 *
	 * @param mixed|null $value    Optional value to set and save. Else save the current value.
	 * @param bool       $autoload Flag to set this value to autoload or not.
	 *
	 * @return bool
	 */
	public function save_value( $value = null, $autoload = false ) {
		if ( $value ) {
			$this->set_value( $value );
		}
		if ( $this->has_param( 'option_name' ) ) {
			$slug = $this->get_option_name();

			return update_option( $slug, $this->get_value(), $autoload );
		} elseif ( $this->has_parent() ) {
			$parent                     = $this->get_parent();
			$value                      = (array) $parent->get_value();
			$value[ $this->get_slug() ] = $this->get_value();
			$parent->set_value( $value );

			return $parent->save_value();
		}

		return false;
	}

	/**
	 * Load the value of the setting.
	 */
	public function load_value() {
		if ( ! $this->is_root_setting() && $this->has_param( 'option_name' ) ) {
			$root          = $this->get_root_setting();
			$root_value    = (array) $root->get_value();
			$default_value = $this->get_param( 'default', null );
			$option        = $this->get_param( 'option_name' );
			$data          = get_option( $option, $default_value );
			// If has settings, get value defaults and merge with found data.
			if ( $this->has_settings() ) {
				$default_val = $this->get_value();
				$data        = array_filter( wp_parse_args( $data, $default_val ) );
			}
			$root_value[ $this->get_slug() ] = $data;
			$root->set_value( $root_value );
		}
	}

	/**
	 * Check if setting has a value.
	 *
	 * @return bool
	 */
	public function has_value() {
		return ! is_null( $this->value );
	}

	/**
	 * Get the value for a setting, or a related setting.
	 *
	 * @param string|null $slug Optional setting slug to get value for.
	 *
	 * @return mixed
	 */
	public function get_value( $slug = null ) {
		if ( is_string( $slug ) && $slug !== $this->get_slug() ) {
			if ( $this->get_setting( $slug, false ) ) {
				return $this->get_setting( $slug )->get_value();
			}
			if ( $this->is_root_setting() || $this->is_option_parent() && ! $this->get_setting( $slug, false ) ) {
				return $this->find_setting( $slug )->get_value();
			}
		}
		if ( is_null( $this->value ) ) {
			if ( $this->is_option_parent() ) {
				$default = $this->get_param( 'default' );
				$option  = $this->get_param( 'option_name' );
				$data    = get_option( $option, $default );
				if ( $this->has_settings() ) {
					$default_children = (array) $this->get_default_values_recursive();
					$default_value    = wp_parse_args( $default_children, (array) $default );
					$data             = wp_parse_args( (array) $data, $default_value );
				}
				$this->value = $data;
			} elseif ( $this->is_page() ) {
				// Find the option parent below.
				$this->value = $this->get_option_children();
			} else {
				$values   = $this->get_option_parent()->get_value();
				$own_slug = $this->get_slug();
				if ( empty( $values[ $own_slug ] ) || $this->has_settings() ) {
					$default_children    = (array) $this->get_default_values_recursive();
					$values[ $own_slug ] = array_intersect_key( $values, $default_children );
				}
				$this->value = $values[ $own_slug ];
			}
		}
		$return      = $this->value;
		$return_slug = $this->get_slug();
		if ( $this->is_option_parent() && isset( $slug ) && isset( $this->value[ $slug ] ) ) {
			$return      = $this->value[ $slug ];
			$return_slug = $slug;
		}

		/**
		 * Filter the setting value.
		 *
		 * @param mixed  $value The setting value.
		 * @param string $slug  The setting slug.
		 */
		return apply_filters( 'cloudinary_setting_get_value', $return, $return_slug );
	}

	/**
	 * Get the values of the settings recursively.
	 *
	 * @return array
	 */
	protected function get_default_values_recursive() {
		$value = array();
		foreach ( $this->get_settings() as $setting ) {
			if ( $setting->has_settings() && ! $setting->has_param( 'setting_registered' ) ) {
				$value = array_merge( $value, $setting->get_default_values_recursive() );
				if ( $setting->is_option_parent() ) {
					$value = array(
						$setting->get_slug() => $value,
					);
				}
			} elseif ( $setting->is_capture() ) {
				$value[ $setting->get_slug() ] = $setting->get_param( 'default' );
			}
		}

		return $value;
	}

	/**
	 * Create a setting.
	 *
	 * @param string  $slug   The setting slag to add.
	 * @param array   $params Settings params.
	 * @param Setting $parent The optional parent to add new setting to.
	 *
	 * @return Setting
	 */
	public function create_setting( $slug, $params = array(), $parent = null ) {
		$slug = sanitize_file_name( $slug );
		if ( $this->setting_exists( $slug ) ) {
			// translators: Placeholder is the slug.
			$message = sprintf( __( 'Duplicate setting slug %s. This setting will not be usable.', 'cloudinary' ), $slug );
			$this->add_admin_notice( 'duplicate_setting', $message, 'warning' );
		}
		$new_setting = new Setting( $slug, $params, $this->root_setting );
		$new_setting->set_value( null ); // Set value to null.
		if ( $parent ) {
			$parent->add_setting( $new_setting );
		}

		return $new_setting;
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
	public function add_admin_notice( $error_code, $error_message, $type = 'error', $dismissible = false, $duration = 0, $icon = null ) {

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

		$option_parent  = $this->get_option_parent();
		$option_notices = $option_parent->get_setting( '_notices' );
		$notices        = $option_notices->get_param( '_notices', array() );

		// Set new notice.
		$params                  = array(
			'type'     => 'notice',
			'level'    => $type,
			'message'  => $error_message,
			'code'     => $error_code,
			'dismiss'  => $dismissible,
			'duration' => $duration,
			'icon'     => $icon,
		);
		$notice_slug             = md5( wp_json_encode( $params ) );
		$notices[ $notice_slug ] = $this->create_setting( $notice_slug, $params );
		$option_notices->set_param( '_notices', $notices );
	}

	/**
	 * Get admin notices.
	 *
	 * @return Setting[]
	 */
	public function get_admin_notices() {
		$option_parent   = $this->get_option_parent();
		$setting_notices = get_settings_errors( $option_parent->get_option_name() );
		foreach ( $setting_notices as $key => $notice ) {
			$option_parent->add_admin_notice( $notice['code'], $notice['message'], $notice['type'], true );
		}

		return $option_parent->get_setting( '_notices' )->get_param( '_notices' );
	}

	/**
	 * Add a setting to setting, if it already exists, move setting.
	 *
	 * @param Setting $setting The setting to add.
	 *
	 * @return Setting
	 */
	public function add_setting( $setting ) {

		$setting->set_parent( $this );

		/**
		 * PHP array sorting methods vary between versions. Sorting ASC, where the value is equal, is sometimes randomized.
		 * Since multiple priority 10 items are equal, their placement may not be as they are added.
		 * I.E an item added as 10 while other 10 priorities exist, might get added to the top rather than the expected bottom.
		 * Here, we are determining the index based on the next highest priority, guaranteeing correct placement.
		 */
		$index = $this->get_position_index( $setting->get_param( 'priority', 10 ) );

		$new_setting = array(
			$setting->get_slug() => $setting,
		);
		// Splice in setting based on the position index.
		$this->settings = array_slice( $this->settings, 0, $index, true ) + $new_setting + array_slice( $this->settings, $index, null, true );

		return $setting;
	}

	/**
	 * Get the index where the new setting should go based on the priority.
	 * The position will be the just after the same priority, but before any priority that's higher.
	 * This maintains the first-come-first serve for like priorities.
	 *
	 * @param int $priority The priority to get the index for.
	 *
	 * @return int
	 */
	protected function get_position_index( $priority ) {
		$index = 0;
		foreach ( $this->settings as $setting_check ) {
			$check_priority = $setting_check->get_param( 'priority', 10 );
			if ( $priority < $check_priority ) {
				break;
			}
			$index ++;
		}

		return $index;
	}

	/**
	 * Set a settings parent.
	 *
	 * @param Setting $parent The parent to set as.
	 *
	 * @return $this
	 */
	public function set_parent( $parent ) {

		// Remove old parent.
		if ( ! $this->is_root_setting() && $this->has_parent() && $this->get_parent() !== $parent ) {
			$this->get_parent()->remove_setting( $this->get_slug() );
		}
		$this->parent = $parent;

		return $this;
	}

	/**
	 * Remove a setting.
	 *
	 * @param string $slug The setting slug to remove.
	 */
	public function remove_setting( $slug ) {
		if ( $this->has_setting( $slug ) ) {
			unset( $this->settings[ $slug ] );
		}
	}

	/**
	 * Get the root setting.
	 *
	 * @return Setting
	 */
	public function get_root_setting() {
		if ( ! is_null( $this->root_setting ) ) {
			return $this->root_setting;
		}
		$parent = $this;
		if ( $this->has_parent() && ! $this->get_parent() instanceof Settings ) {
			$parent = $this->get_parent()->get_root_setting();
		}

		return $parent;
	}

	/**
	 * Set the root setting.
	 *
	 * @param Setting $setting The root setting to set.
	 *
	 * @return $this
	 */
	public function set_root_setting( $setting ) {
		$this->root_setting = $setting;

		return $this;
	}

	/**
	 * Check if this is the root setting.
	 *
	 * @return bool
	 */
	public function is_root_setting() {
		return $this === $this->get_root_setting();
	}

	/**
	 * Check if this is the option parent setting.
	 *
	 * @return bool
	 */
	public function is_option_parent() {
		return $this === $this->get_option_parent();
	}

	/**
	 * Check if setting is a page.
	 *
	 * @return bool
	 */
	public function is_page() {
		return 'page' === $this->get_param( 'type' );
	}

	/**
	 * Check if the setting has a capture component recursively.
	 *
	 * @return bool
	 */
	public function is_capture() {
		$capture = $this->get_component()->capture;
		if ( false === $capture && $this->has_settings() ) {
			foreach ( $this->get_settings() as $setting ) {
				if ( $setting->is_capture() ) {
					$capture = true;
					break;
				}
			}
		}

		return $capture;
	}

	/**
	 * Checks if the setting has a component.
	 *
	 * @return bool
	 */
	public function has_component() {
		return ! is_null( $this->component );
	}

	/**
	 * Check if the slug is private. i.e starts with _.
	 *
	 * @param string $slug the slug to check.
	 *
	 * @return bool
	 */
	protected function is_private_slug( $slug ) {
		return '_' === substr( $slug, 0, 1 );
	}

	/**
	 * Check if a setting is public.
	 */
	protected function is_public() {
		return ! $this->is_private_slug( $this->get_slug() );
	}

	/**
	 * Get the submitted value.
	 *
	 * @param null|mixed $default The default value if submission is not available.
	 *
	 * @return mixed|null
	 */
	public function get_submitted_value( $default = null ) {

		$input_slug = $this->get_option_name();
		$args       = array(
			$input_slug => array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_REQUIRE_ARRAY,
			),
		);
		$input      = filter_input_array( INPUT_POST, $args );

		return isset( $input[ $input_slug ][ $this->get_slug() ] ) ? $input[ $input_slug ][ $this->get_slug() ] : $default;
	}

}
