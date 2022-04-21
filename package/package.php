<?php
/**
 * Plugin Name: Cloudinary Update Tester
 * Plugin URI:
 * Description: Test Cloudinary Plugin Update Process (This will deactivate itself, once activated.)
 * Version: 1.1
 * Author: XWP
 * Author URI: https://xwp.co
 * Text Domain: cld-update-tester
 * License: GPL2+
 *
 * @package Cloudinary
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alter the update plugins object.
 *
 * @param object $data plugin update data.
 *
 * @return object
 */
function cld_test_check_update( $data ) {
	$slug        = 'cloudinary-image-management-and-manipulation-in-the-cloud-cdn/cloudinary.php';
	$file        = plugin_dir_path( __FILE__ ) . 'cloudinary-wordpress-STABLETAG.zip';
	$version     = 'STABLETAG';
	$this_plugin = 'cloudinary-update-tester-STABLETAG/cloudinary-update-tester.php';
	if ( ! empty( $data->no_update ) ) {
		if ( ! empty( $data->no_update[ $slug ] ) ) {
			$data->no_update[ $slug ]->package     = $file;
			$data->no_update[ $slug ]->new_version = $version;
			$data->response[ $slug ]               = $data->no_update[ $slug ];
			unset( $data->no_update[ $slug ] );
			deactivate_plugins( $this_plugin );
		}
	}
	// Add if available.
	if ( ! empty( $data->response ) ) {
		$slug = 'cloudinary-image-management-and-manipulation-in-the-cloud-cdn/cloudinary.php';
		if ( ! empty( $data->response[ $slug ] ) ) {
			$data->response[ $slug ]->package     = $file;
			$data->response[ $slug ]->new_version = $version;
			$data->response[ $slug ]              = $data->response[ $slug ];
			deactivate_plugins( $this_plugin );
		}
	}

	return $data;
}

add_filter( 'pre_set_site_transient_update_plugins', 'cld_test_check_update', 100 );

/**
 * Delete the update transient on activation.
 */
function cld_test_init_update() {
	delete_site_transient( 'update_plugins' );
}

register_activation_hook( __FILE__, 'cld_test_init_update' );
