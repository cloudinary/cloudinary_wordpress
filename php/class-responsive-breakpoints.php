<?php
/**
 * Responsive breakpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Assets;
use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Responsive_Breakpoints implements Setup, Assets {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the Media instance.
	 *
	 * @var Media
	 */
	protected $media;

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'responsive_breakpoints';

	/**
	 * Flag to determine if we're in the breakpoints change.
	 *
	 * @var bool
	 */
	protected $doing_responsive = false;

	/**
	 * Holds the settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Holds the current post.
	 *
	 * @var int
	 */
	protected $current_post;

	/**
	 * Responsive_Breakpoints constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
		$this->media  = $plugin->get_component( 'media' );
	}

	/**
	 * Get the placeholder generation transformations.
	 *
	 * @param string $placeholder The placeholder to get.
	 *
	 * @return array
	 */
	public function get_placeholder_transformations( $placeholder ) {

		$transformations = array(
			'predominant' => array(
				array(
					'$currWidth'  => 'w',
					'$currHeight' => 'h',
				),
				array(
					'width'        => 'iw_div_2',
					'aspect_ratio' => 1,
					'crop'         => 'pad',
					'background'   => 'auto',
				),
				array(
					'crop'    => 'crop',
					'width'   => 10,
					'height'  => 10,
					'gravity' => 'north_east',
				),
				array(
					'width'  => '$currWidth',
					'height' => '$currHeight',
					'crop'   => 'fill',
				),
				array(
					'fetch_format' => 'auto',
					'quality'      => 'auto',
				),
			),
			'vectorize'   => array(
				array(
					'effect'       => 'vectorize:3:0.1',
					'fetch_format' => 'svg',
				),
			),
			'blur'        => array(
				array(
					'effect'       => 'blur:2000',
					'quality'      => 1,
					'fetch_format' => 'auto',
				),
			),
			'pixelate'    => array(
				array(
					'effect'       => 'pixelate',
					'quality'      => 1,
					'fetch_format' => 'auto',
				),
			),
		);

		return $transformations[ $placeholder ];
	}

	/**
	 * Add features to a tag element set.
	 *
	 * @param array  $tag_element   The tag element set.
	 * @param int    $attachment_id The attachment id.
	 * @param string $original_tag  The original html tag.
	 *
	 * @return array
	 */
	public function add_features( $tag_element, $attachment_id, $original_tag ) {

		$settings = $this->settings->get_value();

		$meta   = wp_get_attachment_metadata( $attachment_id, true );
		$format = $this->media->get_settings()->get_value( 'image_format' );

		$src                       = $tag_element['atts']['src'];
		$transformations           = $this->media->get_transformations_from_string( $src );
		$placehold_transformations = $transformations;
		$original_string           = Api::generate_transformation_string( $transformations );
		$breakpoints               = $this->media->get_settings()->get_value( 'enable_breakpoints' );
		if ( 'on' === $breakpoints ) {
			// Check if first is a size.
			if ( isset( $transformations[0] ) && isset( $transformations[0]['width'] ) || isset( $transformations[0]['height'] ) ) {
				// remove the size.
				array_shift( $transformations );
			}
			$size_str                        = '--size--/' . Api::generate_transformation_string( $transformations );
			$data_url                        = str_replace( $original_string, $size_str, $src );
			$tag_element['atts']['data-src'] = $data_url;
			if ( isset( $tag_element['atts']['srcset'] ) ) {
				unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );
			}
		}

		// placeholder.
		if ( 'off' !== $settings['lazy_placeholder'] ) {
			// Remove the optimize (last) transformation.
			array_pop( $placehold_transformations );
			$placehold_transformations = array_merge( $placehold_transformations, $this->get_placeholder_transformations( $settings['lazy_placeholder'] ) );
			$palcehold_str             = Api::generate_transformation_string( $placehold_transformations );
			$placeholder               = str_replace( $original_string, $palcehold_str, $src );

			$tag_element['atts']['src'] = $placeholder;
			if ( ! empty( $settings['lazy_preloader'] ) ) {
				$tag_element['atts']['data-placeholder'] = $placeholder;
			}
		}

		if ( ! empty( $settings['lazy_preloader'] ) ) {
			$svg                              = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $meta['width'] . '" height="' . $meta['height'] . '"><rect width="100%" height="100%"><animate attributeName="fill" values="rgba(200,200,200,0.3);rgba(200,200,200,1);rgba(200,200,200,0.3)" dur="2s" repeatCount="indefinite" /></rect></svg>';
			$tag_element['atts']['src']       = 'data:image/svg+xml;utf8,' . $svg;
			$tag_element['atts']['data-type'] = $format;
		}

		unset( $tag_element['atts']['loading'] );
		$tag_element['atts']['decoding']   = 'async';
		$tag_element['atts']['data-width'] = $meta['width'];

		return $tag_element;
	}

	/**
	 * Check if component is active.§
	 *
	 * @return bool
	 */
	public function is_active() {

		return ! is_admin();
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {

		wp_register_script( 'cld-responsive-breakpoints', $this->plugin->dir_url . 'js/responsive-breakpoints.js', null, $this->plugin->version, false );
		if ( ! is_admin() ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets() {

		wp_enqueue_script( 'cld-responsive-breakpoints' );
		$breakpoints = $this->media->get_settings()->get_value( 'image_display' );
		wp_add_inline_script( 'cld-responsive-breakpoints', 'var CLDLB = ' . wp_json_encode( $breakpoints ), 'before' );
	}

	/**
	 * Setup the class.
	 */
	public function setup() {

		$this->register_settings();
		if ( ! is_admin() ) {
			$settings = $this->settings->get_value();
			if ( isset( $settings['use_lazy_loading'] ) && 'on' === $settings['use_lazy_loading'] ) {
				add_filter( 'cloudinary_pre_image_tag', array( $this, 'add_features' ), 10, 3 );
			}
		}
	}

	/**
	 * Define the settings.
	 *
	 * @return array
	 */
	public function settings() {

		$args = array(
			'type'     => 'group',
			'title'    => __( 'Lazy Loading', 'cloudinary' ),
			'slug'     => 'lazy_loading',
			'priority' => 9,
			array(
				'type'        => 'on_off',
				'description' => __( 'Enable lazy loading', 'cloudinary' ),
				'slug'        => 'use_lazy_loading',
				'default'     => 'off',
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'use_lazy_loading' => true,
				),
				array(
					'type'        => 'number',
					'title'       => __( 'Lazy loading threshold', 'cloudinary' ),
					'slug'        => 'lazy_threshold',
					'description' => __( ' The threshold', 'cloudinary' ),
					'default'     => 1000,
				),
				array(
					'type'        => 'radio',
					'title'       => __( 'Placeholder generation', 'cloudinary' ),
					'slug'        => 'lazy_placeholder',
					'description' => __( ' The placeholder', 'cloudinary' ),
					'default'     => 'blur',
					'options'     => array(
						'blur'        => __( 'Blur', 'cloudinary' ),
						'pixelate'    => __( 'Pixelate', 'cloudinary' ),
						'vectorize'   => __( 'Vectorize', 'cloudinary' ),
						'predominant' => __( 'Dominant Color', 'cloudinary' ),
						'off'         => __( 'Off', 'cloudinary' ),
					),
				),
				array(
					'type'        => 'checkbox',
					'title'       => __( 'Initial preloader', 'cloudinary' ),
					'slug'        => 'lazy_preloader',
					'description' => __( ' The preloader', 'cloudinary' ),
					'default'     => 'on',
					'options'     => array(
						'on' => __( 'Use an initial preloader', 'cloudinary' ),
					),
				),
				array(
					'type'        => 'checkbox',
					'title'       => __( 'Use custom preloader', 'cloudinary' ),
					'slug'        => 'lazy_custom_preloader',
					'description' => __( ' The custom preloader', 'cloudinary' ),
					'default'     => 'on',
					'condition'   => array(
						'lazy_preloader' => true,
					),
					'options'     => array(
						'on' => __( 'Use a custom preloader', 'cloudinary' ),
					),
				),
			),
		);

		return $args;
	}

	/**
	 * Register the setting under media.
	 */
	protected function register_settings() {

		// Move setting to media.
		$media_settings  = $this->media->get_settings()->get_setting( 'image_display' );
		$condition       = array(
			'use_lazy_loading' => false,
		);
		$settings_params = $this->settings();
		$this->settings  = $this->plugin->settings->create_setting( $this->settings_slug, $settings_params );
		$media_settings->add_setting( $this->settings );

		$bk = $media_settings->get_setting( 'breakpoints' );
		$bk->set_param( 'condition', $condition );
		$bk->rebuild_component();
	}
}
