<?php
/**
 * Select UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Radio extends Text {

	/**
	 * Filter the select input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {

		$return                        = array();
		$base_class                    = 'cld-input-' . $this->type;
		$options                       = $this->setting->get_param( 'options', array() );
		$struct['attributes']['type']  = $this->type;
		$struct['attributes']['name']  = $this->get_name();
		$struct['attributes']['class'] = array(
			$base_class,
		);
		foreach ( $options as $key => $value ) {
			// Create the label wrapper.
			$id                           = $this->setting->get_slug() . '_' . $key;
			$label                        = $this->get_part( 'label' );
			$label['attributes']['for']   = $id;
			$label['attributes']['class'] = array(
				$base_class . '-label',
			);
			if ( true === $this->setting->get_param( 'inline' ) ) {
				$label['attributes']['class'][] = 'list-inline';
			}
			// Create the label content.
			$content            = $this->get_part( 'span' );
			$content['content'] = $value;
			// Create the option.
			$option = $struct;
			if ( is_int( $key ) ) {
				// Set to value if a non keyed array.
				$key = $value;
			}
			if ( $this->is_checked( $key ) ) {
				$option['attributes']['checked'] = 'checked';
			}
			$option['attributes']['value']        = $key;
			$option['attributes']['id']           = $id;
			$label['children'][ $key ]            = $option;
			$label['children'][ $key . '_label' ] = $content;
			$return[]                             = $label;
		}

		return $return;
	}

	/**
	 * Check if the option is in the value.
	 *
	 * @param string $key The hey to check if it's checked.
	 *
	 * @return bool
	 */
	protected function is_checked( $key ) {

		return in_array( $key, (array) $this->setting->get_value(), true );
	}
}
