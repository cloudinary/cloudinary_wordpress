<?php
/**
 * Lazyload Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Connect\Api;
use Cloudinary\Plugin;
use Cloudinary\Delivery\Lazy_Load;
use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

/**
 * Lazyload preview component.
 *
 * @package Cloudinary\UI
 */
class Lazyload_Preview extends Breakpoints_Preview {

	/**
	 * Holds the Lazy_Load instance.
	 *
	 * @var Lazy_Load
	 */
	protected $lazyload;

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();
		$this->plugin   = get_plugin_instance();
		$this->lazyload = $this->plugin->get_component( 'lazy_load' );
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['style'] = 'position:relative;';

		// bar.
		$bar                            = $this->get_part( 'div' );
		$bar['attributes']['id']        = 'progress-bar';
		$bar['attributes']['class'][]   = 'progress-bar';
		$bar['render']                  = true;
		$struct['children']['progress'] = $bar;

		// Initial Image.
		$image                                        = $this->get_preloader_image();
		$image['attributes']['id']                    = 'preloader-image';
		$preloader_box                                = $this->make_sized_image_box( 1, $image );
		$preloader_box['attributes']['style']         = array(
			'width:100%',
		);
		$preloader_box['children']['text']['content'] = __( 'Pre-loader', 'cloudinary' );
		$preloader_box['attributes']['class'][]       = 'main-image';
		$struct['children']['preloader']              = $preloader_box;

		$placeholder_size                               = '85%';
		$placeholder_box                                = $this->makePlaceholder( 'predominant' );
		$placeholder_box['children']['text']['content'] = __( 'Placeholder: Predominant color', 'cloudinary' );
		$placeholder_box['attributes']['style'][]       = 'width:' . $placeholder_size;
		$struct['children']['predominant']              = $placeholder_box;

		$placeholder_box                                = $this->makePlaceholder( 'vectorize' );
		$placeholder_box['children']['text']['content'] = __( 'Placeholder: Vectorize', 'cloudinary' );
		$placeholder_box['attributes']['style'][]       = 'width:' . $placeholder_size;
		$struct['children']['vectorize']                = $placeholder_box;

		$placeholder_box                                = $this->makePlaceholder( 'blur' );
		$placeholder_box['children']['text']['content'] = __( 'Placeholder: Blur', 'cloudinary' );
		$placeholder_box['attributes']['style'][]       = 'width:' . $placeholder_size;
		$struct['children']['blur']                     = $placeholder_box;

		$placeholder_box                                = $this->makePlaceholder( 'pixelate' );
		$placeholder_box['children']['text']['content'] = __( 'Placeholder: Pixelate', 'cloudinary' );
		$placeholder_box['attributes']['style'][]       = 'width:' . $placeholder_size;
		$struct['children']['pixelate']                 = $placeholder_box;

		$image['attributes']['src']                     = $this->config['lazyload_preview'];
		$image['attributes']['id']                      = 'lazyload-image';
		$image['render']                                = true;
		$loadedimage_box                                = $this->make_sized_image_box( 1, $image );
		$loadedimage_box['children']['text']['content'] = __( 'Loaded image', 'cloudinary' );
		$loadedimage_box['attributes']['style'][]       = 'width:65%';

		$struct['children']['loaded'] = $loadedimage_box;

		return $struct;
	}

	/**
	 * Get the SVG preloader image.
	 *
	 * @return array
	 */
	protected function get_preloader_image() {
		$tag_element = array(
			'tag'    => 'img',
			'type'   => 'image',
			'crop'   => false,
			'width'  => '600px',
			'height' => '400px',
			'format' => 'jpg',
			'atts'   => array(
				'src'        => $this->config['lazyload_preview'],
				'width'      => '600px',
				'height'     => '400px',
				'id'         => 'lazyload-image',
				'data-color' => $this->config['lazy_custom_color'],
				'data-svg'   => Utils::svg_encoded(),
			),
		);

		$tag_element         = $this->lazyload->add_features( $tag_element );
		$image               = $this->get_part( 'img' );
		$image['attributes'] = $tag_element['atts'];
		$image['render']     = true;

		return $image;
	}

	/**
	 * Get a placeholder image.
	 *
	 * @param string $type The type of placeholder to get.
	 *
	 * @return array
	 */
	protected function makePlaceholder( $type ) {
		$placeholder                      = $this->get_part( 'img' );
		$transformation                   = Api::generate_transformation_string( $this->lazyload->get_placeholder_transformations( $type ), 'image' );
		$placeholder['attributes']['src'] = str_replace( 'leather_bag', $transformation . '/leather_bag', $this->config['lazyload_preview'] );
		$placeholder['render']            = true;
		$box                              = $this->make_sized_image_box( 100, $placeholder );
		$box['attributes']['id']          = 'placeholder-' . $type;
		if ( $type !== $this->config['lazy_placeholder'] ) {
			$box['attributes']['style'] = array( 'display:none;' );
		}
		$box['children']['image'] = $placeholder;

		return $box;
	}

	/**
	 * Filter the url parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function url( $struct ) {

		$struct['element']             = 'span';
		$struct['attributes']['id']    = 'preview-details';
		$struct['attributes']['class'] = array(
			'global-transformations-url-link',
		);

		// Details path.
		$details_path                         = $this->get_part( 'span' );
		$button                               = $this->get_part( 'button' );
		$button['attributes']['id']           = 'preview-cycle';
		$button['attributes']['type']         = 'button';
		$button['content']                    = __( 'Run loading simulation', 'cloudinary' );
		$button['attributes']['class']        = array(
			'button',
			'button-primary',
		);
		$button['render']                     = true;
		$details_path['children']['checkbox'] = $button;
		$details_path['attributes']['class']  = array(
			'global-transformations-url-resource',
		);

		// Build parts.
		$struct['children']['details'] = $details_path;

		return $struct;
	}

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		$plugin = get_plugin_instance();
		wp_enqueue_script( 'lazyload-preview', $plugin->dir_url . 'js/lazyload-preview.js', array(), $plugin->version, true );
	}

	/**
	 * Setup the JS data before rendering.
	 */
	protected function pre_render() {
		$this->enqueue_scripts();
	}
}
