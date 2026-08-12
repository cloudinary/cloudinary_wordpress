<?php
/**
 * Link UI Component.
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
class Link extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'link_tag';

	/**
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function link_tag( $struct ) {

		$struct['element']              = 'a';
		$struct['content']              = $this->setting->get_param( 'content' );
		$struct['attributes']['href']   = $this->setting->get_param( 'url' );
		$struct['attributes']['target'] = $this->setting->get_param( 'target', '_blank' );
		$struct['render']               = true;
		// `get_param()` splits on `$this->separator` ('.' by default), so a
		// colon-delimited path here never matched the nested 'attributes'
		// array below it — it silently fell through to the default class
		// list. Read the 'attributes' param directly instead.
		$attributes                    = $this->setting->get_param( 'attributes', array() );
		$struct['attributes']['class'] = isset( $attributes['class'] ) ? $attributes['class'] : array( 'button', 'button-primary' );

		return $struct;
	}
}
