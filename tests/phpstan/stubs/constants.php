<?php
/**
 * Constant stubs for static analysis.
 *
 * These constants are defined at runtime and cannot be discovered by PHPStan:
 *
 * - The plugin's own CLDN_* constants are defined in cloudinary.php.
 * - The CLOUDINARY_ENDPOINTS_* constants are defined conditionally in
 *   Cloudinary\Plugin::setup_endpoints().
 * - CLOUDINARY_CONNECTION_STRING is an optional constant a site owner may
 *   define in wp-config.php to supply credentials.
 * - WPINC and LOGGED_IN_COOKIE are WordPress core constants that are not
 *   provided by the WordPress stub package.
 *
 * @package Cloudinary
 */

define( 'CLDN_CORE', '' );
define( 'CLDN_PATH', '' );

define( 'CLOUDINARY_CONNECTION_STRING', '' );

// The endpoint values mirror the real defaults from
// Cloudinary\Plugin::setup_endpoints() so PHPStan sees matching sprintf
// placeholders (the *_CORE, *_SCRIPT and *_STYLE URLs contain a %s version
// token).
define( 'CLOUDINARY_ENDPOINTS_API', 'api.cloudinary.com' );
define( 'CLOUDINARY_ENDPOINTS_CORE', 'https://unpkg.com/cloudinary-core@%s/cloudinary-core-shrinkwrap.min.js' );
define( 'CLOUDINARY_ENDPOINTS_CORE_VERSION', '2.6.3' );
define( 'CLOUDINARY_ENDPOINTS_DEACTIVATION', 'https://analytics-api.cloudinary.com/wp_deactivate_reason' );
define( 'CLOUDINARY_ENDPOINTS_GALLERY', 'https://product-gallery.cloudinary.com/all.js' );
define( 'CLOUDINARY_ENDPOINTS_MEDIA_LIBRARY', 'https://media-library.cloudinary.com/global/all.js' );
define( 'CLOUDINARY_ENDPOINTS_PREVIEW_IMAGE', 'https://res.cloudinary.com/demo/image/upload/' );
define( 'CLOUDINARY_ENDPOINTS_PREVIEW_VIDEO', 'https://res.cloudinary.com/demo/video/upload/' );
define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_EMBED', 'https://player.cloudinary.com/embed/' );
define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_SCRIPT', 'https://unpkg.com/cloudinary-video-player@%s/dist/cld-video-player.min.js' );
define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_STYLE', 'https://unpkg.com/cloudinary-video-player@%s/dist/cld-video-player.min.css' );
define( 'CLOUDINARY_ENDPOINTS_VIDEO_PLAYER_VERSION', '3.0.2' );

define( 'WPINC', 'wp-includes' );
define( 'LOGGED_IN_COOKIE', '' );
