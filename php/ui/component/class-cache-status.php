<?php
/**
 * Base HTML UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use Cloudinary\UI\Component;
use function Cloudinary\get_plugin_instance;

/**
 * Cache Status Component to render components only.
 *
 * @package Cloudinary\UI
 */
class Cache_Status extends Sync {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|status/|/wrap';

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function status( $struct ) {
		$struct['attributes']['class'] = array(
			'notification',
			'dashicons-before',
		);
		$struct['element'] = 'div';
		$files             = get_option( 'cache_files', array() );
		if ( 'on' === $this->setting->get_value( 'enable_site_cache' ) ) {
			//get_plugin_instance()->components['api']->background_request( 'cache_site', array(), 'GET' );
		}

		$struct['element'] = 'div';

		// Set basis.
		$state      = 'notification-success';
		$icon       = 'dashicons-yes-alt';
		$state_text = __( 'Site is cached', 'cloudinary' );

		if ( !empty( $files ) ) {
			$state      = 'notification-syncing';
			$icon       = 'dashicons-update';
			$state_text = __( 'Syncing ' . count($files) . ' now', 'cloudinary' );
		}

		$message                         = $this->get_part( 'span' );
		$message['content']              = $state_text;
		$struct['attributes']['class'][] = $state;
		$struct['attributes']['class'][] = $icon;

		$struct['children']['message'] = $message;

		return $struct;
	}
}
