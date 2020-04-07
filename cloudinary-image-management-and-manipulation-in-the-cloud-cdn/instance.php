<?php
/**
 * Instantiates the Cloudinary plugin
 *
 * @package Cloudinary
 */

namespace Cloudinary;

define( 'CLDN_ASSET_DEBUG', defined( 'DEBUG_SCRIPTS' ) ? '' : '.min' );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once __DIR__ . '/php/class-plugin.php';

/**
 * Cloudinary Plugin Instance
 *
 * @return Plugin
 */
function get_plugin_instance() {
	static $cloudinary_plugin;

	if ( null === $cloudinary_plugin ) {
		$cloudinary_plugin = new Plugin();
	}
	return $cloudinary_plugin;
}

get_plugin_instance();
