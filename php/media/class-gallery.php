<?php
/**
 * Manages Gallery Widget and Block settings.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Media;
use Cloudinary\REST_API;
use Cloudinary\Utils;

/**
 * Class Gallery.
 *
 * Handles gallery.
 */
class Gallery {

	/**
	 * The enqueue script handle for the gallery widget lib.
	 *
	 * @var string
	 */
	const GALLERY_LIBRARY_HANDLE = 'cld-gallery';

	/**
	 * The gallery widget lib cdn url.
	 *
	 * @var string
	 */
	const GALLERY_LIBRARY_URL = 'https://product-gallery.cloudinary.com/all.js';

	/**
	 * The default config in case no settings are saved.
	 *
	 * @var array
	 */
	public static $default_config = array(
		'enable_gallery'                    => 'on',
		'primary_color'                     => '#000000',
		'on_primary_color'                  => '#000000',
		'active_color'                      => '#777777',
		'aspect_ratio'                      => '1:1',
		'zoom_trigger'                      => 'click',
		'zoom_type'                         => 'popup',
		'zoom_viewer_position'              => 'bottom',
		'carousel_location'                 => 'top',
		'carousel_offset'                   => 5,
		'carousel_style'                    => 'thumbnails',
		'carousel_thumbnail_width'          => 64,
		'carousel_thumbnail_height'         => 64,
		'carousel_button_shape'             => 'radius',
		'carousel_thumbnail_selected_style' => 'gradient',
		'custom_settings'                   => array(),
	);

	/**
	 * Holds instance of the Media class.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the current config.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Holds the original, unparsed config.
	 *
	 * @var array
	 */
	protected $original_config = array();

	/**
	 * Init gallery.
	 *
	 * @param Media $media Media class instance.
	 */
	public function __construct( Media $media ) {
		$this->media = $media;

		if ( isset( $media->plugin->config['settings']['gallery'] ) && count( $media->plugin->config['settings']['gallery'] ) ) {
			$this->original_config = $media->plugin->config['settings']['gallery'];
		} else {
			$this->original_config = self::$default_config;
		}

		$this->setup_hooks();
		$this->setup_settings();
	}

	/**
	 * Gets the gallery settings in the expected json format.
	 *
	 * @return array
	 */
	public function get_config() {
		if ( count( $this->config ) ) {
			return $this->config;
		}

		$config        = $this->original_config;
		$custom_config = $config['custom_settings'];

		// unset things that don't need to be in the final json.
		unset( $config['enable_gallery'], $config['custom_settings'] );

		$config = $this->prepare_config( $config );
		$config = Utils::expand_dot_notation( $config );
		$config = Utils::array_filter_recursive(
			$config,
			function ( $item ) {
				return ! empty( $item );
			}
		);

		$config['cloudName']   = $this->media->plugin->components['connect']->get_cloud_name();
		$config['container']   = '.woocommerce-product-gallery';
		$config['mediaAssets'] = array();

		if ( ! empty( $custom_config ) ) {
			$custom_config = json_decode( $custom_config, true );

			if ( ! empty( $custom_config ) ) {
				$config = array_merge( $config, $custom_config );
			}
		}

		$this->config = apply_filters( 'cloudinary_gallery_config', $config );

		return $this->config;
	}

	/**
	 * Convert an array's keys to camelCase and transform booleans.
	 * This is used for Cloudinary's gallery widget lib.
	 *
	 * @param array $input The array input that will have its keys camelcase-d.
	 *
	 * @return array
	 */
	public function prepare_config( array $input ) {
		foreach ( $input as $key => $val ) {
			if ( 'on' === $val || 'off' === $val ) {
				$val = 'on' === $val;
			} elseif ( is_numeric( $val ) ) {
				$val = (int) $val;
			}

			if ( 'none' !== $val ) {
				$new_key           = lcfirst( implode( '', array_map( 'ucfirst', explode( '_', $key ) ) ) );
				$input[ $new_key ] = $val;
			}

			unset( $input[ $key ] );
		}

		return $input;
	}

	/**
	 * Register frontend assets for the gallery.
	 */
	public function enqueue_gallery_library() {
		wp_enqueue_script(
			self::GALLERY_LIBRARY_HANDLE,
			self::GALLERY_LIBRARY_URL,
			array(),
			$this->media->plugin->version,
			true
		);

		$json_config = wp_json_encode( $this->get_config() );
		wp_add_inline_script( self::GALLERY_LIBRARY_HANDLE, "var cloudinaryGalleryConfig = JSON.parse( '{$json_config}' );" );

		wp_enqueue_script(
			'cloudinary-gallery-init',
			$this->media->plugin->dir_url . 'js/gallery-init.js',
			array( self::GALLERY_LIBRARY_HANDLE ),
			$this->media->plugin->version,
			true
		);
	}

	/**
	 * Register blocked editor assets for the gallery.
	 */
	public function block_editor_scripts_styles() {
		$this->enqueue_gallery_library();

		wp_enqueue_style(
			'cloudinary-gallery-block-css',
			$this->media->plugin->dir_url . 'css/gallery-block.css',
			array(),
			$this->media->plugin->version
		);

		wp_enqueue_script(
			'cloudinary-gallery-block-js',
			$this->media->plugin->dir_url . 'js/gallery-block.js',
			array( 'wp-blocks', 'wp-editor', 'wp-element', self::GALLERY_LIBRARY_HANDLE ),
			$this->media->plugin->version,
			true
		);

		wp_localize_script(
			'cloudinary-gallery-block-js',
			'cloudinaryGalleryApi',
			array(
				'endpoint' => rest_url( REST_API::BASE . '/image_data' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Checks if the Cloudinary Gallery Widget is enabled.
	 *
	 * @return bool
	 */
	public function gallery_enabled() {
		return isset( $this->original_config['enable_gallery'] ) && 'on' === $this->original_config['enable_gallery'];
	}

	/**
	 * Fetches image public id and transformations.
	 *
	 * @param array|int[]|array[] $images An array of image IDs or a multi-dimensional array with url and id keys.
	 *
	 * @return array
	 */
	public function get_image_data( array $images ) {
		$image_data = array();

		foreach ( $images as $index => $image ) {
			$image_id = is_int( $image ) ? $image : $image['id'];

			if ( ! $this->media->sync->is_synced( $image_id ) ) {
				continue;
			}

			$image_url = is_int( $image ) ? $this->media->cloudinary_url( $image_id ) : $image['url'];

			$image_data[ $index ]             = array();
			$image_data[ $index ]['publicId'] = $this->media->get_public_id( $image_id, true );

			$transformations = $this->media->get_transformations_from_string( $image_url );

			if ( $transformations ) {
				$image_data[ $index ]['transformation'] = array( 'transformation' => $transformations );
			}
		}

		return $image_data;
	}

	/**
	 * This rest endpoint handler will fetch the public_id and transformations off of a list of images.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_cloudinary_image_data( \WP_REST_Request $request ) {
		$request_body = json_decode( $request->get_body(), true );

		if ( empty( $request_body['images'] ) ) {
			return new \WP_Error( 400, 'The "images" key must be present in the request body.' );
		}

		$image_data = $this->get_image_data( $request_body['images'] );

		return new \WP_REST_Response( $image_data );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['image_data'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_cloudinary_image_data' ),
			'args'                => array(),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		);

		return $endpoints;
	}

	/**
	 * Setup hooks for the gallery.
	 */
	public function setup_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_scripts_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gallery_library' ) );
	}

	/**
	 * Add gallery settings to media page.
	 */
	protected function setup_settings() {
		$media_settings = $this->media->get_settings();
		$gallery        = array(
			'type'       => 'page',
			'page_title' => __( 'Gallery Settings', 'cloudinary' ),
			array(
				'type'  => 'panel',
				'title' => __( 'Gallery Settings', 'cloudinary' ),
				'icon'  => $this->media->plugin->dir_url . 'css/gallery.svg',
				array(
					'type'   => 'react',
					'slug'   => 'gallery',
					'script' => array(
						'slug' => 'gallery-widget',
						'src'  => $this->media->plugin->dir_url . 'js/gallery.js',
					),
				),
			),
			array(
				'type' => 'submit',
			),
		);
		$media_settings->create_setting( 'gallery', $gallery, $media_settings );
	}
}
