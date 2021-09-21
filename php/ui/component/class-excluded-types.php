<?php
/**
 * Excluded file types UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Excluded_Types extends text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|div|label|title|link/|/title|/label|/div|input/|description/|/wrap';

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {
		$struct['element']           = 'div';
		$settings                    = $this->setting->get_setting( 'excluded_types' );
		$base                        = $this->get_part( 'input' );
		$base['attributes']['type']  = 'hidden';
		$base['attributes']['name']  = $this->get_name();
		$base['attributes']['value'] = '';
		$struct['children']['base']  = $base;
		$values                      = $settings->get_value();
		$usage                       = $this->setting->get_value( 'connect.last_usage' );

		$free_restrictions = array(
			'pdf',
			'zip',
		);
		foreach ( $values as $ext ) {
			if ( 'free' === strtolower( $usage['plan'] ) && ! in_array( $ext, $free_restrictions, true ) ) {
				continue;
			}

			$id                                  = $this->get_id() . '-' . $ext;
			$closer                              = $this->get_part( 'span' );
			$closer['attributes']['class']       = array(
				'dashicons',
				'dashicons-no-alt',
				'closer',
			);
			$closer['attributes']['data-remove'] = $id;
			if ( 'free' === strtolower( $usage['plan'] ) && in_array( $ext, $free_restrictions, true ) ) {
				$closer['attributes']['data-message'] = __( 'Removal of the following extension requires a support ticket. Click OK, if you already have approval.', 'cloudinary' );
			}
			$closer['render'] = true;

			$tick                          = $this->get_part( 'span' );
			$tick['attributes']['id']      = $id;
			$tick['attributes']['class'][] = 'type';
			$tick['content']               = $ext;
			$tick['children']['closer']    = $closer;

			$value                        = $this->get_part( 'input' );
			$value['attributes']['type']  = 'hidden';
			$value['attributes']['name']  = $this->get_name() . '[]';
			$value['attributes']['value'] = $ext;

			$tick['children']['value'] = $value;

			$struct['children'][ $ext ] = $tick;
		}

		return $struct;
	}

	/**
	 * Sanitize the value.
	 *
	 * @param array $value The value to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_value( $value ) {

		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}
		foreach ( $value as &$type ) {
			$type = sanitize_text_field( $type );
		}

		return $value;
	}
}
