<?php
/**
 * Storage abstraction. Handles how the settings are stored and retrieved.
 *
 * @package   Cloudinary\Settings\Storage
 */

namespace Cloudinary\Settings\Storage;

/**
 * Class Storage
 *
 * @package Cloudinary\Settings\Storage
 */
abstract class Storage {

	/**
	 * Holds the storage prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Holds the current data.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Storage constructor.
	 *
	 * @param string $prefix The prefix to add to storage parts.
	 */
	public function __construct( $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * Get the data.
	 *
	 * @param string $slug   The slug of the setting storage to get.
	 * @param false  $reload Flag to force a reload.
	 *
	 * @return mixed
	 */
	public function get( $slug, $reload = false ) {
		$prefixed = $this->prefix( $slug );
		if ( ! isset( $this->data[ $prefixed ] ) || true === $reload ) {
			$this->set( $slug, $this->load( $prefixed ) );
		}

		return $this->data[ $prefixed ];
	}

	/**
	 * Prefix the  slug for saving data.
	 *
	 * @param string $slug The slug to prefix.
	 *
	 * @return string
	 */
	protected function prefix( $slug ) {
		$prefix = null;
		if ( '_' !== $slug[0] && 0 !== strpos( $slug, $this->prefix ) ) {
			$prefix = $this->prefix . '_';
		}

		return $prefix . $slug;
	}

	/**
	 * Set the data.
	 *
	 * @param string $slug The slug of the setting storage to get.
	 * @param mixed  $data The data to set.
	 */
	public function set( $slug, $data ) {
		$this->data[ $this->prefix( $slug ) ] = $data;
	}

	/**
	 * Get the storage keys.
	 *
	 * @return array
	 */
	public function get_keys() {
		return array_keys( $this->data );
	}

	/**
	 * Load the data from storage source.
	 *
	 * @param string $prefixed_slug The prefixed slug to load.
	 *
	 * @return mixed
	 */
	abstract protected function load( $prefixed_slug );

	/**
	 * Delete the data from storage source.
	 *
	 * @param string $slug The slug of the setting storage to delete.
	 *
	 * @return bool
	 */
	abstract public function delete( $slug );

	/**
	 * Save the data to storage source.
	 *
	 * @param string $slug The slug of the setting storage to save.
	 *
	 * @return bool
	 */
	abstract public function save( $slug );
}
