<?php
/**
 * UI States Component.
 *
 * @package Cloudinary
 */

namespace Cloudinary\UI;

use Cloudinary\Plugin;
use Cloudinary\REST_API;
use Cloudinary\Settings;
use Cloudinary\UI;
use Cloudinary\Utils;
use function Cloudinary\get_plugin_instance;

/**
 *  Component.
 *
 * @package Cloudinary\UI
 */
class State {

	/**
	 * Holds the instance of the plugin.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the current user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Holds the current state.
	 *
	 * @var array
	 */
	protected $state;

	/**
	 * Holds the current nonce.
	 *
	 * @var string
	 */
	protected $nonce;

	/**
	 * Holds the meta key.
	 *
	 * @var string
	 */
	const STATE_KEY = '_cld_ui_state';

	/**
	 * State constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$this->plugin  = get_plugin_instance();
		$this->user_id = get_current_user_id();

		if ( ! empty( $this->user_id ) ) {
			$this->nonce = wp_create_nonce( 'wp_rest' );
			add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
			add_action( 'admin_init', array( $this, 'setup_state' ) );
		}
	}

	/**
	 * Setup the state.
	 */
	public function setup_state() {
		$url = Utils::rest_url( REST_API::BASE . '/ui-state' );
		$this->plugin->add_script_data( 'stateURL', $url );
		$this->plugin->add_script_data( 'stateNonce', $this->nonce );
		$this->state = get_user_meta( $this->user_id, self::STATE_KEY, true );
		if ( empty( $this->state ) ) {
			$this->state = array();
		}
	}

	/**
	 * Add endpoints to the REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['ui-state'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'set_state' ),
			'args'                => array(),
			'permission_callback' => array( 'Cloudinary\REST_API', 'validate_request' ),
		);

		return $endpoints;
	}



	/**
	 * Set the UI state.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function set_state( \WP_REST_Request $request ) {
		$state_set = $request->get_params();
		if ( ! $state_set ) {
			$state_set = array();
		}
		$return = array(
			'state'   => $state_set,
			'success' => false,
		);
		if ( ! empty( $state_set ) ) {
			// Clear out states that are default to keep data set small.
			foreach ( $state_set as $slug => $state ) {
				$setting = $this->plugin->settings->get_setting( $slug );
				if ( $setting->get_param( 'collapsable' ) === $state ) {
					unset( $state_set[ $slug ], $this->state[ $slug ] );
				}
			}
			$return['state']   = wp_parse_args( $state_set, $this->state );
			$return['up']      = true;
			$return['success'] = true;
			if ( ! empty( $return['state'] ) ) {
				$return['success'] = update_user_meta( $this->user_id, self::STATE_KEY, $return['state'] );
			}
		}

		return rest_ensure_response( $return );
	}

	/**
	 * Get a state.
	 *
	 * @param string $key     The key to get.
	 * @param mixed  $default The default if no state is set.
	 *
	 * @return mixed
	 */
	public function get_state( $key, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound

		// Add default if not set yet.
		if ( empty( $this->state[ $key ] ) ) {
			$this->state[ $key ] = $default;
		}

		$this->plugin->add_script_data( 'stateData', $this->state );

		return $this->state[ $key ];
	}
}
