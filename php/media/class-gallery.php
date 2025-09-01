<?php
/**
 * Manages Gallery Widget and Block settings.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Media;

use Cloudinary\Plugin;
use Cloudinary\Settings\Setting;
use Cloudinary\Media;
use Cloudinary\REST_API;
use Cloudinary\Settings_Component;
use Cloudinary\Utils;
use WP_Screen;

/**
 * Class Gallery.
 *
 * Handles gallery.
 */
class Gallery extends Settings_Component {

	/**
	 * The enqueue script handle for the gallery widget lib.
	 *
	 * @var string
	 */
	const GALLERY_LIBRARY_HANDLE = 'cld-gallery';

	/**
	 * This is a "false" mime type for GLB files.
	 *
	 * Canonically, the correct mime type for GLB files is `model/gltf-binary`,
	 * however we need to fake an `image/` mime type to make the gallery
	 * picker to list GLB files.
	 */
	const GALLERY_GLB_MIME_TYPE = 'image/glb';

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
	 * @param Plugin $plugin Main class instance.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->media = $plugin->get_component( 'media' );
		$this->setup_hooks();
	}

	/**
	 * Gets the gallery settings in the expected json format.
	 *
	 * @return array
	 */
	public function get_config() {
		$screen = null;

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		$config = ! empty( $this->settings->get_value( 'gallery_config' ) ) ?
			$this->settings->get_value( 'gallery_config' ) :
			self::$default_config;

		$this->config = $this->maybe_decode_config( $config );
		$config       = Utils::array_filter_recursive( $this->config ); // Remove empty values.

		$config['cloudName'] = $this->plugin->components['connect']->get_cloud_name();

		/**
		 * Filter the gallery HTML container.
		 *
		 * @param string $selector The target HTML selector.
		 */
		$config['container'] = apply_filters( 'cloudinary_gallery_html_container', '' );

		$credentials = $this->plugin->components['connect']->get_credentials();

		// Do not use privateCdn and secureDistribution on gallery settings page.
		if (
			! empty( $credentials['cname'] )
			&& ( ! $screen instanceof WP_Screen || 'cloudinary_page_cloudinary_gallery' !== $screen->id )
		) {
			$config['secureDistribution'] = $credentials['cname'];
			$config['privateCdn']         = true;
		}

		/**
		 * Filter the gallery configuration.
		 *
		 * @param array $config The current gallery config.
		 */
		$config = apply_filters( 'cloudinary_gallery_config', $config );

		$config['queryParam'] = 'AA';

		// Make sure custom settings are a json string.
		if ( ! empty( $config['customSettings'] ) && is_array( $config['customSettings'] ) ) {
			$config['customSettings'] = wp_json_encode( $config['customSettings'] );
		}

		return $config;
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
			CLOUDINARY_ENDPOINTS_GALLERY,
			array(),
			$this->plugin->version,
			true
		);

		$json_config = wp_json_encode( $this->get_config() );
		wp_add_inline_script( self::GALLERY_LIBRARY_HANDLE, "var CLD_GALLERY_CONFIG = {$json_config};" );

		wp_enqueue_script(
			'cloudinary-gallery-init',
			$this->plugin->dir_url . 'js/gallery-init.js',
			array( self::GALLERY_LIBRARY_HANDLE ),
			$this->plugin->version,
			true
		);
	}

	/**
	 * Enqueue admin UI scripts if needed.
	 */
	public function enqueue_admin_scripts() {
		if ( Utils::get_active_setting() !== $this->settings->get_setting( $this->settings_slug ) ) {
			return;
		}

		$this->block_editor_scripts_styles();

		wp_enqueue_style(
			'cloudinary-gallery-settings-css',
			$this->plugin->dir_url . 'css/gallery-ui.css',
			array(),
			$this->plugin->version
		);

		$script = array(
			'slug'      => 'gallery_config',
			'src'       => $this->plugin->dir_url . 'js/gallery.js',
			'in_footer' => true,
		);

		$asset = $this->get_asset();
		wp_enqueue_script( $script['slug'], $script['src'], $asset['dependencies'], $asset['version'], $script['in_footer'] );

		$color_palette = array_filter( (array) get_theme_support( 'editor-color-palette' ) );
		if ( ! empty( $color_palette ) ) {
			$color_palette = array_shift( $color_palette );
		}
		$color_palette = wp_json_encode( $color_palette );
		wp_add_inline_script( $script['slug'], "var CLD_THEME_COLORS = $color_palette;", 'before' );
	}

	/**
	 * Retrieve asset dependencies.
	 *
	 * @return array
	 */
	private function get_asset() {
		$asset = require $this->plugin->dir_path . 'js/gallery.asset.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

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
			$this->plugin->dir_url . 'css/gallery-block.css',
			array(),
			$this->plugin->version
		);

		wp_enqueue_script(
			'cloudinary-gallery-block-js',
			$this->plugin->dir_url . 'js/gallery-block.js',
			array( 'wp-blocks', 'wp-editor', 'wp-element', self::GALLERY_LIBRARY_HANDLE ),
			$this->plugin->version,
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
			'permission_callback' => function () {
				return Utils::user_can( 'add_gallery', 'edit_posts' );
			},
		);

		return $endpoints;
	}

	/**
	 * Add settings to pages.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {

		$pages['gallery'] = array(
			'page_title'          => __( 'Gallery settings', 'cloudinary' ),
			'menu_title'          => __( 'Gallery settings', 'cloudinary' ),
			'priority'            => 5,
			'requires_connection' => true,
			'sidebar'             => true,
		);

		$panel = array(
			'type'        => 'panel',
			'title'       => __( 'Gallery Settings', 'cloudinary' ),
			'option_name' => 'gallery',
		);

		if ( WooCommerceGallery::woocommerce_active() ) {
			$panel[] = array(
				'type' => 'group',
				array(
					'type'         => 'on_off',
					'slug'         => 'gallery_woocommerce_enabled',
					'title'        => __( 'Replace WooCommerce Gallery', 'cloudinary' ),
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
					'content' => __(
						'The Cloudinary Gallery is available as a new block type which can be inserted to any post or page. Note, this is not available when using the classic editor.',
						'cloudinary'
					),
				),
			);
		}

		$panel[] = array(
			'type'   => 'react',
			'slug'   => 'gallery_config',
			'script' => array(
				'slug' => 'gallery-widget',
				'src'  => $this->plugin->dir_url . 'js/gallery.js',
			),
		);

		$panel[] = array(
			'type'  => 'info_box',
			'icon'  => $this->plugin->dir_url . 'css/images/academy-icon.svg',
			'title' => __( 'Need help?', 'cloudinary' ),
			'text'  => sprintf(
				// Translators: The HTML for opening and closing link tags.
				__(
					'Watch free lessons on how to use the Gallery Settings in the %1$sCloudinary Academy%2$s.',
					'cloudinary'
				),
				'<a href="https://training.cloudinary.com/learn/course/introduction-to-cloudinary-for-wordpress-administrators-70-minute-course-1h85/lessons/using-cloudinary-for-product-galleries-in-woocommerce-and-page-content-906?page=1" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
		);

		$pages['gallery']['settings'] = array(
			$panel,
		);

		return $pages;
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
			// Since the gallery widget uses public ID's, if an attachment is deleted, it can still remain working in a gallery.
			if ( ! wp_get_attachment_url( $attachment['attachmentId'] ) ) {
				continue;
			}

			// Initialize 3D model support.
			if ( self::GALLERY_GLB_MIME_TYPE === get_post_mime_type( $attachment['attachmentId'] ) ) {
				$attachment['mediaType'] = '3d';
			}

			$transformations = $this->media->get_transformations( $attachment['attachmentId'] );
			if ( ! empty( $transformations ) ) {
				$attachment['transformation'] = array( 'transformation' => $transformations );
			}
			$attributes['mediaAssets'][] = $attachment;
		}

		$attributes['cloudName'] = $this->plugin->components['connect']->get_cloud_name();

		$credentials = $this->plugin->components['connect']->get_credentials();

		if ( ! empty( $credentials['cname'] ) ) {
			$attributes['secureDistribution'] = $credentials['cname'];
			$attributes['privateCdn']         = true;
		}

		if ( ! empty( $attributes['customSettings'] ) ) {
			if ( is_string( $attributes['customSettings'] ) && ! is_admin() ) {
				$attributes['customSettings'] = json_decode( $attributes['customSettings'], true );
			}
			$attributes = wp_parse_args( $attributes['customSettings'], $attributes );
		}

		unset( $attributes['selectedImages'], $attributes['customSettings'] );

		$attributes['queryParam'] = 'AA';

		ob_start();
		?>
		<script>
			window.addEventListener( 'load', function() {
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
			function_exists( 'has_block' ) && has_block( 'cloudinary/gallery' )
			&&
			! is_admin()
		) {
			$can = true;
		}

		// Can on back end on block editor and gallery settings page.
		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if (
				! is_null( $screen )
				&& (
					'cloudinary_page_cloudinary_gallery' === $screen->id || ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() )
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
	 * Allow GLB files to be uploaded.
	 *
	 * @param array $mimes The mime types.
	 *
	 * @return array
	 */
	public function add_glb_mime( $mimes ) {
		$mimes['glb'] = self::GALLERY_GLB_MIME_TYPE;

		return $mimes;
	}

	/**
	 * Pass the GLB file type check.
	 *
	 * @param array  $file_data The file data.
	 * @param string $file      The file.
	 * @param string $filename  The filename.
	 *
	 * @return array
	 */
	public function pass_glb_filetype_check( $file_data, $file, $filename ) {
		if ( 'glb' === pathinfo( $filename, PATHINFO_EXTENSION ) ) {
			$file_data['ext']  = 'glb';
			$file_data['type'] = self::GALLERY_GLB_MIME_TYPE;
		}

		return $file_data;
	}

	/**
	 * Allow GLB files to be uploaded to Cloudinary.
	 *
	 * @param array $types The allowed extensions.
	 *
	 * @return array
	 */
	public function allow_glb_for_cloudinary( $types ) {
		$types[] = 'glb';

		return $types;
	}

	/**
	 * Allow GLB files to be delivered from Cloudinary.
	 *
	 * @param bool $is Whether the file is deliverable.
	 * @param int  $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function allow_glb_delivery_from_cloudinary( $is, $attachment_id ) {
		if ( self::GALLERY_GLB_MIME_TYPE === get_post_mime_type( $attachment_id ) ) {
			$is = true;
		}

		return $is;
	}

	/**
	 * Replace image urls from .glb to .png.
	 *
	 * This allows the GLB files to be served as PNG files in the image context.
	 *
	 * @param string $url The URL.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function admin_replace_glb_url( $url, $attachment_id ) {
		if ( ! is_admin() ) {
			return $url;
		}

		if ( self::GALLERY_GLB_MIME_TYPE === get_post_mime_type( $attachment_id ) ) {
			$url = str_replace( '.glb', '.png', $url );
		}
		return $url;
	}

	/**
	 * We pretend that GLB files are images with dimensions of 512x512.
	 *
	 * This allows WordPress to correctly render the GLB files as images in the media library.
	 *
	 * @param array $metadata The attachment metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array
	 */
	public function inject_glb_metadata( $metadata, $attachment_id ) {
		if ( self::GALLERY_GLB_MIME_TYPE === get_post_mime_type( $attachment_id ) ) {
			$metadata['width']  = 512;
			$metadata['height'] = 512;
		}
		return $metadata;
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
		add_filter( 'cloudinary_admin_pages', array( $this, 'register_settings' ) );

		/**
		 * Filter to allow GLB 3D model files to be uploaded.
		 *
		 * WARNING: This is an experimental hook. The only place where GLB files can be used
		 *          is in the Cloudinary Gallery block.
		 *
		 * @hook   cloudinary_allow_glb_upload
		 * @since  3.2.6
		 *
		 * @param bool $allow_glb_upload Whether to allow GLB files to be uploaded.
		 *
		 * @return bool
		 */
		if ( apply_filters( 'cloudinary_allow_glb_upload', false ) ) {
			add_filter( 'upload_mimes', array( $this, 'add_glb_mime' ), 20, 1 ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'pass_glb_filetype_check' ), 10, 3 );
			add_filter( 'cloudinary_allowed_extensions', array( $this, 'allow_glb_for_cloudinary' ) );
			add_filter( 'cloudinary_is_deliverable', array( $this, 'allow_glb_delivery_from_cloudinary' ), 10, 2 );
			add_filter( 'wp_get_attachment_url', array( $this, 'admin_replace_glb_url' ), 11, 2 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'inject_glb_metadata' ), 10, 2 );
		}
	}
}
