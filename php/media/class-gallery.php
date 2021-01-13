<?php
/**
 * Manages Gallery Widget and Block settings.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Component\Settings;
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
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	public $settings_slug = 'gallery';

	/**
	 * Holds the sync settings object.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * The default config in case no settings are saved.
	 *
	 * @var array
	 */
	public static $default_config = array(
		'mediaAssets'      => array(),
		'transition'       => 'fade',
		'aspectRatio'      => '3:4',
		'navigation'       => 'always',
		'zoom'             => true,
		'carouselLocation' => 'top',
		'carouselOffset'   => 5,
		'carouselStyle'    => 'thumbnails',
		'displayProps'     => array( 'mode' => 'classic' ),
		'indicatorProps'   => array( 'shape' => 'round' ),
		'themeProps'       => array(
			'primary'   => '#cf2e2e',
			'onPrimary' => '#000000',
			'active'    => '#777777',
		),
		'zoomProps'        => array(
			'type'           => 'popup',
			'viewerPosition' => 'bottom',
			'trigger'        => 'click',
		),
		'thumbnailProps'   => array(
			'width'                  => 64,
			'height'                 => 64,
			'navigationShape'        => 'radius',
			'selectedStyle'          => 'gradient',
			'selectedBorderPosition' => 'all',
			'selectedBorderWidth'    => 4,
			'mediaSymbolShape'       => 'round',
		),
		'customSettings'   => '',
	);

	/**
	 * Holds instance of the Media class.
	 *
	 * @var Media
	 */
	public $media;

	/**
	 * Holds the current config.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Init gallery.
	 *
	 * @param Media $media Media class instance.
	 */
	public function __construct( Media $media ) {
		$this->media = $media;

		$this->setup_hooks();

		$config = ! empty( $media->plugin->settings->get_value( 'gallery_config' ) ) ?
			$media->plugin->settings->get_value( 'gallery_config' ) :
			wp_json_encode( self::$default_config );

		$this->config = json_decode( $config, true );
	}

	/**
	 * Gets the gallery settings in the expected json format.
	 *
	 * @return array
	 */
	public function get_config() {
		$config = Utils::array_filter_recursive( $this->config ); // Remove empty values.

		$config['cloudName'] = $this->media->plugin->components['connect']->get_cloud_name();

		/**
		 * Filter the gallery HTML container.
		 *
		 * @param string $selector The target HTML selector.
		 */
		$config['container'] = apply_filters( 'cloudinary_gallery_html_container', '' );

		/**
		 * Filter the gallery configuration.
		 *
		 * @param array $config The current gallery config.
		 */
		return apply_filters( 'cloudinary_gallery_config', $config );
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

		$post         = get_post();
		$post_content = $post ? "'" . implode( '', explode( "\n", $post->post_content ) ) . "'" : 'null';

		wp_add_inline_script( self::GALLERY_LIBRARY_HANDLE, "var cloudinaryPostContent = {$post_content};" );

		wp_enqueue_script(
			'cloudinary-gallery-init',
			$this->media->plugin->dir_url . 'js/gallery-init.js',
			array( self::GALLERY_LIBRARY_HANDLE ),
			$this->media->plugin->version,
			true
		);
	}

	/**
	 * Enqueue admin UI scripts if needed.
	 */
	public function enqueue_admin_scripts() {
		if ( Utils::get_active_setting() === $this->settings ) {
			$this->block_editor_scripts_styles();
			wp_enqueue_style(
				'cloudinary-gallery-settings-css',
				$this->media->plugin->dir_url . 'css/gallery-ui.css',
				array(),
				$this->media->plugin->version
			);

			$script = array(
				'slug'      => 'gallery_config',
				'src'       => $this->media->plugin->dir_url . 'js/gallery.js',
				'in_footer' => true,
			);
			$asset  = $this->get_asset();
			wp_enqueue_script( $script['slug'], $script['src'], $asset['dependencies'], $asset['version'], $script['in_footer'] );

			$color_palette = wp_json_encode( current( (array) get_theme_support( 'editor-color-palette' ) ) );
			wp_add_inline_script( $script['slug'], "var CLD_THEME_COLORS = JSON.parse( '$color_palette' );", 'before' );
		}
	}

	/**
	 * Retrieve asset dependencies.
	 *
	 * @return array
	 */
	private function get_asset() {
		$asset = require $this->media->plugin->dir_path . 'js/gallery.asset.php';

		$asset['dependencies'] = array_filter(
			$asset['dependencies'],
			static function ( $dependency ) {
				return false === strpos( $dependency, '/' );
			}
		);

		return $asset;
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

			$transformations      = null;
			$image_data[ $index ] = array();

			if ( ! $this->media->sync->is_synced( $image_id ) ) {
				$res = $this->media->sync->managers['upload']->upload_asset( $image_id );

				if ( ! is_wp_error( $res ) ) {
					$image_data[ $index ]['publicId'] = $this->media->get_public_id_from_url( $res['url'] );
					$transformations                  = $this->media->get_transformations_from_string( $res['url'] );
				}
			} else {
				$image_data[ $index ]['publicId'] = $this->media->get_public_id( $image_id, true );

				$image_url       = is_int( $image ) ? $this->media->cloudinary_url( $image_id ) : $image['url'];
				$transformations = $this->media->get_transformations_from_string( $image_url );
			}

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
	 * Define the settings.
	 *
	 * @return array
	 */
	public function settings() {
		$settings = array(
			'type'        => 'page',
			'page_title'  => __( 'Gallery Settings', 'cloudinary' ),
			'option_name' => 'cloudinary_gallery',
		);

		$panel = array(
			'type'  => 'panel',
			'title' => __( 'Gallery Settings', 'cloudinary' ),
			'icon'  => $this->media->plugin->dir_url . 'css/gallery.svg',
		);

		if ( WooCommerceGallery::woocommerce_active() ) {
			$panel[] = array(
				'type'  => 'group',
				'title' => 'WooCommerce',
				array(
					'type'         => 'on_off',
					'slug'         => 'gallery_woocommerce_enabled',
					'title'        => __( 'Replace Gallery', 'cloudinary' ),
					'tooltip_text' => __( 'Replace the default WooCommerce gallery with the Cloudinary Product Gallery on product pages.', 'cloudinary' ),
				),
			);
		}

		$panel[] = array(
			'type'   => 'react',
			'slug'   => 'gallery_config',
			'script' => array(
				'slug' => 'gallery-widget',
				'src'  => $this->media->plugin->dir_url . 'js/gallery.js',
			),
		);

		$settings[] = $panel;
		$settings[] = array( 'type' => 'submit' );

		return $settings;
	}

	/**
	 * Register the setting under media.
	 */
	protected function register_settings() {
		$settings_params = $this->settings();
		$this->settings  = $this->media->plugin->settings->create_setting( $this->settings_slug, $settings_params );

		// Move setting to media.
		$media_settings = $this->media->get_settings();
		$media_settings->add_setting( $this->settings );
	}

	/**
	 * Setup hooks for the gallery.
	 */
	public function setup_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_scripts_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gallery_library' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Register Settings.
		$this->register_settings();
	}
}
