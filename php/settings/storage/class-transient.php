<?php
/**
 * Storage Transient. handles storing setting in WP Options.
 *
 * @package   Cloudinary\Settings\Storage
 */

namespace Cloudinary\Settings\Storage;

/**
 * Class Options
 *
 * @package Cloudinary\Settings\Storage
 */
class Transient extends Storage {

	/**
	 * Load the data from storage source.
	 *
	 * @param string $prefixed_slug The slug to load.
	 *
	 * @return mixed
	 */
	protected function load( $prefixed_slug ) {
		$data = get_transient( $prefixed_slug );

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
		$prefixed_slug = $this->prefix( $slug );
		if ( empty( $this->data[ $prefixed_slug ] ) ) {
			return $this->delete( $prefixed_slug );
		}

		return set_transient( $prefixed_slug, $this->get( $slug ) );
	}

	/**
	 * Delete the data from storage source.
	 *
	 * @param string $slug The slug of the setting storage to delete.
	 *
	 * @return bool
	 */
	public function delete( $slug ) {
		return delete_transient( $this->prefix( $slug ) );
	}
}
