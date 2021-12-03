<?php
/**
 * Breakpoints Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings\Setting;
use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;

/**
 * Breakpoints preview component.
 *
 * @package Cloudinary\UI
 */
class Breakpoints_Preview extends Image_Preview {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|notice/|preview_frame|title/|preview/|refresh/|spinner/|/preview_frame|url_frame|url/|/url_frame|/wrap';

	/**
	 * Holds breakpoints settings.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();
		$this->config = $this->setting->get_value( 'media_display' );
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

		$size = $this->config['max_width'];
		// Initial Image.
		$image                              = $this->get_part( 'img' );
		$image['attributes']['src']         = $this->config['breakpoints_preview'];
		$image['render']                    = true;
		$image_box                          = $this->make_sized_image_box( $size, $image );
		$image_box['attributes']['class'][] = 'main-image';
		$image_box['attributes']['id']      = 'main-image';
		$struct['children'][ $size ]        = $image_box;
	
		return $struct;
	}

	/**
	 * Create a sized box for the preview size.
	 *
	 * @param int   $size  The size of the box.
	 * @param array $image The image add.
	 *
	 * @return array
	 */
	protected function make_sized_image_box( $size, $image ) {
		$image_box                        = $this->get_part( 'div' );
		$image_box['attributes']['style'] = array(
			'width:' . $size / $this->config['max_width'] * 100 . '%;',
		);
		$image_box['children']['image']   = $image;
		$image_box['attributes']['class'] = array( 'preview-image' );

		$text                          = $this->get_part( 'span' );
		$text['content']               = $size . 'px';
		$text['attributes']['class'][] = 'preview-text';
		$image_box['children']['text'] = $text;

		return $image_box;
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
		$details_path           = $this->get_part( 'span' );
		$details_path['render'] = true;

		$details_path['attributes']['class'] = array(
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
		wp_enqueue_script( 'breakpoints-preview', $plugin->dir_url . 'js/breakpoints-preview.js', array(), $plugin->version, true );
	}

	/**
	 * Setup the JS data before rendering.
	 */
	protected function pre_render() {
		$this->enqueue_scripts();
	}
}
