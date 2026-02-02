<?php
/**
 * Push Sync to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;
use Cloudinary\Utils;

/**
 * Class Push_Sync
 *
 * Push media library to Cloudinary.
 */
class Push_Sync {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the ID of the last attachment synced.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Holds the media component.
	 *
	 * @var \Cloudinary\Media
	 */
	protected $media;

	/**
	 * Holds the sync component.
	 *
	 * @var \Cloudinary\Sync
	 */
	protected $sync;

	/**
	 * Holds the connect component.
	 *
	 * @var \Cloudinary\Connect
	 */
	protected $connect;

	/**
	 * Holds the Rest_API component.
	 *
	 * @var \Cloudinary\REST_API
	 */
	protected $api;

	/**
	 * Holds the sync queue object.
	 *
	 * @var \Cloudinary\Sync\Sync_Queue
	 */
	public $queue;

	/**
	 * Push_Sync constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->register_hooks();
	}

	/**
	 * Register any hooks that this component needs.
	 */
	private function register_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Setup this component.
	 */
	public function setup() {
		// Setup components.
		$this->media   = $this->plugin->components['media'];
		$this->sync    = $this->plugin->components['sync'];
		$this->connect = $this->plugin->components['connect'];
		$this->api     = $this->plugin->components['api'];
		$this->queue   = $this->sync->managers['queue'];

		add_action( 'cloudinary_run_queue', array( $this, 'process_queue' ) );
		add_action( 'cloudinary_sync_items', array( $this, 'process_assets' ) );
	}

	/**
	 * Add endpoints to the \Cloudinary\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['attachments'] = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_get_queue_status' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
		);

		$endpoints['sync'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_start_sync' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_assets' ),
		);

		$endpoints['queue'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'process_queue' ),
			'args'                => array(),
			'permission_callback' => array( 'Cloudinary\REST_API', 'validate_request' ),
		);
		$endpoints['stats'] = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this->queue, 'get_total_synced_media' ),
			'args'                => array(),
			'permission_callback' => array( 'Cloudinary\REST_API', 'validate_request' ),
		);

		return $endpoints;
	}

	/**
	 * Admin permission callback.
	 *
	 * Explicitly defined to allow easier testability.
	 *
	 * @return bool
	 */
	public function rest_can_manage_assets() {
		return Utils::user_can( 'manage_assets', 'manage_options', 'push_sync' );
	}

	/**
	 * Get status of the current queue via REST API.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_queue_status() {

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->queue->is_running(),
			)
		);
	}

	/**
	 * Starts a sync backbround process.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_start_sync( \WP_REST_Request $request ) {

		$type  = $request->get_param( 'type' );
		$start = $this->queue->is_enabled();
		$state = array(
			'success' => false,
		);
		if ( empty( $start ) ) {
			$this->queue->stop_queue();
		} else {
			$state['success'] = $this->queue->start_queue( $type );
		}

		return rest_ensure_response( $state );
	}

	/**
	 * Process asset sync.
	 *
	 * @param int|array $attachments An attachment ID or an array of ID's.
	 *
	 * @return array
	 */
	public function process_assets( $attachments = array() ) {

		$stat = array();
		// If a single specified ID, push and return response.
		$ids    = array_map( 'intval', (array) $attachments );
		$thread = $this->plugin->settings->get_param( 'current_sync_thread' );
		// Handle based on Sync Type.
		foreach ( $ids as $attachment_id ) {

			// Skip non uploadable media.
			if ( ! $this->media->is_uploadable_media( $attachment_id ) ) {
				continue;
			}
			// Skip unsyncable delivery types.
			if ( ! $this->sync->is_syncable( $attachment_id ) ) {
				continue;
			}
			// Flag attachment as being processed.
			update_post_meta( $attachment_id, Sync::META_KEYS['syncing'], time() );
			$stat[ $attachment_id ] = array();
			while ( $type = $this->sync->get_sync_type( $attachment_id, false ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
				// translators: variable is sync type.
				$action_message = sprintf( __( 'Sync type: %s', 'cloudinary' ), $type );
				do_action( '_cloudinary_queue_action', $action_message, $thread );
				if ( isset( $stat[ $attachment_id ][ $type ] ) ) {
					// Loop prevention.
					break;
				}
				$stat[ $attachment_id ][ $type ] = true;
				$result                          = $this->sync->run_sync_method( $type, 'sync', $attachment_id );
				if ( ! empty( $result ) ) {
					$this->sync->log_sync_result( $attachment_id, $type, $result );
				}
			}

			Utils::clean_up_sync_meta( $attachment_id );
		}

		return $stat;
	}

	/**
	 * Attempts to restart an auto-sync thread if needed.
	 */
	public function init_autosync_restart() {
		$thread = $this->plugin->settings->get_param( 'current_sync_thread' );
		if ( 1 !== $this->queue->get_thread_state( $thread ) && $this->queue->get_post( $thread ) ) {
			do_action( '_cloudinary_queue_action', __( 'Starting new thread.', 'cloudinary' ) );
			$this->plugin->components['api']->background_request( 'queue', array( 'thread' => $thread ) );
		}
	}

	/**
	 * Resume the bulk sync.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function process_queue( \WP_REST_Request $request ) {

		$thread = $request->get_param( 'thread' );

		// A second thread would technically overwrite this, however, the manual queue is out in v3.
		$this->plugin->settings->set_param( 'current_sync_thread', $thread );
		$thread_type = $this->queue->get_thread_type( $thread );
		if ( 'autosync' === $thread_type ) {
			add_action( 'shutdown', array( $this, 'init_autosync_restart' ) );
		}
		$queue   = $this->queue->get_thread_queue( $thread );
		$runs    = 0;
		$last_id = 0;
		if ( ! empty( $queue['next'] ) && $this->queue->is_running( $thread_type ) ) {
			while ( ( $attachment_id = $this->queue->get_post( $thread ) ) && $runs < 10 ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
				if ( $last_id === $attachment_id ) {
					update_post_meta( $attachment_id, Sync::META_KEYS['sync_error'], __( 'Asset in sync loop.', 'cloudinary' ) );
					delete_post_meta( $attachment_id, $thread );
					continue;
				}

				// translators: variable is thread name and asset ID.
				$action_message = sprintf( __( '%1$s - cycle %3$s: Syncing asset %2$d', 'cloudinary' ), $thread, $attachment_id, $runs );
				do_action( '_cloudinary_queue_action', $action_message, $thread );
				$this->process_assets( $attachment_id );
				++$runs;
				$last_id = $attachment_id;
			}
			$this->queue->stop_maybe( $thread_type );
		}

		// translators: variable is thread name.
		$action_message = sprintf( __( 'Ending thread %s', 'cloudinary' ), $thread );
		do_action( '_cloudinary_queue_action', $action_message, $thread );
	}
}
