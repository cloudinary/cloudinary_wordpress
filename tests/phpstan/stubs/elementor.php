<?php
/**
 * Minimal Elementor stubs for static analysis.
 *
 * Elementor is an optional integration that is only loaded when the Elementor
 * plugin is active. These stubs describe the small subset of Elementor's API
 * used by the Cloudinary integration so PHPStan can analyse
 * php/integrations/class-elementor.php.
 *
 * @package Cloudinary
 */

namespace Elementor {
	class Element_Base {
		/**
		 * Get the element settings prepared for display.
		 *
		 * @return array
		 */
		public function get_settings_for_display() {}

		/**
		 * Get the unique CSS selector for the element.
		 *
		 * @return string
		 */
		public function get_unique_selector() {}
	}

	class Plugin {
		/**
		 * Holds the files manager instance.
		 *
		 * @var object
		 */
		public $files_manager;

		/**
		 * Get the Elementor plugin instance.
		 *
		 * @return \Elementor\Plugin
		 */
		public static function instance() {}
	}
}

namespace Elementor\Core\Files\CSS {
	use Elementor\Element_Base;

	class Post {
		/**
		 * Get the unique selector for an element.
		 *
		 * @param Element_Base $element The Elementor element.
		 *
		 * @return string
		 */
		public function get_element_unique_selector( $element ) {}

		/**
		 * Get the stylesheet instance.
		 *
		 * @return object
		 */
		public function get_stylesheet() {}
	}
}
