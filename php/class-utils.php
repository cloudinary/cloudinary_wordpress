<?php
/**
 * Utilities for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Misc\Image_Sizes_No_Textdomain;
use Cloudinary\Settings\Setting;
use Google\Web_Stories\Story_Post_Type;
use WP_Post;

/**
 * Class that includes utility methods.
 *
 * @package Cloudinary
 */
class Utils {

	/**
	 * Holds a list of temp files to be purged.
	 *
	 * @var array
	 */
	public static $file_fragments = array();

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
	 * @param array $data The array to check.
	 *
	 * @return int
	 */
	public static function array_depth( array $data ) {
		$depth = 0;

		foreach ( $data as $value ) {
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
	 * @param string $task       The task to check.
	 * @param string $capability The default capability.
	 * @param string $context    The context for the task.
	 * @param mixed  ...$args    Optional further parameters.
	 *
	 * @return bool
	 */
	public static function user_can( $task, $capability = 'manage_options', $context = '', ...$args ) {

		// phpcs:disable WordPress.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
		/**
		 * Filter the capability required for a specific Cloudinary task.
		 *
		 * @hook    cloudinary_task_capability_{task}
		 * @since   2.7.6. In 3.0.6 $context and $args added.
		 *
		 * @example
		 * <?php
		 *
		 * // Enforce `manage_options` to download an asset from Cloudinary.
		 * add_filter(
		 *     'cloudinary_task_capability_manage_assets',
		 *     function( $task, $context ) {
		 *         if ( 'download' === $context ) {
		 *             $capability = 'manage_options';
		 *         }
		 *         return $capability;
		 *     },
		 *     10,
		 *     2
		 * );
		 *
		 * @param $capability {string} The capability.
		 * @param $context    {string} The context for the task.
		 * @param $args       {mixed}  The optional arguments.
		 *
		 * @default 'manage_options'
		 * @return  {string}
		 */
		$capability = apply_filters( "cloudinary_task_capability_{$task}", $capability, $context, ...$args );

		/**
		 * Filter the capability required for Cloudinary tasks.
		 *
		 * @hook    cloudinary_task_capability
		 * @since   2.7.6. In 3.0.6 $context and $args added.
		 *
		 * @example
		 * <?php
		 *
		 * // Enforce `manage_options` to download an asset from Cloudinary.
		 * add_filter(
		 *     'cloudinary_task_capability',
		 *     function( $capability, $task, $context ) {
		 *         if ( 'manage_assets' === $task && 'download' === $context ) {
		 *             $capability = 'manage_options';
		 *         }
		 *         return $capability;
		 *     },
		 *     10,
		 *     3
		 * );
		 *
		 * @param $capability {string} The current capability for the task.
		 * @param $task       {string} The task.
		 * @param $context    {string} The context for the task.
		 * @param $args       {mixed}  The optional arguments.
		 *
		 * @return  {string}
		 */
		$capability = apply_filters( 'cloudinary_task_capability', $capability, $task, $context, ...$args );
		// phpcs:enable WordPress.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed

		return current_user_can( $capability, ...$args ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined
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
	  media_context varchar(12) DEFAULT 'default',
	  width int(11) DEFAULT NULL,
	  height int(11) DEFAULT NULL,
	  format varchar(12) DEFAULT NULL,
	  sync_type varchar(45) DEFAULT NULL,
	  post_state varchar(12) DEFAULT NULL,
	  transformations text DEFAULT NULL,
	  text_overlay text DEFAULT NULL,
	  image_overlay text DEFAULT NULL,
	  signature varchar(45) DEFAULT NULL,
	  public_hash varchar(45) DEFAULT NULL,
	  url_hash varchar(45) DEFAULT NULL,
	  parent_hash varchar(45) DEFAULT NULL,
	  PRIMARY KEY (id),
	  UNIQUE KEY media (url_hash, media_context),
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
		// Ensure that the plugin bootstrap is loaded.
		get_plugin_instance()->init();

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
		$upgrade_sequence = array();
		$sequences        = array(
			'3.0.0'  => array(
				'range'  => array( '3.0.0' ),
				'method' => array( 'Cloudinary\Utils', 'upgrade_3_0_1' ),
			),
			'3.1.9'  => array(
				'range'  => array( '3.0.1', '3.1.9' ),
				'method' => array( 'Cloudinary\Utils', 'upgrade_3_1_9' ),
			),
			'3.3.0' => array(
				'range'  => array( '3.1.9', '3.3.0' ),
				'method' => array( 'Cloudinary\Utils', 'upgrade_3_3_0' ),
			),

		);
		$previous_version = get_option( Sync::META_KEYS['db_version'], '3.0.0' );
		$current_version  = get_plugin_instance()->version;
		foreach ( $sequences as $sequence ) {
			if (
				version_compare( $current_version, $previous_version, '>' )
				&& version_compare( $previous_version, reset( $sequence['range'] ), '>=' )
				&& version_compare( $previous_version, end( $sequence['range'] ), '<' )
			) {
				$upgrade_sequence[] = $sequence['method'];
			}
		}

		/**
		 * Filter the upgrade sequence.
		 *
		 * @hook   cloudinary_upgrade_sequence
		 * @since  3.0.1
		 *
		 * @param $upgrade_sequence {array} The default sequence.
		 *
		 * @return {array}
		 */
		return apply_filters( 'cloudinary_upgrade_sequence', $upgrade_sequence );
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
	 * Upgrade DB from v3.0.1 to v3.1.9.
	 */
	public static function upgrade_3_1_9() {
		global $wpdb;
		$tablename = self::get_relationship_table();

		// Add new columns.
		$wpdb->query( "ALTER TABLE {$tablename} ADD COLUMN `media_context` VARCHAR(12) DEFAULT 'default' AFTER `sized_url`" ); // phpcs:ignore WordPress.DB

		// Update indexes.
		$wpdb->query( "ALTER TABLE {$tablename} DROP INDEX url_hash" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} ADD UNIQUE INDEX media (url_hash, media_context)" ); // phpcs:ignore WordPress.DB

		// Set DB Version.
		update_option( Sync::META_KEYS['db_version'], get_plugin_instance()->version );
	}

	/**
	 * Upgrade DB from v3.1.9 to v3.2.15.
	 * Adds columns for overlay data.
	 */
	public static function upgrade_3_3_0() {
		global $wpdb;
		$tablename = self::get_relationship_table();

		// Add new columns for overlays.
		$wpdb->query( "ALTER TABLE {$tablename} ADD COLUMN `text_overlay` TEXT DEFAULT NULL AFTER `transformations`" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "ALTER TABLE {$tablename} ADD COLUMN `image_overlay` TEXT DEFAULT NULL AFTER `text_overlay`" ); // phpcs:ignore WordPress.DB

		// Update sample.jpg to leather_bag.jpg in media_display settings.
		$media_display = get_option( 'cloudinary_media_display', array() );

		if ( ! empty( $media_display ) && is_array( $media_display ) ) {
			$updated = false;
			$fields  = array( 'image_preview', 'lazyload_preview', 'breakpoints_preview' );

			foreach ( $fields as $field ) {
				if ( isset( $media_display[ $field ] ) && is_string( $media_display[ $field ] ) ) {
					if ( false !== strpos( $media_display[ $field ], 'sample.jpg' ) ) {
						$media_display[ $field ] = str_replace( 'sample.jpg', 'leather_bag.jpg', $media_display[ $field ] );
						$updated                 = true;
					}
				}
			}

			if ( $updated ) {
				update_option( 'cloudinary_media_display', $media_display );
			}
		}

		// Set DB Version.
		update_option( Sync::META_KEYS['db_version'], get_plugin_instance()->version );
	}

	/**
	 * Gets the URL for opening a Support Request.
	 *
	 * @param array $args The arguments.
	 *
	 * @return string
	 */
	public static function get_support_link( $args = array() ) {
		$user   = wp_get_current_user();
		$plugin = get_plugin_instance();
		$url    = 'https://support.cloudinary.com/hc/en-us/requests/new';

		$default_args = array(
			'tf_anonymous_requester_email' => $user->user_email,
			'tf_22246877'                  => $user->display_name,
			'tf_360007219560'              => $plugin->components['connect']->get_cloud_name(),
			'tf_360017815680'              => 'other_help_needed',
			'tf_subject'                   => esc_attr(
				sprintf(
					// translators: The plugin version.
					__( 'I need help with Cloudinary WordPress plugin version %s', 'cloudinary' ),
					$plugin->version
				)
			),
			'tf_description'               => esc_attr( __( 'Please, provide more details on your request, and if possible, attach a System Report', 'cloudinary' ) ),
		);

		$args = wp_parse_args(
			$args,
			$default_args
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

		printf( "<script type='text/javascript'>%s</script>\n", $javascript ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get a sanitized input text field.
	 *
	 * @param string $var_name The value to get.
	 * @param int    $type     The type to get.
	 *
	 * @return mixed
	 */
	public static function get_sanitized_text( $var_name, $type = INPUT_GET ) {
		return filter_input( $type, $var_name, FILTER_CALLBACK, array( 'options' => 'sanitize_text_field' ) );
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
		$path = str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode

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
		if ( ! is_string( $thing ) ) {
			return false;
		}

		$thing = trim( $thing );

		if ( empty( $thing ) ) {
			return false;
		}

		if ( ! in_array( $thing[0], array( '{', '[' ), true ) ) {
			return false;
		}

		return true;
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
			$rest_base   = wp_parse_url( static::rest_url( '/' ), PHP_URL_PATH );
			$request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
			$is          = is_string( $request_uri ) && strpos( $request_uri, $rest_base ) === 0;
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
		// Check if this is a frontend ajax request.
		$is_frontend_ajax = ! $is_admin && defined( 'DOING_AJAX' ) && DOING_AJAX;
		// If it's not an obvious WP ajax request, check if it's a custom frontend ajax request.
		if ( ! $is_frontend_ajax && ! $is_admin ) {
			// Catch the content type of the $_SERVER['CONTENT_TYPE'] variable.
			$type             = filter_input( INPUT_SERVER, 'CONTENT_TYPE', FILTER_CALLBACK, array( 'options' => 'sanitize_text_field' ) );
			$is_frontend_ajax = $type && false !== strpos( $type, 'json' );
		}

		return $is_frontend_ajax;
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
		// Remove inline SVG data URIs, as they can cause parsing issues when extracting URLs.
		$content = self::strip_inline_svg_data_uris( $content );

		preg_match_all(
			"#([\"']?)("
				. '(?:[\w-]+:)?//?'
				. '[^\s()<>"\']+'
				. '[.,]'
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
	 * Strip inline SVG data URIs from content.
	 *
	 * @param string $content The content to process.
	 *
	 * @return string The content with SVG data URIs removed.
	 */
	public static function strip_inline_svg_data_uris( $content ) {
		// Pattern to match the data URI structure: data:image/svg+xml;base64,<base64-encoded-data>.
		$svg_data_uri_pattern = '/data:image\/svg\+xml;base64,[a-zA-Z0-9\/\+\=]+/i';

		// Remove all occurrences of SVG data URIs from the content.
		$cleaned_content = preg_replace( $svg_data_uri_pattern, '', $content );

		// In case an error occurred, we return the original content to avoid data loss.
		return is_null( $cleaned_content ) ? $content : $cleaned_content;
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

	/**
	 * Wrapper for get_post_parent.
	 *
	 * @param int|WP_Post|null $post The post.
	 *
	 * @return WP_Post|null
	 */
	public static function get_post_parent( $post = null ) {
		if ( is_callable( 'get_post_parent' ) ) {
			return get_post_parent( $post );
		}

		$wp_post = get_post( $post );
		return ! empty( $wp_post->post_parent ) ? get_post( $wp_post->post_parent ) : null;
	}

	/**
	 * Download a fragment of a file URL to a temp file and return the file URI.
	 *
	 * @param string $url  The URL to download.
	 * @param int    $size The size of the fragment to download.
	 *
	 * @return string|false
	 */
	public static function download_fragment( $url, $size = 1048576 ) {

		$temp_file = wp_tempnam( basename( $url ) );
		$pointer   = fopen( $temp_file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$file      = false;
		if ( $pointer ) {
			// Prep to purge.
			$index = count( self::$file_fragments );
			if ( empty( $index ) ) {
				add_action( 'shutdown', array( __CLASS__, 'purge_fragments' ) );
			}
			self::$file_fragments[ $index ] = array(
				'pointer' => $pointer,
				'file'    => $temp_file,
			);
			// Get the metadata of the stream.
			$data = stream_get_meta_data( $pointer );
			// Stream the content to the temp file.
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 300, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'stream'              => true,
					'filename'            => $data['uri'],
					'limit_response_size' => $size,
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$file = $data['uri'];
			} else {
				// Clean up if there was an error.
				self::purge_fragment( $index );
			}
		}

		return $file;
	}

	/**
	 * Purge fragment temp files on shutdown.
	 */
	public static function purge_fragments() {
		foreach ( array_keys( self::$file_fragments ) as $index ) {
			self::purge_fragment( $index );
		}
	}

	/**
	 * Purge a fragment temp file.
	 *
	 * @param int $index The index of the fragment to purge.
	 */
	public static function purge_fragment( $index ) {
		if ( isset( self::$file_fragments[ $index ] ) ) {
			fclose( self::$file_fragments[ $index ]['pointer'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			unlink( self::$file_fragments[ $index ]['file'] ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
	}

	/**
	 * Log a debug message.
	 *
	 * @param string      $message The message to log.
	 * @param string|null $key     The key to log the message under.
	 */
	public static function log( $message, $key = null ) {
		if ( get_plugin_instance()->get_component( 'report' )->enabled() ) {
			$messages = get_option( Sync::META_KEYS['debug'], array() );
			if ( $key ) {
				$hash                      = md5( $message );
				$messages[ $key ][ $hash ] = $message;
			} else {
				$messages[] = $message;
			}
			update_option( Sync::META_KEYS['debug'], $messages, false );
		}
	}

	/**
	 * Get the debug messages.
	 *
	 * @return array
	 */
	public static function get_debug_messages() {
		return get_option( Sync::META_KEYS['debug'], array( __( 'Debug log is empty', 'cloudinary' ) ) );
	}

	/**
	 * Check if the tag attributes contain possible third party manipulated data, and return found data.
	 *
	 * @param array  $attributes The tag attributes.
	 * @param string $tag        The tag.
	 *
	 * @return string|false
	 */
	public static function maybe_get_third_party_changes( $attributes, $tag ) {
		static $filtered_keys, $filtered_classes;
		$lazy_keys    = array(
			'src',
			'lazyload',
			'lazy',
			'loading',
		);
		$lazy_classes = array(
			'lazyload',
			'lazy',
			'loading',
		);
		if ( ! $filtered_keys ) {
			/**
			 * Filter the keywords in data-* attributes on tags to be ignored from lazy-loading.
			 *
			 * @hook   cloudinary_ignored_data_keywords
			 * @since  3.0.8
			 *
			 * @param $lazy_keys {array} The built-in ignore data-* keywords.
			 *
			 * @return {array}
			 */
			$filtered_keys = apply_filters( 'cloudinary_ignored_data_keywords', $lazy_keys );

			/**
			 * Filter the keywords in classes on tags to be ignored from lazy-loading.
			 *
			 * @hook   cloudinary_ignored_class_keywords
			 * @since  3.0.8
			 *
			 * @param $lazy_classes {array} The built-in ignore class keywords.
			 *
			 * @return {array}
			 */
			$filtered_classes = apply_filters( 'cloudinary_ignored_class_keywords', $lazy_classes );
		}
		$is = false;

		// Source tag on Picture tags are not lazy-loaded.
		if ( 'source' !== $tag ) {
			if ( ! isset( $attributes['src'] ) ) {
				$is = __( 'Missing SRC attribute.', 'cloudinary' );
			} elseif ( false !== strpos( $attributes['src'], 'data:image' ) ) {
				$is = $attributes['src'];
			} elseif ( isset( $attributes['class'] ) ) {
				$classes = explode( '-', str_replace( ' ', '-', $attributes['class'] ) );
				if ( ! empty( array_intersect( $filtered_classes, $classes ) ) ) {
					$is = $attributes['class'];
				}
			}
		}

		// If the above didn't find anything, check the data-* attributes.
		if ( ! $is ) {
			foreach ( $attributes as $key => $value ) {
				if ( 'data-' !== substr( $key, 0, 5 ) ) {
					continue;
				}
				$parts = explode( '-', $key );
				if ( ! empty( array_intersect( $parts, $filtered_keys ) ) ) {
					$is = $key;
					break;
				}
			}
		}

		return $is;
	}

	/**
	 * Clean up meta after sync.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public static function clean_up_sync_meta( $attachment_id ) {

		// translators: The attachment ID.
		$action_message = sprintf( __( 'Clean up sync metadata for %d', 'cloudinary' ), $attachment_id );
		do_action( '_cloudinary_queue_action', $action_message );

		// remove pending.
		delete_post_meta( $attachment_id, Sync::META_KEYS['pending'] );

		// Remove processing flag.
		delete_post_meta( $attachment_id, Sync::META_KEYS['syncing'] );

		$sync_thread = get_post_meta( $attachment_id, Sync::META_KEYS['queued'], true );
		if ( ! empty( $sync_thread ) ) {
			delete_post_meta( $attachment_id, Sync::META_KEYS['queued'] );
			delete_post_meta( $attachment_id, $sync_thread );
		}
	}

	/**
	 * Get the registered image sizes, the labels and crop settings.
	 *
	 * @param null|int $attachment_id The attachment ID to get the sizes. Defaults to generic registered sizes.
	 *
	 * @return array
	 */
	public static function get_registered_sizes( $attachment_id = null ) {
		$additional_sizes   = wp_get_additional_image_sizes();
		$all_sizes          = array();
		$labels             = array();
		$intermediate_sizes = array();

		if ( is_null( $attachment_id ) ) {
			$intermediate_sizes = get_intermediate_image_sizes(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes
		} else {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $meta['sizes'] ) ) {
				$additional_sizes   = wp_parse_args( $additional_sizes, $meta['sizes'] );
				$intermediate_sizes = array_keys( $meta['sizes'] );
			}
		}

		foreach ( $intermediate_sizes as $size ) {
			$labels[ $size ] = ucwords( str_replace( array( '-', '_' ), ' ', $size ) );
		}

		/** This filter is documented in wp-admin/includes/media.php */
		$image_sizes = apply_filters(
			'image_size_names_choose',
			Image_Sizes_No_Textdomain::get_image_sizes()
		);

		$labels = wp_parse_args( $labels, $image_sizes );

		foreach ( $intermediate_sizes as $size ) {
			if ( isset( $additional_sizes[ $size ] ) ) {
				$all_sizes[ $size ] = array(
					'label'  => $labels[ $size ],
					'width'  => $additional_sizes[ $size ]['width'],
					'height' => $additional_sizes[ $size ]['height'],
				);
			} else {
				$all_sizes[ $size ] = array(
					'label'  => $labels[ $size ],
					'width'  => (int) get_option( "{$size}_size_w" ),
					'height' => (int) get_option( "{$size}_size_h" ),
				);
			}

			if ( ! empty( $additional_sizes[ $size ]['crop'] ) ) {
				$all_sizes[ $size ]['crop'] = $additional_sizes[ $size ]['crop'];
			} else {
				$all_sizes[ $size ]['crop'] = (bool) get_option( "{$size}_crop" );
			}
		}

		/**
		 * Filter the all sizes available.
		 *
		 * @param array $all_sizes All the registered sizes.
		 *
		 * @since 3.1.3
		 *
		 * @hook  cloudinary_registered_sizes
		 */
		return apply_filters( 'cloudinary_registered_sizes', $all_sizes );
	}

	/**
	 * Get the attachment ID from the attachment URL.
	 *
	 * @param string $url The attachment URL.
	 *
	 * @return int|null
	 */
	public static function attachment_url_to_postid( $url ) {
		$key = "postid_{$url}";

		if ( function_exists( 'wpcom_vip_attachment_url_to_postid' ) ) {
			$attachment_id = wpcom_vip_attachment_url_to_postid( $url );
		} else {
			$attachment_id = wp_cache_get( $key, 'cloudinary' );
		}

		if ( empty( $attachment_id ) ) {
			$attachment_id = attachment_url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid
			wp_cache_set( $key, $attachment_id, 'cloudinary' );
		}

		if ( empty( $attachment_id ) ) {
			$media           = get_plugin_instance()->get_component( 'media' );
			$maybe_public_id = $media->get_public_id_from_url( $url );
			$relations       = self::query_relations( array( $maybe_public_id ) );
			foreach ( $relations as $relation ) {
				if ( ! empty( $relation['post_id'] ) ) {
					$attachment_id = (int) $relation['post_id'];
					wp_cache_set( $key, $attachment_id, 'cloudinary' );
				}
			}
		}

		return $attachment_id;
	}

	/**
	 * Run a query with Public_id's and or local urls.
	 *
	 * @param array $public_ids List of Public_IDs qo query.
	 * @param array $urls       List of URLS to query.
	 *
	 * @return array
	 */
	public static function query_relations( $public_ids, $urls = array() ) {
		$chunk_size = 25;
		$results    = array();
		$tablename  = self::get_relationship_table();

		/**
		 * Filter the media context query.
		 *
		 * @hook   cloudinary_media_context_query
		 * @since  3.2.0
		 * @param $media_context_query {string} The default media context query.
		 * @return {string}
		 */
		$media_context_query = apply_filters( 'cloudinary_media_context_query', 'media_context = %s' );

		/**
		 * Filter the media context things.
		 *
		 * @hook   cloudinary_media_context_things
		 * @since  3.2.0
		 * @param $media_context_things {array} The default media context things.
		 * @return {array}
		 */
		$media_context_things = apply_filters( 'cloudinary_media_context_things', array( 'default' ) );

		// Query for urls in chunks.
		if ( ! empty( $urls ) ) {
			$results = array_merge( $results, self::run_chunked_query( 'url_hash', $urls, $chunk_size, $tablename, $media_context_query, $media_context_things ) );
		}
		// Query for public_ids in chunks.
		if ( ! empty( $public_ids ) ) {
			$results = array_merge( $results, self::run_chunked_query( 'public_hash', $public_ids, $chunk_size, $tablename, $media_context_query, $media_context_things ) );
		}

		return $results;
	}
	/**
	 * Run a chunked query and merge results.
	 *
	 * @param string $field The DB field to query (url_hash or public_hash).
	 * @param array  $items The items to query.
	 * @param int    $chunk_size Number of items per chunk.
	 * @param string $tablename The table name.
	 * @param string $media_context_query The media context SQL.
	 * @param array  $media_context_things The media context values.
	 * @return array
	 */
	protected static function run_chunked_query( $field, $items, $chunk_size, $tablename, $media_context_query, $media_context_things ) {
		global $wpdb;

		$all_results = array();
		$chunks      = array_chunk( $items, $chunk_size );

		foreach ( $chunks as $chunk ) {
			$list            = implode( ', ', array_fill( 0, count( $chunk ), '%s' ) );
			$where           = "({$field} IN( {$list} ) AND {$media_context_query} )";
			$searched_things = array_merge( array_map( 'md5', $chunk ), $media_context_things );
			$sql             = "SELECT * from {$tablename} WHERE {$where}";
			$prepared        = $wpdb->prepare( $sql, $searched_things ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cache_key       = md5( $prepared );
			$chunk_results   = wp_cache_get( $cache_key, 'cld_delivery' );

			if ( empty( $chunk_results ) ) {
				$chunk_results = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

				wp_cache_add( $cache_key, $chunk_results, 'cld_delivery' );
			}

			if ( ! empty( $chunk_results ) ) {
				$all_results = array_merge( $all_results, $chunk_results );
			}
		}

		return $all_results;
	}

	/**
	 * Clean a url: adds scheme if missing, removes query and fragments.
	 *
	 * @param string $url         The URL to clean.
	 * @param bool   $scheme_less Flag to clean out scheme.
	 *
	 * @return string
	 */
	public static function clean_url( $url, $scheme_less = true ) {
		$default = array(
			'scheme' => '',
			'host'   => '',
			'path'   => '',
			'port'   => '',
		);
		$parts   = wp_parse_args( wp_parse_url( $url ), $default );
		$host    = $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$host .= ':' . $parts['port'];
		}
		$url = '//' . $host . $parts['path'];

		if ( false === $scheme_less ) {
			$url = $parts['scheme'] . ':' . $url;
		}

		return $url;
	}

	/**
	 * Get the path from a url.
	 *
	 * @param string $url            The url.
	 * @param bool   $bypass_filters Flag to bypass the filters.
	 *
	 * @return string
	 */
	public static function get_path_from_url( $url, $bypass_filters = false ) {
		$content_url = content_url();

		if ( ! $bypass_filters ) {
			$content_url = apply_filters( 'cloudinary_content_url', $content_url );
		}
		$path = explode( self::clean_url( $content_url ), $url );
		$path = end( $path );

		return $path;
	}

	/**
	 * Make a scaled version.
	 *
	 * @param string $url The url to make scaled.
	 *
	 * @return string
	 */
	public static function make_scaled_url( $url ) {
		$file = self::pathinfo( $url );
		$dash = strrchr( $file['filename'], '-' );
		if ( '-scaled' === $dash ) {
			return $url;
		}

		return $file['dirname'] . '/' . $file['filename'] . '-scaled.' . $file['extension'];
	}

	/**
	 * Make a descaled version.
	 *
	 * @param string $url The url to descaled.
	 *
	 * @return string
	 */
	public static function descaled_url( $url ) {
		$file = self::pathinfo( $url );
		$dash = strrchr( $file['filename'], '-' );
		if ( '-scaled' === $dash ) {
			$file['basename'] = str_replace( '-scaled.', '.', $file['basename'] );
			$url              = $file['dirname'] . '/' . $file['basename'];
		}

		return $url;
	}

	/**
	 * Get the media context.
	 *
	 * @param int|null $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public static function get_media_context( $attachment_id = null ) {
		/**
		 * Filter the media context.
		 *
		 * This filter allows you to set a media context for the media for cases where the same asset is used in
		 * different use cases, such as in a multilingual context.
		 *
		 * @hook    cloudinary_media_context
		 * @since   3.1.9
		 * @default {'default'}
		 *
		 * @param $media_context {string}   The media context.
		 * @param $attachment_id {int|null} The attachment ID.
		 *
		 * @return {string}
		 */
		$context = apply_filters( 'cloudinary_media_context', 'default', $attachment_id );

		return sanitize_key( $context );
	}

	/**
	 * Get the home URL.
	 *
	 * @param string $path   The path to be appended to the home URL.
	 * @param string $scheme The scheme to give the home URL context. Accepts 'http', 'https', or 'relative'.
	 *
	 * @return string
	 */
	public static function home_url( $path = '', $scheme = null ) {
		$blog_id = null;
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
		}
		$home_url = get_home_url( $blog_id, $path, $scheme );

		/**
		 * Filter the home url.
		 *
		 * @hook cloudinary_home_url
		 * @since 3.2.0
		 *
		 * @param $home_url {string} The home url.
		 * @param $path     {string} The path to be appended to the home URL.
		 * @param $scheme   {string} The scheme to give the home URL context. Accepts 'http', 'https', or 'relative'.
		 *
		 * @return {string}
		 */
		return apply_filters( 'cloudinary_home_url', $home_url, $path, $scheme );
	}

	/**
	 * Get the site URL.
	 *
	 * @param string $path   The path to be appended to the site URL.
	 * @param string $scheme The scheme to give the site URL context. Accepts 'http', 'https', or 'relative'.
	 *
	 * @return string
	 */
	public static function site_url( $path = '', $scheme = null ) {
		$blog_id = null;
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
		}
		$site_url = get_site_url( $blog_id, $path, $scheme );

		/**
		 * Filter the site URL.
		 *
		 * @hook cloudinary_site_url
		 * @since 3.2.2
		 *
		 * @param $site_url {string} The site URL.
		 * @param $path     {string} The path to be appended to the site URL.
		 * @param $scheme   {string} The scheme to give the site URL context. Accepts 'http', 'https', or 'relative'.
		 *
		 * @return {string}
		 */
		return apply_filters( 'cloudinary_site_url', $site_url, $path, $scheme );
	}

	/**
	 * Get the rest URL.
	 *
	 * @param string $path   The path to be appended to the rest URL.
	 * @param string $scheme The scheme to give the rest URL context. Accepts 'http', 'https', or 'relative'.
	 *
	 * @return string
	 */
	public static function rest_url( $path = '', $scheme = null ) {
		$rest_url = rest_url( $path, $scheme );

		/**
		 * Filter the rest url.
		 *
		 * @hook cloudinary_rest_url
		 * @since 3.2.2
		 *
		 * @param $rest_url {string} The rest url.
		 * @param $path     {string} The path to be appended to the rest URL.
		 * @param $scheme   {string} The scheme to give the rest URL context. Accepts 'http', 'https', or 'relative'.
		 *
		 * @return {string}
		 */
		return apply_filters( 'cloudinary_rest_url', $rest_url, $path, $scheme );
	}

	/**
	 * Get the transformations title.
	 *
	 * @param string $context The context.
	 *
	 * @return string
	 */
	public static function get_transformations_title( $context ) {
		$transformations_title = __( 'Cloudinary global transformations', 'cloudinary' );
		$taxonomy_slug         = static::get_sanitized_text( 'taxonomy' );

		if ( $taxonomy_slug ) {
			$taxonomy              = get_taxonomy( $taxonomy_slug );
			$transformations_title = sprintf(
				// translators: %1$s is the taxonomy label and the %2$s is the context of the use.
				__( '%1$s %2$s transformations', 'cloudinary' ),
				$taxonomy->labels->singular_name,
				$context
			);

			$taxonomy_id = static::get_sanitized_text( 'tag_ID' );

			if ( $taxonomy_id ) {
				$transformations_title = sprintf(
					// translators: %s is the term name.
					__( '%s transformations', 'cloudinary' ),
					get_term( $taxonomy_id )->name
				);
			}
		}

		return $transformations_title;
	}

	/**
	 * Get inline SVG content safely.
	 *
	 * @param string $file_path The absolute or relative path to the SVG file.
	 * @param bool   $echo      Whether to echo the SVG content or return it. Default true.
	 *
	 * @return string|void The SVG content if $echo is false, void otherwise.
	 */
	public static function get_inline_svg( $file_path, $echo = true ) {
		// If relative path, make it absolute from plugin root.
		if ( ! file_exists( $file_path ) ) {
			$plugin_dir = dirname( __DIR__ );
			$file_path  = $plugin_dir . '/' . ltrim( $file_path, '/' );
		}

		// Check if file exists and is an SVG.
		if ( ! file_exists( $file_path ) || 'svg' !== pathinfo( $file_path, PATHINFO_EXTENSION ) ) {
			return '';
		}

		// Get the SVG content.
		$svg_content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $svg_content ) {
			return '';
		}

		// Sanitize SVG content to prevent XSS attacks.
		$svg_content = self::sanitize_svg( $svg_content );

		if ( $echo ) {
			echo $svg_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized by sanitize_svg.
		} else {
			return $svg_content;
		}
	}

	/**
	 * Sanitize SVG content to prevent XSS attacks.
	 *
	 * @param string $svg_content The SVG content to sanitize.
	 *
	 * @return string The sanitized SVG content.
	 */
	public static function sanitize_svg( $svg_content ) {
		// Define allowed SVG tags and attributes.
		$allowed_tags = array(
			'svg'            => array(
				'class'           => true,
				'aria-hidden'     => true,
				'aria-labelledby' => true,
				'role'            => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
			),
			'g'              => array(
				'fill'      => true,
				'stroke'    => true,
				'transform' => true,
				'class'     => true,
			),
			'title'          => array( 'title' => true ),
			'desc'           => array(),
			'path'           => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'transform'       => true,
				'class'           => true,
			),
			'rect'           => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
				'class'        => true,
			),
			'circle'         => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
				'class'        => true,
			),
			'ellipse'        => array(
				'cx'           => true,
				'cy'           => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
				'class'        => true,
			),
			'line'           => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
				'class'        => true,
			),
			'polyline'       => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
				'class'        => true,
			),
			'polygon'        => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
				'class'        => true,
			),
			'defs'           => array(),
			'lineargradient' => array(
				'id'            => true,
				'x1'            => true,
				'y1'            => true,
				'x2'            => true,
				'y2'            => true,
				'gradientunits' => true,
			),
			'radialgradient' => array(
				'id'            => true,
				'cx'            => true,
				'cy'            => true,
				'r'             => true,
				'gradientunits' => true,
			),
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			),
			'use'            => array(
				'xlink:href' => true,
				'href'       => true,
			),
			'clippath'       => array( 'id' => true ),
			'mask'           => array( 'id' => true ),
			'pattern'        => array(
				'id'           => true,
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'patternunits' => true,
			),
			'text'           => array(
				'x'           => true,
				'y'           => true,
				'fill'        => true,
				'font-size'   => true,
				'font-family' => true,
				'text-anchor' => true,
				'transform'   => true,
				'class'       => true,
			),
			'tspan'          => array(
				'x'           => true,
				'y'           => true,
				'fill'        => true,
				'font-size'   => true,
				'font-family' => true,
				'class'       => true,
			),
		);

		/**
		 * Filter the allowed SVG tags and attributes.
		 *
		 * @hook   cloudinary_allowed_svg_tags
		 * @since  3.2.15
		 *
		 * @param $allowed_tags {array} The allowed SVG tags and their attributes.
		 *
		 * @return {array}
		 */
		$allowed_tags = apply_filters( 'cloudinary_allowed_svg_tags', $allowed_tags );

		// Use wp_kses to sanitize the SVG content.
		return wp_kses( $svg_content, $allowed_tags );
	}
}
