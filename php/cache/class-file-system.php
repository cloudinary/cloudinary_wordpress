<?php
/**
 * Handles reading from the file system.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Cache;

use Cloudinary\Plugin;

/**
 * Class File System.
 *
 * Handles reading from the file system.
 */
class File_System {

	/**
	 * The plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * WP file system
	 *
	 * @var \WP_Filesystem_Direct
	 */
	public $wp_file_system;

	/**
	 * Holds the path locations
	 *
	 * @var array
	 */
	protected $paths;

	/**
	 * File_System constructor.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$this->wp_file_system = $wp_filesystem;
			$this->setup_paths();
		}
	}

	/**
	 * Setup the paths.
	 */
	protected function setup_paths() {
		$paths = array(
			plugins_url()        => $this->wp_file_system->wp_plugins_dir(),
			get_theme_root_uri() => $this->wp_file_system->wp_themes_dir(),
			content_url()        => $this->wp_file_system->wp_content_dir(),
			admin_url()          => $this->wp_file_system->abspath() . 'wp-admin/',
			includes_url()       => $this->wp_file_system->abspath() . 'wp-includes/',
			home_url()           => $this->wp_file_system->abspath(),
		);
		foreach ( $paths as $url => $path ) {
			$this->paths[ trailingslashit( $url ) ] = trailingslashit( $path );
		}
	}

	/**
	 * Check if the file system is available.
	 *
	 * @return bool
	 */
	public function enabled() {
		return ! is_null( $this->wp_file_system );
	}

	/**
	 * Get the wp_filesystem.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	public function wp() {
		return $this->wp_file_system;
	}

	/**
	 * Checks if resource is a file.
	 *
	 * @param string $file File path.
	 *
	 * @return bool Whether $file is a file.
	 */
	public function is_file( $file ) {
		return $this->wp_file_system->is_file( $file );
	}

	/**
	 * Checks if resource is a directory.
	 *
	 * @param string $path Directory path.
	 *
	 * @return bool Whether $path is a directory.
	 */
	public function is_dir( $path ) {
		return $this->wp_file_system->is_dir( $path );
	}

	/**
	 * Get the plugins dir.
	 *
	 * @return string
	 */
	public function wp_plugins_dir() {
		return $this->wp_file_system->wp_plugins_dir();
	}

	/**
	 * Get the content dir.
	 *
	 * @return string
	 */
	public function wp_content_dir() {
		return $this->wp_file_system->wp_content_dir();
	}

	/**
	 * Get the themes dir.
	 *
	 * @return string
	 */
	public function wp_themes_dir() {
		return $this->wp_file_system->wp_themes_dir();
	}

	/**
	 * Get the Admin dir.
	 *
	 * @return string
	 */
	public function wp_admin_dir() {
		return $this->wp_file_system->abspath() . 'wp-admin/';
	}

	/**
	 * Get the Includes dir.
	 *
	 * @return string
	 */
	public function wp_includes_dir() {
		return $this->wp_file_system->abspath() . 'wp-includes/';
	}

	/**
	 * Get the root locations for a file.
	 *
	 * @param string $file_url The file to cet locations for.
	 *
	 * @return array
	 */
	public function get_file_src_root( $file_url ) {

		foreach ( $this->paths as $url => $src ) {
			if ( false !== strpos( $file_url, $url ) ) {
				return array(
					'path' => $src,
					'url'  => $url,
				);
			}
		}
	}

	/**
	 * Determine the location of the path.
	 *
	 * @param string $path The path.
	 *
	 * @return string
	 */
	public function get_location( $path ) {
		$location_path = trailingslashit( dirname( $path ) );
		$type          = array_search( $location_path, $this->paths );

		return $type ? $type : 'root';
	}

	/**
	 * Get the file path from the URL.
	 *
	 * @param string $file_url The url to get file src for.
	 *
	 * @return string|null
	 */
	public function get_src_path( $file_url ) {
		$src_root = $this->get_file_src_root( $file_url );
		$src_file = wp_normalize_path( str_replace( $src_root['url'], $src_root['path'], $file_url ) );
		if ( $this->wp_file_system->exists( $src_file ) ) {
			return $src_file;
		}

		return null;
	}

	/**
	 * Get the URL's for a list of src files.
	 *
	 * @param string $path    The path to get urls for.
	 * @param array  $files   The list of files.
	 * @param null   $version The version.
	 *
	 * @return array|false|mixed
	 */
	public function get_file_urls( $path, $files = array(), $version = null ) {

		$location_point = basename( $path );
		$type           = $this->get_location( $path );
		switch ( $type ) {
			case 'plugin':
				$callback = 'plugins_url';
				break;
			case 'theme':
				$callback = function ( $file ) {
					return trailingslashit( get_theme_root_uri() ) . $file;
				};
				break;
			case 'content':
				$callback = 'content_url';
				break;
			case 'admin':
				$callback = 'admin_url';
				break;
			case 'includes':
				$callback = 'includes_url';
				break;
			default:
				$callback = 'home_url';
				break;
		}
		if ( empty( $files ) ) {
			return call_user_func( $callback, $location_point );
		}
		$urls    = array();
		$version = $version ? $version : get_bloginfo( 'version' );
		foreach ( $files as $file ) {
			$url          = call_user_func( $callback, wp_normalize_path( $location_point . '/' . $file ) );
			$file         = trailingslashit( $path ) . $file;
			$urls[ $url ] = add_query_arg( 'ver', $version, $file );
		}

		return $urls;
	}

	/**
	 * Get files from a folder.
	 *
	 * @param string $path  The file path.
	 * @param array  $types The file types to include.
	 *
	 * @return array
	 */
	public function get_files( $path, $types = array() ) {

		$exclude = array(
			'node_modules',
			'vendor',
		);

		$files = list_files( $path, 100, $exclude );
		if ( ! empty( $types ) ) {
			$files = array_filter(
				$files,
				function ( $file ) use ( $types ) {
					return in_array( pathinfo( $file, PATHINFO_EXTENSION ), $types, true );
				}
			);
		}

		$path_len = strlen( $path ) + 1;
		$files    = array_map(
			function ( $file ) use ( $path_len ) {
				return substr( $file, $path_len );
			},
			$files
		);
		sort( $files );

		return self::filter_min( $files );
	}

	/**
	 * Get all extensions of files in an array.
	 *
	 * @param array $files Files array to get extension for.
	 *
	 * @return array
	 */
	public static function get_unique_extensions( $files ) {
		$all_extensions = array_map(
			function ( $file ) {
				return pathinfo( $file, PATHINFO_EXTENSION );
			},
			$files
		);
		$return         = array_filter( $all_extensions );
		sort( $return );

		return array_unique( $return );
	}

	/**
	 * Filter out file types from a list of files.
	 *
	 * @param array $files The list of files to filter.
	 * @param array $types The types to filter.
	 *
	 * @return array
	 */
	public static function filter_file_types( $files, $types ) {
		$files = array_filter(
			$files,
			function ( $file ) use ( $types ) {
				return in_array( pathinfo( $file, PATHINFO_EXTENSION ), $types, true );
			}
		);
		sort( $files );

		return self::filter_min( $files );
	}

	/**
	 * Filter out files that have a .min version.
	 *
	 * @param array $files List of files to filter.
	 *
	 * @return array
	 */
	public static function filter_min( $files ) {

		$files = array_filter(
			$files,
			function ( $file ) use ( $files ) {
				$ext      = pathinfo( $file, PATHINFO_EXTENSION );
				$offset   = strlen( $file ) - strlen( $ext );
				$min_file = substr( $file, 0, $offset ) . 'min.' . $ext;

				return ! in_array( $min_file, $files );
			}
		);

		return $files;
	}
}
