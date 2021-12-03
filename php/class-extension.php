<?php
/**
 * Extension class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component;
use Cloudinary\Traits\Singleton_Trait;

/**
 * Class extension
 */
abstract class Extension implements Component\Assets {

	use Singleton_Trait;

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Extension constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register assets to be used for the class.
	 */
	public function register_assets() {
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets() {
	}

	/**
	 * Check if the extension is active on the page (for assets to be loaded).
	 *
	 * @return bool|void
	 */
	public function is_active() {
	}
}
