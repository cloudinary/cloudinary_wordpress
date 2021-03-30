<?php
/**
 * Utilities for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Settings\Setting;
use Google\Web_Stories\Story_Post_Type;
use function Clue\StreamFilter\fun;

/**
 * Class that includes utility methods.
 *
 * @package Cloudinary
 */
class Utils {

	/**
	 * Filter an array recursively
	 *
	 * @param array         $input    The array to filter.
	 * @param callable|null $callback The callback to run for filtering.
	 *
	 * @return array
	 */
	public static function array_filter_recursive( array $input, $callback = null ) {
		// PHP array_filter does this, so we'll do it too.
		if ( null === $callback ) {
			$callback = static function ( $item ) {
				return ! empty( $item );
			};
		}

		foreach ( $input as &$value ) {
			if ( is_array( $value ) ) {
				$value = self::array_filter_recursive( $value, $callback );
			}
		}

		return array_filter( $input, $callback );
	}

	/**
	 * Gets the active child setting.
	 *
	 * @return Setting
	 */
	public static function get_active_setting() {
		$settings = get_plugin_instance()->settings;
		$setting  = $settings->get_param( 'active_setting', $settings );
		if ( $setting->has_param( 'has_tabs' ) ) {
			$setting = $setting->get_param( 'active_tab', $setting );
		}

		return $setting;
	}

	/**
	 * Detects array keys with dot notation and expands them to form a new multi-dimensional array.
	 *
	 * @param array  $input     The array that will be processed.
	 * @param string $separator Separator string.
	 *
	 * @return array
	 */
	public static function expand_dot_notation( array $input, $separator = '.' ) {
		$result = array();
		foreach ( $input as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::expand_dot_notation( $value );
			}

			foreach ( array_reverse( explode( $separator, $key ) ) as $inner_key ) {
				$value = array( $inner_key => $value );
			}

			// phpcs:ignore
			/** @noinspection SlowArrayOperationsInLoopInspection */
			$result = array_merge_recursive( $result, $value );
		}

		return $result;
	}

	/**
	 * Check whether the inputted HTML string is powered by AMP.
	 * Reference on how to detect an AMP page: https://amp.dev/documentation/guides-and-tutorials/learn/spec/amphtml/?format=websites#ampd.
	 *
	 * @param string $html_string The HTML string to check.
	 *
	 * @return bool
	 */
	public static function is_amp( $html_string ) {
		return strpos( $html_string, '<html amp' ) !== false || strpos( $html_string, '<html ⚡' ) !== false;
	}

	/**
	 * Check whether the inputted post type is a webstory.
	 *
	 * @param string $post_type The post type to compare to.
	 *
	 * @return bool
	 */
	public static function is_webstory_post_type( $post_type ) {
		return class_exists( Story_Post_Type::class ) && Story_Post_Type::POST_TYPE_SLUG === $post_type;
	}

	/**
	 * Get all the attributes from an HTML tag.
	 *
	 * @param string $tag HTML tag to get attributes from.
	 *
	 * @return array
	 */
	public static function get_tag_attributes( $tag ) {
		$tag    = strstr( $tag, ' ', false );
		$tag    = trim( $tag, '> ' );
		$args   = shortcode_parse_atts( $tag );
		$return = array();
		foreach ( $args as $key => $value ) {
			if ( is_int( $key ) ) {
				$return[ $value ] = 'true';
				continue;
			}
			$return[ $key ] = $value;
		}

		return $return;
	}

	/**
	 * Get and sanitize files from a folder.
	 *
	 * @param string $folder       The folder to get from.
	 * @param string $version      The version.
	 * @param string $callback     The callback for sanitizing the url.
	 * @param bool   $strip_folder Flag for stripping the basename.
	 *
	 * @return array
	 */
	public static function get_folder_files( $folder, $version, $callback = 'home_url', $strip_folder = true ) {
		if ( ! is_callable( $callback ) ) {
			$callback = 'home_url';
		}
		$default      = array(
			'version' => null,
			'files'   => array(),
		);
		$folder_key   = md5( $folder );
		$folder_cache = get_option( $folder_key, $default );
		if ( empty( $folder_cache['files'] ) || $folder_cache['version'] !== $version ) {
			$folder_cache['files']   = array();
			$folder_cache['version'] = $version;
			$found                   = self::get_files( $folder );
			foreach ( $found as $file ) {
				$strip_length                  = $strip_folder ? strlen( $folder ) : strlen( dirname( $folder ) . '/' );
				$file_part                     = substr( $file, $strip_length );
				$url                           = call_user_func( $callback, wp_normalize_path( $file_part ) );
				$folder_cache['files'][ $url ] = $file . '?ver=' . $version;
			}
			// Add files cache.
			update_option( $folder_key, $folder_cache, false );
		}

		return $folder_cache['files'];
	}

	/**
	 * Get files from a folder.
	 *
	 * @param string $path      The file path.
	 * @param array  $types     The file types to include.
	 * @param bool   $trim_base Flag to trim the base off.
	 *
	 * @return array
	 */
	public static function get_files( $path, $types = array(), $trim_base = false ) {

		$exclude = array(
			'node_modules',
			'vendor',
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$files = list_files( $path, PHP_INT_MAX, $exclude );
		if ( ! empty( $types ) ) {
			$files = array_filter(
				$files,
				function ( $file ) use ( $types ) {
					return in_array( pathinfo( $file, PATHINFO_EXTENSION ), $types, true );
				}
			);
		}

		if ( true === $trim_base ) {
			$path_len = strlen( $path );
			$files    = array_map(
				function ( $file ) use ( $path_len ) {
					return substr( $file, $path_len );
				},
				$files
			);
		}

		sort( $files );

		return $files;
	}
}
