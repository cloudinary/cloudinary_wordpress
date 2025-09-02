<?php
/**
 * Row UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;

/**
 * Row Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Row extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|settings/|/wrap';

	/**
	 * Gets the wrap structs.
	 *
	 * @param array $struct The wrap struct.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'][] = 'cld-row';
		if ( $this->setting->has_param( 'align' ) ) {
			$struct['attributes']['class'][] = 'align-' . $this->setting->get_param( 'align' );
		}

		return $struct;
	}
}
