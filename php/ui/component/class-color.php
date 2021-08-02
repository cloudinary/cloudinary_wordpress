<?php
/**
 * Color Field UI Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI\Component;

use function Cloudinary\get_plugin_instance;

/**
 * Class Color Component
 *
 * @package Cloudinary\UI
 */
class Color extends Text {

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		parent::enqueue_scripts();
		$instance = get_plugin_instance();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker-alpha', $instance->dir_url . 'js/wp-color-picker-alpha.js', array( 'wp-color-picker' ), $instance->version, true );
	}

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {
		$struct                                     = parent::input( $struct );
		$struct['attributes']['type']               = 'text';
		$struct['attributes']['data-alpha-enabled'] = true;
		$struct['attributes']['data-default-color'] = $this->setting->get_param( 'default' );

		return $struct;
	}

	/**
	 * Render the component.
	 *
	 * @param false $echo Flag to echo out or return.
	 *
	 * @return string|null
	 */
	public function render( $echo = false ) {
		$return = parent::render( $echo );
		?>
		<script>
			( function( $ ) {
				// Add Color Picker init script.
				$( function() {
					$( '#<?php echo esc_attr( $this->setting->get_slug() ); ?>' ).wpColorPicker();
				} );
			} )( jQuery );
		</script>
		<?php

		return $return;
	}

}
