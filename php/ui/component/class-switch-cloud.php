<?php
/**
 * Switch Cloud UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;

/**
 * Connect Link Component.
 *
 * @package Cloudinary\UI
 */
class Switch_Cloud extends Submit {

	/**
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function html_button( $struct ) {

		$plugin = get_plugin_instance();
		$url    = add_query_arg(
			array(
				'switch-account' => true,
			),
			$this->setting->get_option_parent()->get_component()->get_url()
		);

		$struct['element']             = 'a';
		$struct['content']             = __( 'Switch Cloud', 'cloudinary' );
		$struct['attributes']['href']  = $url;
		$struct['attributes']['class'] = array(
			'button',
			'button-primary',
		);

		if ( $plugin->get_component( 'connect' )->has_connection_string_constant() ) {
			unset( $struct['attributes']['href'] );
			$struct['element']                = 'button';
			$struct['attributes']['disabled'] = 'disabled';
			$struct['attributes']['title']    = __( 'Connection string defined by constant.', 'cloudinary' );
		}

		return $struct;
	}
}
