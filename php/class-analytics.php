<?php
/**
 * Analytics class for Cloudinary.
 *
 * Phase 1 POC: custom-events framework that emits activation-funnel events to
 * the Cloudinary analytics collector. This component provides the transport
 * (server-side, fail-silent), the global parameter envelope, and a REST bridge
 * for client-side events. The funnel events themselves are wired in a later PR.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Analytics.
 *
 * Sends custom analytics events to the Cloudinary custom-events collector.
 *
 * @package Cloudinary
 */
class Analytics {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Memoized per-request session identifier.
	 *
	 * @var string|null
	 */
	protected $session_id = null;

	/**
	 * The internal REST route that the client-side bridge posts events to.
	 *
	 * @var string
	 */
	protected static $rest_route = 'events';

	/**
	 * Constant source value attached to every event.
	 *
	 * @var string
	 */
	const SOURCE = 'wordpress_plugin';

	/**
	 * Initiate the analytics component.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script_data' ) );
		add_action( 'admin_init', array( $this, 'maybe_send_smoke_event' ) );
		add_action( 'admin_init', array( $this, 'maybe_send_pending_activation' ) );
		add_action( 'cloudinary_uploaded_asset', array( $this, 'maybe_first_api_consumption' ), 10, 2 );
	}

	/**
	 * Option/transient keys used by the activation funnel.
	 */
	const PENDING_ACTIVATION = '_cloudinary_pending_activation';
	const LAST_ACTIVE        = '_cloudinary_last_active';
	const FIRST_API_FLAG     = '_cloudinary_first_api_emitted';

	/**
	 * Records the activation type on plugin activation (funnel step 1).
	 *
	 * Runs from the activation hook (`Utils::install`). Detects fresh install /
	 * reactivation / upgrade / downgrade from the persisted install marker
	 * (`db_version`) vs. the current version, then stashes a transient that the
	 * next admin load turns into a `plugin_activated` event — by which point the
	 * full mandatory params and `session_id` are available.
	 *
	 * @return void
	 */
	public static function stash_activation() {
		try {
			$current    = get_plugin_instance()->version;
			$db_version = get_option( Sync::META_KEYS['db_version'] );

			if ( empty( $db_version ) ) {
				$type     = 'fresh_install';
				$previous = null;
			} elseif ( version_compare( $db_version, $current, '<' ) ) {
				$type     = 'upgrade';
				$previous = $db_version;
			} elseif ( version_compare( $db_version, $current, '>' ) ) {
				$type     = 'downgrade';
				$previous = $db_version;
			} else {
				$type     = 'reactivation';
				$previous = $db_version;
			}

			$days_since_last_active = null;
			if ( 'reactivation' === $type ) {
				$last = (int) get_option( self::LAST_ACTIVE );
				if ( $last > 0 ) {
					$days_since_last_active = (int) floor( ( time() - $last ) / DAY_IN_SECONDS );
				}
			}

			set_transient(
				self::PENDING_ACTIVATION,
				array(
					'activation_type'        => $type,
					'previous_version'       => $previous,
					'new_version'            => $current,
					'days_since_last_active' => $days_since_last_active,
				),
				HOUR_IN_SECONDS
			);
		} catch ( \Throwable $e ) {
			// Fail silent: activation must never break.
			return;
		}
	}

	/**
	 * Persists a last-active timestamp on deactivation.
	 *
	 * Feeds `days_since_last_active` on the next reactivation. Runs from the
	 * deactivation hook.
	 *
	 * @return void
	 */
	public static function record_deactivation() {
		update_option( self::LAST_ACTIVE, time(), false );
	}

	/**
	 * Emits the stashed `plugin_activated` event on the next admin load.
	 *
	 * @return void
	 */
	public function maybe_send_pending_activation() {
		$pending = get_transient( self::PENDING_ACTIVATION );
		if ( empty( $pending ) || ! is_array( $pending ) ) {
			return;
		}
		delete_transient( self::PENDING_ACTIVATION );

		$params = array(
			'activation_type' => $pending['activation_type'],
			'new_version'     => $pending['new_version'],
		);
		if ( ! empty( $pending['previous_version'] ) ) {
			$params['previous_version'] = $pending['previous_version'];
		}
		if ( isset( $pending['days_since_last_active'] ) && null !== $pending['days_since_last_active'] ) {
			$params['days_since_last_active'] = $pending['days_since_last_active'];
		}

		$this->track( 'plugin_activated', 'activation_funnel', 1, $params );
	}

	/**
	 * Emits the one-time `first_api_consumption` activation marker (funnel step 9).
	 *
	 * Hooked to `cloudinary_uploaded_asset`, which fires after an asset upload.
	 * Emitted once on the first successful upload, then suppressed.
	 *
	 * @param int             $attachment_id The attachment ID.
	 * @param array|\WP_Error $result       The upload result.
	 *
	 * @return void
	 */
	public function maybe_first_api_consumption( $attachment_id, $result ) {
		if ( empty( $result ) || is_wp_error( $result ) ) {
			return;
		}
		if ( get_option( self::FIRST_API_FLAG ) ) {
			return;
		}
		update_option( self::FIRST_API_FLAG, true, false );

		$asset_type = '';
		if ( is_array( $result ) && ! empty( $result['resource_type'] ) ) {
			$asset_type = $result['resource_type'];
		}

		$this->track(
			'first_api_consumption',
			'activation_funnel',
			9,
			array(
				'api_endpoint' => 'upload',
				'asset_type'   => $asset_type,
			)
		);
	}

	/**
	 * Whether analytics emission is enabled.
	 *
	 * Master switch so the transport can be disabled site-wide without removing
	 * any instrumentation.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		/**
		 * Filter whether the plugin emits custom analytics events.
		 *
		 * @hook  cloudinary_analytics_enabled
		 * @since 3.3.4
		 *
		 * @param $enabled {bool} Whether analytics are enabled.
		 *
		 * @return {bool}
		 */
		return (bool) apply_filters( 'cloudinary_analytics_enabled', true );
	}

	/**
	 * Tracks a custom analytics event.
	 *
	 * Fail-silent: a collector outage or any error never affects wp-admin.
	 *
	 * @param string   $event_name  The snake_case event identifier.
	 * @param string   $category    The event category (funnel/category).
	 * @param int|null $funnel_step Ordinal step within a funnel, or null.
	 * @param array    $params      Event-specific flat params.
	 *
	 * @return void
	 */
	public function track( $event_name, $category, $funnel_step = null, $params = array() ) {
		if ( empty( $event_name ) || ! $this->is_enabled() ) {
			return;
		}

		try {
			$event = array_merge(
				$this->base_params(),
				array(
					'event_id'        => wp_generate_uuid4(),
					'event_name'      => $event_name,
					'event_category'  => $category,
					'event_timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				),
				is_array( $params ) ? $params : array()
			);

			if ( null !== $funnel_step ) {
				$event['funnel_step'] = (int) $funnel_step;
			}

			$this->dispatch( $event );
		} catch ( \Throwable $e ) {
			$this->log_silent( $e );
		}
	}

	/**
	 * Dispatches the event to the collector without blocking the request.
	 *
	 * @param array $event The full event payload (flat key/value pairs).
	 *
	 * @return void
	 */
	protected function dispatch( array $event ) {
		if ( ! defined( 'CLOUDINARY_ENDPOINTS_ANALYTICS' ) ) {
			return;
		}

		wp_remote_post(
			CLOUDINARY_ENDPOINTS_ANALYTICS,
			array(
				'timeout'  => 1,
				'blocking' => false,
				'body'     => $event,
			)
		);
	}

	/**
	 * Builds the global parameter envelope attached to every event.
	 *
	 * Mandatory params are always present; contextual params (cloud_name, plan)
	 * are added once a Cloudinary account is connected.
	 *
	 * @return array
	 */
	protected function base_params() {
		$params = array(
			'source'         => self::SOURCE,
			'plugin_version' => $this->plugin->version,
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'site_id'        => hash( 'sha256', home_url() ),
			'session_id'     => $this->get_session_id(),
			'user_role'      => $this->get_user_role(),
			'is_multisite'   => is_multisite(),
		);

		$connect = $this->plugin->get_component( 'connect' );
		if ( $connect && $connect->is_connected() ) {
			$cloud_name = $connect->get_cloud_name();
			if ( ! empty( $cloud_name ) ) {
				$params['cloud_name'] = $cloud_name;
			}

			$plan = $connect->get_usage_stat( 'plan' );
			if ( ! empty( $plan ) ) {
				$params['plan'] = $plan;
			}
		}

		return $params;
	}

	/**
	 * Per-admin-session identifier, derived from the hashed WP login token.
	 *
	 * Returns an empty string in non-interactive (cron/async) contexts.
	 *
	 * @return string
	 */
	protected function get_session_id() {
		if ( null !== $this->session_id ) {
			return $this->session_id;
		}

		$this->session_id = '';
		if ( function_exists( 'wp_get_session_token' ) ) {
			$token = wp_get_session_token();
			if ( ! empty( $token ) ) {
				$this->session_id = hash( 'sha256', $token );
			}
		}

		return $this->session_id;
	}

	/**
	 * The primary WordPress role of the acting user.
	 *
	 * @return string
	 */
	protected function get_user_role() {
		$user = wp_get_current_user();
		if ( $user && ! empty( $user->roles ) ) {
			return (string) reset( $user->roles );
		}

		return '';
	}

	/**
	 * Registers the client-side event bridge endpoint.
	 *
	 * @param array $endpoints The registered endpoints.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {
		$endpoints[ self::$rest_route ] = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_track' ),
			'args'                => array(),
			'permission_callback' => function () {
				return Utils::user_can( 'analytics', 'manage_options' );
			},
		);

		return $endpoints;
	}

	/**
	 * Handles a client-side event, enriching it with the server-side envelope.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_track( WP_REST_Request $request ) {
		$event_name = sanitize_key( $request->get_param( 'event_name' ) );
		$category   = sanitize_key( $request->get_param( 'event_category' ) );
		$funnel     = $request->get_param( 'funnel_step' );
		$params     = $request->get_param( 'params' );

		$clean = array();
		if ( is_array( $params ) ) {
			foreach ( $params as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$clean[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
				}
			}
		}

		if ( ! empty( $event_name ) ) {
			$this->track(
				$event_name,
				$category,
				is_numeric( $funnel ) ? (int) $funnel : null,
				$clean
			);
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Exposes the analytics config to the client-side bridge via cldData.
	 *
	 * @return void
	 */
	public function enqueue_script_data() {
		$this->plugin->add_script_data(
			'analytics',
			array(
				'endpoint' => Utils::rest_url( REST_API::BASE . '/' . self::$rest_route ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'enabled'  => $this->is_enabled(),
			)
		);
	}

	/**
	 * Emits a one-off smoke-test event when explicitly enabled.
	 *
	 * Used during the POC to validate the collector path end-to-end. Off by
	 * default and throttled so it never floods the collector.
	 *
	 * @return void
	 */
	public function maybe_send_smoke_event() {
		/**
		 * Filter whether the analytics smoke-test event is emitted.
		 *
		 * @hook  cloudinary_analytics_smoke_test
		 * @since 3.3.4
		 *
		 * @param $enabled {bool} Whether to emit the smoke-test event.
		 *
		 * @return {bool}
		 */
		if ( ! apply_filters( 'cloudinary_analytics_smoke_test', false ) ) {
			return;
		}

		$throttle_key = '_cloudinary_analytics_smoke';
		if ( get_transient( $throttle_key ) ) {
			return;
		}
		set_transient( $throttle_key, true, 5 * MINUTE_IN_SECONDS );

		$this->track( 'poc_smoke_test', 'poc' );
	}

	/**
	 * Logs an error silently (only when debugging) without surfacing it.
	 *
	 * @param \Throwable $error The caught error.
	 *
	 * @return void
	 */
	protected function log_silent( $error ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Cloudinary analytics: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
