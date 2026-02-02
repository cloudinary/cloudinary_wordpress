<?php
/**
 * Notice UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\REST_API;
use Cloudinary\UI\Component;
use Cloudinary\Utils;

/**
 * Frame Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Notice extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|div|icon/|message/|settings/|dismiss/|/div|/wrap';

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'] = array(
			'cld-notice-box',
			'is-' . $this->setting->get_param( 'level', 'success' ),
		);
		if ( $this->setting->has_param( 'icon' ) ) {
			$struct['attributes']['class'][] = 'has-icon';
		}
		if ( ! empty( $this->setting->get_param( 'dismiss', false ) ) ) {
			$struct['attributes']['class'][] = 'is-dismissible';
		}

		$struct['attributes']['data-dismiss']  = $this->setting->get_slug();
		$struct['attributes']['data-duration'] = $this->setting->get_param( 'duration', 0 );

		return $struct;
	}

	/**
	 * Filter the icon parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function icon( $struct ) {
		$struct['element']             = 'span';
		$struct['attributes']['class'] = array( 'cld-ui-icon' );
		if ( $this->setting->has_param( 'icon' ) ) {
			$struct['attributes']['class'][] = $this->setting->get_param( 'icon' );
			$struct['attributes']['class'][] = 'dashicons';
		}
		return $struct;
	}

	/**
	 * Filter the message part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function message( $struct ) {
		$struct['element'] = 'span';
		$struct['content'] = $this->setting->get_param( 'message' );

		return $struct;
	}

	/**
	 * Filter the message part structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function dismiss( $struct ) {
		$struct['element']             = 'button';
		$struct['attributes']['class'] = array(
			'notice-dismiss',
		);
		if ( ! empty( $this->setting->get_param( 'dismiss', false ) ) ) {
			$struct['render'] = true;
			$this->setting->get_option_parent()->set_param( 'dismissible_notice', true );
		}

		return $struct;
	}

	/**
	 * Renders the component.
	 *
	 * @param bool $echo Flag to echo output or return it.
	 *
	 * @return string
	 */
	public function render( $echo = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.echoFound
		// Render component via parent.
		$return = parent::render();
		$this->setting->set_param( 'rendered', true );
		// Output notice endpoint data only if a dismissible notice has been shown.
		if ( $this->setting->get_option_parent()->has_param( 'dismissible_notice' ) && ! $this->setting->get_option_parent()->has_param( 'notice_scripts' ) ) {
			$args = array(
				'url'   => Utils::rest_url( REST_API::BASE . '/dismiss_notice' ),
				'nonce' => wp_create_nonce( REST_API::NONCE_KEY ),
			);
			wp_add_inline_script( 'cloudinary', 'var CLDIS = ' . wp_json_encode( $args ), 'before' );
			$this->setting->get_option_parent()->set_param( 'notice_scripts', true ); // Prevent repeated rendering.
		}

		return $return;
	}

	/**
	 * Check if component is enabled.
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		return empty( get_transient( $this->setting->get_slug() ) );
	}
}
