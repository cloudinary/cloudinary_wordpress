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
	 * Holds the storage slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Holds the current data.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Storage constructor.
	 *
	 * @param string $slug The storage slug.
	 */
	public function __construct( $slug ) {
		$this->slug = $slug;
		$this->load();
	}

	/**
	 * Get the data.
	 *
	 * @param false $reload Flag to force a reload.
	 *
	 * @return mixed
	 */
	public function get( $reload = false ) {
		if ( null === $this->data || true === $reload ) {
			$this->set( $this->load() );
		}

		return $this->data;
	}

	/**
	 * Set the data.
	 *
	 * @param mixed $data The data to set.
	 */
	public function set( $data ) {
		$this->data = $data;
	}

	/**
	 * Load the data from storage source.
	 *
	 * @return mixed
	 */
	abstract protected function load();

	/**
	 * Save the data to storage source.
	 *
	 * @return bool
	 */
	abstract public function save();

}
