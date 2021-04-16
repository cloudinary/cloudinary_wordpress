<?php
/**
 * On off UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

/**
 * Class On_Off Component
 *
 * @package Cloudinary\UI
 */
class On_Off extends Text {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'label|title|tooltip/|/title|prefix/|/label|wrap|description_left/|control|false_value/|input/|slider/|/control|description/|/wrap';

	/**
	 * Filter the false_value parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function false_value( $struct ) {

		$struct['element']             = 'input';
		$struct['attributes']['type']  = 'hidden';
		$struct['attributes']['name']  = $this->get_name();
		$struct['attributes']['value'] = 'off';
		unset( $struct['attributes']['class'] );
		unset( $struct['attributes']['data-bound'] );
		$struct['render'] = true;

		return $struct;
	}

	/**
	 * Sets the left hand description.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array|null
	 */
	protected function description_left( $struct ) {
		if ( $this->setting->has_param( 'description_left' ) ) {
			$struct                          = $this->description( $struct );
			$struct['attributes']['class'][] = 'left';
			$struct['content']               = $this->setting->get_param( 'description_left' );

			return $struct;
		}

		return null;
	}

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {

		$struct['element']                       = 'input';
		$struct['attributes']['type']            = 'checkbox';
		$struct['attributes']['name']            = $this->get_name();
		$struct['attributes']['id']              = $this->setting->get_slug();
		$struct['attributes']['value']           = 'on';
		$struct['attributes']['data-controller'] = $this->setting->get_slug();
		if ( 'on' === $this->setting->get_value() ) {
			$struct['attributes']['checked'] = 'checked';
		}
		$struct['attributes']['class'][] = 'cld-ui-input';

		$struct['render'] = true;
		if ( $this->setting->has_param( 'master' ) ) {
			$struct['attributes']['data-master'] = wp_json_encode( $this->setting->get_param( 'master' ) );
		}

		if ( true === $this->setting->get_param( 'disabled', false ) || true === $this->setting->has_param( 'master_required', false ) && empty( $struct['attributes']['data-master'] ) ) {
			$struct['attributes']['disabled'] = 'disabled';
		}

		return $struct;
	}

	/**
	 * Filter the control parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function control( $struct ) {

		$struct['element']             = 'label';
		$struct['attributes']['class'] = array(
			'cld-input-' . $this->type . '-control',
		);
		if ( true === $this->setting->get_param( 'mini', false ) ) {
			$struct['attributes']['class'][] = 'mini';
		}
		if (
			true === $this->setting->get_param( 'disabled', false ) ||
			true === $this->setting->get_param( 'master_required', false ) &&
			empty(
				$this->setting->get_param(
					'master',
					array()
				)
			)
		) {
			$struct['attributes']['class'][] = 'disabled';
		}

		return $struct;
	}

	/**
	 * Filter the slider parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function slider( $struct ) {
		$struct['element']             = 'span';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'cld-input-' . $this->type . '-control-slider',
		);
		$struct['attributes']['style'] = array();
		if ( $this->setting->has_param( 'disabled_color' ) ) {
			$struct['attributes']['style'][] = 'background-color:' . $this->setting->get_param( 'disabled_color' ) . ';';
		}

		$on                         = $this->get_part( 'i' );
		$on['attributes']['class']  = array(
			'icon-on',
			'dashicons',
			$this->setting->get_param( 'on' ),
		);
		$on['render']               = true;
		$off                        = $this->get_part( 'i' );
		$off['attributes']['class'] = array(
			'icon-off',
			'dashicons',
			$this->setting->get_param( 'off' ),
		);
		$off['render']              = true;

		$struct['children']['on']  = $on;
		$struct['children']['off'] = $off;

		return $struct;
	}

	/**
	 * Sanitize the value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return bool
	 */
	public function sanitize_value( $value ) {
		$allowed = array(
			'on',
			'some',
			'off',
		);

		return in_array( $value, $allowed, true ) ? $value : 'off';
	}
}
