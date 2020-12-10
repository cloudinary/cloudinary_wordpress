<?php
/**
 * Bootstrap PHPUnit related dependencies.
 *
 * @package FooBar
 */

global $_plugin_root;

$_plugin_root = realpath( __DIR__ . '/..' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Travis CI & Vagrant SSH tests directory.
if ( empty( $_tests_dir ) ) {
	$_tests_dir = '/tmp/wordpress-tests';
}

// Composer tests directory.
if ( ! is_dir( $_tests_dir . '/includes/' ) ) {
	$_tests_dir = $_plugin_root . '/vendor/xwp/wordpress-tests/phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/' ) ) {
	trigger_error( 'Unable to locate wordpress-tests', E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugins for testing.
 */
function unit_test_load_plugin_file() {
	global $_plugin_root;
	// Load the plugins.
	require_once $_plugin_root . '/cloudinary.php';
	unset( $_plugin_root );
}

tests_add_filter( 'muplugins_loaded', 'unit_test_load_plugin_file' );

// Run Integration Tests.
require_once $_tests_dir . '/includes/bootstrap.php';
