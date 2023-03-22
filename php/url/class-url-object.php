<?php
/**
 * Abstract class for the urls.
 *
 * @package Cloudinary
 */

namespace Cloudinary\URL;

use Cloudinary\Traits\Relation_Trait;

/**
 * Class Cloudinary
 */
abstract class Url_Object {

	use Relation_Trait;

	/**
	 * Holds the raw URL
	 *
	 * @var string The raw WordPress URL.
	 */
	protected $raw_url;

	/**
	 * Holds the Object relation.
	 *
	 * @var array
	 */
	protected $relation = array();

	/**
	 * Url constructor.
	 *
	 * @param string|null $url The raw url.
	 */
	public function __construct( $url = null ) {
		if ( $url ) {
			$this->raw_url = strstr( $url, '//' );
		}
	}

	/**
	 * Parse URL.
	 *
	 * @param string|null $component The optional component to get.
	 *
	 * @return array|string
	 */
	public function parse( $component = null ) {
		static $parsed;
		if ( ! $parsed ) {
			$parsed = wp_parse_url( $this->raw_url );
		}

		return $component && $parsed[ $component ] ? $parsed[ $component ] : $parsed;
	}

	/**
	 * Get the type of asset for the URL.
	 *
	 * @return string
	 */
	public function get_type() {
		static $type;
		if ( ! $type ) {
			$parts = wp_check_filetype( basename( $this->relation['sized_url'] ) );
			$type  = strstr( $parts['type'], '/', true );
		}

		return $type;
	}

	/**
	 * Get the size of the url.
	 *
	 * @param string $size The size to get size array from.
	 *
	 * @return array
	 */
	public function get_size( $size ) {
		$return = array();
		if ( ! empty( $this->relation['metadata'] ) ) {
			$meta             = $this->relation['metadata'];
			$return['width']  = $meta['width'];
			$return['height'] = $meta['height'];
			if ( ! empty( $meta['sizes'][ $size ] ) ) {
				$return['width']  = $meta['sizes'][ $size ]['width'];
				$return['height'] = $meta['sizes'][ $size ]['height'];
			}
		}

		// @todo: Add in global and image specific crop and gravity.

		return $return;
	}

	/**
	 * Set the Relation.
	 *
	 * @param array $relation The array of relation.
	 */
	public function set_relation( $relation ) {
		$this->relation = wp_parse_args( $relation, $this->relation );
	}

	/**
	 * Get the raw URL.
	 *
	 * @param string|array $size The size or array of width and height.
	 *
	 * @return string
	 */
	abstract public function url( $size = null );

	/**
	 * Get the ID.
	 *
	 * @return int|null
	 */
	abstract public function get_id();
}
