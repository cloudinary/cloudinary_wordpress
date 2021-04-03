<?php
/**
 * Utilities for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Settings\Setting;
use Google\Web_Stories\Story_Post_Type;

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
}
