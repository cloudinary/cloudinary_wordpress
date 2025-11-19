<?php
/**
 * Asset Preview UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Asset_Preview_Settings extends Asset {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'edit|label|transformation/|/label|save/|/edit';


	/**
	 * Filter the edit parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function edit( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'cld-asset-edit',
		);

		return $struct;
	}

	/**
	 * Filter the edit parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function label( $struct ) {

		$struct['element']             = 'label';
		$struct['content']             = $this->setting->get_param( 'label', __( 'Transformations', 'cloudinary' ) );
		$struct['attributes']['class'] = array(
			'cld-asset-preview-label',
		);

		return $struct;
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function transformation( $struct ) {

		$struct['element']             = 'input';
		$struct['attributes']['type']  = 'text';
		$struct['attributes']['id']    = 'cld-asset-edit-transformations';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'regular-text',
			'cld-asset-preview-input',
		);

		return $struct;
	}

	/**
	 * Filter the preview parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function save( $struct ) {

		$struct['element']            = 'button';
		$struct['attributes']['type'] = 'button';
		$struct['attributes']['id']   = 'cld-asset-edit-save';

		$struct['render']              = true;
		$struct['content']             = $this->setting->get_param( 'save', __( 'Save', 'cloudinary' ) );
		$struct['attributes']['class'] = array(
			'button',
			'button-primary',
			'cld-asset-edit-button',
		);

		return $struct;
	}
}
