<?php
/**
 * Progress ring UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Ring Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Progress_Ring extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap/';

	/**
	 * Gets the wrap structs.
	 *
	 * @param array $struct The wrap struct.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'][]       = 'cld-progress-circular';
		$struct['render']                      = true;
		$struct['attributes']['data-value']    = $this->setting->get_param( 'value', 100 );
		$struct['attributes']['data-text']     = $this->setting->get_param( 'text' );
		$struct['attributes']['data-color']    = $this->setting->get_param( 'color', '#304ec4' );
		$struct['attributes']['data-progress'] = 'circle';

		return $struct;
	}
}
