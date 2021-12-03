<?php
/**
 * Return to referrer UI Component.
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
class Referrer_Link extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'link/';

	/**
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function link( $struct ) {
		$struct['element'] = null;
		$referrer          = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_URL );

		if ( $referrer ) {
			$struct['element']             = 'a';
			$struct['attributes']['href']  = $referrer;
			$struct['attributes']['class'] = array(
				'cld-referrer-link',
			);
			// Icon.
			$icon                        = $this->get_part( 'span' );
			$icon['render']              = true;
			$icon['attributes']['class'] = array(
				'dashicons',
				'dashicons-arrow-left-alt',
			);
			$struct['children']['icon']  = $icon;

			// Link Text.
			$link                       = $this->get_part( 'span' );
			$link['element']            = 'span';
			$link['content']            = $referrer;
			$struct['children']['link'] = $link;

		}

		return $struct;
	}
}
