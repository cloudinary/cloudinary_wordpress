<?php
/**
 * Color Field UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Color Component
 *
 * @package Cloudinary\UI
 */
class Color extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|icon/|div|label|title|link/|/title|extra_title/|/label|/div|prefix/|preview/|input/|picker/|suffix/|description/|tooltip/|/wrap';

	/**
	 * Filter the picker parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function picker( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'cld-input-color-picker';

		$struct['attributes']['acp-color']      = $this->setting->get_value();
		$struct['attributes']['acp-show-rgb']   = 'no';
		$struct['attributes']['acp-show-hsl']   = 'no';
		$struct['attributes']['acp-show-hex']   = 'no';
		$struct['attributes']['acp-show-alpha'] = 'yes';
		$struct['attributes']['data-id']             = $this->get_id();
		$struct['render']                       = true;

		return $struct;
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function preview( $struct ) {
		$struct['element']               = 'span';
		$struct['attributes']['class'][] = 'cld-input-color-preview';

		$struct['attributes']['style'] = 'background-color:' . $this->setting->get_value();
		$struct['attributes']['id']    = $this->get_id() . '_preview';
		$struct['render']              = true;

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

		$struct                                     = parent::input( $struct );
		$struct['attributes']['type']               = 'hidden';
		$struct['attributes']['class'][]            = 'cld-input-color';
		$struct['attributes']['data-alpha-enabled'] = true;
		$struct['attributes']['data-default-color'] = $this->setting->get_param( 'default' );

		return $struct;
	}

	/**
	 * Filter the description parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function description( $struct ) {
		$struct            = parent::description( $struct );
		$struct['element'] = 'div';

		return $struct;
	}
}
