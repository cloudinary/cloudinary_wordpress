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
	 * @return mixed
	 */
	protected function load() {
		$data = get_transient( $this->slug );

		return $data;
	}

	/**
	 * Save the data to the option.
	 *
	 * @return bool|void
	 */
	public function save() {
		if ( empty( $this->data ) ) {
			return $this->delete();
		}

		return set_transient( $this->slug, $this->get() );
	}

	/**
	 * Delete the data.
	 *
	 * @return bool
	 */
	public function delete() {
		return delete_transient( $this->slug );
	}
}
