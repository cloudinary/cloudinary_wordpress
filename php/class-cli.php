<?php
/**
 * Cloudinary CLI.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Traits\CLI_Trait;
use WP_CLI_Command;

// phpcs:disable WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli
/**
 * CLI class.
 *
 * @since   2.5.1
 */
class CLI extends WP_CLI_Command {
	// phpcs:enable

	use CLI_Trait;

	/**
	 * Workaround to prevent memory leaks from growing variables
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache;
		$wpdb->queries = array();
		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();
			$wp_object_cache->cache          = array();
			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}
	}
}
