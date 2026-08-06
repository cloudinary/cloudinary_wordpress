<?php
/**
 * Interface for settings based classes.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Component;

use Cloudinary\Settings as CoreSettings;

/**
 * Defines an object that requires settings.
 */
interface Settings {

	/**
	 * Init Settings Object.
	 *
	 * @param CoreSettings $setting The core setting.
	 */
	public function init_settings( $setting );
}
