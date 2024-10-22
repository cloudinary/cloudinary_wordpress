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
	protected $blueprint = 'wrap|icon/|div|label|title|link/|/title|extra_title/|/label|/div|prefix/|input/|suffix/|description/|tooltip/|/wrap';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	protected static $capture = true;

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

		if ( $this->setting->has_param( 'anchor' ) ) {
			$struct['attributes']['id'] = 'text-' . str_replace( '_', '-', $this->setting->get_slug() );
		}

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
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function link( $struct ) {
		$link = $this->setting->get_param( 'link', array() );
		if ( ! empty( $link ) ) {
			$struct['element']               = 'a';
			$struct['attributes']['class'][] = 'cld-input-label-link';
			$struct['attributes']['href']    = $link['href'];
			$struct['attributes']['target']  = '_blank';
			$struct['content']               = $link['text'];
		}

		return $struct;
	}

	/**
	 * Filter the extra_title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function extra_title( $struct ) {
		$struct['content'] = null;
		if ( $this->setting->has_param( 'extra_title' ) ) {
			$struct['render']              = true;
			$struct['attributes']['class'] = array(
				'cld-tooltip',
			);
			$struct['content']             = $this->setting->get_param( 'extra_title' );
		}

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
		$struct['attributes']['id']      = $this->get_id();
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
			$value                           = $this->setting->get_param( 'suffix' );
			if ( false !== strpos( $value, '@value' ) ) {
				$struct['attributes']['data-suffix'][] = $this->get_id() . '_suffix';
			}
		}

		if ( $this->setting->has_param( 'placeholder' ) ) {
			$struct['attributes']['placeholder'] = $this->setting->get_param( 'placeholder' );
		}

		return $struct;
	}

	/**
	 * Filter the suffix parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function suffix( $struct ) {
		$value = null;

		if ( $this->setting->has_param( 'suffix' ) ) {
			$value = $this->setting->get_param( 'suffix' );
			if ( false !== strpos( $value, '@value' ) ) {
				$struct['attributes']['data-template'] = $value;
				$value                                 = str_replace( '@value', $this->get_value(), $value );
			}
		}
		$struct['attributes']['id'] = $this->get_id() . '_suffix';
		$struct['content']          = $value;

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
		$parts = explode( $this->setting->separator, $this->setting->get_slug() );
		$name  = array_shift( $parts );
		if ( ! empty( $parts ) ) {
			$name .= '[' . implode( $this->setting->separator, $parts ) . ']';
		}

		return $name;
	}

	/**
	 * Get the field ID.
	 *
	 * @return string
	 */
	protected function get_id() {
		return $this->setting->get_slug();
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
