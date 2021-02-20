<?php
/**
 * Tests for Plugin class.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Tests for Plugin class.
 *
 * @group   plugin
 *
 * @package Cloudinary
 */
class Test_Plugin extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = new Plugin();
	}

	/**
	 * Test constructor.
	 *
	 * @see Plugin::__construct()
	 */
	public function test_construct() {
		$this->assertEquals( 9, has_action( 'plugins_loaded', array( $this->plugin, 'init' ) ) );
		$this->assertEquals( 11, has_action( 'admin_enqueue_scripts', array( $this->plugin, 'register_enqueue_styles' ) ) );
		$this->assertEquals( 10, has_action( 'init', array( $this->plugin, 'setup' ) ) );
		$this->assertEquals( 10, has_action( 'init', array( $this->plugin, 'register_assets' ) ) );
	}

	/**
	 * Test for init() method.
	 *
	 * @see Plugin::init()
	 */
	public function test_init() {
		$plugin = get_plugin_instance();
		$plugin->init();

		$this->assertInternalType( 'array', $plugin->config );
		$this->assertInternalType( 'array', $plugin->settings->get_value() );
	}

	/**
	 * Test locate_plugin.
	 *
	 * @see Plugin::locate_plugin()
	 */
	public function test_locate_plugin() {
		$location = $this->plugin->locate_plugin();
		$actual_dir = basename( dirname( dirname( __DIR__ ) ) );
		$this->assertEquals( $actual_dir, $location['dir_basename'] );
		$this->assertContains( 'plugins/cloudinary', $location['dir_path'] );
		$this->assertContains( 'plugins/cloudinary', $location['dir_url'] );
	}

	/**
	 * Test relative_path.
	 *
	 * @see Plugin::relative_path()
	 */
	public function test_relative_path() {
		$this->assertEquals( 'plugins/cloudinary', $this->plugin->relative_path( '/srv/www/wordpress-develop/src/wp-content/plugins/cloudinary', 'wp-content', '/' ) );
		$this->assertEquals(
			'themes/twentysixteen/plugins/cloudinary',
			$this->plugin->relative_path( '/srv/www/wordpress-develop/src/wp-content/themes/twentysixteen/plugins/cloudinary', 'wp-content', '/' )
		);
	}
}
