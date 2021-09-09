<?php
/**
 * Lazy Load.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Delivery;

use Cloudinary\Delivery_Feature;
use Cloudinary\Connect\Api;
use Cloudinary\Plugin;
use Cloudinary\UI\Component\HTML;
use Cloudinary\Utils;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Lazy_Load extends Delivery_Feature {

	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		add_filter( 'cloudinary_image_tag', array( $this, 'js_noscript' ), 10, 2 );
	}

	public function js_noscript( $tag, $tag_element ) {

		$options = $tag_element['atts'];
		$options['class'] = implode(' ', $options['class'] );
		unset(
			$options['srcset'],
			$options['sizes'],
			$options['loading'],
			$options['src'],
			$options['class'],
		);
		$atts = array(
			'data-image' => wp_json_encode( $options ),
		);

		return HTML::build_tag( 'noscript', $atts ) . $tag . HTML::build_tag( 'noscript', null, 'close' );

	}

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'media_display';

	/**
	 * Holds the enabler slug.
	 *
	 * @var string
	 */
	protected $enable_slug = 'use_lazy_load';

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
	 * @param array $tag_element The tag element set.
	 *
	 * @return array
	 */
	public function add_features( $tag_element ) {

		$cloudinary_url                              = $this->media->cloudinary_url( $tag_element['id'], 'raw', $tag_element['transformations'], null, $tag_element['overwrite_transformations'] );
		$transformations                             = $this->media->get_transformations_from_string( $cloudinary_url );

		$tag_element['atts']['data-transformations'] = API::generate_transformation_string( $transformations, $tag_element['type'] );

		//$svg                   = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$tag_element['atts']['data-size'][0].'" height="'.$tag_element['atts']['data-size'][1].'"><rect width="100%" height="100%"><animate attributeName="fill" values="' . $this->config['lazy_custom_color'] . '" dur="2s" repeatCount="indefinite" /></rect></svg>';

		//$tag_element['atts']['data-src'] = $tag_element['atts']['src'];
		//$tag_element['atts']['src'] = 'data:image/svg+xml;utf8,' . $svg;
		//unset( $tag_element['atts']['srcset'],  $tag_element['atts']['size'] );
		//$tag_element['atts']['onLoad'] = "! this.dataset.loaded ? ( this.src = this.dataset.src.replace('images/',`images/w_\${this.width},h_\${this.height}/`) ) : ''; this.dataset.loaded = true;";
		return $tag_element;

	}

	/**
	 * Register front end hooks.
	 */
	public function register_assets() {
		wp_register_script( 'cld-lazy-load', $this->plugin->dir_url . 'js/lazy-load.js', null, $this->plugin->version, false );
	}

	/**
	 * Apply front end filters on the enqueue_assets hook.
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'cld-lazy-load' );
		$config = $this->config; // Get top most config.

		$svg                   = '<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="100%" height="100%"><animate attributeName="fill" values="' . $config['lazy_custom_color'] . '" dur="2s" repeatCount="indefinite" /></rect></svg>';
		$config['svg_loader']  = 'data:image/svg+xml;utf8,' . $svg;
		if( 'off' !== $config['lazy_placeholder'] ) {
			$config['placeholder'] = API::generate_transformation_string( $this->get_placeholder_transformations( $config['lazy_placeholder'] ) );
		}
		$config['base_url']    = $this->media->base_url;
		wp_add_inline_script( 'cld-lazy-load', 'var CLDLB = ' . wp_json_encode( $config ), 'before' );
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
	 * Add the settings.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function register_settings( $pages ) {

		$pages['lazy_loading'] = array(
			'page_title'          => __( 'Lazy Loading', 'cloudinary' ),
			'menu_title'          => __( 'Lazy Loading', 'cloudinary' ),
			'priority'            => 5,
			'requires_connection' => true,
			'sidebar'             => true,
			'settings'            => array(
				array(
					'type'        => 'panel',
					'title'       => __( 'Lazy Loading', 'cloudinary' ),
					'priority'    => 9,
					'option_name' => 'media_display',
					array(
						'type'        => 'on_off',
						'description' => __( 'Enable lazy loading', 'cloudinary' ),
						'slug'        => 'use_lazy_load',
						'default'     => 'on',
					),
					array(
						'type'      => 'group',
						'condition' => array(
							'use_lazy_load' => true,
						),
						array(
							'type'       => 'text',
							'title'      => __( 'Lazy loading threshold', 'cloudinary' ),
							'slug'       => 'lazy_threshold',
							'attributes' => array(
								'style'            => array(
									'width:100px;display:block;',
								),
								'data-auto-suffix' => '*px;em;rem;vh',
							),
							'default'    => '1000px',
						),
						array(
							'type'      => 'radio',
							'title'     => __( 'Placeholder generation', 'cloudinary' ),
							'slug'      => 'lazy_placeholder',
							'default'   => 'blur',
							'condition' => array(
								'use_lazy_load' => true,
							),
							'options'   => array(
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
				),
			),
		);

		return $pages;
	}
}
