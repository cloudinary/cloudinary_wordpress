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
	 * Holds the storage object.
	 *
	 * @var Storage
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
				$slug             = $initial . $this->separator . $param['slug'];
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

		$storage       = $this->get_param( 'storage' );
		$this->storage = new $storage( $this->slug );
		$data          = wp_parse_args( $this->storage->get(), $this->get_value() );
		$this->set_param( '@data', $data );
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
		$this->set_param( '@primaries' . $this->separator . $parts[0] );
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
		$current_value = $this->get_param( '@data' . $this->separator . $slug, $default );
		$slug_parts    = explode( $this->separator, $slug );
		array_pop( $slug_parts );
		$parent = implode( $this->separator, $slug_parts );
		if ( ! isset( $this->settings[ $slug ] ) ) {
			$setting = $this->create_child( $slug, $params );
			$setting->set_type( gettype( $default ) );
			if ( ! empty( $parent ) ) {
				$setting->set_parent( $parent );
			}
			$this->settings[ $slug ] = $setting;
		}
		$this->set_param( '@data' . $this->separator . $slug, $current_value );

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
		foreach ( $this->flatten() as $key ) {
			$parts = explode( $this->separator, $key );
			$index = array_search( $slug, $parts, true );
			if ( false !== $index ) {
				$setting_slug             = implode( $this->separator, array_splice( $parts, 0, $index + 1 ) );
				$results[ $setting_slug ] = $this->settings[ $setting_slug ];
			}
		}
		if ( 1 === count( $results ) ) {
			$results = array_shift( $results );
		}

		return $results;
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

		if ( ! $found ) {
			$found = $this->find_setting( $slug );
		}
		if ( empty( $found ) ) {
			$found = null;
			if ( true === $create ) {
				$found = $this->add( $slug, null );
			}
		}

		return $found;
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
	 * @return mixed
	 */
	public function get_pending() {
		return $this->get_param( '@save' );
	}

	/**
	 * Save the settings values to the storage.
	 *
	 * @return bool|\WP_Error
	 */
	public function save() {

		$pending   = $this->get_pending();
		$slug      = $this->slug;
		$new_value = array();
		foreach ( $pending as $slug => $new_value ) {
			$setting       = $this->get_setting( $slug );
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
			$new_value = apply_filters( "cloudinary_pre_save_settings_{$slug}", $new_value, $current_value, $setting );
			$new_value = apply_filters( 'cloudinary_pre_save_settings', $new_value, $current_value, $setting );
			if ( is_wp_error( $new_value ) ) {
				return $new_value;
			}

			$this->set_value( $slug, $new_value );
		}

		$this->remove_param( '@save' );
		$this->storage->set( $this->get_value() );

		$saved = $this->storage->save();
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

}
