<?php
/**
 * Cloudinary Settings represents a collection of settings.
 *
 * @package   Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Settings\Setting;
use Cloudinary\Traits\Params_Trait;
use Cloudinary\Settings\Storage\Storage;

/**
 * Class Settings
 *
 * @package Cloudinary
 */
class Settings {

	use Params_Trait;

	/**
	 * Holds the child settings.
	 *
	 * @var Setting[]
	 */
	protected $settings = array();

	/**
	 * Holds the storage objects.
	 *
	 * @var Storage[]
	 */
	protected $storage;

	/**
	 * Holds the slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Setting constructor.
	 *
	 * @param string $slug   The slug/name of the settings set.
	 * @param array  $params Optional params for the setting.
	 */
	public function __construct( $slug, $params = array() ) {

		$this->slug = $slug;
		if ( isset( $params['storage'] ) ) {
			// Test if shorthand was used.
			if ( class_exists( 'Cloudinary\\Settings\\Storage\\' . $params['storage'] ) ) {
				$params['storage'] = 'Cloudinary\\Settings\\Storage\\' . $params['storage'];
			}
		} else {
			// Default.
			$params['storage'] = 'Cloudinary\\Settings\\Storage\\Options';
		}

		// Set the storage.
		$this->set_param( 'storage', $params['storage'] );

		if ( ! empty( $params['settings'] ) ) {

			foreach ( $params['settings'] as $key => &$param ) {
				$param = $this->get_default_settings( $param, $key );
			}

			$this->set_params( $params );
		}

		$this->init();
	}

	/**
	 * Get the default settings based on the Params.
	 *
	 * @param array       $params  The params to get defaults from.
	 * @param null|string $initial The initial slug to be pre-pended..
	 *
	 * @return array
	 */
	public function get_default_settings( $params, $initial = null ) {

		if ( isset( $params['slug'] ) ) {
			$initial .= $this->separator . $params['slug'];
		}
		if ( isset( $params['option_name'] ) ) {
			$this->set_alias( $initial, $params['option_name'] );
		}

		foreach ( $params as $key => &$param ) {
			if ( ! is_numeric( $key ) && 'settings' !== $key ) {
				continue;
			}

			if ( isset( $param[0] ) || isset( $param['settings'] ) ) {
				$param = $this->get_default_settings( $param, $initial );
			} elseif ( isset( $param['slug'] ) ) {

				$default = '';
				if ( isset( $param['default'] ) ) {
					$default = $param['default'];
				}
				$slug = $initial . $this->separator . $param['slug'];
				if ( isset( $param['option_name'] ) ) {
					$this->set_alias( $slug, $param['option_name'] );
				}
				$param['setting'] = $this->add( $slug, $default, $param );
			}
		}

		return $params;
	}

	/**
	 * Flatten a setting into a string.
	 *
	 * @return array
	 */
	public function flatten() {
		return array_keys( $this->settings );
	}

	/**
	 * Magic method to get a chainable setting.
	 *
	 * @param string $name The name of the setting to get dynamically.
	 *
	 * @return Setting|null
	 */
	public function __get( $name ) {

		if ( ! isset( $this->settings[ $name ] ) ) {
			$this->settings[ $name ] = $this->add( $name );
		}

		return $this->settings[ $name ];
	}

	/**
	 * Remove a setting.
	 *
	 * @param string $slug The setting to remove.
	 */
	public function delete( $slug ) {

		$this->remove_param( '@data' . $this->separator . $slug );
	}

	/**
	 * Init the settings.
	 */
	protected function init() {

	}

	/**
	 * Register a settings storage point.
	 *
	 * @param string $slug The slug (option-name) to store under.
	 */
	protected function register_storage( $slug ) {
		$storage                = $this->get_param( 'storage' );
		$storage_slug           = $this->get_alias( $slug );
		$this->storage[ $slug ] = new $storage( $storage_slug );
		$this->set_param( '@data' . $this->separator . $slug, $this->storage[ $slug ]->get() );
	}

	/**
	 * Add a setting.
	 *
	 * @param string $slug    The setting slug.
	 * @param mixed  $default The default value.
	 * @param array  $params  The params.
	 *
	 * @return Setting|\WP_Error
	 */
	public function add( $slug, $default = array(), $params = array() ) {

		$parts      = explode( $this->separator, $slug );
		$path       = array();
		$value      = array();
		$last_child = null;
		if ( ! empty( $params['option_name'] ) ) {
			$this->set_alias( $parts[0], $params['option_name'] );
		}
		while ( ! empty( $parts ) ) {
			$path[] = array_shift( $parts );
			if ( empty( $parts ) ) {
				$value = $default;
			}

			$name  = implode( $this->separator, $path );
			$child = $this->register( $name, $value, $params );
			if ( is_wp_error( $child ) ) {
				return $child;
			}

			if ( $last_child ) {
				$last_child->add( $child );
			}
			$last_child = $child;
		}

		return $this->settings[ $slug ];
	}

	/**
	 * Sanitize a slug to be a clean alias.
	 *
	 * @param string $slug SLug to sanitize.
	 *
	 * @return string
	 */
	protected function sanitize_alias( $slug ) {
		return md5( $slug );
	}

	/**
	 * Set an alias for a slug.
	 *
	 * @param string $slug  The original slug.
	 * @param string $alias The alias slug.
	 */
	protected function set_alias( $slug, $alias ) {
		$this->set_param( '@alias' . $this->separator . $this->sanitize_alias( $slug ), $alias );
	}

	/**
	 * Get an alias for a slug.
	 *
	 * @param string $slug The original slug.
	 *
	 * @return string
	 */
	protected function get_alias( $slug ) {
		return $this->get_param( '@alias' . $this->separator . $this->sanitize_alias( $slug ), $this->get_slug() . '_' . $slug );
	}

	/**
	 * Checks if the slug has an alias.
	 *
	 * @param string $slug Slug to check.
	 *
	 * @return bool
	 */
	protected function has_alias( $slug ) {
		return $this->has_param( '@alias' . $this->separator . $this->sanitize_alias( $slug ) );
	}

	/**
	 * Register a new setting with internals.
	 *
	 * @param string $slug    The setting slug.
	 * @param mixed  $default The default value.
	 * @param array  $params  The params.
	 *
	 * @return mixed|Setting|\WP_Error
	 */
	protected function register( $slug, $default, $params ) {

		if ( isset( $this->settings[ $slug ] ) ) {
			$exists = $this->settings[ $slug ];
			if ( $exists->get_type() !== 'array' ) {
				return new \WP_Error();
			}
		}
		$slug_parts = explode( $this->separator, $slug );
		array_pop( $slug_parts );
		$parent = implode( $this->separator, $slug_parts );
		if ( ! isset( $this->settings[ $slug ] ) ) {
			if ( empty( $parent ) || $this->has_alias( $slug ) ) {
				$this->register_storage( $slug );
			}
			$setting = $this->create_child( $slug, $params );
			$setting->set_type( gettype( $default ) );
			if ( ! empty( $parent ) ) {
				$setting->set_parent( $parent );
			}
			$this->settings[ $slug ] = $setting;
		}

		if ( ! $this->has_param( '@data' . $this->separator . $slug ) ) {
			$this->set_param( '@data' . $this->separator . $slug, $default );
		}

		return $this->settings[ $slug ];
	}

	/**
	 * Create a new child.
	 *
	 * @param string $slug   The slug.
	 * @param array  $params Optional Params.
	 *
	 * @return Setting
	 */
	protected function create_child( $slug, $params ) {

		return new Settings\Setting( $slug, $this, $params );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $slug The slug to get.
	 *
	 * @return Setting|null
	 */
	public function get_value( $slug = null ) {

		$key = '@data';
		if ( $slug ) {
			if ( ! isset( $this->settings[ $slug ] ) ) {
				$setting = $this->find_setting( $slug );
				if ( $setting ) {
					$slug = $setting->get_slug();
				}
			}
			$key .= $this->separator . $slug;
		}

		$value = $this->get_param( $key );
		if ( ! $slug ) {
			$slug = $this->slug;
		}
		$base_slug = explode( $this->separator, $slug );
		$base_slug = array_pop( $base_slug );

		/**
		 * Filter the setting value.
		 *
		 * @hook cloudinary_setting_get_value
		 *
		 * @param $value {mixed} The setting value.
		 * @param $slug  {string}  The setting slug.
		 */
		return apply_filters( 'cloudinary_setting_get_value', $value, $slug );
	}

	/**
	 * Get the slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Find a Setting.
	 *
	 * @param string $slug The setting slug.
	 *
	 * @return self|Setting
	 */
	public function find_setting( $slug ) {

		$results = array();
		$setting = null;
		foreach ( $this->flatten() as $key ) {
			$parts = explode( $this->separator, $key );
			$index = array_search( $slug, $parts, true );
			if ( false !== $index ) {
				$setting_slug             = implode( $this->separator, array_splice( $parts, 0, $index + 1 ) );
				$results[ $setting_slug ] = $this->settings[ $setting_slug ];
			}
		}
		if ( ! empty( $results ) ) {
			$setting = array_shift( $results );
		}

		return $setting ? $setting : $this->add( '@dynamic' . $this->separator . $slug );
	}

	/**
	 * Get a setting.
	 *
	 * @param string $slug   The slug to get.
	 * @param bool   $create Flag to create setting if not found.
	 *
	 * @return Setting|null
	 */
	public function get_setting( $slug, $create = true ) {
		$found = null;
		if ( isset( $this->settings[ $slug ] ) ) {
			$found = $this->settings[ $slug ];
		}

		if ( empty( $found ) ) {
			$found = $this->find_setting( $slug );
			if ( false === $create && '@' === $found->get_slug()[0] ) {
				$found = null;
			}
		}

		return $found;
	}

	/**
	 * Get the root setting.
	 *
	 * @return self
	 */
	public function get_root_setting() {
		return $this;
	}

	/**
	 * Set a setting's value.
	 *
	 * @param string $slug  The slag of the setting to set.
	 * @param mixed  $value The value to set.
	 *
	 * @return bool
	 */
	public function set_value( $slug, $value ) {
		$set = false;
		if ( isset( $this->settings[ $slug ] ) ) {
			$current = $this->get_param( '@data' . $this->separator . $slug );
			if ( $current !== $value ) {
				$this->set_param( '@data' . $this->separator . $slug, $value );
				$set = true;
			}
		}

		return $set;
	}

	/**
	 * Pend a setting's value, for prep to update.
	 *
	 * @param string $slug  The slag of the setting to pend set.
	 * @param mixed  $value The value to set.
	 */
	public function set_pending( $slug, $value ) {
		$this->set_param( '@save' . $this->separator . $slug, $value );
	}

	/**
	 * Get a setting's pending value for update.
	 *
	 * @param string $slug The slug to get the pending data for.
	 *
	 * @return mixed
	 */
	public function get_pending( $slug ) {
		return $this->get_param( '@save' . $this->separator . $slug );
	}

	/**
	 * Save the settings values to the storage.
	 *
	 * @param string $slug The slug to save.
	 *
	 * @return bool|\WP_Error
	 */
	public function save( $slug ) {

		$pending = $this->get_pending( $slug );

		$this->remove_param( '@save' );

		$new_value = array();
		foreach ( $pending as $child_slug => &$new_value ) {
			$setting       = $this->get_setting( $child_slug );
			$current_value = $setting->get_value();
			/**
			 * Pre-Filter the value before saving a setting.
			 *
			 * @hook   cloudinary_pre_save_settings_{$slug}
			 * @hook   cloudinary_pre_save_settings
			 * @since  2.7.6
			 *
			 * @param $new_value     {int}     The new setting value.
			 * @param $current_value {string}  The setting current value.
			 * @param $setting       {Setting} The setting object.
			 *
			 * @return {mixed}
			 */
			$new_value = apply_filters( "cloudinary_pre_save_settings_{$child_slug}", $new_value, $current_value, $setting );
			$new_value = apply_filters( 'cloudinary_pre_save_settings', $new_value, $current_value, $setting );
			if ( is_wp_error( $new_value ) ) {
				return $new_value;
			}
		}

		$storage = $this->get_storage( $slug );
		$storage->set( $pending );

		$saved = $storage->save();
		if ( $saved ) {
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
	}

	/**
	 * Get the storage object for a slug.
	 *
	 * @param string $slug The slug to get storage object for.
	 *
	 * @return Storage
	 */
	protected function get_storage( $slug ) {
		if ( isset( $this->storage[ $slug ] ) ) {
			return $this->storage[ $slug ];
		}
		$setting = $this->get_setting( $slug );

		return $this->get_storage( $setting->get_parent()->get_slug() );
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

		$notices = $this->get_param( '@notices', array() );

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
		$notices[ $notice_slug ] = $params;
		$this->set_param( '@notices', $notices );
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
