<?php
/**
 * Tests for the Cloudinary\Utils helpers.
 *
 * @package Cloudinary
 */

use Cloudinary\Utils;

/**
 * Covers the pure static helpers in php/class-utils.php.
 */
class Test_Utils extends WP_UnitTestCase {

	/**
	 * Dot notation keys expand into a nested array.
	 *
	 * @return void
	 */
	public function test_expand_dot_notation_creates_nested_arrays() {
		$expanded = Utils::expand_dot_notation(
			array(
				'image.quality' => 'auto',
				'image.format'  => 'auto',
				'video.quality' => 'auto:eco',
			)
		);

		$expected = array(
			'image' => array(
				'quality' => 'auto',
				'format'  => 'auto',
			),
			'video' => array(
				'quality' => 'auto:eco',
			),
		);

		$this->assertSame( $expected, $expanded );
	}

	/**
	 * Keys without the separator are left alone.
	 *
	 * @return void
	 */
	public function test_expand_dot_notation_leaves_flat_keys_untouched() {
		$this->assertSame(
			array( 'quality' => 'auto' ),
			Utils::expand_dot_notation( array( 'quality' => 'auto' ) )
		);
	}

	/**
	 * A custom separator is honoured.
	 *
	 * @return void
	 */
	public function test_expand_dot_notation_accepts_a_custom_separator() {
		$this->assertSame(
			array(
				'image' => array(
					'quality' => 'auto',
				),
			),
			Utils::expand_dot_notation( array( 'image|quality' => 'auto' ), '|' )
		);
	}

	/**
	 * A flat array has no nesting.
	 *
	 * @return void
	 */
	public function test_array_depth_of_a_flat_array_is_zero() {
		$this->assertSame( 0, Utils::array_depth( array( 'a', 'b', 'c' ) ) );
	}

	/**
	 * An empty array has no nesting.
	 *
	 * @return void
	 */
	public function test_array_depth_of_an_empty_array_is_zero() {
		$this->assertSame( 0, Utils::array_depth( array() ) );
	}

	/**
	 * Nesting is measured from the deepest branch.
	 *
	 * @return void
	 */
	public function test_array_depth_measures_the_deepest_branch() {
		$data = array(
			'shallow' => array( 'one' ),
			'deep'    => array(
				'deeper' => array(
					'deepest' => array( 'value' ),
				),
			),
		);

		$this->assertSame( 3, Utils::array_depth( $data ) );
	}

	/**
	 * Path parts are returned for a plain ASCII path.
	 *
	 * @return void
	 */
	public function test_pathinfo_returns_the_path_parts() {
		$pathinfo = Utils::pathinfo( 'wp-content/uploads/2026/08/sample.jpg' );

		$this->assertSame( 'sample.jpg', $pathinfo['basename'] );
		$this->assertSame( 'sample', $pathinfo['filename'] );
		$this->assertSame( 'jpg', $pathinfo['extension'] );
		$this->assertSame( 'wp-content/uploads/2026/08', $pathinfo['dirname'] );
	}

	/**
	 * Non ASCII file names survive, which plain pathinfo() cannot guarantee
	 * because it is locale dependent.
	 *
	 * @return void
	 */
	public function test_pathinfo_keeps_non_ascii_file_names() {
		$pathinfo = Utils::pathinfo( 'wp-content/uploads/2026/08/aufnahme-schön.jpg' );

		$this->assertSame( 'aufnahme-schön.jpg', $pathinfo['basename'] );
		$this->assertSame( 'aufnahme-schön', $pathinfo['filename'] );
		$this->assertSame( 'jpg', $pathinfo['extension'] );
	}

	/**
	 * A single element can be requested with a flag.
	 *
	 * @return void
	 */
	public function test_pathinfo_returns_a_single_element_for_a_flag() {
		$this->assertSame(
			'sample.jpg',
			Utils::pathinfo( 'wp-content/uploads/2026/08/sample.jpg', PATHINFO_BASENAME )
		);
	}
}
