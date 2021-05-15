<?php
/**
 * Responsive breakpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Assets;

/**
 * Class Responsive_Breakpoints
 *
 * @package Cloudinary
 */
class Responsive_Breakpoints implements Assets {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Responsive_Breakpoints constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false', PHP_INT_MAX );
	}

	/**
	 * Check if component is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return ! is_admin();
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {
		wp_register_script( 'cld-responsive-breakpoints', $this->plugin->dir_url . 'js/responsive-breakpoints.js', null, $this->plugin->version, false );
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'cld-responsive-breakpoints' );
	}
}
