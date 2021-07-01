<?php
/**
 * Cloudinary Asset
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Connect;
use Cloudinary\Media\Cloudinary_URL;
use Cloudinary\Sync;
use function Cloudinary\get_plugin_instance;

/**
 * Class WooCommerceGallery.
 *
 * Handles gallery for woo.
 */
class Asset {

	/**
	 * The assets ID.
	 *
	 * @var int
	 */
	protected $attachment_id;

	/**
	 * Holds the assets sync state.
	 *
	 * @var bool
	 */
	protected $synced;

	/**
	 * Holds the assets full size.
	 *
	 * @var array
	 */
	protected $full_size = array();
	/**
	 * Holds a list of available sizes.
	 *
	 * @var array
	 */
	protected $sizes = array();

	/**
	 * Holds the filenames of the different sizes.
	 *
	 * @var array
	 */
	protected $files = array();

	/**
	 * Holds the current post ID.
	 *
	 * @var int
	 */
	protected $current_post_id;

	/**
	 * Holds the current size.
	 *
	 * @var string|array
	 */
	protected $current_size;

	/**
	 * Holds the core structure.
	 *
	 * @var array
	 */
	protected $parts = array(
		'scheme'          => 'https',
		'host'            => null,
		'cloudname'       => null,
		'resource_type'   => null,
		'delivery'        => null,
		'size'            => null,
		'transformations' => array(
			'asset'         => array(),
			'taxonomy'      => array(),
			'global'        => array(),
			'optimizations' => array(),
		),
		'version'         => 'v1',
		'public_id'       => null,
		'seo'             => null,
		'extension'       => null,
	);

	/**
	 * Asset constructor.
	 *
	 * @param int        $attachment_id The attachment ID.
	 * @param array|null $meta          The post meta.
	 */
	public function __construct( $attachment_id, $meta = null ) {
		$this->attachment_id = $attachment_id;
		$this->init( $meta );
		$this->synced();
		$this->set_post_context();
	}

	/**
	 * Init an asset.
	 *
	 * @param null|array $meta The post meta to init the asset with.
	 */
	protected function init( $meta = null ) {
		if ( ! $meta ) {
			$meta = wp_get_attachment_metadata( $this->attachment_id, true );
		}
		if ( ! empty( $meta['sizes'] ) ) {
			$this->current_size        = 'full';
			$this->full_size['width']  = ! empty( $meta['width'] ) ? $meta['width'] : $meta['sizes']['full']['width'];
			$this->full_size['height'] = ! empty( $meta['height'] ) ? $meta['height'] : $meta['sizes']['full']['height'];
			foreach ( $meta['sizes'] as $size => $data ) {
				$this->files[ $size ] = $data['file'];
				$this->sizes[ $size ] = array(
					'width'  => $data['width'],
					'height' => $data['height'],
				);
				if ( ! $this->matches_ratio( $data['width'], $data['height'] ) ) {
					$this->sizes[ $size ]['crop'] = 'fill';
				}
			}
		}
	}

	/**
	 * Checks if a size matches the original size.
	 *
	 * @param int $width  The Width to check.
	 * @param int $height the Height to check.
	 *
	 * @return bool
	 */
	public function matches_ratio( $width, $height ) {
		$matches = false;
		if ( ! empty( $this->full_size ) ) {
			$matches = wp_image_matches_ratio(
			// PDFs do not always have width and height, but they do have full sizes.
			// This is important for the thumbnail crops on the media library.
				$this->full_size['width'],
				$this->full_size['height'],
				$width,
				$height
			);
		}

		return $matches;
	}

	/**
	 * Set the objects size.
	 *
	 * @param null|string|array $size The size to set.
	 */
	public function set_size( $size = null ) {
		$set_size           = $this->full_size;
		$this->parts['seo'] = null;
		if ( isset( $this->sizes[ $size ] ) ) {
			$set_size           = $this->sizes[ $size ];
			$this->parts['seo'] = $this->files[ $size ];
		} elseif ( is_array( $size ) ) {
			$clean             = array_filter( $size, 'is_numeric' );
			$set_size['width'] = array_shift( $clean );
			if ( ! empty( $clean ) ) {
				$set_size['height'] = array_shift( $clean );
				if ( ! $this->matches_ratio( $set_size['width'], $set_size['height'] ) ) {
					$set_size['crop'] = 'fill';
				}
			}
		}

		$this->parts['size'] = $set_size;
	}

	/**
	 * Get the assets ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->attachment_id;
	}

	/**
	 * Checks if synced.
	 *
	 * @param bool $recheck Flag to signal a recheck.
	 *
	 * @return bool
	 */
	public function synced( $recheck = false ) {
		if ( is_null( $this->synced ) || true === $recheck ) {
			$this->synced = get_plugin_instance()->get_component( 'sync' )->is_synced( $this->attachment_id );
		}

		return $this->synced;
	}

	/**
	 * Magic method to set the Parts, which can be larger as needed..
	 *
	 * @param string $name  The key to set.
	 * @param mixed  $value The value to set.
	 */
	public function __set( $name, $value ) {
		$this->parts[ $name ] = $value;
	}

	/**
	 * Set transformations.
	 *
	 * @param string $type  The type to set.
	 * @param array  $value The transformation array to set.
	 */
	public function set_transformation( $type, $value ) {
		$this->parts['transformations'][ $type ] = $value;
	}

	/**
	 * Clear a transformation set.
	 *
	 * @param string $type The key to clear.
	 */
	public function clear_transformation( $type ) {
		$this->set_transformation( $type, array() );
	}

	/**
	 * Sets the post id.
	 *
	 * @param null|int $post_context_id The post ID.
	 */
	public function set_post_context( $post_context_id = null ) {
		if ( is_null( $post_context_id ) ) {
			$post_context = get_post();
			if ( $post_context ) {
				$post_context_id = $post_context->ID;
			}
		}

		if ( $post_context_id && $post_context_id !== $this->current_post_id ) {
			$globals = Cloudinary_URL::get_instance()->get_post_globals( $post_context_id );
			if ( ! empty( $globals['taxonomy'] ) ) {
				$this->set_transformation( 'taxonomy', $globals['taxonomy'] );
				if ( true === $globals['taxonomy_overwrite'] ) {
					$this->clear_transformation( 'global' );
				}
			}
			$this->current_post_id = $post_context_id;
		}
	}

	/**
	 * Get the assets local URL.
	 *
	 * @return false|string
	 */
	public function get_local_url() {
		if ( 'image' === $this->parts['resource_type'] ) {
			$url = wp_get_attachment_image_url( $this->attachment_id, $this->current_size );
		} else {
			$url = wp_get_attachment_url( $this->attachment_id );
		}

		return $url;
	}

	/**
	 * Render the cloudinary URL.
	 *
	 * @return false|string
	 */
	public function render() {
		if ( ! $this->synced ) {
			return $this->get_local_url();
		}
		$parts            = $this->parts;
		$parts['scheme'] .= ':/';
		if ( ! empty( $parts['seo'] ) ) {
			$parts['resource_type'] = 'images';
			$parts['public_id']     = strtok( $parts['public_id'], '.' );
			unset( $parts['delivery'] );
		}
		$parts = array_filter( $parts );

		return Cloudinary_URL::generate_transformation_string( $parts );
	}
}
