<?php
/**
 * Test_Cloudinary
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Class Test_Cloudinary
 *
 * @package Cloudinary
 */
class Test_Cloudinary extends \WP_UnitTestCase {

	/**
	 * Test _cloudinary_php_version_error().
	 *
	 * @see _cloudinary_php_version_error()
	 */
	public function test_cloudinary_php_version_error() {
		ob_start();
		_cloudinary_php_version_error();
		$buffer = ob_get_clean();
		$this->assertContains( '<div class="error">', $buffer );
	}

	/**
	 * Test _cloudinary_php_version_text().
	 *
	 * @see _cloudinary_php_version_text()
	 */
	public function test_cloudinary_php_version_text() {
		$this->assertContains( 'Cloudinary plugin error:', _cloudinary_php_version_text() );
	}
}
