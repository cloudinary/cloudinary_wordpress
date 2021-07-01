<?php
/**
 * Cloudinary URL
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Connect\Api;
use Cloudinary\Media;
use Cloudinary\Media\Asset;
use Cloudinary\Sync;
use Cloudinary\Utils;
use WP_Post;
use function Cloudinary\get_plugin_instance;

/**
 * Class WooCommerceGallery.
 *
 * Handles gallery for woo.
 */
class Cloudinary_URL {

	/**
	 * Holds the singleton instance.
	 *
	 * @var Cloudinary_URL
	 */
	protected static $instance;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * The cloudinary credentials array.
	 *
	 * @var array
	 */
	public $credentials;

	/**
	 * Cloudinary API URL.
	 *
	 * @var string
	 */
	public $api_url = 'api.cloudinary.com';

	/**
	 * Cloudinary Asset URL.
	 *
	 * @var string
	 */
	public $asset_url = 'res.cloudinary.com';

	/**
	 * List of cloudinary transformations.
	 *
	 * @var array
	 */
	public static $transformation_index = array(
		'image' => array(
			'a'   => 'angle',
			'ar'  => 'aspect_ratio',
			'b'   => 'background',
			'bo'  => 'border',
			'c'   => 'crop',
			'co'  => 'color',
			'dpr' => 'dpr',
			'du'  => 'duration',
			'e'   => 'effect',
			'eo'  => 'end_offset',
			'fl'  => 'flags',
			'h'   => 'height',
			'l'   => 'overlay',
			'o'   => 'opacity',
			'q'   => 'quality',
			'r'   => 'radius',
			'so'  => 'start_offset',
			't'   => 'named_transformation',
			'u'   => 'underlay',
			'vc'  => 'video_codec',
			'w'   => 'width',
			'x'   => 'x',
			'y'   => 'y',
			'z'   => 'zoom',
			'ac'  => 'audio_codec',
			'af'  => 'audio_frequency',
			'br'  => 'bit_rate',
			'cs'  => 'color_space',
			'd'   => 'default_image',
			'dl'  => 'delay',
			'dn'  => 'density',
			'f'   => 'fetch_format',
			'g'   => 'gravity',
			'p'   => 'prefix',
			'pg'  => 'page',
			'sp'  => 'streaming_profile',
			'vs'  => 'video_sampling',
			'if'  => 'if',
		),
		'video' => array(
			'w'   => 'width',
			'h'   => 'height',
			'c'   => 'crop',
			'ar'  => 'aspect_ratio',
			'g'   => 'gravity',
			'b'   => 'background',
			'e'   => 'effect',
			'l'   => 'overlay',
			'so'  => 'start_offset',
			'eo'  => 'end_offset',
			'du'  => 'duration',
			'a'   => 'angle',
			'vs'  => 'video_sampling',
			'dl'  => 'delay',
			'vc'  => 'video_codec',
			'fps' => 'fps',
			'dpr' => 'dpr',
			'br'  => 'bit_rate',
			'ki'  => 'keyframe_interval',
			'sp'  => 'streaming_profile',
			'ac'  => 'audio_codec',
			'af'  => 'audio_frequency',
			'fl'  => 'flags',
			'f'   => 'fetch_format',
			'q'   => 'quality',
			'if'  => 'if',
		),
	);

	/**
	 * Holds the base URLS for each media type.
	 *
	 * @var array
	 */
	protected $base_urls = array();

	/**
	 * Holds the base media types.
	 *
	 * @var array
	 */
	protected $media_types = array(
		'image',
		'video',
	);

	/**
	 * Holds the posts globals.
	 *
	 * @var array
	 */
	protected $posts = array();

	/**
	 * Holds requested assets.
	 *
	 * @var Asset[]
	 */
	protected $assets = array();

	/**
	 * Holds all the local urls for requested assets.
	 *
	 * @var array
	 */
	protected $local_urls = array();

	/**
	 * Holds the current post ID.
	 *
	 * @var int
	 */
	protected $current_post_id;

	/**
	 * Filter constructor.
	 *
	 * @param Media $media The plugin.
	 */
	public function __construct( Media $media ) {
		$this->media       = $media;
		$this->credentials = $media->credentials;
		add_action( 'the_post', array( $this, 'capture_post_transformations' ) );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'meta_asset' ), 10, 2 );
	}

	/**
	 * Get the single instance.
	 *
	 * @return Cloudinary_URL
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			$media          = get_plugin_instance()->get_component( 'media' );
			self::$instance = new self( $media );
		}

		return self::$instance;
	}

	/**
	 * Catch an asset via the meta filter.
	 *
	 * @param array $meta          The metadata.
	 * @param int   $attachment_id The attachement id.
	 *
	 * @return array
	 */
	public function meta_asset( $meta, $attachment_id ) {

		if ( ! isset( $this->assets[ $attachment_id ] ) ) {
			$this->assets[ $attachment_id ] = new Asset( $attachment_id, $meta );
			$this->init_asset( $attachment_id );
			$this->assets[ $attachment_id ]->set_post_context( $this->current_post_id );

			$local_url                                        = wp_get_attachment_url( $attachment_id );
			$this->local_urls[ $local_url ]['attachment_id']  = $attachment_id;
			$this->local_urls[ $local_url ]['cloudinary_url'] = $this->assets[ $attachment_id ]->render();
			if ( $meta['sizes'] ) {
				foreach ( $meta['sizes'] as $size => $data ) {
					$sized = wp_get_attachment_image_url( $attachment_id, $size );
					$this->assets[ $attachment_id ]->set_size( $size );
					$this->local_urls[ $sized ] = array(
						'attachment_id'  => $attachment_id,
						'size'           => $size,
						'cloudinary_url' => $this->assets[ $attachment_id ]->render(),
					);
				}
			}
		}

		return $meta;
	}

	/**
	 * Get the globals at a post level.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array
	 */
	public function get_post_globals( $post_id ) {
		$return = array();
		if ( isset( $this->posts[ $post_id ] ) ) {
			$return = $this->posts[ $post_id ];
		}

		return $return;
	}

	/**
	 * Get an asset by URL.
	 *
	 * @param string $url The url to get asset for.
	 *
	 * @return null|Asset
	 */
	public function get_asset_by_url( $url ) {
		$asset = null;
		if ( isset( $this->local_urls[ $url ] ) ) {
			$attachment_id = $this->local_urls[ $url ]['attachment_id'];
			$asset         = $this->get_asset( $attachment_id );
			if ( ! empty( $this->local_urls[ $url ]['size'] ) ) {
				$asset->set_size( $this->local_urls[ $url ]['size'] );
			}
		}

		return $asset;
	}

	/**
	 * Get all url pairs.
	 *
	 * @return array
	 */
	public function get_url_pairs() {
		return array_map(
			function ( $item ) {
				return $item['cloudinary_url'];
			},
			$this->local_urls
		);
	}

	/**
	 * Capture globals for a post.
	 *
	 * @param WP_Post $post The post.
	 */
	public function capture_post_transformations( $post ) {
		$this->current_post_id = $post->ID;
		if ( $post && ! isset( $this->posts[ $post->ID ] ) ) {
			foreach ( $this->media_types as $type ) {
				$this->posts[ $post->ID ]['taxonomy'][ $type ]  = $this->media->global_transformations->get_taxonomy_transformations( $type );
				$this->posts[ $post->ID ]['taxonomy_overwrite'] = $this->media->global_transformations->is_taxonomy_overwrite();
			}
		}
	}

	/**
	 * Get an asset by ID.
	 *
	 * @param int               $attachment_id The attachment ID.
	 * @param string|array|null $size          The optional size.
	 *
	 * @return Asset
	 */
	public function get_asset( $attachment_id, $size = null ) {
		if ( ! isset( $this->assets[ $attachment_id ] ) ) {
			$this->assets[ $attachment_id ] = new Asset( $attachment_id );
			$this->init_asset( $attachment_id );
		}
		$asset = $this->assets[ $attachment_id ];
		$asset->set_size( $size );

		return $this->assets[ $attachment_id ];
	}

	/**
	 * Get all assets.
	 *
	 * @return Asset[]
	 */
	public function get_assets() {
		return $this->assets;
	}

	/**
	 * Init the base urls.
	 */
	protected function init_base_urls() {

		/**
		 * Filter the base Media Types.
		 *
		 * @param array $default The default media types array.
		 *
		 * @return array
		 */
		$base_types = apply_filters( 'cloudinary_base_media_types', $this->media_types );
		foreach ( $base_types as $type ) {
			$this->base_urls[ $type ] = $this->url( $type );
		}
	}

	/**
	 * Return an endpoint for a specific resource type.
	 *
	 * @param string $resource The resource type for the endpoint.
	 * @param string $delivery The delivery type.
	 *
	 * @return array
	 */
	protected function url( $resource, $delivery = 'upload' ) {
		$globals                = apply_filters( "cloudinary_default_freeform_transformations_{$resource}", array(), array() );
		$global_transformations = array();
		foreach ( $globals as $global ) {
			$global_transformations = array_merge( $global_transformations, self::get_transformations_from_string( $global, $resource ) );
		}
		$parts = array(
			'scheme'          => 'https',
			'host'            => $this->asset_url,
			'cloudname'       => isset( $this->credentials['cname'] ) ? $this->credentials['cname'] : $this->credentials['cloud_name'],
			'resource_type'   => $resource,
			'delivery'        => $delivery,
			'transformations' => array(
				'asset'         => array(),
				'taxonomy'      => array(),
				'global'        => $global_transformations,
				'optimizations' => apply_filters( "cloudinary_default_qf_transformations_{$resource}", array(), array() ),
			),

		);

		return $parts;
	}

	/**
	 * Generate a transformation set.
	 *
	 * @param array  $options The transformation options to generate from.
	 * @param string $type    The asset Type.
	 *
	 * @return string
	 */
	protected static function build_transformation_set( array $options, $type = 'image' ) {
		if ( ! isset( self::$transformation_index[ $type ] ) ) {
			return '';
		}
		$transformation_index = array_flip( self::$transformation_index[ $type ] );
		$transformations      = array();

		foreach ( $options as $key => $value ) {
			if ( isset( $transformation_index[ $key ] ) ) {
				$transformations[] = $transformation_index[ $key ] . '_' . $value;
			} elseif ( ! is_numeric( $key ) && '$' === $key[0] ) {
				$transformations[] = $key . '_' . $value;
			}
		}
		// Clear out empty parts.
		$transformations = array_filter( $transformations );

		return implode( ',', $transformations );
	}

	/**
	 * Generate a transformation string made up of slash separated transformation sets.
	 *
	 * @param array  $options The transformation options to generate from.
	 * @param string $type    The asset Type.
	 *
	 * @return string
	 */
	public static function generate_transformation_string( array $options, $type = 'image' ) {
		if ( ! isset( self::$transformation_index[ $type ] ) ) {
			return '';
		}
		$transformations = array();
		foreach ( $options as $index => $option ) {
			if ( is_string( $option ) ) {
				$transformations[] = $option;
				continue;
			}
			if ( is_array( $option ) && ! empty( $option ) ) {
				$depth = Utils::array_depth( $option );
				if ( 0 === $depth ) {
					$transformations[] = self::build_transformation_set( $option, $type );
				} else {
					$transformations[] = self::generate_transformation_string( $option, $type );
				}
			}
			// Clear out empty parts.
			$transformations = array_filter( $transformations );
		}

		return implode( '/', $transformations );
	}

	/**
	 * Convert a url param based transformation string into an array.
	 *
	 * @param string $str  The transformation string.
	 * @param string $type The type of transformation string.
	 *
	 * @return array The array of found transformations within the string.
	 */
	public static function get_transformations_from_string( $str, $type = 'image' ) {

		$params = self::$transformation_index[ $type ];

		$transformation_chains = explode( '/', $str );
		$transformations       = array();
		foreach ( $transformation_chains as $index => $chain ) {
			$items = explode( ',', $chain );
			foreach ( $items as $item ) {
				$item = trim( $item );
				foreach ( $params as $param => $type ) {
					if ( substr( $item, 0, strlen( $param ) + 1 ) === $param . '_' ) {
						$transformations[ $index ][ $type ] = substr( $item, strlen( $param ) + 1 );
					}
				}
			}
		}

		return array_values( $transformations ); // Reset the keys.
	}

	/**
	 * Get the cloudinary URL for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	protected function init_asset( $attachment_id ) {
		if ( empty( $this->base_urls ) ) {
			$this->init_base_urls();
		}

		$type = $this->media->get_resource_type( $attachment_id );
		if ( ! isset( $this->base_urls[ $type ] ) || ! isset( $this->assets[ $attachment_id ] ) ) {
			return;
		}

		foreach ( $this->base_urls[ $type ] as $key => $value ) {
			$this->assets[ $attachment_id ]->{$key} = $value;
		}
		$this->assets[ $attachment_id ]->version   = 'v' . $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['version'], true );
		$this->assets[ $attachment_id ]->public_id = $this->media->cloudinary_id( $attachment_id );
		$this->assets[ $attachment_id ]->set_transformation( 'asset', $this->media->get_transformation_from_meta( $attachment_id ) );
	}
}
