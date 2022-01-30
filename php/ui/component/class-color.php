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
	protected $blueprint = 'wrap|icon/|div|label|title|link/|/title|extra_title/|/label|/div|prefix/|preview/|input/|reset/|picker/|suffix/|description/|tooltip/|/wrap';

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
		$struct['attributes']['data-id']        = $this->get_id();
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
		$struct['element']               = 'button';
		$struct['attributes']['class'][] = 'cld-input-color-grid';
		$struct['attributes']['type']    = 'button';
		$struct['attributes']['id']      = $this->get_id() . '_container';

		$preview                          = $this->get_part( 'span' );
		$preview['attributes']['class'][] = 'cld-input-color-preview';

		$preview['attributes']['style'] = 'background-color:' . $this->setting->get_value();
		$preview['attributes']['id']    = $this->get_id() . '_preview';
		$preview['render']              = true;

		$text                          = $this->get_part( 'span' );
		$text['attributes']['class'][] = 'cld-input-color-text';
		$text['content']               = __( 'Select Color', 'cloudinary' );
		$struct['children']['text']    = $text;
		$struct['children']['preview'] = $preview;

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
		$struct['attributes']['type']               = 'text';
		$struct['attributes']['class'][]            = 'cld-input-color';
		$struct['attributes']['data-alpha-enabled'] = true;
		$struct['attributes']['data-default-color'] = $this->setting->get_param( 'default' );

		return $struct;
	}

	/**
	 * Filter the input reset parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function reset( $struct ) {

		$struct['element']                          = 'button';
		$struct['attributes']['type']               = 'button';
		$struct['attributes']['id']                 = $this->get_id() . '_default';
		$struct['attributes']['class'][]            = 'button';
		$struct['attributes']['class'][]            = 'button-small';
		$struct['attributes']['data-default-color'] = $this->setting->get_param( 'default' );

		$struct['content'] = __( 'Default', 'cloudinary' );

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
