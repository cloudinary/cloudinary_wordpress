<?php
/**
 * Connect class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Component\Config;
use Cloudinary\Component\Notice;
use Cloudinary\Component\Setup;
use Cloudinary\Connect\Api;

/**
 * Cloudinary connection class.
 *
 * Sets up the initial cloudinary connection and makes the API object available for some uses.
 */
class Connect implements Config, Setup, Notice {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	private $plugin;

	/**
	 * Holds the cloudinary API instance
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Connect\Api
	 */
	public $api;

	/**
	 * Holds the cloudinary usage info.
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	public $usage;

	/**
	 * Holds the cloudinary credentials.
	 *
	 * @since   0.1
	 *
	 * @var     array
	 */
	private $credentials = array();

	/**
	 * Holds the handle for the media page.
	 *
	 * @var string
	 */
	public $handle;

	/**
	 * Initiate the plugin resources.
	 *
	 * @param \Cloudinary\Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'pre_update_option_cloudinary_connect', array( $this, 'verify_connection' ) );
	}

	/**
	 * Add the Cloudinary media library scripts.
	 */
	public function media_library_script() {
		$screen = get_current_screen();
		if ( is_object( $screen ) && $screen->id === $this->handle ) {

			// External assets.
			wp_enqueue_script( 'cloudinary-media-library', 'https://media-library.cloudinary.com/global/all.js', array(), $this->plugin->version, true );
			$params = array(
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'mloptions' => array(
					'cloud_name'    => $this->credentials['cloud_name'],
					'api_key'       => $this->credentials['api_key'],
					'remove_header' => true,
				),
			);

			// sign maybe.
			if ( ! empty( $this->credentials['user_email'] ) ) {
				$timestamp                        = current_time( 'timestamp' );
				$params['mloptions']['username']  = $this->credentials['user_email'];
				$params['mloptions']['timestamp'] = (string) $timestamp;
				$query                            = array(
					'cloud_name' => $this->credentials['cloud_name'],
					'timestamp'  => $timestamp,
					'username'   => $this->credentials['user_email'] . $this->credentials['api_secret'],
				);
				$params['mloptions']['signature'] = hash( 'sha256', build_query( $query ) );
			}
			$params['mloptions']['insert_transformation'] = true;
			$params['mloptions']['inline_container']      = '#cloudinary-embed';

			wp_add_inline_script( 'cloudinary-media-library', 'var CLD_ML = ' . wp_json_encode( $params ), 'before' );
		}
	}

	/**
	 * Verify that the connection details are correct.
	 *
	 * @param array $data The submitted data to verify.
	 *
	 * @return array The data if cleared.
	 */
	public function verify_connection( $data ) {
		if ( empty( $data['cloudinary_url'] ) ) {
			delete_option( 'cloudinary_connection_signature' );
			add_settings_error( 'cloudinary_connect', 'connection_error', __( 'Connection to Cloudinary has been removed.', 'cloudinary' ), 'notice-warning' );

			return $data;
		}
		$current = $this->plugin->config['settings']['connect'];
		if ( $current['cloudinary_url'] === $data['cloudinary_url'] ) {
			return $data;
		}

		if ( ! preg_match( '~^cloudinary://~', $data['cloudinary_url'] ) ) {
			add_settings_error( 'cloudinary_connect', 'format_mismatch', __( 'The Environment variable URL must be in this format: cloudinary://API_Key:API_Secret@Cloud_Name', 'cloudinary' ), 'error' );

			return $current;
		}

		$result = $this->test_connection( $data['cloudinary_url'] );
		if ( ! empty( $result['message'] ) ) {
			add_settings_error( 'cloudinary_connect', $result['type'], $result['message'], 'error' );

			return $current;
		}
		add_settings_error( 'cloudinary_connect', 'connection_success', __( 'Successfully connected to Cloudinary.', 'cloudinary' ), 'updated' );
		update_option( 'cloudinary_connection_signature', md5( $data['cloudinary_url'] ) );

		return $data;
	}

	/**
	 * Test the connection url.
	 *
	 * @param string $url The url to test.
	 *
	 * @return mixed
	 */
	public function test_connection( $url ) {
		$result = array(
			'type'    => 'connection_success',
			'message' => null,
		);

		$test  = wp_parse_url( $url );
		$valid = array_filter(
			array_keys( $test ),
			function ( $a ) {
				return in_array( $a, [ 'scheme', 'host', 'user', 'pass' ], true );
			}
		);

		if ( 4 > count( $valid ) ) {
			$result['type']    = 'invalid_url';
			$result['message'] = sprintf(
			// translators: Placeholder refers to the expected URL format.
				__( 'Incorrect Format. Expecting: %s', 'cloudinary' ),
				'<code>cloudinary://API_Key:API_Secret@Cloud_Name</code>'
			);

			return $result;
		}
		// Test if has a cname and is valid.
		if ( ! empty( $test['query'] ) ) {
			$config_params = array();
			wp_parse_str( $test['query'], $config_params );
			if ( ! empty( $config_params['cname'] ) ) {
				if ( defined( 'FILTER_VALIDATE_DOMAIN' ) ) {
					$is_valid = filter_var( $config_params['cname'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME );
				} else {
					$cname    = 'https://' . $config_params['cname'];
					$is_valid = filter_var( $cname, FILTER_VALIDATE_URL );
				}
				if ( ! substr_count( $is_valid, '.' ) || false === $is_valid ) {
					$result['type']    = 'invalid_cname';
					$result['message'] = __( 'CNAME is not a valid domain name.', 'cloudinary' );

					return $result;
				}
			}
		}

		$this->config_from_url( $url );
		$test = new Connect\Api( $this, $this->plugin->version );
		$test = $test->ping();
		if ( is_wp_error( $test ) ) {
			$result['type']    = 'connection_error';
			$result['message'] = ucwords( str_replace( '_', ' ', $test->get_error_message() ) );
		}

		return $result;
	}

	/**
	 * Get the Cloudinary credentials.
	 *
	 * @return array
	 */
	public function get_credentials() {
		return $this->credentials;
	}

	/**
	 * Set the config credentials from an array.
	 *
	 * @param array $data The config array data.
	 *
	 * @return array
	 */
	public function set_credentials( $data = array() ) {
		$this->credentials = array_merge( $this->credentials, $data );

		return $this->credentials;
	}

	/**
	 * Set the credentials from the cloudinary url.
	 *
	 * @param string $url The Cloudinary URL.
	 */
	public function config_from_url( $url ) {
		$parts = wp_parse_url( $url );
		$creds = array();

		foreach ( $parts as $type => $part ) {
			switch ( $type ) {
				case 'host':
					$creds['cloud_name'] = $part;
					break;
				case 'user':
					$creds['api_key'] = $part;
					break;
				case 'pass':
					$creds['api_secret'] = $part;
					break;
			}
		}

		$this->set_credentials( $creds );

		// Check for and Append query params.
		if ( ! empty( $parts['query'] ) ) {
			$config_params = array();
			wp_parse_str( $parts['query'], $config_params );
			if ( ! empty( $config_params ) ) {
				$this->set_credentials( $config_params );
			}
		}
	}

	/**
	 * Setup connection
	 *
	 * @since  0.1
	 */
	public function setup() {
		// Get the cloudinary url from plugin config.
		$config = $this->plugin->config['settings']['connect'];
		if ( ! empty( $config['cloudinary_url'] ) ) {

			$this->config_from_url( $config['cloudinary_url'] );

			$this->api = new Connect\Api( $this, $this->plugin->version );
			$stats     = get_transient( '_cloudinary_usage' );
			if ( empty( $stats ) ) {
				// Get users plan.
				$stats = $this->plugin->components['connect']->api->usage();
				if ( ! is_wp_error( $stats ) && ! empty( $stats['media_limits'] ) ) {
					$stats['max_image_size'] = $stats['media_limits']['image_max_size_bytes'];
					$stats['max_video_size'] = $stats['media_limits']['video_max_size_bytes'];
					set_transient( '_cloudinary_usage', $stats, HOUR_IN_SECONDS );
				}
			}
			$this->usage = $stats;

		}
	}

	/**
	 * Gets the config of a connection.
	 *
	 * @since  0.1
	 *
	 * @return array The array of the config options stored.
	 */
	public function get_config() {
		$signature = get_option( 'cloudinary_connection_signature', null );
		if ( empty( $signature ) ) {
			// Check if theres a previous version.
			$version = get_option( 'cloudinary_version' );
			if ( version_compare( $this->plugin->version, $version, '>' ) ) {
				$data = array(
					'cloudinary_url' => get_option( 'cloudinary_url' ),
				);
				$test = $this->test_connection( $data['cloudinary_url'] );
				if ( 'connection_success' === $test['type'] ) {
					$signature = md5( $data['cloudinary_url'] );

					// remove filters as we've already verified it and 'add_settings_error()' isin't available yet.
					remove_filter( 'pre_update_option_cloudinary_connect', array( $this, 'verify_connection' ) );
					update_option( 'cloudinary_connect', $data );
					update_option( 'cloudinary_connection_signature', $signature );
					update_option( 'cloudinary_version', $this->plugin->version );
					delete_option( 'cloudinary_settings_cache' ); // remove the cache.
					$this->plugin->config['settings']['connect'] = $data; // Set the connection url for this round.
				}
			}
		}

		return $signature;
	}

	/**
	 * Get admin notices.
	 */
	public function get_notices() {
		$screen  = get_current_screen();
		$notices = array();
		if ( empty( $this->plugin->config['connect'] ) ) {
			if ( is_object( $screen ) && in_array( $screen->id, $this->plugin->components['settings']->handles, true ) ) {
				$link      = '<a href="' . esc_url( admin_url( 'admin.php?page=cld_connect' ) ) . '">' . __( 'Connect', 'cloudinary' ) . '</a> ';
				$notices[] = array(
					'message'     => $link . __( 'your Cloudinary account with WordPress to get started.', 'cloudinary' ),
					'type'        => 'error',
					'dismissible' => true,
				);
			}
		}

		return $notices;
	}

}
