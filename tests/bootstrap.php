<?php
/**
 * PHPUnit bootstrap file
 */

define( 'WP_TESTS_DIR', getenv( 'WP_PHPUNIT__DIR' ) );
define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) );
// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
$_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
// Give access to tests_add_filter() function.
require_once $_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		// test set up, plugin activation, etc.
		require dirname( __DIR__ ) . '/cloudinary.php';
	}
);

tests_add_filter('pre_option_cloudinary_connect', function(){
	$settings = array (
		'cloudinary_url' => 'cloudinary://token:secret@cloud_name',
	);
	return $settings;
} );

// Start up the WP testing environment.
require $_phpunit_dir . '/includes/bootstrap.php';
