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

	const METADATA = array(
		'actions' => array(
			'add_{object}_metadata',
			'update_{object}_metadata',
		),
		'objects' => array(
			'post',
			'term',
			'user',
		),
	);

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
		$active   = null;
		if ( $settings->has_param( 'active_setting' ) ) {
			$active = $settings->get_setting( $settings->get_param( 'active_setting' ) );
		}

		return $active;
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
	 * Check whether the inputted HTML string is powered by AMP, or if the request is an amp page.
	 * Reference on how to detect an AMP page: https://amp.dev/documentation/guides-and-tutorials/learn/spec/amphtml/?format=websites#ampd.
	 *
	 * @param string|null $html_string Optional: The specific HTML string to check.
	 *
	 * @return bool
	 */
	public static function is_amp( $html_string = null ) {
		if ( ! empty( $html_string ) ) {
			return preg_match( '/<html.+(amp|⚡)+[^>]/', substr( $html_string, 0, 200 ), $found );
		}
		$is_amp = false;
		if ( function_exists( 'amp_is_request' ) ) {
			$is_amp = amp_is_request();
		}

		return $is_amp;
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
	 * Get the depth of an array.
	 *
	 * @param array $array The array to check.
	 *
	 * @return int
	 */
	public static function array_depth( array $array ) {
		$depth = 0;

		foreach ( $array as $value ) {
			if ( is_array( $value ) ) {
				$level = self::array_depth( $value ) + 1;

				if ( $level > $depth ) {
					$depth = $level;
				}
			}
		}

		return $depth;
	}

	/**
	 * Check if the current user can perform a task.
	 *
	 * @param string $task The task to check.
	 *
	 * @return bool
	 */
	public static function user_can( $task ) {

		/**
		 * Filter the capability required for a specific cloudinary task.
		 *
		 * @hook    cloudinary_task_capability_{task}
		 * @since   2.7.6
		 *
		 * @param $capability {string} The capability.
		 *
		 * @default 'manage_options'
		 * @return  {string}
		 */
		$capability = apply_filters( "cloudinary_task_capability_{$task}", 'manage_options' );

		/**
		 * Filter the capability required for cloudinary tasks.
		 *
		 * @hook    cloudinary_task_capability
		 * @since   2.7.6
		 *
		 * @param $capability {string} The current capability for the task.
		 * @param $task       {string} The task.
		 *
		 * @return  {string}
		 */
		$capability = apply_filters( 'cloudinary_task_capability', $capability, $task );

		return current_user_can( $capability );
	}

	/**
	 * Get the Cloudinary relationships table name.
	 *
	 * @return string
	 */
	public static function get_relationship_table() {
		global $wpdb;

		return $wpdb->prefix . 'cloudinary_relationships';
	}

	/**
	 * Get the table create SQL.
	 *
	 * @return string
	 */
	public static function get_table_sql() {
		global $wpdb;

		$table_name      = self::get_relationship_table();
		$charset_collate = $wpdb->get_charset_collate();
		// Setup the sql.
		$sql = "CREATE TABLE $table_name (
	  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	  post_id bigint(20) DEFAULT NULL,
	  public_id varchar(1000) DEFAULT NULL,
	  parent_path varchar(1000) DEFAULT NULL,
	  sized_url varchar(1000) DEFAULT NULL,
	  width int(11) DEFAULT NULL,
	  height int(11) DEFAULT NULL,
	  format varchar(12) DEFAULT NULL,
	  sync_type varchar(45) DEFAULT NULL,
	  post_state varchar(12) DEFAULT NULL,
	  transformations text DEFAULT NULL,
	  signature varchar(45) DEFAULT NULL,
	  public_hash varchar(45) DEFAULT NULL,
	  url_hash varchar(45) DEFAULT NULL,
	  parent_hash varchar(45) DEFAULT NULL,
	  PRIMARY KEY (id),
	  UNIQUE KEY url_hash (url_hash),
	  KEY post_id (post_id),
	  KEY parent_hash (parent_hash),
	  KEY public_hash (public_hash),
	  KEY sync_type (sync_type)
	) ENGINE=InnoDB $charset_collate";

		return $sql;
	}

	/**
	 * Check if table exists.
	 *
	 * @return bool
	 */
	protected static function table_installed() {
		global $wpdb;
		$exists     = false;
		$table_name = self::get_relationship_table();
		$name       = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $table_name === $name ) {
			$exists = true;
		}

		return $exists;
	}

	/**
	 * Install our custom table.
	 */
	public static function install() {

		$sql = self::get_table_sql();

		if ( false === self::table_installed() ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbdelta
			update_option( Sync::META_KEYS['db_version'], get_plugin_instance()->version );
		} else {
			self::upgrade_install();
		}
	}

	/**
	 * Upgrade the installation.
	 */
	protected static function upgrade_install() {
		$sequence = self::get_upgrade_sequence();
		foreach ( $sequence as $callable ) {
			if ( is_callable( $callable ) ) {
				call_user_func( $callable );
			}
		}
	}

	/**
	 * Get the DB upgrade sequence.
	 *
	 * @return array
	 */
	protected static function get_upgrade_sequence() {
		$sequence         = array();
		$sequences        = array(
			'3.0.0' => array( 'Cloudinary\Utils', 'upgrade_3_0_1' ),
		);
		$upgrade_versions = array_keys( $sequences );
		$previous_version = get_option( Sync::META_KEYS['db_version'], '3.0.0' );
		$current_version  = get_plugin_instance()->version;
		if ( version_compare( $current_version, $previous_version, '>' ) ) {
			$index = array_search( $previous_version, $upgrade_versions, true );
			if ( false !== $index ) {
				$sequence = array_slice( $sequences, $index );
			}
		}

		/**
		 * Filter the upgrade sequence.
		 *
		 * @hook   cloudinary_upgrade_sequence
		 * @since  3.0.1
		 *
		 * @param $sequence {array} The default sequence.
		 *
		 * @return {array}
		 */
		return apply_filters( 'cloudinary_upgrade_sequence', $sequence );
	}

	/**
	 * Upgrade DB from v3.0.0 to v3.0.1.
	 */
	public static function upgrade_3_0_1() {
		global $wpdb;
		$tablename = self::get_relationship_table();

		// Drop old indexes.
		$wpdb->query( "ALTER TABLE {$tablename} DROP INDEX sized_url" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} DROP INDEX parent_path" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} DROP INDEX public_id" ); // phpcs:ignore WordPress.DB
		// Add new columns.
		$wpdb->query( "ALTER TABLE {$tablename} ADD `public_hash` VARCHAR(45)  NULL  DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} ADD `url_hash` VARCHAR(45)  NULL  DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} ADD `parent_hash` VARCHAR(45)  NULL  DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		// Add new indexes.
		$wpdb->query( "ALTER TABLE {$tablename} ADD UNIQUE INDEX url_hash (url_hash)" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} ADD INDEX public_hash (public_hash)" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} ADD INDEX parent_hash (parent_hash)" ); // phpcs:ignore WordPress.DB
		// Alter sizes.
		$wpdb->query( "ALTER TABLE {$tablename} CHANGE public_id public_id varchar(1000) DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} CHANGE parent_path parent_path varchar(1000) DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} CHANGE sized_url sized_url varchar(1000) DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		// Alter engine.
		$wpdb->query( "ALTER TABLE {$tablename} ENGINE=InnoDB;" );// phpcs:ignore WordPress.DB

		// Set DB Version.
		update_option( Sync::META_KEYS['db_version'], get_plugin_instance()->version );

	}

	/**
	 * Gets the URL for opening a Support Request.
	 *
	 * @param string $reason  The reason option slug.
	 * @param string $subject The subject for the request.
	 *
	 * @return string
	 */
	public static function get_support_link( $reason = '', $subject = '' ) {
		$user   = wp_get_current_user();
		$plugin = get_plugin_instance();
		$url    = 'https://support.cloudinary.com/hc/en-us/requests/new';

		if ( empty( $reason ) ) {
			$reason = 'other_help_needed';
		}

		if ( empty( $subject ) ) {
			$subject = sprintf(
			// translators: The plugin version.
				__( 'I need help with Cloudinary WordPress plugin version %s', 'cloudinary' ),
				$plugin->version
			);
		}

		$args = array(
			'request_anonymous_requester_email'  => $user->display_name,
			'request_custom_fields_22246877'     => $user->user_email,
			'request_custom_fields_360007219560' => $plugin->components['connect']->get_cloud_name(),
			'request_custom_fields_360017815680' => $reason,
			'request_subject'                    => $subject,
			'request_description'                => __( 'Please, provide more details on your request, and if possible, attach a System Report', 'cloudinary' ),
		);

		return add_query_arg( array_filter( $args ), $url );
	}

	/**
	 * Wrapper function to core wp_get_inline_script_tag.
	 *
	 * @param string $javascript Inline JavaScript code.
	 */
	public static function print_inline_tag( $javascript ) {
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $javascript );

			return;
		}

		$javascript = "\n" . trim( $javascript, "\n\r " ) . "\n";

		echo sprintf( "<script type='text/javascript'>%s</script>\n", $javascript ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get a sanitized input text field.
	 *
	 * @param string $var  The value to get.
	 * @param int    $type The type to get.
	 *
	 * @return mixed
	 */
	public static function get_sanitized_text( $var, $type = INPUT_GET ) {
		return filter_input( $type, $var, FILTER_CALLBACK, array( 'options' => 'sanitize_text_field' ) );
	}

	/**
	 * Returns information about a file path by normalizing the locale.
	 *
	 * @param string $path  The path to be parsed.
	 * @param int    $flags Specifies a specific element to be returned.
	 *                      Defaults to 15 which stands for PATHINFO_ALL.
	 *
	 * @return array|string|string[]
	 */
	public static function pathinfo( $path, $flags = 15 ) {

		/**
		 * Approach based on wp_basename.
		 *
		 * @see wp-includes/formatting.php
		 */
		$path = str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) );

		$pathinfo = pathinfo( $path, $flags );

		return is_array( $pathinfo ) ? array_map( 'urldecode', $pathinfo ) : urldecode( $pathinfo );
	}

	/**
	 * Check if a thing looks like a json string.
	 *
	 * @param mixed $thing The thing to check.
	 *
	 * @return bool
	 */
	public static function looks_like_json( $thing ) {
		return is_string( $thing ) && in_array( ltrim( $thing )[0], array( '{', '[' ), true );
	}

	/**
	 * Check if we're in a REST API request.
	 *
	 * @return bool
	 */
	public static function is_rest_api() {
		$is = defined( 'REST_REQUEST' ) && REST_REQUEST;
		if ( ! $is ) {
			$is = ! empty( $GLOBALS['wp']->query_vars['rest_route'] );
		}
		if ( ! $is ) {
			// Fallback if rest engine is not setup yet.
			$rest_base   = wp_parse_url( rest_url( '/' ), PHP_URL_PATH );
			$request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
			$is          = strpos( $request_uri, $rest_base ) === 0;
		}

		return $is;
	}

	/**
	 * Check if we are in WordPress ajax.
	 *
	 * @return bool
	 */
	public static function is_frontend_ajax() {
		$referer    = wp_get_referer();
		$admin_base = admin_url();
		$is_admin   = $referer ? 0 === strpos( $referer, $admin_base ) : false;

		return ! $is_admin && defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Check if this is an admin request, but not an ajax one.
	 *
	 * @return bool
	 */
	public static function is_admin() {
		return is_admin() && ! self::is_frontend_ajax();
	}

	/**
	 * Inspected on wp_extract_urls.
	 * However, there's a shortcoming on some transformations where the core extractor will fail to fully parse such URLs.
	 *
	 * @param string $content The content.
	 *
	 * @return array
	 */
	public static function extract_urls( $content ) {
		preg_match_all(
			"#([\"']?)("
				. '(?:[\w-]+:)?//?'
				. '[^\s()<>"\']+'
				. '[.]'
				. '(?:'
					. '\([\w\d]+\)|'
					. '(?:'
						. "[^`!()\[\]{};:'\".,<>«»“”‘’\s]|"
						. '(?:[:]\w+)?/?'
					. ')+'
				. ')'
			. ")\\1#",
			$content,
			$post_links
		);

		$post_links = array_unique( array_map( 'html_entity_decode', $post_links[2] ) );

		return array_values( $post_links );
	}

	/**
	 * Is saving metadata.
	 *
	 * @return bool
	 */
	public static function is_saving_metadata() {
		$saving   = false;
		$metadata = self::METADATA;

		foreach ( $metadata['actions'] as $action ) {
			foreach ( $metadata['objects'] as $object ) {
				$inline_action = str_replace( array( '{object}', 'metadata' ), array( $object, 'meta' ), $action );
				if ( did_action( $inline_action ) ) {
					$saving = true;
					break;
				}
			}
		}

		return $saving;
	}

	/**
	 * Encode SVG placeholder.
	 *
	 * @param string $width  The SVG width.
	 * @param string $height The SVG height.
	 * @param string $color  The SVG color.
	 *
	 * @return string
	 */
	public static function svg_encoded( $width = '600px', $height = '400px', $color = '-color-' ) {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '"><rect width="100%" height="100%"><animate attributeName="fill" values="' . $color . '" dur="2s" repeatCount="indefinite" /></rect></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
