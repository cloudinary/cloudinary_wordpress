<?php
/**
 * Manages Gallery Widget and Block settings.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Settings\Setting;
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
	 * @var Setting
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
		'displayProps'     => array(
			'mode'    => 'classic',
			'columns' => 1,
		),
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
	}

	/**
	 * Gets the gallery settings in the expected json format.
	 *
	 * @return array
	 */
	public function get_config() {

		$config = ! empty( $this->settings->get_value( 'gallery_config' ) ) ?
			$this->settings->get_value( 'gallery_config' ) :
			self::$default_config;

		$this->config = $this->maybe_decode_config( $config );
		$config       = Utils::array_filter_recursive( $this->config ); // Remove empty values.

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
		// Bail enqueuing the scripts if conditions aren't met.
		if ( ! $this->maybe_enqueue_scripts() ) {
			return;
		}

		wp_enqueue_script(
			self::GALLERY_LIBRARY_HANDLE,
			self::GALLERY_LIBRARY_URL,
			array(),
			$this->media->plugin->version,
			true
		);

		$json_config = wp_json_encode( $this->get_config() );
		wp_add_inline_script( self::GALLERY_LIBRARY_HANDLE, "var CLD_GALLERY_CONFIG = {$json_config};" );

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
		if ( Utils::get_active_setting() !== $this->settings ) {
			return;
		}

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

		$asset = $this->get_asset();
		wp_enqueue_script( $script['slug'], $script['src'], $asset['dependencies'], $asset['version'], $script['in_footer'] );

		$color_palette = wp_json_encode( current( (array) get_theme_support( 'editor-color-palette' ) ) );
		wp_add_inline_script( $script['slug'], "var CLD_THEME_COLORS = $color_palette;", 'before' );
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

		wp_add_inline_script( 'cloudinary-gallery-block-js', 'var CLD_REST_ENDPOINT = "/' . REST_API::BASE . '";', 'before' );
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

			$transformations = null;
			$data            = array();

			// Send back the attachment id.
			$data['attachmentId'] = $image_id;

			// Fetch the public id by either syncing NOW or getting the current public id.
			if ( ! $this->media->has_public_id( $image_id ) ) {
				$res = $this->media->sync->managers['upload']->upload_asset( $image_id );

				if ( is_wp_error( $res ) ) {
					// Skip and move on to the next image as this is unlikely to get synced.
					continue;
				}
			}
			// If synced now, the ID will be available in the meta.
			$data['publicId'] = $this->media->get_public_id( $image_id, true );
			$transformations  = $this->media->get_transformations( $image_id );
			if ( $transformations ) {
				$data['transformation'] = array( 'transformation' => $transformations );
			}

			// Add to output array.
			$image_data[] = $data;
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
			'page_title'  => __( 'Gallery Settings (Beta)', 'cloudinary' ),
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
					'tooltip_text' => __( 'Replace the default WooCommerce gallery with the Cloudinary Gallery on product pages.', 'cloudinary' ),
				),
			);
		} else {
			$panel[] = array(
				array(
					'type'    => 'tag',
					'element' => 'h3',
					'content' => __( 'Cloudinary Gallery block defaults', 'cloudinary' ),
				),
				array(
					'content' => __( 'The Cloudinary Gallery is available as a new block type which can be inserted to any post or page. Note, this is not available when using the classic editor.', 'cloudinary' ),
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
	 * Inits the cloudinary gallery using block attributes.
	 *
	 * @param string $content The post content.
	 * @param array  $block   Block data.
	 *
	 * @return string
	 */
	public function prepare_block_render( $content, $block ) {
		if ( 'cloudinary/gallery' !== $block['blockName'] ) {
			return $content;
		}

		// Ensure library is enqueued. Deals with archive pages that render the content.
		$this->enqueue_gallery_library();

		$attributes = Utils::expand_dot_notation( $block['attrs'], '_' );
		$attributes = array_merge( self::$default_config, $attributes );

		// Gallery without images. Don't render.
		if ( empty( $attributes['selectedImages'] ) ) {
			return $content;
		}

		$attributes['mediaAssets'] = array();
		foreach ( $attributes['selectedImages'] as $attachment ) {
			$transformations = $this->media->get_transformations( $attachment['attachmentId'] );
			if ( ! empty( $transformations ) ) {
				$attachment['transformation'] = array( 'transformation' => $transformations );
			}
			$attributes['mediaAssets'][] = $attachment;
		}

		$attributes['cloudName'] = $this->media->plugin->components['connect']->get_cloud_name();

		$credentials = $this->media->plugin->components['connect']->get_credentials();

		if ( ! empty( $credentials['cname'] ) ) {
			$attributes['secureDistribution'] = $credentials['cname'];
			$attributes['privateCdn']         = true;
		}
		unset( $attributes['selectedImages'], $attributes['customSettings'] );

		ob_start();
		?>
		<script>
			window.addEventListener( 'load', function () {
				if ( cloudinary && cloudinary.galleryWidget ) {
					var attributes = <?php echo wp_json_encode( $attributes ); ?>;
					attributes.container = '.' + attributes.container;
					cloudinary.galleryWidget( attributes ).render();
				}
			}, false );
		</script>
		<?php

		return $content . ob_get_clean();
	}

	/**
	 * Maybe decode the gallery configuration.
	 * This has historical reasons, as it was used to be stored as encoded information.
	 *
	 * @param array|string $config The configuration for the gallery.
	 *
	 * @return array
	 */
	protected function maybe_decode_config( $config ) {
		if ( ! is_array( $config ) ) {
			$config = json_decode( $config, true );
		}

		return $config;
	}

	/**
	 * Maybe enqueue gallery scripts.
	 *
	 * @return bool
	 */
	protected function maybe_enqueue_scripts() {
		$can = false;

		// Can if front end and have the block.
		if (
			! is_admin() &&
			has_block( 'cloudinary/gallery' )
		) {
			$can = true;
		}

		// Can on back end on block editor and gallery settings page.
		if ( is_admin() ) {
			$screen = get_current_screen();
			if (
				! is_null( $screen ) &&
				(
					'cloudinary_page_media' === $screen->id ||
					( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() )
				)
			) {
				$can = true;
			}
		}

		// Bail enqueuing the script several times.
		if ( wp_script_is( self::GALLERY_LIBRARY_HANDLE ) ) {
			$can = false;
		}

		/**
		 * Filter the enqueue of gallery script.
		 *
		 * @param bool $can Default value.
		 *
		 * @return bool
		 */
		$can = apply_filters( 'cloudinary_enqueue_gallery_script', $can );

		return $can;
	}

	/**
	 * Setup hooks for the gallery.
	 */
	public function setup_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_scripts_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gallery_library' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'render_block', array( $this, 'prepare_block_render' ), 10, 2 );

		// Register Settings.
		$this->register_settings();
	}
}
