<?php
/**
 * Tests for Setting class.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Settings\Setting;
use Cloudinary\UI\Component;

/**
 * Tests for Plugin class.
 *
 * @group   plugin
 *
 * @package Cloudinary
 */
class Test_Settings extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * @var Settings
	 */
	public $settings;

	/**
	 * @var Setting
	 */
	public $setting;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin  = get_plugin_instance();
		$structure     = array(
			'version'    => '1.0.2',
			'page_title' => __( 'Cloudinary', 'cloudinary' ),
			'menu_title' => __( 'Cloudinary', 'cloudinary' ),
			'capability' => 'manage_options',
			'icon'       => 'dashicons-cloudinary',
			array(
				'type' => 'text',
				'slug' => 'added_setting',
			),
		);
		$this->settings = Settings::create_setting( 'test_setting', $structure );
	}

	/**
	 * Test constructor.
	 *
	 * @see Plugin::__construct()
	 */
	public function test_construct() {
		$this->assertTrue( $this->plugin->settings instanceof Setting );
		$this->assertTrue( $this->settings instanceof Setting );

		$new_instance = new Settings();
		$this->assertEquals( 10, has_action( 'admin_init', array( $new_instance, 'register_wordpress_settings' ) ) );
		$this->assertEquals( 10, has_action( 'admin_menu', array( $new_instance, 'build_menus' ) ) );
	}

	/**
	 *
	 */
	public function test_init_setting() {
		$main       = $this->settings;
		$child      = $main->get_setting( 'added_setting' );
		$main_value = \PHPUnit\Framework\Assert::readAttribute( $main, 'component' );
		$this->assertTrue( is_null( $main_value ) );
		$child_value = \PHPUnit\Framework\Assert::readAttribute( $child, 'component' );
		$this->assertTrue( is_null( $child_value ) );
		Settings::init_setting( 'test_setting' );

		$main_value = \PHPUnit\Framework\Assert::readAttribute( $main, 'component' );
		$this->assertTrue( $main_value instanceof Component );
		$child_value = \PHPUnit\Framework\Assert::readAttribute( $child, 'component' );
		$this->assertTrue( $child_value instanceof Component );
	}

	public function test_build_menus() {
		global $menu;
		$this->assertEmpty( $menu );
		do_action( 'admin_menu' );
		$this->assertTrue( ! empty( $menu ) );
	}

}
