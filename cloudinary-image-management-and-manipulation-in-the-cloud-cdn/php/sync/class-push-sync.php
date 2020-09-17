<?php
/**
 * Push Sync to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;

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
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		$endpoints['sync'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_start_sync' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'rest_can_manage_options' ),
		);

		$endpoints['process'] = array(
			'method'   => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'process_sync' ),
			'args'     => array(),
		);

		$endpoints['queue'] = array(
			'method'   => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'process_queue' ),
			'args'     => array(),
		);

		$endpoints['video_explicit_upload'] = array(
			'method'   => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'rest_video_explicit_upload' ),
			'args'     => array(),
		);

		$endpoints['video_explicit_upload_callback'] = array(
			'method'   => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'rest_video_explicit_upload_callback' ),
			'args'     => array(),
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
	public function rest_can_manage_options() {
		return current_user_can( 'manage_options' );
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
				'data'    => $this->queue->get_queue_status(),
			)
		);
	}

	/**
	 * Send an explicit request for large videos so that transformations can be applied async.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_video_explicit_upload( \WP_REST_Request $request ) {
		$req_body = json_decode( $request->get_body(), true );

		if ( ! $this->plugin->components['sync']->is_pending( $req_body['attachment_id'] ) ) {
			$res = $this->media->video->queue_eager_video( $req_body );
			return rest_ensure_response( ! is_wp_error( $res ) ? 'ok' : 'error' );
		}

		return rest_ensure_response( 'pending' );
	}

	/**
	 * Wait for Cloudinary's response on the eager sync status.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_video_explicit_upload_callback( \WP_REST_Request $request ) {
		if ( ! $request->get_body() ) {
			return;
		}

		$body = json_decode( $request->get_body(), true );

		if ( ! isset( $body['eager'][0]['status'] ) && isset( $body['public_id'], $body['eager'][0]['secure_url'] ) ) {
			$sync_key        = $body['public_id'];
			$transformations = $this->media->get_transformations_from_string( $body['eager']['secure_url'] );

			if ( ! empty( $transformations ) ) {
				$sync_key .= wp_json_encode( $transformations );
			}

			$attachment_id = $this->media->get_id_from_sync_key( $sync_key );
			delete_post_meta( $attachment_id, Sync::META_KEYS['pending'] );
		} else {
			// do something? we'll see.
			error_log( 'an error has occurred: ' . $body['public_id'] );
		}
	}

	/**
	 * Starts a sync backbround process.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_start_sync( \WP_REST_Request $request ) {

		$stop   = $request->get_param( 'stop' );
		$status = $this->queue->get_queue_status();
		if ( empty( $status['pending'] ) || ! empty( $stop ) ) {
			$this->queue->stop_queue();

			return $this->rest_get_queue_status(); // Nothing to sync.
		}

		$this->queue->start_queue();

		return $this->rest_get_queue_status();
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
		$ids = array_map( 'intval', (array) $attachments );
		// Handle based on Sync Type.
		foreach ( $ids as $attachment_id ) {
			// Flag attachment as being processed.
			update_post_meta( $attachment_id, Sync::META_KEYS['syncing'], time() );
			while ( $type = $this->sync->get_sync_type( $attachment_id, false ) ) {
				if ( isset( $stat[ $attachment_id ][ $type ] ) ) {
					// Loop prevention.
					break;
				}
				$stat[ $attachment_id ][ $type ] = $this->sync->run_sync_method( $type, 'sync', $attachment_id );
			}
			// remove pending.
			delete_post_meta( $attachment_id, Sync::META_KEYS['pending'] );
			// Record Process log.
			$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['process_log'], $stat[ $attachment_id ] );
			// Remove processing flag.
			delete_post_meta( $attachment_id, Sync::META_KEYS['syncing'] );

			// Create synced post meta as a way to search for synced / unsynced items.
			update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], $this->media->get_public_id( $attachment_id ) );
		}

		return $stat;
	}

	/**
	 * Process assets to sync vai WP REST API.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed|\WP_REST_Response
	 */
	public function process_sync( \WP_REST_Request $request ) {
		$process_key = $request->get_param( 'process_key' );
		$note        = 'no process key';
		if ( ! empty( $process_key ) ) {
			$attachments = get_transient( $process_key );
			if ( ! empty( $attachments ) ) {
				delete_transient( $process_key );

				return rest_ensure_response(
					array(
						'success' => true,
						'data'    => $this->process_assets( $attachments ),
					)
				);
			}
			$note = 'no attachments';
		}

		return rest_ensure_response(
			array(
				'success' => false,
				'note'    => $note,
			)
		);
	}

	/**
	 * Resume the bulk sync.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return void
	 */
	public function process_queue( \WP_REST_Request $request ) {
		$thread = $request->get_param( 'thread' );
		$queue  = $this->queue->get_thread_queue( $thread );

		if ( ! empty( $queue ) && $this->queue->is_running() ) {
			while ( $attachment_id = $this->queue->get_post( $thread ) ) {
				$this->process_assets( $attachment_id );
				$this->queue->mark( $attachment_id, 'done' );
			}
			$this->queue->stop_maybe();
		}
	}
}
