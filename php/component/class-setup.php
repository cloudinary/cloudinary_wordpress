<?php
/**
 * Interface for config based classes.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Component;

/**
 * Defines an object that requires setup.
 */
interface Setup {

	/**
	 * Setup the object.
	 */
	public function setup(); // phpcs:ignore
}
