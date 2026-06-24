<?php
/**
 * Uninstall handler for Cloudinary.
 *
 * @package Cloudinary
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'CLDN_CORE', __DIR__ . '/cloudinary.php' );
define( 'CLDN_PATH', plugin_dir_path( CLDN_CORE ) );

require_once __DIR__ . '/instance.php';

$plugin = \Cloudinary\get_plugin_instance();

// Bootstrap enough runtime state for component-aware cleanup.
$plugin->init();
$plugin->plugins_loaded();
$plugin->setup_settings();

\Cloudinary\Cleanup::run( $plugin );
