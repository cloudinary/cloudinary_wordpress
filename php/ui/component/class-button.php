<?php
/**
 * Submit UI Component.
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
class Button extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|html_button/|settings/|/wrap';

	/**
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function html_button( $struct ) {

		$struct['element']             = 'button';
		$struct['content']             = $this->setting->get_param( 'label', __( 'Save Changes', 'cloudinary' ) );
		$struct['attributes']['type']  = $this->type;
		$struct['attributes']['id']    = $this->setting->get_slug();
		$classes                       = array(
			'button',
		);
		$struct['attributes']['class'] = array_merge( $classes, (array) $this->setting->get_param( 'style', array( 'button-primary' ) ) );

		return $struct;
	}
}
