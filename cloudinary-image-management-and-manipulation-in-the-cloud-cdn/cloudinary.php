<?php
/**
 * Plugin Name: Cloudinary
 * Plugin URI: https://cloudinary.com/documentation/wordpress_integration
 * Description: With the Cloudinary plugin, you can upload and manage your media assets in the cloud, then deliver them to your users through a fast content delivery network, improving your website’s loading speed and overall user experience. Apply multiple transformations and take advantage of a full digital asset management solution without leaving WordPress.
 * Version: 2.2.0
 * Author:  Cloudinary Ltd., XWP
 * Author URI: https://cloudinary.com/
 * License: GPLv2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: cloudinary
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package Cloudinary
 */

// Define Cloudinary Constants.
define( 'CLDN_CORE', __FILE__ );
define( 'CLDN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLDN_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( phpversion(), '5.6', '>=' ) ) {
	require_once __DIR__ . '/instance.php';
} else {
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::warning( _cloudinary_php_version_text() );
	} else {
		add_action( 'admin_notices', '_cloudinary_php_version_error' );
	}
}

/**
 * Admin notice for incompatible versions of PHP.
 */
function _cloudinary_php_version_error() {
	printf( '<div class="error"><p>%s</p></div>', esc_html( _cloudinary_php_version_text() ) );
}

/**
 * String describing the minimum PHP version.
 *
 * @return string
 */
function _cloudinary_php_version_text() {
	return __( 'Cloudinary plugin error: Your version of PHP is too old to run this plugin. You must be running PHP 5.6 or higher.', 'cloudinary' );
}
