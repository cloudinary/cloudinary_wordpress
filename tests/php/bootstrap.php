<?php
/**
 * PHPUnit bootstrap file.
 *
 * Defines the minimal WordPress function stubs needed to load class-connect.php
 * in isolation, without a full WordPress installation.
 *
 * @package Cloudinary
 */

// Autoload the plugin's PHP classes.
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Cloudinary\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( $prefix, '', $class );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );
		$file     = __DIR__ . '/../../php/' . $relative . '.php';

		// Also try class- prefix convention.
		if ( ! file_exists( $file ) ) {
			$parts    = explode( '/', $relative );
			$last     = array_pop( $parts );
			$parts[]  = 'class-' . $last;
			$file     = __DIR__ . '/../../php/' . implode( '/', $parts ) . '.php';
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// ---------------------------------------------------------------------------
// Minimal WordPress stubs so class-connect.php can be parsed without WP core.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stub for wp_parse_url().
	 *
	 * @param string   $url       The URL.
	 * @param int      $component Optional PHP_URL_* constant.
	 * @return array|string|int|null
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/** Stub for add_filter(). */
	function add_filter() {}
}

if ( ! function_exists( 'add_action' ) ) {
	/** Stub for add_action(). */
	function add_action() {}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub for __().
	 *
	 * @param string $text   The text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Stub for is_wp_error().
	 *
	 * @param mixed $thing Value to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/** Minimal WP_Error stub. */
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/** @return string */
		public function get_error_code() {
			return $this->code;
		}

		/** @return string */
		public function get_error_message() {
			return $this->message;
		}
	}
}
