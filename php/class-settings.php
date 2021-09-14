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
	 * @var Storage
	 */
	protected $storage;

	/**
	 * Holds the list of storage keys.
	 *
	 * @var array
	 */
	protected $storage_keys = array();

	/**
	 * Holds the slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Holds the keys for meta storage.
	 *
	 * @var array
	 */
	const META_KEYS = array(
		'pending' => '@pending',
		'data'    => '@data',
		'storage' => 'storage_path',
	);

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
		$this->init();

		// Build the settings from params.
		if ( ! empty( $params['settings'] ) ) {

			foreach ( $params['settings'] as $key => &$param ) {
				$param['type'] = 'page';// Hard set root items as pages.
				$param         = $this->get_default_settings( $param, $key, $key );
			}

			$this->set_params( $params );
		}
	}

	/**
	 * Get the default settings based on the Params.
	 *
	 * @param array       $params  The params to get defaults from.
	 * @param null|string $initial The initial slug to be pre-pended..
	 * @param string|bool $root    Flag to indicate we're ata root item for storage.
	 *
	 * @return array
	 */
	public function get_default_settings( $params, $initial = null, $root = false ) {
		static $storage_name;

		// Reset the storage name.
		if ( ! empty( $root ) ) {
			$storage_name = $initial;
		}

		// If we have an option_name, lets set the storage name to that.
		if ( ! empty( $params['option_name'] ) ) {
			$storage_name = $params['option_name'];
		}

		if ( isset( $params['slug'] ) ) {
			$initial .= $this->separator . $params['slug'];
		}

		foreach ( $params as $key => &$param ) {
			if ( ! is_numeric( $key ) && 'settings' !== $key ) {
				continue;
			}
			if ( ! isset( $param['type'] ) ) {
				$param['type'] = 'tag'; // Set the default.
			}
			if ( isset( $param[0] ) || isset( $param['settings'] ) ) {
				$param = $this->get_default_settings( $param, $initial );
			} elseif ( isset( $param['slug'] ) ) {

				$default = '';
				if ( isset( $param['default'] ) ) {
					$default = $param['default'];
				}

				// Set the slug path.
				$slug          = $initial . $this->separator . $param['slug'];
				$storage_parts = explode( $this->separator, $slug, 2 );
				// Append the slug to the storage path.
				$param[ self::META_KEYS['storage'] ] = $storage_name . $this->separator . $storage_parts[1];
				$param['setting']                    = $this->add( $slug, $default, $param );
			}
		}

		return $params;
	}

	/**
	 * Magic method to get a chainable setting.
	 *
	 * @param string $name The name of the setting to get dynamically.
	 *
	 * @return Setting|null
	 */
	public function __get( $name ) {
		$setting = null;
		if ( isset( $this->settings[ $name ] ) ) {
			$setting = $this->settings[ $name ];
		}
		if ( ! $setting ) {
			$setting = $this->find_setting( $name );
		}

		return $setting;
	}

	/**
	 * Remove a setting.
	 *
	 * @param string $slug The setting to remove.
	 *
	 * @return bool
	 */
	public function delete( $slug ) {
		$this->remove_param( self::META_KEYS['data'] . $this->separator . $slug );

		return $this->storage->delete( $slug );
	}

	/**
	 * Init the settings.
	 */
	protected function init() {
		$storage       = $this->get_param( 'storage' );
		$this->storage = new $storage( $this->slug );
	}

	/**
	 * Register a settings storage point.
	 *
	 * @param string $slug The key (option-name) to register the storage as.
	 */
	protected function register_storage( $slug ) {
		// Get the root key.
		$slug = explode( $this->separator, $slug, 2 )[0];
		if ( ! isset( $this->storage_keys[ $slug ] ) ) {
			$data     = $this->storage->get( $slug );
			$defaults = $this->get_param( self::META_KEYS['data'] . $this->separator . $slug, null );
			if ( ! empty( $data ) ) {
				if ( ! empty( $defaults ) && is_array( $data ) ) {
					$data = wp_parse_args( $data, (array) $defaults );
				}
				$this->set_param( self::META_KEYS['data'] . $this->separator . $slug, $data );
			}
			$this->storage_keys[ $slug ] = $data;
		}
	}

	/**
	 * Get the storage key.
	 *
	 * @param string $slug The slug to get.
	 * @param string $type The setting type.
	 *
	 * @return string
	 */
	public function get_storage_key( $slug, $type = null ) {
		if ( null == $type ) {
			$type = $this->get_setting( $slug )->get_param( 'type' );
		}
		$prefix = null;
		if ( 'data' !== $type ) {
			// Data types are stored and retrieved without prefixes so we can handle external or legacy options.
			$prefix = $this->slug . '_';
		}

		return $prefix . $slug;
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
		$default_params = array(
			'type'                     => 'tag',
			self::META_KEYS['storage'] => $slug,
		);
		$params         = wp_parse_args( $params, $default_params );
		$parts          = explode( $this->separator, trim( $slug, $this->separator ) );
		$storage_paths  = explode( $this->separator, trim( $params[ self::META_KEYS['storage'] ], $this->separator ) );
		$path           = array();
		$value          = array();
		$storage        = array();
		$last_child     = null;

		// If we have an option_name, in a single field, lets set the storage name for that item only.
		if ( ! empty( $params['option_name'] ) ) {
			array_pop( $storage_paths ); // Knockoff the end slug.
			$storage_key      = $this->get_storage_key( $params['option_name'], $params['type'] );
			$storage_paths[0] = $storage_key; // Set the base storage.
		}

		while ( ! empty( $parts ) ) {
			$path[] = array_shift( $parts );
			if ( ! empty( $storage_paths ) ) {
				$storage[] = array_shift( $storage_paths );
			}
			if ( empty( $parts ) ) {
				$value = $default;
			}
			$name                                 = implode( $this->separator, $path );
			$params[ self::META_KEYS['storage'] ] = implode( $this->separator, $storage );
			$child                                = $this->register( $name, $value, $params );
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
	 * @return mixed|Setting
	 */
	protected function register( $slug, $default, $params ) {

		if ( isset( $this->settings[ $slug ] ) ) {
			return $this->settings[ $slug ];
		}

		$slug_parts   = explode( $this->separator, $slug );
		$params['id'] = array_pop( $slug_parts );
		$parent       = implode( $this->separator, $slug_parts );

		$setting = $this->create_child( $slug, $params );
		$setting->set_type( gettype( $default ) );
		if ( ! empty( $parent ) ) {
			$setting->set_parent( $parent );
		}
		$this->settings[ $slug ] = $setting;

		// Register storage.
		$this->register_storage( $params[ self::META_KEYS['storage'] ] );

		if ( ! empty( $default ) && ! $this->has_param( self::META_KEYS['data'] . $this->separator . $params[ self::META_KEYS['storage'] ] ) ) {
			$this->set_param( self::META_KEYS['data'] . $this->separator . $params[ self::META_KEYS['storage'] ], $default );
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
	 * @return mixed
	 */
	public function get_value( $slug = null ) {
		$key = self::META_KEYS['data'];
		if ( $slug ) {
			$setting      = $this->get_setting( $slug );
			$storage_path = $setting->get_param( self::META_KEYS['storage'], $setting->get_slug() );
			$key         .= $this->separator . $storage_path;
		}
		if ( ! isset( $value ) ) {
			$value = $this->get_param( $key );
		}
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
	 * @param string $slug   The setting slug.
	 * @param bool   $create Flag to create a setting if not found.
	 *
	 * @return self|Setting
	 */
	public function find_setting( $slug, $create = true ) {
		$setting = null;
		foreach ( array_keys( $this->settings ) as $key ) {
			if ( false !== strpos( $key, $slug ) ) {
				$maybe = strstr( $key, $slug, true ) . $slug;
				if ( isset( $this->settings[ $maybe ] ) ) {
					$setting = $this->settings[ $maybe ];
					break;
				}
			}
		}

		if ( ! $setting && true === $create ) {
			$setting = $this->add( $slug, null, array( 'type' => 'dynamic' ) );
		}

		return $setting;
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
			if ( false === $create && 'dynamic' === $found->get_param( 'type' ) ) {
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
			$key     = $this->settings[ $slug ]->get_param( self::META_KEYS['storage'] );
			$current = $this->get_param( self::META_KEYS['data'] . $this->separator . $key );
			if ( $current !== $value ) {
				$this->set_param( self::META_KEYS['data'] . $this->separator . $key, $value );
				$set = true;
			}
		} else {
			$found = $this->find_setting( $slug );
			if ( $found ) {
				$set = $this->set_value( $found->get_slug(), $value );
			}
		}

		return $set;
	}

	/**
	 * Pend a setting's value, for prep to update.
	 *
	 * @param string $slug          The slag of the setting to pend set.
	 * @param mixed  $new_value     The value to set.
	 * @param mixed  $current_value The optional current value to compare.
	 *
	 * @return bool|\WP_Error
	 */
	public function set_pending( $slug, $new_value, $current_value = null ) {

		$setting = $this->get_setting( $slug );
		/**
		 * Pre-Filter the value before saving a setting.
		 *
		 * @hook   cloudinary_settings_save_setting_{$slug}
		 * @hook   cloudinary_settings_save_setting
		 * @since  2.7.6
		 *
		 * @param $new_value     {int}     The new setting value.
		 * @param $current_value {string}  The setting current value.
		 * @param $setting       {Setting} The setting object.
		 *
		 * @return {mixed}
		 */
		$new_value = apply_filters( "cloudinary_settings_save_setting_{$slug}", $new_value, $current_value, $setting );
		$new_value = apply_filters( 'cloudinary_settings_save_setting', $new_value, $current_value, $setting );
		if ( is_wp_error( $new_value ) ) {
			return $new_value;
		}
		$path  = $setting->get_param( self::META_KEYS['storage'] );
		$store = explode( $this->separator, $path, 2 )[0];
		if ( ! $this->has_param( self::META_KEYS['pending'] . $this->separator . $store ) ) {
			$parent = $this->get_param( self::META_KEYS['data'] . $this->separator . $store );
			$this->set_param( self::META_KEYS['pending'] . $this->separator . $store, $parent );
		}
		$this->set_param( self::META_KEYS['pending'] . $this->separator . $path, $new_value );

		return true;
	}

	/**
	 * Get a setting's pending value for update.
	 *
	 * @param string $slug The slug to get the pending data for.
	 *
	 * @return mixed
	 */
	public function get_pending( $slug = null ) {
		$slug = $slug ? $this->separator . $slug : null;

		return $this->get_param( self::META_KEYS['pending'] . $slug, array() );
	}

	/**
	 * Check if a slug has a pending set of changes.
	 *
	 * @param string $slug The slug to get the pending data for.
	 *
	 * @return bool
	 */
	public function has_pending( $slug ) {
		return $this->has_param( self::META_KEYS['pending'] . $this->separator . $slug );
	}

	/**
	 * Remove a pending set.
	 *
	 * @param string $slug The slug to get the pending data for.
	 */
	public function remove_pending( $slug ) {
		$this->remove_param( self::META_KEYS['pending'] . $this->separator . $slug );
	}

	/**
	 * Save settings.
	 *
	 * @return bool[]|\WP_Error[]
	 */
	public function save() {
		$pending   = array_keys( $this->get_pending() );
		$responses = array();
		foreach ( $pending as $slug ) {
			if ( $this->save_setting( $slug ) ) {
				$responses[] = $slug;
			}
		}

		return $responses;
	}

	/**
	 * Save the settings values to the storage.
	 *
	 * @param string $storage_key The storage_key slug to save.
	 *
	 * @return bool|\WP_Error
	 */
	public function save_setting( $storage_key ) {

		$pending = $this->get_pending( $storage_key );
		$this->remove_pending( $storage_key );
		$this->storage->set( $storage_key, $pending );
		$saved = $this->storage->save( $storage_key );
		if ( true === $saved ) {
			$this->set_value( $storage_key, $pending );
		}

		return $saved;
	}

	/**
	 * Get the storage keys.
	 *
	 * @return array
	 */
	public function get_storage_keys() {
		return $this->storage->get_keys();
	}

	/**
	 * Get the storage parent.
	 *
	 * @param string $slug The slug to get storage object for.
	 *
	 * @return string
	 */
	protected function get_storage_parent( $slug ) {
		$parts = explode( $this->separator, $slug );

		return array_shift( $parts );
	}
}
