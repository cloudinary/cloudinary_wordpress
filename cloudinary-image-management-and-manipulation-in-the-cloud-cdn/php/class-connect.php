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
	 * @var     \Cloudinary\Api
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
	 * Holder of general notices.
	 *
	 * @var array
	 */
	protected $notices = array();

	/**
	 * Account Disabled Flag.
	 *
	 * @var bool
	 */
	public $disabled = false;

	/**
	 * Holds the meta keys for connect meta to maintain consistency.
	 */
	const META_KEYS = array(
		'usage'      => '_cloudinary_usage',
		'last_usage' => '_cloudinary_last_usage',
		'signature'  => 'cloudinary_connection_signature',
		'version'    => 'cloudinary_version',
		'url'        => 'cloudinary_url',
		'connect'    => 'cloudinary_connect',
		'cache'      => 'cloudinary_settings_cache',
		'status'     => 'cloudinary_status',
	);

	/**
	 * Regex to match Cloudinary environment variable.
	 */
	const CLOUDINARY_VARIABLE_REGEX = '^(?:CLOUDINARY_URL=)?cloudinary://[0-9]+:[A-Za-z_\-0-9]+@[A-Za-z]+';

	/**
	 * Initiate the plugin resources.
	 *
	 * @param \Cloudinary\Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'pre_update_option_cloudinary_connect', array( $this, 'verify_connection' ) );
		add_filter( 'cron_schedules', array( $this, 'get_status_schedule' ) );
		add_action( 'cloudinary_status', array( $this, 'check_status' ) );
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
			delete_option( self::META_KEYS['signature'] );

			add_settings_error(
				'cloudinary_connect',
				'connection_error',
				__( 'Connection to Cloudinary has been removed.', 'cloudinary' ),
				'notice-warning'
			);

			return $data;
		}

		$data['cloudinary_url'] = str_replace( 'CLOUDINARY_URL=', '', $data['cloudinary_url'] );
		$current                = $this->plugin->config['settings']['connect'];

		// Same URL, return original data.
		if ( $current['cloudinary_url'] === $data['cloudinary_url'] ) {
			return $data;
		}

		// Pattern match to ensure validity of the provided url
		if ( ! preg_match( '~' . self::CLOUDINARY_VARIABLE_REGEX . '~', $data['cloudinary_url'] ) ) {
			add_settings_error(
				'cloudinary_connect',
				'format_mismatch',
				__( 'The environment variable URL must be in this format: cloudinary://API_KEY:API_SECRET@CLOUD_NAME', 'cloudinary' ),
				'error'
			);

			return $current;
		}

		$result = $this->test_connection( $data['cloudinary_url'] );

		if ( ! empty( $result['message'] ) ) {
			add_settings_error( 'cloudinary_connect', $result['type'], $result['message'], 'error' );

			return $current;
		}

		add_settings_error(
			'cloudinary_connect',
			'connection_success',
			__( 'Successfully connected to Cloudinary.', 'cloudinary' ),
			'updated'
		);

		update_option( self::META_KEYS['signature'], md5( $data['cloudinary_url'] ) );

		return $data;
	}

	/**
	 * Check whether a connection was established.
	 *
	 * @return boolean
	 */
	public function is_connected() {
		$signature = get_option( self::META_KEYS['signature'], null );

		if ( null === $signature ) {
			return false;
		}

		$connect_data = get_option( self::META_KEYS['connect'], [] );
		$current_url  = isset( $connect_data['cloudinary_url'] ) ? $connect_data['cloudinary_url'] : null;

		if ( null === $current_url ) {
			return false;
		}

		if ( md5( $current_url ) !== $signature ) {
			return false;
		}

		$status = get_option( self::META_KEYS['status'], null );
		if ( is_wp_error( $status ) ) {
			// Error, we stop here.
			if ( ! isset( $this->notices['__status'] ) ) {
				$error   = $status->get_error_message();
				$message = sprintf(
				// translators: Placeholder refers the error from API.
					__( 'Cloudinary Error: %s', 'cloudinary' ),
					ucwords( $error )
				);
				if ( 'disabled account' === strtolower( $error ) ) {
					// Flag general disabled.
					$this->disabled = true;
					$message        = sprintf(
					// translators: Placeholders are <a> tags.
						__( 'Cloudinary Account Disabled. %1$s Upgrade your plan %3$s or %2$s submit a support request %3$s for assistance.', 'cloudinary' ),
						'<a href="https://cloudinary.com/console/upgrade_options" target="_blank">',
						'<a href="https://support.cloudinary.com/hc/en-us/requests/new" target="_blank">',
						'</a>'
					);
				}
				$this->notices['__status'] = array(
					'message'     => $message,
					'type'        => 'error',
					'dismissible' => true,
				);
			}

			return false;
		}


		return true;
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
				'<code>cloudinary://API_KEY:API_SECRET@CLOUD_NAME</code>'
			);

			return $result;
		}

		$cname_str   = $this->extract_cname( $test );
		$cname_valid = $this->validate_domain( $cname_str );

		if ( $cname_str && ( ! substr_count( $cname_valid, '.' ) || false === $cname_valid ) ) {
			$result['type']    = 'invalid_cname';
			$result['message'] = __( 'CNAME is not a valid domain name.', 'cloudinary' );

			return $result;
		}

		$this->config_from_url( $url );
		$test_result = $this->check_status();

		if ( is_wp_error( $test_result ) ) {
			$error = $test_result->get_error_message();
			if ( 'disabled account' !== strtolower( $error ) ) {
				// Account Disabled, is still successful, so allow it, else we will never be able to change it.
				$result['type'] = 'connection_error';
			}
			$result['message'] = ucwords( str_replace( '_', ' ', $test_result->get_error_message() ) );
		} else {
			$this->usage_stats( true );
		}

		return $result;
	}

	/**
	 * Check the status of Cloudinary.
	 *
	 * @return array|\WP_Error
	 */
	public function check_status() {
		$status = $this->test_ping();
		update_option( self::META_KEYS['status'], $status );

		return $status;
	}

	/**
	 * Do a ping test on the API.
	 *
	 * @return array|\WP_Error
	 */
	public function test_ping() {
		$test      = new Connect\Api( $this, $this->plugin->version );
		$this->api = $test;

		return $test->ping();
	}

	/**
	 * Extracts the CNAME from a parsed connection URL.
	 *
	 * @param array $parsed_url
	 *
	 * @return string|null
	 */
	protected function extract_cname( $parsed_url ) {
		$cname = null;

		if ( ! empty( $test['query'] ) ) {
			$config_params = array();
			wp_parse_str( $parsed_url['query'], $config_params );
			$cname = isset( $config_params['cname'] ) ? $config_params['cname'] : $cname;
		} elseif ( ! empty( $parsed_url['path'] ) ) {
			$cname = ltrim( $parsed_url['path'], '/' );
		}

		return $cname;
	}

	/**
	 * Safely validate a domain.
	 *
	 * @param string $domain
	 *
	 * @return bool
	 */
	protected function validate_domain( $domain ) {
		$is_valid = false;

		if ( defined( 'FILTER_VALIDATE_DOMAIN' ) ) {
			$is_valid = filter_var( $domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME );
		} else {
			$domain   = 'https://' . $domain;
			$is_valid = filter_var( $domain, FILTER_VALIDATE_URL );
		}

		return $is_valid;
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
	 * Get the cloud name if set.
	 *
	 * @return string|null
	 */
	public function get_cloud_name() {
		return $this->credentials['cloud_name'] ? $this->credentials['cloud_name'] : null;
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

		// Specifically set CNAME
		$cname = $this->extract_cname( $parts );
		if ( ! empty( $cname ) ) {
			$this->set_credentials( array( 'cname' => $cname ) );
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
			$this->usage_stats();
			$this->setup_status_cron();
		}
	}

	/**
	 * Add our every minute schedule.
	 *
	 * @param array $schedules Array of schedules.
	 *
	 * @return array
	 */
	public function get_status_schedule( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every Minute', 'cloudinary' ),
		);

		return $schedules;
	}

	/**
	 * Setup Status cron.
	 */
	protected function setup_status_cron() {
		if ( false === wp_get_schedule( 'cloudinary_status' ) ) {
			$now = current_time( 'timestamp' );
			wp_schedule_event( $now + ( MINUTE_IN_SECONDS ), 'every_minute', 'cloudinary_status' );
		}
	}

	/**
	 * Set the usage stats from the Cloudinary API.
	 *
	 * @param bool $refresh Flag to force a refresh.
	 */
	public function usage_stats( $refresh = false ) {
		$stats = get_transient( self::META_KEYS['usage'] );
		if ( empty( $stats ) || true === $refresh ) {
			// Get users plan.
			$stats = $this->api->usage();
			if ( ! is_wp_error( $stats ) && ! empty( $stats['media_limits'] ) ) {
				$stats['max_image_size'] = $stats['media_limits']['image_max_size_bytes'];
				$stats['max_video_size'] = $stats['media_limits']['video_max_size_bytes'];
				set_transient( self::META_KEYS['usage'], $stats, HOUR_IN_SECONDS );
				update_option( self::META_KEYS['last_usage'], $stats );// Save the last successful call to prevent crashing.
			} else {
				// Handle error by logging and fetching the last success.
				// @todo : log issue.
				$stats = get_option( self::META_KEYS['last_usage'] );
			}
		}
		$this->usage = $stats;
	}

	/**
	 * Get a usage stat for display.
	 *
	 * @param string      $type The type of stat to get.
	 * @param string|null $stat The stat to get.
	 *
	 * @return bool|string
	 */
	public function get_usage_stat( $type, $stat = null ) {
		$value = false;
		if ( isset( $this->usage[ $type ] ) ) {
			if ( is_string( $this->usage[ $type ] ) ) {
				$value = $this->usage[ $type ];
			} elseif ( is_array( $this->usage[ $type ] ) && isset( $this->usage[ $type ][ $stat ] ) ) {
				$value = $this->usage[ $type ][ $stat ];
			} elseif ( is_array( $this->usage[ $type ] ) ) {

				if ( 'limit' === $stat && isset( $this->usage[ $type ]['usage'] ) ) {
					$value = $this->usage[ $type ]['usage'];
				} elseif ( 'used_percent' === $stat && isset( $this->usage[ $type ]['credits_usage'] ) ) {
					// Calculate percentage based on credit limit and usage.
					$value = round( $this->usage[ $type ]['credits_usage'] / $this->usage['credits']['limit'] * 100, 2 );
				}
			}
		}

		return $value;
	}

	/**
	 * Gets the config of a connection.
	 *
	 * @since  0.1
	 *
	 * @return array The array of the config options stored.
	 */
	public function get_config() {
		$signature = get_option( self::META_KEYS['signature'], null );
		$version   = get_option( self::META_KEYS['version'] );
		if ( empty( $signature ) || version_compare( $this->plugin->version, $version, '>' ) ) {
			// Check if there's a previous version, or missing signature.
			$cld_url = get_option( self::META_KEYS['url'], null );
			if ( null === $cld_url ) {
				// Post V1.
				$data = get_option( self::META_KEYS['connect'], array() );
				if ( ! isset( $data['cloudinary_url'] ) || empty( $data['cloudinary_url'] ) ) {
					return null; // return null to indicate not valid.
				}
			} else {
				// from V1 to V2.
				$data = array(
					'cloudinary_url' => $cld_url,
				);
				// Set auto sync off.
				$sync = get_option( 'cloudinary_sync_media' );
				if ( empty( $sync ) ) {
					$sync = array(
						'auto_sync'         => '',
						'cloudinary_folder' => '',
					);
				}
				$sync['auto_sync'] = 'off';
				update_option( 'cloudinary_sync_media', $sync );
				delete_option( 'cloudinary_settings_cache' ); // remove the cache.
			}

			$data['cloudinary_url'] = str_replace( 'CLOUDINARY_URL=', '', $data['cloudinary_url'] );
			$test                   = $this->test_connection( $data['cloudinary_url'] );

			if ( 'connection_success' === $test['type'] ) {
				$signature = md5( $data['cloudinary_url'] );

				// remove filters as we've already verified it and 'add_settings_error()' isin't available yet.
				remove_filter( 'pre_update_option_cloudinary_connect', array( $this, 'verify_connection' ) );
				update_option( self::META_KEYS['connect'], $data );
				update_option( self::META_KEYS['signature'], $signature );
				update_option( self::META_KEYS['version'], $this->plugin->version );
				delete_option( self::META_KEYS['cache'] ); // remove the cache.
				$this->plugin->config['settings']['connect'] = $data; // Set the connection url for this round.
			}
		}

		return $signature;
	}

	/**
	 * Set usage notices if limits are towards higher end.
	 */
	public function usage_notices() {
		if ( ! empty( $this->usage ) ) {
			foreach ( $this->usage as $stat => $values ) {

				if ( ! is_array( $values ) ) {
					continue;
				}
				$usage = $this->get_usage_stat( $stat, 'used_percent' );
				if ( empty ( $usage ) ) {
					continue;
				}
				$link      = null;
				$link_text = null;
				if ( 90 <= $usage ) {
					// 90% used - show error.
					$level     = 'error';
					$link      = 'https://cloudinary.com/console/lui/upgrade_options';
					$link_text = __( 'upgrade your account', 'cloudinary' );
				} elseif ( 80 <= $usage ) {
					$level     = 'warning';
					$link_text = __( 'upgrade your account', 'cloudinary' );
				} elseif ( 70 <= $usage ) {
					$level     = 'neutral';
					$link_text = __( 'upgrade your account', 'cloudinary' );
				} else {
					continue;
				}
				// translators: Placeholders are URLS and percentage values.
				$message         = sprintf(
					__(
						'<span class="dashicons dashicons-cloudinary"></span> You are %2$s of the way through your monthly quota for %1$s on your Cloudinary account. If you exceed your quota, the Cloudinary plugin will be deactivated until your next billing cycle and your media assets will be served from your WordPress Media Library. You may wish to <a href="%3$s" target="_blank">%4$s</a> and increase your quota to ensure you maintain full functionality.',
						'cloudinary'
					),
					ucwords( $stat ),
					$usage . '%',
					$link,
					$link_text
				);
				$this->notices[] = array(
					'message'     => $message,
					'type'        => $level,
					'dismissible' => true,
					'duration'    => MONTH_IN_SECONDS,
				);
			}
		}
	}

	/**
	 * Get admin notices.
	 */
	public function get_notices() {
		$this->usage_notices();
		$screen = get_current_screen();
		if ( empty( $this->plugin->config['connect'] ) ) {
			if ( is_object( $screen ) && in_array( $screen->id, $this->plugin->components['settings']->handles, true ) ) {
				$link            = '<a href="' . esc_url( admin_url( 'admin.php?page=cld_connect' ) ) . '">' . __( 'Connect', 'cloudinary' ) . '</a> ';
				$this->notices[] = array(
					'message'     => $link . __( 'your Cloudinary account with WordPress to get started.', 'cloudinary' ),
					'type'        => 'error',
					'dismissible' => true,
				);
			}
		}

		return $this->notices;
	}

}
