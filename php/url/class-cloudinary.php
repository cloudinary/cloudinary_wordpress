<?php
/**
 * Cloudinary class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary\URL;

use Cloudinary\Connect\Api;
use Cloudinary\Media;
use function Cloudinary\get_plugin_instance;

/**
 * Class Cloudinary
 */
class Cloudinary extends Url_Object {

	/**
	 * Holds the Connect API object.
	 *
	 * @var Api
	 */
	protected $api;

	/**
	 * Constructor.
	 *
	 * @param string|null $url The optional raw URL.
	 */
	public function __construct( $url = null ) {
		$this->api = get_plugin_instance()->get_component( 'connect' )->api;
		parent::__construct( $url );
	}

	/**
	 * Get cloudinary transformations.
	 *
	 * @return array
	 */
	public function get_transformations() {
		static $transformations;
		if ( ! $transformations && ! empty( $this->relation['transformations'] ) ) {
			$transformations = Media::extract_transformations_from_string( $this->relation['transformations'], $this->get_type() );
		}

		return $transformations;
	}

	/**
	 * Get a cloudinary transformation.
	 *
	 * @param string $transformation The transformation to get.
	 *
	 * @return array|string
	 */
	public function get_transformation( $transformation ) {
		static $fetched = array();
		if ( ! isset( $fetched[ $transformation ] ) ) {
			$transformations = $this->get_transformations();
			$matched         = null;
			foreach ( $transformations as $transformation_set ) {
				if ( isset( $transformation_set[ $transformation ] ) ) {
					$matched = $transformation_set[ $transformation ];
					break;
				}
			}
			$fetched[ $transformation ] = $matched;
		}

		return $fetched[ $transformation ];
	}

	/**
	 * Get the URL version.
	 *
	 * @return string
	 */
	public function get_version() {
		static $version;
		if ( ! $version ) {
			$version = 'v1';
			if ( preg_match( '/\/(v\d*)\//', $this->raw_url, $matches ) ) {
				$version = $matches[1];
			}
		}

		return $version;
	}

	/**
	 * Get the public id of the url.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->relation['public_id'] . '.' . $this->relation['format'];
	}

	/**
	 * Get the attachment id.
	 *
	 * @return int
	 */
	public function get_attachment_id() {
		return $this->relation['post_id'];
	}

	/**
	 * Get WordPress URL
	 *
	 * @return WordPress
	 */
	public function get_wordpress_url() {
		static $object = array();
		if ( empty( $object ) ) {
			$url_component = get_plugin_instance()->get_component( 'url' );
			$id            = $this->get_attachment_id();
			$url           = wp_get_attachment_image_url( $id, 'full' );
			$object        = $url_component->wordpress_url( $url );
		}

		return $object;
	}

	/**
	 * Get the raw URL.
	 *
	 * @param string|array $size The size or array of width and height.
	 *
	 * @return string
	 */
	public function url( $size = null ) {
		$args            = array(
			'resource_type' => $this->get_type(),
		);
		$transformations = $this->get_transformations();
		if ( ! empty( $transformations ) ) {
			$args['transformation'] = $transformations;
		}
		if ( is_string( $size ) ) {
			$size = $this->get_size( $size );
		}

		return $this->api->cloudinary_url( $this->get_id(), $args, $size );
	}
}
