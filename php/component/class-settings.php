<?php
/**
 * Interface for settings based classes.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Component;

use Cloudinary\Settings\Setting;

/**
 * Defines an object that requires settings.
 */
interface Settings {

	/**
	 * Register Settings.
	 *
	 * @param Setting $setting The core setting.
	 */
	public function register_settings( $setting );

}
