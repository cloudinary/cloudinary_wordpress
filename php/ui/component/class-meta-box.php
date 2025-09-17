<?php
/**
 * Metabox UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Stand_Alone Class Component
 *
 * @package Cloudinary\UI
 */
class Meta_Box extends Page {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|form/|settings/|/wrap';

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {
		$struct = parent::wrap( $struct );
		unset( $struct['attributes']['id'] );

		return $struct;
	}

	/**
	 * Filter the form parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function form( $struct ) {

		$struct['element'] = 'div';
		// Don't run action if page has tabs, since the page actions will be different for each tab.
		$struct['children'] = $this->page_actions();

		return $struct;
	}

	/**
	 * Creates the options page and action inputs.
	 *
	 * @return array
	 */
	protected function page_actions() {

		$option_name = $this->get_option_name();

		// Set the attributes for the field.
		$option_atts                    = array(
			'type'  => 'hidden',
			'name'  => 'cloudinary-active-metabox',
			'value' => $option_name,
		);
		$inputs                         = array(
			'active'      => $this->get_part( 'input' ),
			'no_redirect' => $this->get_part( 'input' ),
		);
		$inputs['active']['attributes'] = $option_atts;
		$inputs['active']['content']    = true;

		$option_atts['name']  = '_cld_no_redirect';
		$option_atts['value'] = 'true';

		$inputs['no_redirect']['attributes'] = $option_atts;
		$inputs['no_redirect']['content']    = true;

		return $inputs;
	}
}
