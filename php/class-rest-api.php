<?php
/**
 * REST_API is the parent component for the Cloudinary plugin endpoints.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Class REST_API
 */
class REST_API {

	/**
	 * Base path for the REST API endpoints.
	 *
	 * @var string
	 */
	const BASE = 'cloudinary/v1';

	/**
	 * Plugin REST API endpoints.
	 *
	 * @var array
	 */
	public $endpoints;

	/**
	 * The nonce key used for WordPress REST API authentication.
	 *
	 * @var string
	 */
	const NONCE_KEY = 'wp_rest';

	/**
	 * REST_API constructor.
	 *
	 * @param Plugin $plugin Instance of the global Plugin.
	 */
	public function __construct( Plugin $plugin ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ), PHP_INT_MAX );
	}

	/**
	 * Init the REST API endpoints.
	 */
	public function rest_api_init() {

		$defaults = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => __return_empty_array(),
			'args'                => array(),
			'permission_callback' => array( __CLASS__, 'validate_request' ),
		);

		$this->endpoints = apply_filters( 'cloudinary_api_rest_endpoints', array() );

		foreach ( $this->endpoints as $route => $endpoint ) {
			$endpoint = wp_parse_args( $endpoint, $defaults );

			register_rest_route(
				static::BASE,
				$route,
				array(
					'methods'             => $endpoint['method'],
					'callback'            => $endpoint['callback'],
					'args'                => $endpoint['args'],
					'permission_callback' => $endpoint['permission_callback'],
				)
			);
		}
	}

	/**
	 * Basic permission callback.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @return bool
	 */
	public static function rest_can_connect() {
		return Utils::user_can( 'connect' );
	}

	/**
	 * Initilize a background request.
	 *
	 * @param string $endpoint The REST API endpoint to call.
	 * @param array  $params   Array of parameters to send.
	 * @param string $method   The method to use in the call.
	 */
	public function background_request( $endpoint, $params = array(), $method = 'POST' ) {

		$url = Utils::rest_url( static::BASE . '/' . $endpoint );
		// Setup a call for a background sync.
		$params['nonce'] = wp_create_nonce( static::NONCE_KEY );
		$args            = array(
			'timeout'   => 0.1,
			'blocking'  => false,
			/** This filter is documented in wp-includes/class-wp-http-streams.php */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'method'    => $method,
			'headers'   => array(),
			'body'      => $params,
		);
		if ( is_user_logged_in() ) {
			// Setup cookie.
			$logged_cookie = wp_parse_auth_cookie( '', 'logged_in' );
			if ( ! empty( $logged_cookie ) ) {
				array_pop( $logged_cookie ); // remove the scheme.

				// Add logged in cookie to request.
				$args['cookies'] = array(
					new \WP_Http_Cookie(
						array(
							'name'    => LOGGED_IN_COOKIE,
							'value'   => implode( '|', $logged_cookie ),
							'expires' => '+ 1 min', // Expire after a min only.
						),
						$url
					),
				);
			}
		}
		$args['headers']['X-WP-Nonce'] = $params['nonce'];

		// Send request.
		wp_remote_request( $url, $args );
	}

	/**
	 * Validation for request.
	 *
	 * @param \WP_REST_Request $request The original request.
	 *
	 * @return bool
	 */
	public static function validate_request( $request ) {
		return wp_verify_nonce( $request->get_header( 'x_wp_nonce' ), self::NONCE_KEY );
	}

	/**
	 * Permission callback for public health check endpoints.
	 *
	 * Intentionally allows unauthenticated access for REST API connectivity testing.
	 * This endpoint is read-only and returns no sensitive data.
	 *
	 * @return bool Always returns true to allow public access.
	 */
	public static function allow_public_health_check() {
		return true;
	}
}
