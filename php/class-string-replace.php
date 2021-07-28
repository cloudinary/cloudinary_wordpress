<?php
/**
 * Cloudinary string replace class, to replace URLS and other strings on shutdown.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * String replace class.
 */
class String_Replace {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the list of strings and replacements.
	 *
	 * @var array
	 */
	protected static $replacements = array();

	/**
	 * Site Cache constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'template_redirect', array( $this, 'init' ), 1 );
		add_action( 'template_include', array( $this, 'init_debug' ), 1 );
		$types = get_post_types_by_support( 'editor' );
		foreach ( $types as $type ) {
			$post_type = get_post_type_object( $type );
			// Check if this is a rest supported type.
			if ( true === $post_type->show_in_rest ) {
				// Add filter only to rest supported types.
				add_filter( 'rest_prepare_' . $type, array( $this, 'pre_filter_rest_content' ), 10, 3 );
			}
		}
	}

	/**
	 * Filter out local urls in an 'edit' context rest request ( i.e for Gutenburg ).
	 *
	 * @param \WP_REST_Response $response The post data array to save.
	 * @param \WP_Post          $post     The current post.
	 * @param \WP_REST_Request  $request  The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function pre_filter_rest_content( $response, $post, $request ) {
		$context = $request->get_param( 'context' );
		if ( 'view' === $context ) {
			$data                        = $response->get_data();
			$data['content']['rendered'] = $this->replace_strings( $data['content']['rendered'] );
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Init the buffer capture and set the output callback.
	 */
	public function init() {
		if ( ! defined( 'WP_DEBUG' ) || false === WP_DEBUG ) {
			ob_start( array( $this, 'replace_strings' ) );
		}
	}

	/**
	 * Init the buffer capture in debug mode.
	 *
	 * @param string $template The template being loaded.
	 *
	 * @return null|string
	 */
	public function init_debug( $template ) {
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			ob_start();
			include $template;
			$html = ob_get_clean();
			echo $this->replace_strings( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$template = null;
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
	public static function string_set( $string ) {
		return isset( self::$replacements[ $string ] );
	}

	/**
	 * Check if a string is not set for replacement.
	 *
	 * @param string $string String to check.
	 *
	 * @return bool
	 */
	public static function string_not_set( $string ) {
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
	 * Replace string in HTML.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	public function replace_strings( $html ) {

		/**
		 * Do replacement action.
		 *
		 * @hook    cloudinary_string_replace
		 *
		 * @param $html {string} The html of the page.
		 */
		do_action( 'cloudinary_string_replace', $html );
		if ( ! empty( self::$replacements ) ) {
			$html = str_replace( array_keys( self::$replacements ), array_values( self::$replacements ), $html );
		}

		return $html;
	}
}
