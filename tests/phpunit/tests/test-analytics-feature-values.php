<?php
/**
 * Tests for the feature-value normalisation used by analytics events.
 *
 * @package Cloudinary
 */

use function Cloudinary\get_plugin_instance;

/**
 * Covers Analytics::normalize_feature_value().
 */
class Test_Analytics_Feature_Values extends WP_UnitTestCase {

	/**
	 * The analytics component.
	 *
	 * @var \Cloudinary\Analytics
	 */
	protected $analytics;

	/**
	 * Set up the analytics component.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->analytics = get_plugin_instance()->get_component( 'analytics' );
	}

	/**
	 * Settings outside the allowlist, including secrets, are never emitted.
	 *
	 * @return void
	 */
	public function test_non_allowlisted_settings_are_dropped() {
		$this->assertNull( $this->analytics->normalize_feature_value( 'connect', 'cloudinary_url', 'cloudinary://key:secret@demo' ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'connect', 'cloudinary_folder', 'my-folder' ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'lazy_loading', 'lazy_custom_color', 'rgba(0,0,0,1)' ) );
	}

	/**
	 * A setting is only allowlisted on its own settings page.
	 *
	 * @return void
	 */
	public function test_settings_are_allowlisted_per_page() {
		$this->assertSame( 'avif', $this->analytics->normalize_feature_value( 'image_settings', 'image_format', 'avif' ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'video_settings', 'image_format', 'avif' ) );
	}

	/**
	 * A missing value is left out rather than reported as off.
	 *
	 * @return void
	 */
	public function test_missing_values_are_dropped() {
		$this->assertNull( $this->analytics->normalize_feature_value( 'lazy_loading', 'use_lazy_load', null ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'image_settings', 'image_freeform', null ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'image_settings', 'crop_sizes', null ) );
	}

	/**
	 * On/off toggles become booleans.
	 *
	 * @return void
	 */
	public function test_toggles_become_booleans() {
		$this->assertTrue( $this->analytics->normalize_feature_value( 'lazy_loading', 'use_lazy_load', 'on' ) );
		$this->assertFalse( $this->analytics->normalize_feature_value( 'lazy_loading', 'use_lazy_load', 'off' ) );
		$this->assertFalse( $this->analytics->normalize_feature_value( 'video_settings', 'adaptive_streaming', '' ) );
	}

	/**
	 * Numeric fields become ints, and unreadable ones are dropped.
	 *
	 * @return void
	 */
	public function test_numbers_become_ints() {
		$this->assertSame( 200, $this->analytics->normalize_feature_value( 'responsive', 'pixel_step', '200' ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'responsive', 'breakpoints', '' ) );
	}

	/**
	 * Enum values pass through as strings.
	 *
	 * @return void
	 */
	public function test_enums_stay_strings() {
		$this->assertSame( 'avif', $this->analytics->normalize_feature_value( 'image_settings', 'image_format', 'avif' ) );
		$this->assertSame( 'auto:good', $this->analytics->normalize_feature_value( 'image_settings', 'image_quality', 'auto:good' ) );
	}

	/**
	 * The lazy-loading threshold is only sent when it is a CSS length.
	 *
	 * @return void
	 */
	public function test_lazy_threshold_only_keeps_css_lengths() {
		$this->assertSame( '100px', $this->analytics->normalize_feature_value( 'lazy_loading', 'lazy_threshold', ' 100px ' ) );
		$this->assertSame( '1.5em', $this->analytics->normalize_feature_value( 'lazy_loading', 'lazy_threshold', '1.5em' ) );
		$this->assertSame( '20%', $this->analytics->normalize_feature_value( 'lazy_loading', 'lazy_threshold', '20%' ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'lazy_loading', 'lazy_threshold', 'jane@example.com' ) );
		$this->assertNull( $this->analytics->normalize_feature_value( 'lazy_loading', 'lazy_threshold', '' ) );
	}

	/**
	 * Free-text transformations are reduced to a presence flag.
	 *
	 * @return void
	 */
	public function test_freeform_is_reduced_to_presence() {
		$this->assertTrue( $this->analytics->normalize_feature_value( 'image_settings', 'image_freeform', 'l_text:Arial_20:Jane Doe' ) );
		$this->assertFalse( $this->analytics->normalize_feature_value( 'video_settings', 'video_freeform', '  ' ) );
	}

	/**
	 * Crop sizes are summarised as the number of sizes with a custom crop.
	 *
	 * @return void
	 */
	public function test_crop_sizes_count_custom_crops() {
		$crops = array(
			'150x150'  => 'c_thumb,g_face',
			'300x300'  => '',
			'1024x768' => '--',
			'768x0'    => 'c_fill,g_auto',
		);

		$this->assertSame( 2, $this->analytics->normalize_feature_value( 'image_settings', 'crop_sizes', $crops ) );
		$this->assertSame( 0, $this->analytics->normalize_feature_value( 'image_settings', 'crop_sizes', '' ) );
	}
}
