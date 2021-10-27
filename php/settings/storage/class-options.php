<?php
/**
 * Storage Options. Handles storing setting in WP Options.
 *
 * @package   Cloudinary\Settings\Storage
 */

namespace Cloudinary\Settings\Storage;

/**
 * Class Options
 *
 * @package Cloudinary\Settings\Storage
 */
class Options extends Storage {

	/**
	 * Load the data from storage source.
	 *
	 * @param string $prefixed_slug The slug to load.
	 *
	 * @return mixed
	 */
	protected function load( $prefixed_slug ) {
		$data = get_option( $prefixed_slug, array() );

		return $data;
	}

	/**
	 * Save the data to storage source.
	 *
	 * @param string $slug The slug of the setting storage to save.
	 *
	 * @return bool
	 */
	public function save( $slug ) {
		return update_option( $this->prefix( $slug ), $this->get( $slug ), false );
	}

	/**
	 * Delete the data from storage source.
	 *
	 * @param string $slug The slug of the setting storage to delete.
	 *
	 * @return bool
	 */
	public function delete( $slug ) {
		return delete_option( $this->prefix( $slug ) );
	}
}
