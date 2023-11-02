<?php
/**
 * Select UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\Settings\Setting;
use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Crops extends Select {

	/**
	 * Holds the demo file name.
	 *
	 * @var string
	 */
	protected $demo_files = array(
		'sample.jpg',
		'lady.jpg',
		'horses.jpg',
	);

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|hr/|label|title/|prefix/|/label|info_box/|input/|/wrap';

	/**
	 * Enqueue scripts this component may use.
	 */
	public function enqueue_scripts() {
		wp_enqueue_media();
	}

	/**
	 * Filter the hr structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function hr( $struct ) {

		if ( 'image_settings' === $this->setting->get_parent()->get_slug() ) {
			$struct['element'] = 'hr';
			$struct['render']  = true;
		}

		return $struct;
	}

	/**
	 * Filter the info box structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function info_box( $struct ) {
		$panel_toggle = new Setting( 'info_box_crop_gravity' );
		$panel_toggle->set_param( 'title', __( 'What is Crop and Gravity control?', 'cloudinary' ) );
		$panel_toggle->set_param(
			'text',
			sprintf(
				// translators: %1$s: link to Crop doc, %2$s: link to Gravity doc.
				__( 'This feature allows you to fine tune the behaviour of the %1$s and %2$s per registered crop size of the delivered images.', 'cloudinary' ),
				'<a href="https://cloudinary.com/documentation/resizing_and_cropping#resize_and_crop_modes" target="_blank">' . __( 'Crop', 'cloudinary' ) . '</a>',
				'<a href="https://cloudinary.com/documentation/resizing_and_cropping#control_gravity" target="_blank">' . __( 'Gravity', 'cloudinary' ) . '</a>'
			)
		);
		$panel_toggle->set_param( 'icon', get_plugin_instance()->dir_url . 'css/images/crop.svg' );
		$title_toggle = new Info_Box( $panel_toggle );

		$struct['element'] = 'div';
		$struct['content'] = $title_toggle->render();

		return $struct;
	}

	/**
	 * Filter the select input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {

		$mode                             = $this->setting->get_param( 'mode', 'demos' );
		$wrapper                          = $this->get_part( 'div' );
		$wrapper['attributes']['class'][] = 'cld-size-items';
		if ( 'full' === $mode ) {
			$wrapper['attributes']['data-base'] = dirname( get_plugin_instance()->get_component( 'connect' )->api->cloudinary_url( '' ) );
		} else {
			$wrapper['attributes']['data-base'] = 'https://res.cloudinary.com/demo/image/upload';
		}

		$value = $this->setting->get_value();
		if ( empty( $value ) ) {
			$value = array();
		}
		$sizes = Utils::get_registered_sizes();

		$selector                                = $this->make_selector();
		$wrapper['children']['control-selector'] = $selector;
		foreach ( $sizes as $size => $details ) {
			if ( empty( $details['crop'] ) ) {
				continue;
			}
			$row                          = $this->get_part( 'div' );
			$row['attributes']['class'][] = 'cld-size-items-item';
			$row['attributes']['class'][] = 'crop-preview';
			$row['content']               = $size;

			$image            = $this->get_part( 'img' );
			$image['content'] = $size;
			$size_array       = array();
			if ( ! empty( $details['width'] ) ) {
				$size_array[]                 = 'w_' . $details['width'];
				$image['attributes']['width'] = $details['width'];
			}
			if ( ! empty( $details['height'] ) ) {
				$size_array[]                  = 'h_' . $details['height'];
				$image['attributes']['height'] = $details['height'];
			}
			$image['attributes']['data-size'] = implode( ',', $size_array );
			$size_key                         = $details['width'] . 'x' . $details['height'];
			if ( empty( $value[ $size_key ] ) ) {
				$value[ $size_key ] = '';
			}
			$row['children']['size']  = $image;
			$row['children']['input'] = $this->make_input( $this->get_name() . '[' . $size_key . ']', $value[ $size_key ] );
			// Set the placeholder.
			$placeholder = 'c_fill,g_auto';

			if ( 'thumbnail' === $size ) {
				$placeholder = 'c_thumb,g_auto';
			}
			$row['children']['input']['children']['input']['attributes']['placeholder'] = $placeholder;

			$wrapper['children'][ $size ] = $row;

		}

		return $wrapper;
	}

	/**
	 * Make an image selector.
	 */
	protected function make_selector() {
		$selector                          = $this->get_part( 'div' );
		$selector['attributes']['class'][] = 'cld-image-selector';
		$mode                              = $this->setting->get_param( 'mode', 'demos' );

		/**
		 * Filter the demo files.
		 *
		 * @hook   cloudinary_registered_sizes
		 * @since  3.1.3
		 *
		 * @param $demo_files {array} array of demo files.
		 *
		 * @return {array}
		 */
		$examples = apply_filters( 'cloudinary_demo_crop_files', $this->demo_files );
		if ( 'full' === $mode ) {
			$public_id = $this->setting->get_root_setting()->get_param( 'preview_id' );
			if ( ! empty( $public_id ) ) {
				$examples = array(
					$public_id,
				);
			}
		}
		foreach ( $examples as $index => $file ) {
			$name                             = pathinfo( $file, PATHINFO_FILENAME );
			$item                             = $this->get_part( 'span' );
			$item['attributes']['data-image'] = $file;
			if ( 0 === $index ) {
				$item['attributes']['data-selected'] = true;

			}
			$item['attributes']['class'][] = 'cld-image-selector-item';

			$item['content']                           = $name;
			$selector['children'][ 'image-' . $index ] = $item;
		}

		return $selector;
	}

	/**
	 * Make an input line.
	 *
	 * @param string $name  The name of the input.
	 * @param string $value The value.
	 *
	 * @return array
	 */
	protected function make_input( $name, $value ) {

		$wrapper                        = $this->get_part( 'span' );
		$wrapper['attributes']['class'] = array(
			'crop-size-inputs',
		);

		$label                      = $this->get_part( 'label' );
		$label['attributes']['for'] = $name;
		$label['content']           = __( 'Disable', 'cloudinary' );

		$check                          = $this->get_part( 'input' );
		$check['attributes']['type']    = 'checkbox';
		$check['attributes']['name']    = $name;
		$check['attributes']['id']      = $name;
		$check['attributes']['value']   = '--';
		$check['attributes']['class'][] = 'disable-toggle';
		$check['attributes']['title']   = __( 'Disable gravity and crops', 'cloudinary' );
		if ( '--' === $value ) {
			$check['attributes']['checked'] = 'checked';
		}

		$input                          = $this->get_part( 'input' );
		$input['attributes']['type']    = 'text';
		$input['attributes']['name']    = $name;
		$input['attributes']['value']   = '--' !== $value ? $value : '';
		$input['attributes']['class'][] = 'regular-text';

		$wrapper['children']['input'] = $input;
		$wrapper['children']['label'] = $label;
		$wrapper['children']['check'] = $check;

		return $wrapper;
	}

	/**
	 * Sanitize the value.
	 *
	 * @param array $value The value to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_value( $value ) {
		return $value;
	}
}
