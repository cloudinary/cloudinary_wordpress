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
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Cloudinary connection class.
 *
 * Sets up the initial cloudinary connection and makes the API object available for some uses.
 */
class Connect extends Settings_Component implements Config, Setup, Notice {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	protected $plugin;

	/**
	 * Holds the cloudinary API instance
	 *
	 * @since   0.1
	 *
	 * @var     Api
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
		'version'    => '_cloudinary_settings_version',
		'url'        => 'cloudinary_url',
		'connection' => 'cloudinary_connect',
		'status'     => 'cloudinary_status',
		'history'    => '_cloudinary_history',
		'notices'    => 'rest_api_notices',
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
		$this->plugin        = $plugin;
		$this->settings_slug = 'dashboard';
		add_filter( 'pre_update_option_cloudinary_connect', array( $this, 'verify_connection' ) );
		add_action( 'update_option_cloudinary_connect', array( $this, 'updated_option' ) );
		add_action( 'cloudinary_status', array( $this, 'check_status' ) );
		add_action( 'cloudinary_version_upgrade', array( $this, 'upgrade_connection' ) );
		add_filter( 'cloudinary_setting_get_value', array( $this, 'maybe_connection_string_constant' ), 10, 2 );
		add_filter( 'cloudinary_admin_pages', array( $this, 'register_meta' ) );
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['test_connection'] = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_test_connection' ),
			'args'                => array(),
			'permission_callback' => array( 'Cloudinary\REST_API', 'rest_can_connect' ),
		);
		$endpoints['save_wizard']     = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_save_wizard' ),
			'args'                => array(),
			'permission_callback' => array( 'Cloudinary\REST_API', 'rest_can_connect' ),
		);
		$endpoints['test_rest_api']   = array(
			'method'              => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_test_rest_api_connectivity' ),
			'args'                => array(),
			'permission_callback' => array( 'Cloudinary\REST_API', 'allow_public_health_check' ),
		);

		return $endpoints;
	}

	/**
	 * Test a connection string.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_test_connection( WP_REST_Request $request ) {

		$url    = $request->get_param( 'cloudinary_url' );
		$result = $this->test_connection( $url );

		return rest_ensure_response( $result );
	}

	/**
	 * Test the REST API connectivity.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_test_rest_api_connectivity() {
		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Save the wizard setup.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_save_wizard( WP_REST_Request $request ) {

		$url      = $request->get_param( 'cldString' );
		$media    = true === $request->get_param( 'mediaLibrary' ) ? 'on' : 'off';
		$nonmedia = true === $request->get_param( 'nonMedia' ) ? 'on' : 'off';
		$advanced = true === $request->get_param( 'advanced' ) ? 'on' : 'off';

		// Cloudinary URL.
		if ( ! empty( $url ) && true !== $url ) {
			$connect = $this->settings->get_setting( 'cloudinary_url' );
			$connect->set_pending( $url );
		}

		// Autosync setup.
		$autosync = $this->settings->get_setting( 'auto_sync' );
		$autosync->set_pending( $media );
		$image_optimisation = $this->settings->get_setting( 'image_optimization' );
		$image_optimisation->set_pending( $media );
		$video_optimisation = $this->settings->get_setting( 'video_optimization' );
		$video_optimisation->set_pending( $media );

		// Non-media setup.
		$assets = $this->settings->get_setting( 'assets' );

		foreach ( $assets->get_settings() as $asset ) {
			$paths = $asset->get_setting( 'paths' );
			foreach ( $paths->get_settings() as $path ) {
				$path->set_pending( $nonmedia );
			}
		}
		$enable_nonmedia = $this->settings->get_setting( 'cache.enable' );
		$enable_nonmedia->set_pending( $nonmedia );

		// Advanced.
		$lazyload = $this->settings->get_setting( 'use_lazy_load' );
		$lazyload->set_pending( $advanced );
		$breakpoints = $this->settings->get_setting( 'enable_breakpoints' );
		$breakpoints->set_pending( $advanced );

		$this->settings->save();

		if ( ! empty( $url ) ) {
			// Warm the last uploaded items in the media library.
			wp_remote_request(
				Utils::rest_url( 'wp/v2/media' ),
				array(
					'timeout'  => 0.1,
					'blocking' => false,
				)
			);
		}

		return rest_ensure_response( $this->settings->get_value() );
	}

	/**
	 * Register meta data with the pages/settings.
	 *
	 * @param array $pages The pages array.
	 *
	 * @return array
	 */
	public function register_meta( $pages ) {

		// Add data storage.
		foreach ( self::META_KEYS as $slug => $option_name ) {
			if ( 'url' === $slug || 'connection' === $slug ) {
				continue; // URL and connection already set.
			}
			$pages['connect']['settings'][] = array(
				'slug'        => $slug,
				'option_name' => $option_name,
				'type'        => 'data',
			);
		}

		return $pages;
	}

	/**
	 * Verify that the connection details are correct.
	 *
	 * @param array $data The submitted data to verify.
	 *
	 * @return array|WP_Error The data if cleared.
	 */
	public function verify_connection( $data ) {
		$admin = $this->plugin->get_component( 'admin' );
		if ( empty( $data['cloudinary_url'] ) ) {
			delete_option( self::META_KEYS['signature'] );
			$admin->add_admin_notice(
				'connection_error',
				__( 'Connection to Cloudinary has been removed.', 'cloudinary' ),
				'error',
				true
			);
			$this->plugin->settings->set_param( 'connected', false );

			return $data;
		}

		$data['cloudinary_url'] = str_replace( 'CLOUDINARY_URL=', '', $data['cloudinary_url'] );
		$current                = $this->plugin->settings->find_setting( 'connect' )->get_value();

		// Same URL, return original data.
		if ( $current['cloudinary_url'] === $data['cloudinary_url'] ) {
			return $data;
		}

		// Pattern match to ensure validity of the provided url.
		if ( ! preg_match( '~' . self::CLOUDINARY_VARIABLE_REGEX . '~', $data['cloudinary_url'] ) ) {
			$admin->add_admin_notice(
				'format_mismatch',
				__( 'The environment variable URL must be in this format: cloudinary://API_KEY:API_SECRET@CLOUD_NAME', 'cloudinary' ),
				'error'
			);

			return $current;
		}

		$result = $this->test_connection( $data['cloudinary_url'] );

		if ( ! empty( $result['message'] ) ) {
			$admin->add_admin_notice(
				$result['type'],
				$result['message'],
				'error'
			);

			return $current;
		}

		$admin->add_admin_notice(
			'connection_success',
			__( 'Successfully connected to Cloudinary.', 'cloudinary' ),
			'updated'
		);

		$this->settings->get_setting( 'signature' )->save_value( md5( $data['cloudinary_url'] ) );
		$this->plugin->settings->set_param( 'connected', true );

		return $data;
	}

	/**
	 * Check whether a connection was established.
	 *
	 * @return boolean
	 */
	public function is_connected() {
		$connected = $this->plugin->settings->get_param( 'connected', null );
		if ( ! is_null( $connected ) ) {
			return $connected;
		}
		$signature = $this->settings->get_value( 'signature' );

		if ( null === $signature ) {
			return false;
		}

		$connect_data = $this->settings->get_value( 'connect' );
		$current_url  = isset( $connect_data['cloudinary_url'] ) ? $connect_data['cloudinary_url'] : null;

		if ( null === $current_url ) {
			return false;
		}

		if ( md5( $current_url ) !== $signature ) {
			return false;
		}

		$status = $this->settings->get_value( 'status' );
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
	 * @return array
	 */
	public function test_connection( $url ) {
		$result = array(
			'type'    => 'connection_success',
			'message' => null,
			'url'     => $url,
		);

		$test  = wp_parse_url( $url );
		$valid = array_filter(
			array_keys( (array) $test ),
			function ( $a ) {
				return in_array( $a, array( 'scheme', 'host', 'user', 'pass' ), true );
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
	 * Get historical usage data.
	 *
	 * @param int $days Number of days to get.
	 *
	 * @return array
	 */
	public function history( $days = 1 ) {
		$return  = array();
		$history = get_option( self::META_KEYS['history'], array() );
		$plan    = ! empty( $this->usage['plan'] ) ? $this->usage['plan'] : $this->credentials['cloud_name'];
		for ( $i = 1; $i <= $days; $i++ ) {
			$date = date_i18n( 'd-m-Y', strtotime( '- ' . $i . ' days' ) );
			if ( ! isset( $history[ $plan ][ $date ] ) || is_wp_error( $history[ $plan ][ $date ] ) ) {
				$history[ $plan ][ $date ] = $this->api->usage( $date );
				uksort(
					$history[ $plan ],
					static function ( $a, $b ) {
						$a = strtotime( $a );
						$b = strtotime( $b );

						if ( $a === $b ) {
							return 0;
						}

						return $a < $b ? - 1 : 1;
					}
				);
				$history[ $plan ] = array_slice( $history[ $plan ], -30 );
			}
			$return[ $date ] = $history[ $plan ][ $date ];
		}
		update_option( self::META_KEYS['history'], $history, false );

		return $return;
	}

	/**
	 * Upgrade method for version changes.
	 *
	 * @param string $previous_version The previous version number.
	 * @param string $new_version      The New version number.
	 */
	public function upgrade_settings( $previous_version, $new_version ) {
		// Check if we need to upgrade the history.
		if ( version_compare( $previous_version, '3.1.0', '<' ) ) {
			add_action(
				'cloudinary_ready',
				function () {
					$history = get_option( self::META_KEYS['history'], array() );
					$plan    = false;

					if ( ! empty( $this->usage['plan'] ) ) {
						$plan = $this->usage['plan'];
					} elseif ( ! empty( $this->credentials['cloud_name'] ) ) {
						$plan = $this->credentials['cloud_name'];
					}

					// Check whether history has migrated.
					if ( ! empty( $plan ) && ! empty( $history[ $plan ] ) ) {
						return;
					}

					// Cleanup history.
					$new_history = array();

					update_option( self::META_KEYS['history'], $new_history, false );
				}
			);
		}
	}

	/**
	 * After updating the cloudinary_connect option, remove flag.
	 */
	public function updated_option() {
		if ( ! defined( 'REST_REQUEST' ) || true !== REST_REQUEST ) {
			$page = 'cloudinary';
			if ( $this->is_connected() ) {
				$page .= '_connect';
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => $page,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Check the status of Cloudinary.
	 *
	 * @return array|WP_Error
	 */
	public function check_status() {
		$status = $this->test_ping();
		$this->settings->get_setting( 'status' )->save_value( $status );

		return $status;
	}

	/**
	 * Do a ping test on the API.
	 *
	 * @return array|WP_Error
	 */
	public function test_ping() {
		$test      = new Connect\Api( $this, $this->plugin->version );
		$this->api = $test;

		return $test->ping();
	}

	/**
	 * Extracts the CNAME from a parsed connection URL.
	 *
	 * @param array $parsed_url Parsed URL.
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
	 * @param string $domain The domain.
	 *
	 * @return bool
	 */
	protected function validate_domain( $domain ) {
		$is_valid = false;

		if ( defined( 'FILTER_VALIDATE_DOMAIN' ) ) { // phpcs:ignore PHPCompatibility.Constants.NewConstants.filter_validate_domainFound
			$is_valid = filter_var( $domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ); // phpcs:ignore PHPCompatibility.Constants.NewConstants
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
		return ! empty( $this->credentials['cloud_name'] ) ? $this->credentials['cloud_name'] : null;
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
			if ( empty( $config_params['upload_prefix'] ) ) {
				$this->set_credentials( array( 'upload_prefix' => reset( Api::$qualified_upload_prefixes ) ) );
			}
			if ( ! empty( $config_params ) ) {
				$this->set_credentials( $config_params );
			}
		}

		// Specifically set CNAME.
		$cname = $this->extract_cname( $parts );
		if ( ! empty( $cname ) ) {
			$this->set_credentials( array( 'cname' => $cname ) );

			if ( empty( $this->credentials['private_cdn'] ) ) {
				$this->set_credentials( array( 'private_cdn' => 'true' ) );
			}
		}
	}

	/**
	 * Setup connection
	 *
	 * @since  0.1
	 */
	public function setup() {
		$this->setup_rest_api_cron();

		// Get the cloudinary url from settings.
		$cloudinary_url = $this->settings->get_value( 'cloudinary_url' );
		if ( ! empty( $cloudinary_url ) ) {
			$this->config_from_url( $cloudinary_url );
			$this->api = new Connect\Api( $this, $this->plugin->version );
			$this->usage_stats();
			$this->setup_status_cron();
			$this->plugin->settings->set_param( 'connected', $this->is_connected() );
		}
	}

	/**
	 * Setup Status cron.
	 */
	protected function setup_status_cron() {
		Cron::register_process( 'check_status', array( $this, 'check_status' ), DAY_IN_SECONDS );
	}

	/**
	 * Setup the REST API cron.
	 *
	 * @return void
	 */
	protected function setup_rest_api_cron() {
		Cron::register_process( 'rest_api', array( $this, 'check_rest_api_connectivity' ), DAY_IN_SECONDS );
	}

	/**
	 * Set the usage stats from the Cloudinary API.
	 *
	 * @param bool $refresh Flag to force a refresh.
	 */
	public function usage_stats( $refresh = false ) {
		$stats = get_transient( self::META_KEYS['usage'] );
		if ( empty( $stats ) || true === $refresh ) {
			$last_usage = $this->settings->get_setting( 'last_usage' );
			// Get users plan.
			$stats = $this->api->usage();
			if ( ! is_wp_error( $stats ) && ! empty( $stats['media_limits'] ) ) {
				$stats['max_image_size'] = $stats['media_limits']['image_max_size_bytes'];
				$stats['max_video_size'] = $stats['media_limits']['video_max_size_bytes'];
				$last_usage->save_value( $stats );// Save the last successful call to prevgent crashing.
			} else {
				// Handle error by logging and fetching the last success.
				// @todo : log issue.
				$stats = $last_usage->get_value();
			}
			// Set useage state to the results, either new or the last, to prevent API hits.
			set_transient( self::META_KEYS['usage'], $stats, HOUR_IN_SECONDS );
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
				} elseif (
					'used_percent' === $stat
					&& isset( $this->usage[ $type ]['credits_usage'] )
					&& ! empty( $this->usage['credits']['limit'] )
				) {
					// Calculate percentage based on credit limit and usage.
					$value = round( $this->usage[ $type ]['credits_usage'] / $this->usage['credits']['limit'] * 100, 2 );
				}
			}
		}

		return $value;
	}

	/**
	 * Gets the config of a connection.
	 */
	public function get_config() {
		$old_version = $this->settings->get_value( 'version' );
		if ( empty( $old_version ) ) {
			$old_version = '2.0.1';
		}
		if ( version_compare( $this->plugin->version, $old_version, '>' ) ) {
			/**
			 * Do action to allow upgrading of different areas.
			 *
			 * @since 2.3.1
			 *
			 * @param string $new_version The version upgrading to.
			 *
			 * @param string $old_version The version upgrading from.
			 */
			do_action( 'cloudinary_version_upgrade', $old_version, $this->plugin->version );
		}
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
				if ( empty( $usage ) ) {
					continue;
				}
				$link      = 'https://cloudinary.com/console/lui/upgrade_options';
				$link_text = __( 'upgrade your account', 'cloudinary' );
				if ( 90 <= $usage ) {
					// 90% used - show error.
					$level = 'error';
				} elseif ( 80 <= $usage ) {
					$level = 'warning';
				} elseif ( 70 <= $usage ) {
					$level = 'neutral';
				} else {
					continue;
				}

				// translators: Placeholders are URLS and percentage values.
				$message = sprintf(
				/* translators: %1$s quota size, %2$s amount in percent, %3$s link URL, %4$s link anchor text. */
					__(
						'You are %2$s of the way through your monthly quota for %1$s on your Cloudinary account. If you exceed your quota, the Cloudinary plugin will be deactivated until your next billing cycle and your media assets will be served from your WordPress Media Library. You may wish to <a href="%3$s" target="_blank">%4$s</a> and increase your quota to ensure you maintain full functionality.',
						'cloudinary'
					),
					ucwords( $stat ),
					$usage . '%',
					$link,
					$link_text
				);

				$this->notices[] = array(
					'icon'        => 'dashicons-cloudinary',
					'message'     => $message,
					'type'        => $level,
					'dismissible' => true,
					'duration'    => MONTH_IN_SECONDS,
				);
			}
		}
	}

	/**
	 * Setup the connection notices.
	 *
	 * @return void
	 */
	public function connectivity_notices() {
		$plugin  = get_plugin_instance();
		$notices = get_option( $plugin::KEYS['notices'], array() );

		if ( ! empty( $notices[ self::META_KEYS['notices'] ] ) ) {
			$this->notices[] = $notices[ self::META_KEYS['notices'] ];
		}
	}

	/**
	 * Get admin notices.
	 */
	public function get_notices() {
		$this->usage_notices();
		$this->connectivity_notices();

		return array_filter( $this->notices );
	}

	/**
	 * Upgrade connection settings.
	 *
	 * @param string $old_version The previous version.
	 *
	 * @uses action:cloudinary_version_upgrade
	 */
	public function upgrade_connection( $old_version ) {

		if ( version_compare( $old_version, '2.0.0', '>' ) ) {
			// Post V1 - quick check all details are valid.
			$data = $this->settings->get_value( 'connect' );
			if ( ! isset( $data['cloudinary_url'] ) || empty( $data['cloudinary_url'] ) ) {
				return; // Not setup at all, abort upgrade.
			}
		} elseif ( version_compare( $old_version, '2.0.0', '<' ) ) {
			// from V1 to V2.
			$cld_url = get_option( self::META_KEYS['url'], null );
			if ( empty( $cld_url ) ) {
				return; // Upgrade from a non setup V1 nothing to upgrade.
			}
			$data = array(
				'cloudinary_url' => $cld_url,
			);
			$key  = $this->settings->get_storage_key( $this->plugin->get_component( 'sync' )->settings_slug );
			// Set auto sync off.
			$sync = get_option( $key );
			if ( empty( $sync ) ) {
				$sync = array(
					'auto_sync'         => '',
					'cloudinary_folder' => '',
				);
			}
			$sync['auto_sync'] = 'off';
			update_option( $key, $sync );
			delete_option( 'cloudinary_settings_cache' ); // remove the cache.
		}

		// Test upgraded details.
		$data['cloudinary_url'] = str_replace( 'CLOUDINARY_URL=', '', $data['cloudinary_url'] );
		$test                   = $this->test_connection( $data['cloudinary_url'] );

		if ( 'connection_success' === $test['type'] ) {
			$signature = md5( $data['cloudinary_url'] );

			// remove filters as we've already verified it and 'add_settings_error()' isn't available yet.
			remove_filter( 'pre_update_option_cloudinary_connect', array( $this, 'verify_connection' ) );
			update_option( self::META_KEYS['connection'], $data );
			update_option( self::META_KEYS['signature'], $signature );
			update_option( self::META_KEYS['version'], $this->plugin->version );
		}
	}

	/**
	 * Checks if connection string constant is defined.
	 *
	 * @return bool
	 */
	public function has_connection_string_constant() {
		return defined( 'CLOUDINARY_CONNECTION_STRING' );
	}

	/**
	 * Filters the connection parts.
	 *
	 * @param mixed  $value   The default value.
	 * @param string $setting The setting slug.
	 *
	 * @return mixed
	 */
	public function maybe_connection_string_constant( $value, $setting ) {
		if ( ! $this->has_connection_string_constant() ) {
			return $value;
		}

		static $url = null;

		if ( empty( $url ) ) {
			$url = str_replace( 'CLOUDINARY_URL=', '', CLOUDINARY_CONNECTION_STRING );
		}

		if ( 'cloudinary_url' === $setting ) {
			$value = $url;
		}

		if ( 'signature' === $setting ) {
			$value = md5( $url );
		}

		if ( 'connect' === $setting ) {
			$value['cloudinary_url'] = $url;
		}

		return $value;
	}

	/**
	 * Check if the switch account param is set.
	 *
	 * @return bool
	 */
	public function switch_account() {

		$return = false;
		if ( filter_input( INPUT_GET, 'switch-account', FILTER_VALIDATE_BOOLEAN ) ) {
			return true;
		}

		return $return;
	}

	/**
	 * Check the REST API connectivity for Cloudinary's endpoints.
	 *
	 * @return array
	 */
	public static function check_rest_api_connectivity() {

		$connectivity = self::test_rest_api_connectivity();
		$plugin       = get_plugin_instance();
		$notices      = get_option( $plugin::KEYS['notices'], array() );

		if ( $connectivity['working'] ) {
			unset( $notices[ self::META_KEYS['notices'] ] );
			if ( empty( $notices ) ) {
				delete_option( $plugin::KEYS['notices'] );
			} else {
				update_option( $plugin::KEYS['notices'], $notices, false );
			}
		} else {
			update_option(
				$plugin::KEYS['notices'],
				array(
					self::META_KEYS['notices'] => $connectivity,
				),
				false
			);
		}

		return $connectivity;
	}

	/**
	 * Test the REST API connectivity for Cloudinary's endpoints.
	 *
	 * @return array
	 */
	public static function test_rest_api_connectivity() {
		$result = array(
			'working' => true,
			'message' => __( 'Cloudinary was able to connect to the WordPress REST API.', 'cloudinary' ),
		);

		$args = array(
			'headers' => array(
				'Cache-Control' => 'no-cache',
			),
		);

		$url      = Utils::rest_url( REST_API::BASE . '/test_rest_api' );
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$result = array(
				'working' => false,
				'message' => sprintf(
					/* translators: 1: The WordPress error message. 2: The WordPress error code. */
					__( 'The Cloudinary REST API endpoints are not available. Error: %1$s (%2$s)', 'cloudinary' ),
					$response->get_error_message(),
					$response->get_error_code()
				),
			);
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$result = array(
				'working' => false,
				'message' => sprintf(
					/* translators: 1: The WordPress error message. 2: The WordPress error code. */
					__( 'The Cloudinary REST API endpoints are not available. Error: %1$s (%2$s)', 'cloudinary' ),
					wp_remote_retrieve_response_message( $response ),
					wp_remote_retrieve_response_code( $response )
				),
			);
		}

		return $result;
	}

	/**
	 * Get Connection String content for old settings.
	 *
	 * @return string
	 */
	protected function get_connection_string_content() {
		ob_start();
		include $this->plugin->dir_path . 'php/templates/connection-string.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		return ob_get_clean();
	}
}
