<?php
/**
 * Lazy Load.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Delivery;

use Cloudinary\Connect\Api;
use Cloudinary\Delivery_Feature;
use Cloudinary\Plugin;
use Cloudinary\String_Replace;
use Cloudinary\UI\Component\HTML;
use Cloudinary\Utils;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Lazy_Load extends Delivery_Feature {

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
	 * Lazy_Load constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The main instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		add_filter( 'cloudinary_image_tag-disabled', array( $this, 'js_noscript' ), 10, 2 );
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_action( 'wp', array( $this, 'init_lazy_script_maybe' ) );
		add_filter( 'cloudinary_lazy_load_bypass', array( $this, 'bypass_lazy_load' ), 10, 2 );
	}

	/**
	 * Check if the page request is a dynamic lazy load request.
	 */
	public function init_lazy_script_maybe() {

		$flag = Utils::get_sanitized_text( 'cloudinary_lazy_load_loader' );
		if ( $flag ) {
			$expires = HOUR_IN_SECONDS;
			header( 'Cache-Control: max-age=' . $expires );
			cache_javascript_headers();
			echo $this->get_inline_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
	}

	/**
	 * Maybe bypass the lazy load.
	 *
	 * @param bool  $bypass      Whether to bypass lazy load.
	 * @param array $tag_element The TAG element.
	 *
	 * @return bool
	 */
	public function bypass_lazy_load( $bypass, $tag_element ) {

		// Bypass if eager loading.
		if ( ! empty( $tag_element['loading'] ) && 'eager' === $tag_element['loading'] ) {
			$bypass = true;
		}

		// Sources on Picture tag are not lazyloaded.
		if ( 'source' === $tag_element['tag'] ) {
			$bypass = true;
		}

		/**
		 * Filter the classes that bypass lazy loading.
		 *
		 * @hook   cloudinary_lazy_load_bypass_classes
		 * @since  3.0.9
		 *
		 * @param $classes {array} Classes that bypass the Lazy Load.
		 *
		 * @return {bool}
		 *
		 * @example
		 * <?php
		 *
		 * // Extend bypass lazy load classes to include `skip-lazy`.
		 * add_filter(
		 *    'cloudinary_lazy_load_bypass_classes',
		 *    function( $classes ) {
		 *         $classes[] = 'skip-lazy';
		 *         return $classes;
		 *    }
		 * );
		 */
		$bypass_classes = apply_filters( 'cloudinary_lazy_load_bypass_classes', array( 'cld-bypass-lazy' ) );

		if ( ! $bypass && ! empty( array_intersect( $bypass_classes, $tag_element['atts']['class'] ) ) ) {
			$bypass = true;
		}

		return $bypass;
	}

	/**
	 * Get the inline script for lazy load.
	 *
	 * @retrun string
	 */
	public function get_inline_script() {
		$config = $this->get_config();

		return 'var CLDLB = ' . wp_json_encode( $config ) . ';' . file_get_contents( $this->plugin->dir_path . 'js/inline-loader.js' );
	}

	/**
	 * Get the config for lazy load.
	 *
	 * @return array
	 */
	public function get_config() {
		$config = $this->config; // Get top most config.
		if ( 'off' !== $config['lazy_placeholder'] ) {
			$config['placeholder'] = API::generate_transformation_string( $this->get_placeholder_transformations( $config['lazy_placeholder'] ) );
		}
		$config['base_url'] = $this->media->base_url;

		return $config;
	}

	/**
	 * Wrap image tags in noscript to allow no-javascript browsers to get images.
	 *
	 * @param string $tag         The original html tag.
	 * @param array  $tag_element The original tag_element.
	 *
	 * @return string
	 */
	public function js_noscript( $tag, $tag_element ) {

		$options          = $tag_element['atts'];
		$options['class'] = implode( ' ', $options['class'] );

		unset(
			$options['srcset'],
			$options['sizes'],
			$options['loading'],
			$options['src'],
			$options['class']
		);
		$atts = array(
			'data-image' => wp_json_encode( $options ),
		);

		return HTML::build_tag( 'noscript', $atts ) . $tag . HTML::build_tag( 'noscript', null, 'close' );
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
	 * @param array $tag_element The tag element set.
	 *
	 * @return array
	 */
	public function add_features( $tag_element ) {
		if ( Utils::is_amp() ) {
			return $tag_element;
		}
		static $has_loader = false;
		// Bypass file formats that shouldn't be lazy loaded.
		if (
			in_array(
				$tag_element['format'],
				/**
				 * Filter out file formats for Lazy Load.
				 *
				 * @hook  cloudinary_lazy_load_bypass_formats
				 * @since 3.0.0
				 *
				 * @param $formats {array) The list of formats to exclude.
				 */
				apply_filters( 'cloudinary_lazy_load_bypass_formats', array( 'svg' ) ),
				true
			)
		) {
			return $tag_element;
		}

		/**
		 * Short circuit the lazy load.
		 *
		 * @hook  cloudinary_lazy_load_bypass
		 *
		 * @since 3.0.9
		 *
		 * @param $short_circuit {bool}  The short circuit value.
		 * @param $tag_element   {array} The tag element.
		 *
		 * @return {bool}
		 *
		 * @example
		 * <?php
		 *
		 * // Bypass lazy load for images with ID `feature-image`.
		 * add_filter(
		 *    'cloudinary_lazy_load_bypass',
		 *    function( $bypass, $tag_element ) {
		 *        if ( 'feature-image! === $tag_element['id'] ) {
		 *            $bypass = true;
		 *        }
		 *        return $bypass;
		 *    }
		 * );
		 */
		if ( apply_filters( 'cloudinary_lazy_load_bypass', false, $tag_element ) ) {
			return $tag_element;
		}

		$sizes = array(
			$tag_element['width'],
			$tag_element['height'],
		);

		// Capture the original size.
		$tag_element['atts']['data-size'] = array_filter( $sizes );

		$colors    = $this->config['lazy_custom_color'];
		$animation = array(
			$colors,
		);
		if ( 'on' === $this->config['lazy_animate'] ) {
			preg_match_all( '/(\d\.*)+/', $colors, $matched );
			$fade        = $matched[0];
			$fade[3]     = 0.1;
			$fade        = 'rgba(' . implode( ',', $fade ) . ')';
			$animation[] = $fade;
			$animation[] = $colors;
		}
		$colors = implode( ';', $animation );

		// Add svg placeholder.
		$tag_element['atts']['src'] = Utils::svg_encoded( $tag_element['atts']['width'], $tag_element['atts']['height'], $colors );
		if ( isset( $tag_element['atts']['srcset'] ) ) {
			$tag_element['atts']['data-srcset'] = $tag_element['atts']['srcset'];
			$tag_element['atts']['data-sizes']  = $tag_element['atts']['sizes'];
			unset( $tag_element['atts']['srcset'], $tag_element['atts']['sizes'] );
		}
		if ( ! Utils::is_admin() ) {
			$tag_element['atts']['data-delivery'] = $this->media->get_media_delivery( $tag_element['id'] );
			if ( empty( $tag_element['atts']['onload'] ) ) {
				$tag_element['atts']['onload'] = '';
			}
			$loader = 'null;';
			if ( ! $has_loader && Utils::is_frontend_ajax() ) {
				$has_loader = true;
				$url        = add_query_arg( 'cloudinary_lazy_load_loader', true, trailingslashit( Utils::home_url() ) );
				$loader     = 'document.body.appendChild(document.createElement(\'script\')).src=\'' . $url . '\';this.onload=null;';
			}
			// Since we're appending to the onload, check it isn't already in, as it may run twice i.e full page caching.
			if ( false === strpos( $tag_element['atts']['onload'], 'CLDBind' ) ) {
				$tag_element['atts']['data-cloudinary'] = 'lazy';
				$tag_element['atts']['onload']         .= ';window.CLDBind?CLDBind(this):' . $loader;
			}
		}

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
		if ( Utils::is_frontend_ajax() ) {
			return;
		}

		ob_start();
		Utils::print_inline_tag( $this->get_inline_script() );
		$script        = ob_get_clean();
		$script_holder = '<meta name="cld-loader">';
		$allow         = array(
			'meta' => array(
				'name' => true,
			),
		);
		echo wp_kses( $script_holder, $allow );
		String_Replace::replace( $script_holder, $script );
	}

	/**
	 * Enqueue assets if not AMP.
	 */
	public function maybe_enqueue_assets() {
		if ( ! Utils::is_amp() ) {
			parent::maybe_enqueue_assets();
		}
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {

		return ( ! Utils::is_admin() && ! Utils::is_rest_api() ) || Utils::is_frontend_ajax();
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
			'page_title'          => __( 'Lazy loading', 'cloudinary' ),
			'menu_title'          => __( 'Lazy loading', 'cloudinary' ),
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
						'type' => 'tabs',
						'tabs' => array(
							'image_setting' => array(
								'text' => __( 'Settings', 'cloudinary' ),
								'id'   => 'settings',
							),
							'image_preview' => array(
								'text' => __( 'Preview', 'cloudinary' ),
								'id'   => 'preview',
							),
						),
					),
					array(
						'type' => 'row',
						array(
							'type'   => 'column',
							'tab_id' => 'settings',
							array(
								'type'               => 'on_off',
								'description'        => __( 'Enable lazy loading', 'cloudinary' ),
								'tooltip_text'       => __( 'Lazy loading delays the initialization of your web assets to improve page load times.', 'cloudinary' ),
								'optimisation_title' => __( 'Lazy loading', 'cloudinary' ),
								'slug'               => 'use_lazy_load',
								'default'            => 'on',
							),
							array(
								'type'      => 'group',
								'condition' => array(
									'use_lazy_load' => true,
								),
								array(
									'type'         => 'text',
									'title'        => __( 'Lazy loading threshold', 'cloudinary' ),
									'tooltip_text' => __( 'How far down the page to start lazy loading assets.', 'cloudinary' ),
									'slug'         => 'lazy_threshold',
									'attributes'   => array(
										'style'            => array(
											'width:100px;display:block;',
										),
										'data-auto-suffix' => '*px;em;rem;vh',
									),
									'default'      => '100px',
								),
								array(
									'type'    => 'tag',
									'element' => 'hr',
								),
								array(
									'type'        => 'color',
									'title'       => __( 'Pre-loader color', 'cloudinary' ),
									'description' => __(
										'On page load, the pre-loader is used to fill the space while the image is downloaded, preventing content shift.',
										'cloudinary'
									),
									'slug'        => 'lazy_custom_color',
									'default'     => 'rgba(153,153,153,0.5)',
								),
								array(
									'type'        => 'on_off',
									'description' => __( 'Pre-loader animation', 'cloudinary' ),
									'slug'        => 'lazy_animate',
									'default'     => 'on',
								),
								array(
									'type'    => 'tag',
									'element' => 'hr',
								),
								array(
									'type'        => 'radio',
									'title'       => __( 'Placeholder generation type', 'cloudinary' ),
									'description' => __(
										"Placeholders are low-res representations of the image, that's loaded below the fold. They are then replaced with the actual image, just before it comes into view.",
										'cloudinary'
									),
									'slug'        => 'lazy_placeholder',
									'default'     => 'blur',
									'condition'   => array(
										'use_lazy_load' => true,
									),
									'options'     => array(
										'blur'        => __( 'Blur', 'cloudinary' ),
										'pixelate'    => __( 'Pixelate', 'cloudinary' ),
										'vectorize'   => __( 'Vectorize', 'cloudinary' ),
										'predominant' => __( 'Dominant Color', 'cloudinary' ),
										'off'         => __( 'Off', 'cloudinary' ),
									),
								),
								array(
									'type'    => 'tag',
									'element' => 'hr',
								),
								array(
									'type'         => 'select',
									'slug'         => 'dpr',
									'priority'     => 8,
									'title'        => __( 'DPR settings', 'cloudinary' ),
									'tooltip_text' => __( 'The device pixel ratio to use for your generated images.', 'cloudinary' ),
									'default'      => '2X',
									'options'      => array(
										'off' => __( 'Off', 'cloudinary' ),
										'2X'  => __( 'Auto (2x)', 'cloudinary' ),
										'max' => __( 'Max DPR', 'cloudinary' ),
									),
								),
							),
						),
						array(
							'type'      => 'column',
							'tab_id'    => 'preview',
							'class'     => array(
								'cld-ui-preview',
							),
							'condition' => array(
								'use_lazy_load' => true,
							),
							array(
								'type'    => 'lazyload_preview',
								'title'   => __( 'Preview', 'cloudinary' ),
								'slug'    => 'lazyload_preview',
								'default' => CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE . 'w_600/leather_bag.jpg',
							),
						),
					),
					array(
						'type'  => 'info_box',
						'icon'  => $this->plugin->dir_url . 'css/images/academy-icon.svg',
						'title' => __( 'Need help?', 'cloudinary' ),
						'text'  => sprintf(
							// Translators: The HTML for opening and closing link tags.
							__(
								'Watch free lessons on how to use the Lazy Load Settings in the %1$sCloudinary Academy%2$s.',
								'cloudinary'
							),
							'<a href="https://training.cloudinary.com/learn/course/introduction-to-cloudinary-for-wordpress-administrators-70-minute-course-1h85/lessons/lazily-loading-and-delivering-responsive-images-1003?page=1" target="_blank" rel="noopener noreferrer">',
							'</a>'
						),
					),
				),
			),
		);

		return $pages;
	}
}
