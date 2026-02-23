<?php
/**
 * Notice component for notice based classes.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Component;

/**
 * Defines an object that requires setup.
 */
interface Notice {

	/**
	 * Set admin notice.
	 *
	 * @return array
	 */
	public function get_notices();
}
