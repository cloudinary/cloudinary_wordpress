<?php
/**
 * Storage Options. handles storing setting in WP Options.
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
	 * @return mixed
	 */
	protected function load() {
		$data = get_option( $this->slug, array() );
		if ( ! empty( $data ) && is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		return $data;
	}

	/**
	 * Save the data to the option.
	 *
	 * @return bool|void
	 */
	public function save() {
		$data = wp_json_encode( $this->get(), JSON_PRETTY_PRINT );

		return update_option( $this->slug, $data );
	}

}
