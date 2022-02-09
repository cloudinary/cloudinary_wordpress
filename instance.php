<?php
/**
 * Instantiates the Cloudinary plugin
 *
 * @package Cloudinary
 */

namespace Cloudinary;

global $cloudinary_plugin;

require_once __DIR__ . '/php/class-plugin.php';

$cloudinary_plugin = new Plugin();

/**
 * Cloudinary Plugin Instance
 *
 * @return Plugin
 */
function get_plugin_instance() {
	global $cloudinary_plugin;

	return $cloudinary_plugin;
}

/**
 * Get an instance of the CLI Class.
 */
function cloudinary_cli_instance() {
	if ( class_exists( '\WPCOM_VIP_CLI_Command' ) ) {
		return new CLI_VIP();
	}

	return new CLI();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$plugin_instance = get_plugin_instance();
	$cli             = cloudinary_cli_instance();
	$cli->setup_cloudinary( $plugin_instance );
	\WP_CLI::add_command( 'cloudinary', $cli );
}
