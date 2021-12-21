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

	/**
	 * Holds the singleton instance.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Get an instance of this class.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			$called         = get_called_class();
			self::$instance = new $called( get_plugin_instance() );
		}

		return self::$instance;
	}

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
