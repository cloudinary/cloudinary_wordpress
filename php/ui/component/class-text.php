<?php
/**
 * Text UI Component.
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
class Text extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|icon/|div|label|title|tooltip/|/title|/label|/div|prefix/|input/|suffix/|description/|/wrap';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	public $capture = true;

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'] = array(
			'cld-input',
			'cld-input-' . $this->type,
		);

		return $struct;
	}

	/**
	 * Filter the label parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function label( $struct ) {

		$struct['attributes']['class'][] = 'cld-input-label';
		$struct['attributes']['for']     = $this->setting->get_slug();

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

		$struct['element']               = 'input';
		$struct['attributes']['name']    = $this->get_name();
		$struct['attributes']['id']      = $this->setting->get_slug();
		$struct['attributes']['value']   = $this->setting->get_value();
		$struct['attributes']['class'][] = 'regular-' . $this->type;
		$struct['render']                = true;

		if ( $this->setting->has_param( 'required' ) ) {
			$struct['attributes']['required'] = 'required';
		}

		if ( $this->setting->has_param( 'prefix' ) ) {
			$struct['attributes']['class'][] = 'prefixed';
		}

		if ( $this->setting->has_param( 'suffix' ) ) {
			$struct['attributes']['class'][] = 'suffixed';
		}

		if ( $this->setting->has_param( 'placeholder' ) ) {
			$struct['attributes']['placeholder'] = $this->setting->get_param( 'placeholder' );
		}

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

		$struct['element']               = 'label';
		$struct['attributes']['class'][] = 'description';
		$struct['attributes']['for']     = $this->setting->get_slug();
		$struct['content']               = $this->setting->get_param( 'description' );

		return $struct;
	}

	/**
	 * Get the field name.
	 *
	 * @return string
	 */
	protected function get_name() {
		return $this->setting->get_option_name() . '[' . $this->setting->get_slug() . ']';
	}

	/**
	 * Sanitize the value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_value( $value ) {
		if ( 0 === strlen( $value ) && $this->setting->has_param( 'default' ) ) {
			$value = $this->setting->get_param( 'default' );
		}

		return sanitize_text_field( $value );
	}
}
