<?php
/**
 * Tags Input UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Tags_Input extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|title/|input/|selection|capture/|/selection|suffix/|description/|tooltip/|/wrap';

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'] = array(
			'cld-input',
			'cld-' . $this->type,
		);

		return $struct;
	}

	/**
	 * Filter the selection parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function selection( $struct ) {

		$struct['element'] = 'div';
		$struct['render']  = true;

		$struct['attributes']['data-tags-display'] = $this->get_id();
		$struct['attributes']['class']             = array(
			'cld-input-tags',
		);
		// Get the tags.
		$tags = $this->setting->get_value() ? $this->setting->get_value() : array();

		foreach ( $tags as $index => $tag ) {
			$struct['children'][ 'tag-' . $index ] = $this->item( $tag );
		}

		return $struct;
	}

	/**
	 * Create a tag item.
	 *
	 * @param string $tag The tag value.
	 *
	 * @return array
	 */
	protected function item( $tag ) {

		$struct                                = $this->get_part( 'span' );
		$struct['attributes']['data-value']    = $tag;
		$struct['attributes']['data-input-id'] = $this->get_id();
		$struct['attributes']['class']         = array(
			'cld-input-tags-item',
		);
		$text                                  = $this->get_part( 'span' );
		$text['content']                       = $tag;
		$text['attributes']['class']           = array(
			'cld-input-tags-item-text',
		);

		$delete                                   = $this->get_part( 'span' );
		$delete['attributes']['data-tags-delete'] = $this->get_id();

		$delete['render']              = true;
		$delete['attributes']['class'] = array(
			'cld-input-tags-item-delete',
			'dashicons',
			'dashicons-no-alt',
		);

		$struct['children']['tag']    = $text;
		$struct['children']['delete'] = $delete;

		return $struct;
	}

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {
		$struct = parent::input( $struct );
		$value  = ! empty( $struct['attributes']['value'] ) ? $struct['attributes']['value'] : array();

		$struct['attributes']['type']  = 'hidden';
		$struct['attributes']['value'] = wp_json_encode( $value );

		return $struct;
	}

	/**
	 * Filter the capture parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function capture( $struct ) {

		$struct['element']             = 'span';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'cld-input-tags-input',
		);

		$struct['attributes']['data-placeholder'] = $this->setting->get_param( 'placeholder', __( 'Comma/space separated', 'cloudinary' ) );
		$struct['attributes']['data-tags']        = $this->get_id();
		$struct['attributes']['data-format']      = $this->setting->get_param( 'format', 'text' );
		$struct['attributes']['contenteditable']  = 'true';

		return $struct;
	}

	/**
	 * Sanitize the tags.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_value( $value ) {
		$json_maybe = json_decode( $value );
		$value      = array();
		if ( ! empty( $json_maybe ) ) {
			$sanitizer = $this->setting->get_param( 'format', 'text' );
			$callback  = array( $this, 'text' );
			if ( method_exists( $this, $sanitizer ) ) {
				$callback = array( $this, $sanitizer );
			}
			$value = array_map( $callback, $json_maybe );
		}

		return array_filter( $value );
	}

	/**
	 * Sanitize text.
	 *
	 * @param string $value Value to sanitize text field.
	 *
	 * @return string
	 */
	protected function text( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize host.
	 *
	 * @param string $value Value to sanitize as a host.
	 *
	 * @return string
	 */
	protected function host( $value ) {
		if ( ! preg_match( '/^(?:http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)/', $value ) ) {
			$value = 'https://' . $value; // Append scheme to URL.
		}

		return wp_parse_url( $value, PHP_URL_HOST );
	}
}
