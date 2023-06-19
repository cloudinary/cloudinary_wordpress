<?php
/**
 * Cloudinary class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\URL;

/**
 * Class Cloudinary
 */
class WordPress extends Url_Object {

	/**
	 * Holds the Cloudinary URL object.
	 *
	 * @var Cloudinary
	 */
	protected $cloudinary_url;

	/**
	 * Get the WordPress attachment ID.
	 *
	 * @return int|null
	 */
	public function get_id() {
		return $this->relation['post_id'];
	}

	/**
	 * Cloudinary URL
	 *
	 * @param string|array $size The size or array of width and height.
	 *
	 * @return string
	 */
	public function cloudinary_url( $size = null ) {
		if ( ! $this->cloudinary_url ) {
			$this->cloudinary_url = new Cloudinary();
			$this->cloudinary_url->set_relation( $this->relation );
		}

		return $this->cloudinary_url->url( $size );
	}

	/**
	 * Get the raw URL.
	 *
	 * @param string|array $size The size or array of width and height.
	 *
	 * @return string
	 */
	public function url( $size = null ) {
		$return = '';
		if ( empty( $size ) ) {
			$return = wp_get_attachment_url( $this->get_id() );
		} else {
			$return = wp_get_attachment_image_url( $this->get_id(), $size );
		}

		return $return;
	}
}
