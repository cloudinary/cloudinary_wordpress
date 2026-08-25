<?php
/**
 * PHPUnit bootstrap for the Cloudinary plugin.
 *
 * Boots the WordPress core test suite that wp-env mounts into its
 * containers, and loads this plugin into it.
 *
 * Run with:
 *
 *     npm run env:start
 *     npm run test:unit
 *
 * @package Cloudinary
 */

/*
 * The plugin root, two levels up from tests/phpunit. Defined as a constant
 * because PHPUnit includes this bootstrap from inside a function, so local
 * variables here are not available in the global scope that the plugin
 * loader callback runs in.
 */
define( 'CLOUDINARY_PLUGIN_DIR', dirname( __DIR__, 2 ) );

/*
 * The WordPress core test suite. wp-env mounts it at /wordpress-phpunit and
 * exports WP_TESTS_DIR in every container, so the env var is the source of
 * truth. The fallback keeps the suite usable outside wp-env.
 */
$cloudinary_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $cloudinary_tests_dir ) {
	$cloudinary_tests_dir = '/wordpress-phpunit';
}

$cloudinary_tests_dir = rtrim( $cloudinary_tests_dir, '/\\' );

if ( ! file_exists( $cloudinary_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the WordPress test suite at ' . $cloudinary_tests_dir . PHP_EOL;
	echo 'Start the environment first with `npm run env:start`, then run `npm run test:unit`.' . PHP_EOL;
	exit( 1 );
}

/*
 * The core bootstrap requires the PHPUnit Polyfills. Point it at this
 * plugin's own Composer installation, because the WordPress install that
 * wp-env provides does not ship them.
 */
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', CLOUDINARY_PLUGIN_DIR . '/vendor/yoast/phpunit-polyfills' );
}

require_once $cloudinary_tests_dir . '/includes/functions.php';

/**
 * Load the plugin once the test suite has loaded the must-use plugins.
 *
 * @return void
 */
function cloudinary_manually_load_plugin() {
	require CLOUDINARY_PLUGIN_DIR . '/cloudinary.php';
}

tests_add_filter( 'muplugins_loaded', 'cloudinary_manually_load_plugin' );

require $cloudinary_tests_dir . '/includes/bootstrap.php';
