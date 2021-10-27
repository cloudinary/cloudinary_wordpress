<?php
/**
 * Singleton Trait handles a single instance.
 *
 * @package   Cloudinary\Traits
 */

namespace Cloudinary\Traits;

use Cloudinary;
use function Cloudinary\get_plugin_instance;
/**
 * Trait Singleton_Trait
 *
 * @package Cloudinary\Traits
 */
trait Singleton_Trait {

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
}
