<?php
/**
 * Panel UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\REST_API;
use Cloudinary\Settings;
use Cloudinary\UI\Component;
use Cloudinary\Settings\Setting;
use Cloudinary\UI\State;

/**
 * Class Component
 *
 * @package Cloudinary\UI
 */
class Panel extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'header|icon/|title_wrap|title/|description/|/title_wrap|collapse/|/header|wrap|body|/body|section/|/wrap|save/';

	/**
	 * Holds the state.
	 *
	 * @var string
	 */
	protected $current_state;

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();
		$this->current_state = $this->state->get_state( $this->setting->get_slug(), $this->setting->get_param( 'collapsible' ) );
	}

	/**
	 * Holds the save button id an options name is set.
	 *
	 * @param array $struct The struct.
	 *
	 * @return array
	 */
	protected function save( $struct ) {
		$struct['element'] = null;
		if ( $this->setting->has_param( 'option_name' ) ) {
			$struct                          = $this->get_part( 'wrap' );
			$struct['attributes']['class'][] = 'cld-submit';
			$button                          = $this->get_part( 'button' );
			$button['content']               = $this->setting->get_param( 'label', __( 'Save Changes', 'cloudinary' ) );
			$button['attributes']['type']    = $this->type;
			$button['attributes']['name']    = 'cld_submission';
			$button['attributes']['value']   = $this->setting->get_param( 'option_name' );
			$classes                         = array(
				'button',
			);
			$button['attributes']['class']   = array_merge( $classes, (array) $this->setting->get_param( 'style', array( 'button-primary' ) ) );
			$struct['children']['button']    = $button;
		}

		return $struct;
	}

	/**
	 * Filter the header parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function header( $struct ) {

		$struct['attributes']['class'][] = 'cld-' . $this->type . '-heading';
		if ( $this->setting->has_param( 'anchor' ) ) {
			$struct['attributes']['id'] = 'panel-' . str_replace( '_', '-', $this->setting->get_slug() );
		}
		if ( $this->setting->has_param( 'collapsible' ) ) {
			$struct['attributes']['class'][]  = 'collapsible';
			$struct['attributes']['data-for'] = $this->setting->get_slug();
		}

		return parent::header( $struct );
	}

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function title_wrap( $struct ) {

		$struct['element']             = 'div';
		$struct['attributes']['class'] = array(
			'cld-ui-title',
		);

		if ( $this->setting->has_param( 'collapsible' ) ) {
			$struct['attributes']['class'][] = 'collapsible';
		}

		return $struct;
	}

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function title( $struct ) {

		$struct['element']             = 'h2';
		$struct['content']             = $this->setting->get_param( 'title' );
		$struct['attributes']['class'] = array(
			'cld-ui-title-head',
		);
		if ( $this->setting->has_param( 'description' ) ) {
			$struct['attributes']['class'][] = 'has-description';
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
		$struct['element'] = null;
		if ( $this->setting->has_param( 'description' ) ) {
			$struct['element']               = 'div';
			$struct['content']               = $this->setting->get_param( 'description' );
			$struct['attributes']['class'][] = 'description';
		}

		return $struct;
	}

	/**
	 * Filter the collapsible parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function collapse( $struct ) {

		if ( $this->setting->has_param( 'collapsible' ) ) {
			$struct['element']                   = 'span';
			$struct['render']                    = true;
			$struct['attributes']['class'][]     = 'dashicons';
			$struct['attributes']['class'][]     = 'open' === $this->current_state ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';
			$struct['attributes']['data-toggle'] = $this->setting->get_slug();
			$struct['attributes']['id']          = $this->setting->get_slug();
		}

		return $struct;
	}

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		if ( $this->setting->has_param( 'title' ) ) {
			$struct['attributes']['class'][] = 'has-heading';

			if ( $this->setting->has_param( 'collapsible' ) ) {
				$struct['attributes']['class'][]    = $this->current_state;
				$struct['attributes']['data-wrap']  = $this->setting->get_slug();
				$struct['attributes']['data-state'] = $this->current_state;
			}
		}

		return parent::wrap( $struct );
	}

	/**
	 * Gets the active child setting.
	 *
	 * @return Setting
	 */
	protected function get_active_setting() {
		return $this->setting->get_root_setting()->get_param( 'active_tab' );
	}

	/**
	 * Filter the section parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function section( $struct ) {
		$struct            = parent::settings( $struct );
		$struct['element'] = null;

		return $struct;
	}
}
