<?php
/**
 * Cloudinary string replace class, to replace URLS and other strings on shutdown.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Setup;

/**
 * String replace class.
 */
class String_Replace implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the context.
	 *
	 * @var string
	 */
	protected $context;

	/**
	 * Holds the list of strings and replacements.
	 *
	 * @var array
	 */
	protected static $replacements = array();

	/**
	 * Holds the list of strings and replacements.
	 *
	 * @var bool
	 */
	protected static $doing_save = false;

	/**
	 * Site Cache constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Setup the object.
	 */
	public function setup() {
		if ( Utils::is_admin() ) {
			$this->admin_filters();
		} elseif ( Utils::is_frontend_ajax() || Utils::is_rest_api() ) {
			$this->context = 'view';
			$this->start_capture();
		} else {
			$this->public_filters();
		}
		$this->add_rest_filters();
	}

	/**
	 * Add admin filters.
	 */
	protected function admin_filters() {
		// Admin filters can call String_Replace frequently, which is fine, as performance is not an issue.
		add_filter( 'media_send_to_editor', array( $this, 'replace_strings' ), 10, 2 );
		add_filter( 'the_editor_content', array( $this, 'replace_strings' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'replace_strings' ), 11 );
		add_action( 'admin_init', array( $this, 'start_capture' ) );
	}

	/**
	 * Add Public Filters.
	 */
	protected function public_filters() {
		add_action( 'template_include', array( $this, 'init_debug' ), PHP_INT_MAX );
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) { // Not needed on REST API.
			add_action( 'parse_request', array( $this, 'init' ), - 1000 ); // Not crazy low, but low enough to catch most cases, but not too low that it may break AMP.
		}
	}

	/**
	 * Add filters for REST API.
	 */
	protected function add_rest_filters() {
		$types = get_post_types();
		foreach ( $types as $type ) {
			$post_type = get_post_type_object( $type );
			// Check if this is a rest supported type.
			if ( property_exists( $post_type, 'show_in_rest' ) && true === $post_type->show_in_rest ) {
				// Add filter only to rest supported types.
				add_filter( 'rest_prepare_' . $type, array( $this, 'pre_filter_rest_content' ), 10, 3 );
			}
		}
		add_filter( 'rest_pre_echo_response', array( $this, 'pre_filter_rest_echo' ), 900, 3 );
	}

	/**
	 * Filter the result of a rest Request before it's echoed.
	 *
	 * @param array            $result  The result of the REST API.
	 * @param \WP_REST_Server  $server  The REST server instance.
	 * @param \WP_REST_Request $request The original request.
	 *
	 * @return array
	 */
	public function pre_filter_rest_echo( $result, $server, $request ) {
		$route = trim( $request->get_route(), '/' );
		if ( 0 !== strpos( $route, REST_API::BASE ) ) {
			// Only for non-Cloudinary requests.
			$context = $request->get_param( 'context' );
			if ( ! $context || 'view' === $context ) {
				$result = $this->replace_strings( $result, $context );
			}
		}

		return $result;
	}

	/**
	 * Filter out local urls in an 'edit' context rest request ( i.e for Gutenberg ).
	 *
	 * @param \WP_REST_Response $response The post data array to save.
	 * @param \WP_Post          $post     The current post.
	 * @param \WP_REST_Request  $request  The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function pre_filter_rest_content( $response, $post, $request ) {
		$context = $request->get_param( 'context' );

		if ( 'edit' === $context ) {
			// Updating or creating a post.
			if ( in_array( $request->get_method(), array( 'PUT', 'POST' ), true ) ) {
				static::$doing_save = true;
			}
			$data = $response->get_data();
			$data = $this->replace_strings( $data, $context );
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Init the buffer capture and set the output callback.
	 */
	public function init() {
		if ( ! defined( 'CLD_DEBUG' ) || false === CLD_DEBUG ) {
			$this->context = 'view';
			$this->start_capture();
		}
	}

	/**
	 * Stop the buffer capture and set the output callback.
	 */
	public function start_capture() {
		ob_start( array( $this, 'replace_strings' ) );
		ob_start( array( $this, 'replace_strings' ) ); // Second call to catch early buffer flushing.
	}

	/**
	 * Init the buffer capture in debug mode.
	 *
	 * @param string $template The template being loaded.
	 *
	 * @return null|string
	 */
	public function init_debug( $template ) {
		if ( defined( 'CLD_DEBUG' ) && true === CLD_DEBUG && ! Utils::get_sanitized_text( '_bypass' ) ) {
			$this->context = 'view';
			ob_start();
			include $template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			$html = ob_get_clean();
			echo $this->replace_strings( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$template = $this->plugin->template_path . 'blank-template.php';
		}

		return $template;
	}

	/**
	 * Check if a string is set for replacement.
	 *
	 * @param string $string String to check.
	 *
	 * @return bool
	 */
	public static function string_set( $string ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
		return isset( self::$replacements[ $string ] );
	}

	/**
	 * Check if a string is not set for replacement.
	 *
	 * @param string $string String to check.
	 *
	 * @return bool
	 */
	public static function string_not_set( $string ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
		return ! self::string_set( $string );
	}

	/**
	 * Replace a string.
	 *
	 * @param string $search  The string to be replaced.
	 * @param string $replace The string replacement.
	 */
	public static function replace( $search, $replace ) {
		self::$replacements[ $search ] = $replace;
	}

	/**
	 * Flatten an array into content.
	 *
	 * @param array|string $content The array to flatten.
	 *
	 * @return string
	 */
	public function flatten( $content ) {
		$flat = '';
		if ( Utils::looks_like_json( $content ) ) {
			$maybe_content = json_decode( $content, true );
			if ( ! empty( $maybe_content ) ) {
				$content = $maybe_content;
			}
		}
		if ( self::is_iterable( $content ) ) {
			foreach ( $content as $item ) {
				$flat .= "\r\n" . $this->flatten( $item );
			}
		} else {
			$flat = $content;
		}

		return $flat;
	}

	/**
	 * Check if the item is iterable.
	 *
	 * @param mixed $thing Thing to check.
	 *
	 * @return bool
	 */
	public static function is_iterable( $thing ) {

		return is_array( $thing ) || is_object( $thing );
	}

	/**
	 * Prime replacement strings.
	 *
	 * @param mixed  $content The content to prime replacements for.
	 * @param string $context The context to use.
	 */
	protected function prime_replacements( $content, $context = 'view' ) {

		if ( self::is_iterable( $content ) ) {
			$content = $this->flatten( $content );
		}
		/**
		 * Do replacement action.
		 *
		 * @hook  cloudinary_string_replace
		 * @since 3.0.3 Added the `$context` argument.
		 *
		 * @param $content {string} The html of the page.
		 * @param $context {string} The render context.
		 */
		do_action( 'cloudinary_string_replace', $content, $context );
	}

	/**
	 * Replace string in HTML.
	 *
	 * @param string|array $content The HTML.
	 * @param string       $context The context to use.
	 *
	 * @return string
	 */
	public function replace_strings( $content, $context = 'view' ) {
		static $last_content;
		if ( empty( $content ) || $last_content === $content ) {
			return $content; // Bail if nothing to replace.
		}
		// Captured a front end request, since the $context will be an int.
		if ( ! empty( $this->context ) ) {
			$context = $this->context;
		}
		if ( Utils::looks_like_json( $content ) ) {
			$json_maybe = json_decode( $content, true );
			if ( ! empty( $json_maybe ) ) {
				$content = $json_maybe;
			}
		}
		$this->prime_replacements( $content, $context );
		if ( ! empty( self::$replacements ) ) {
			$content = self::do_replace( $content );
		}
		self::reset();
		$last_content = ! empty( $json_maybe ) ? wp_json_encode( $content ) : $content;

		return $last_content;
	}

	/**
	 * Do string replacements.
	 *
	 * @param array|string $content The content to do replacements on.
	 *
	 * @return array|string
	 */
	public static function do_replace( $content ) {

		if ( self::is_iterable( $content ) ) {
			foreach ( $content as &$item ) {
				$item = self::do_replace( $item );
			}
		} elseif ( is_string( $content ) ) {
			$content = str_replace( array_keys( self::$replacements ), array_values( self::$replacements ), $content );
		}

		return $content;
	}

	/**
	 * Check if we are currently saving a REST request (i.e. Gutenberg).
	 * This is used to prevent double replacements.
	 *
	 * @return bool
	 */
	public function doing_save() {
		return static::$doing_save;
	}

	/**
	 * Reset internal replacements.
	 */
	public static function reset() {
		self::$replacements = array();
	}
}
