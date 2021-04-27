<?php
/**
 * On off UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class On_Off Component
 *
 * @package Cloudinary\UI
 */
class Icon_Toggle extends On_Off {

	/**
	 * Filter the slider parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function slider( $struct ) {
		$struct                        = parent::slider( $struct );
		$struct['element']             = 'i';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'cld-input-icon-toggle-control-slider',
		);

		return $struct;
	}

}
