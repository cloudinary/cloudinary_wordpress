<?php
/**
 * Lazy Load.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;
use \Cloudinary\Settings\Setting;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Lazy_Load implements Setup {

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
	protected $settings_slug = 'lazy_load';

	/**
	 * Holds the settings.
	 *
	 * @var Setting
	 */
	protected $settings;

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

		$src = $tag_element['atts']['src'];
		if ( ! $this->media->is_cloudinary_url( $src ) ) {
			$src = $this->media->cloudinary_url( $attachment_id );
		}
		$tag_element['atts']['data-src'] = $src;
		$transformations                 = $this->media->get_transformations_from_string( $src );
		$placehold_transformations       = $transformations;
		$original_string                 = Api::generate_transformation_string( $transformations );

		// placeholder.
		if ( 'off' !== $settings['lazy_placeholder'] ) {
			// Remove the optimize (last) transformation.
			array_pop( $placehold_transformations );
			$placehold_transformations               = array_merge( $placehold_transformations, $this->get_placeholder_transformations( $settings['lazy_placeholder'] ) );
			$palcehold_str                           = Api::generate_transformation_string( $placehold_transformations );
			$placeholder                             = str_replace( $original_string, $palcehold_str, $src );
			$tag_element['atts']['data-placeholder'] = $placeholder;
		}

		$color_str = $settings['lazy_custom_color'];
		if ( 'on' === $settings['lazy_animate'] ) {
			$colors    = explode( ',', rtrim( substr( $settings['lazy_custom_color'], 5 ), ')' ) );
			$color1    = 'rgba(' . $colors[0] . ',' . $colors[1] . ',' . $colors[2] . ',' . $colors[3] . ')';
			$color2    = 'rgba(' . $colors[0] . ',' . $colors[1] . ',' . $colors[2] . ',0)';
			$color_str = $color1 . ';' . $color2 . ';' . $color1;
		}
		$svg                              = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $meta['width'] . '" height="' . $meta['height'] . '"><rect width="100%" height="100%"><animate attributeName="fill" values="' . $color_str . '" dur="2s" repeatCount="indefinite" /></rect></svg>';
		$tag_element['atts']['src']       = 'data:image/svg+xml;utf8,' . $svg;
		$tag_element['atts']['data-type'] = $format;

		unset( $tag_element['atts']['loading'] );
		$tag_element['atts']['decoding']   = 'async';
		$tag_element['atts']['data-width'] = $meta['width'];

		return $tag_element;
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {

		return ! is_admin();
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
			'priority' => 9,
			array(
				'type'        => 'on_off',
				'description' => __( 'Enable lazy loading', 'cloudinary' ),
				'slug'        => 'use_lazy_loading',
				'default'     => 'on',
			),
			array(
				'type'      => 'group',
				'condition' => array(
					'use_lazy_loading' => true,
				),
				array(
					'type'       => 'text',
					'title'      => __( 'Lazy loading threshold', 'cloudinary' ),
					'slug'       => 'lazy_threshold',
					'attributes' => array(
						'style'            => array(
							'width:100px;display:block;',
						),
						'data-auto-suffix' => '*px;em;rem;vw;vh',
					),
					'default'    => '1000px',
				),
				array(
					'type'        => 'radio',
					'title'       => __( 'Placeholder generation', 'cloudinary' ),
					'slug'        => 'lazy_placeholder',
					'description' => __( 'The placeholder', 'cloudinary' ),
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
					'type'    => 'color',
					'title'   => __( 'Use custom color', 'cloudinary' ),
					'slug'    => 'lazy_custom_color',
					'default' => 'rgba(153,153,153,0.5)',
				),
				array(
					'type'    => 'on_off',
					'title'   => __( 'Animate', 'cloudinary' ),
					'slug'    => 'lazy_animate',
					'default' => 'on',
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
		$media_settings = $this->media->get_settings()->get_setting( 'image_display' );

		$settings_params = $this->settings();
		$this->settings  = $media_settings->create_setting( $this->settings_slug, $settings_params, $media_settings );

		// Reset the option parent.
		$this->settings->get_option_parent()->set_value( null );

		$condition = array(
			'use_lazy_loading' => false,
		);
		$bk        = $media_settings->get_setting( 'breakpoints' );
		$bk->set_param( 'condition', $condition );
		$bk->rebuild_component();

		$image_breakpoints = $media_settings->get_setting( 'image_breakpoints' );
		$bytes_step        = $image_breakpoints->get_setting( 'bytes_step' );
		$condition         = array(
			'use_lazy_loading' => false,
		);
		$bytes_step->set_param( 'condition', $condition );
		$bytes_step->rebuild_component();

	}
}
