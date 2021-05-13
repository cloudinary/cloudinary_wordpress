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

	const BASE = 'cloudinary/v1';

	/**
	 * Plugin REST API endpoints.
	 *
	 * @var array
	 */
	public $endpoints;

	/**
	 * REST_API constructor.
	 *
	 * @param Plugin $plugin Instance of the global Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ), PHP_INT_MAX );
	}

	/**
	 * Init the REST API endpoints.
	 */
	public function rest_api_init() {
		$this->endpoints = apply_filters( 'cloudinary_api_rest_endpoints', array() );

		foreach ( $this->endpoints as $route => $endpoint ) {
			$endpoint = wp_parse_args(
				$endpoint,
				array(
					'method'              => 'GET',
					'callback'            => null,
					'arg'                 => array(),
					'permission_callback' => '__return_true',
				)
			);
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
	 * Initilize a background request.
	 *
	 * @param string $endpoint The REST API endpoint to call.
	 * @param array  $params   Array of parameters to send.
	 * @param string $method   The method to use in the call.
	 */
	public function background_request( $endpoint, $params = array(), $method = 'POST' ) {

		$url = rest_url( static::BASE . '/' . $endpoint );
		// Setup a call for a background sync.
		$params['nonce'] = wp_create_nonce( 'wp_rest' );
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
		wp_safe_remote_request( $url, $args );
	}
}
