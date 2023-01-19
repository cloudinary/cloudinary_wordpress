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
class Select extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|icon/|div|label|title|/title|prefix/|/label|/div|input|option/|/input|suffix/|description/|tooltip/|/wrap';

	/**
	 * Filter the select input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {

		$struct                          = parent::input( $struct );
		$struct['element']               = 'select';
		$struct['attributes']['name']    = $this->get_name();
		$struct['attributes']['id']      = $this->setting->get_slug();
		$struct['attributes']['class'][] = 'regular-' . $this->type;
		if ( $this->setting->has_param( 'required' ) ) {
			$struct['attributes']['required'] = 'required';
		}

		return $struct;
	}

	/**
	 * Filter the option parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function option( $struct ) {

		$select_options               = array();
		$options                      = $this->setting->get_param( 'options', array() );
		$struct['attributes']['type'] = $this->type;
		$struct['attributes']['name'] = $this->get_name();
		foreach ( $options as $key => $value ) {
			$option = $struct;
			if ( is_int( $key ) ) {
				// Set to value if a non keyed array.
				$key = $value;
			}
			if ( $key === $this->setting->get_value() ) {
				$option['attributes']['selected'] = 'selected';
			}
			$option['attributes']['value'] = $key;
			$option['content']             = $value;
			$select_options[]              = $option;
		}

		return $select_options;
	}
}
