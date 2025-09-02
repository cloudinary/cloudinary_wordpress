<?php
/**
 * Line Stat Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;

/**
 * Line state Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Progress_Bar extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|title/|bar_box|bar/|value/|/bar_box|/wrap';

	/**
	 * Gets the blueprint part structure.
	 *
	 * @param array $struct The part structure array.
	 *
	 * @return array|null
	 */
	protected function title( $struct ) {
		$struct                        = parent::title( $struct );
		$struct['element']             = 'h4';
		$struct['attributes']['class'] = array(
			'cld-progress-box-title',
		);

		return $struct;
	}

	/**
	 * Gets the blueprint part structure.
	 *
	 * @param array $struct The part structure array.
	 *
	 * @return array|null
	 */
	protected function bar_box( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'cld-progress-box',
		);

		return $struct;
	}

	/**
	 * Gets the blueprint part structure.
	 *
	 * @param array $struct The part structure array.
	 *
	 * @return array|null
	 */
	protected function bar( $struct ) {

		$struct['element'] = 'div';

		$struct['attributes']['style']    = array(
			'background-color:' . $this->setting->get_param( 'color', '#304ec4' ) . ';',
			'width:0%;',
		);
		$struct['attributes']['data-key'] = $this->setting->get_param( 'percent_key', $this->setting->get_param( 'slug' ) . '-total' );
		$struct['attributes']['onfocus']  = 'this.style.width = this.dataset.value';
		$struct['attributes']['class']    = array(
			'cld-progress-box-line',
		);

		$struct['render'] = true;

		return $struct;
	}

	/**
	 * Gets the blueprint part structure.
	 *
	 * @param array $struct The part structure array.
	 *
	 * @return array|null
	 */
	protected function value( $struct ) {

		$struct['element']                 = 'span';
		$struct['content']                 = '&nbsp;';
		$struct['attributes']['data-text'] = $this->setting->get_param( 'value_key', $this->setting->get_param( 'slug' ) . '-value' );
		$struct['attributes']['class']     = array(
			'cld-progress-box-line-value',
		);

		return $struct;
	}
}
