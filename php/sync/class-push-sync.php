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

		$endpoints['eager/(?P<id>[\d]+)'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'capture_eager' ),
			'args'                => array(),
			'permission_callback' => array( $this, 'validate_request' ),
		);

		return $endpoints;
	}

	/**
	 * Validate the incoming request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	public function validate_request( \WP_REST_Request $request ) {
		$timestamp = $request->get_header( 'X-Cld-Timestamp' );
		$body      = $request->get_body();
		$compare   = sha1( $body . $timestamp . $this->connect->api->credentials['api_secret'] );
		$signature = $request->get_header( 'X-Cld-Signature' );

		return $compare === $signature;
	}

	/**
	 * Capture eagers for a video.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return int
	 */
	public function capture_eager( \WP_REST_Request $request ) {

		$attachment_id = (int) $request->get_param( 'id' );
		$body          = $request->get_body();
		$data          = json_decode( $body, ARRAY_A );

		// Precess eagers if found.
		if ( ! empty( $data['eager'] ) ) {
			$this->process_eagers( $attachment_id, $data['eager'] );
		}

		return $attachment_id;
	}

	/**
	 * Process captured eagers.
	 *
	 * @param int   $attachment_id The attachment.
	 * @param array $data          Array of eagers/derivatives.
	 */
	public function process_eagers( $attachment_id, $data ) {
		$updated        = false;
		$eagers         = (array) $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['video_eagers'], true );
		$pending_eagers = (array) $this->media->get_post_meta( $attachment_id, Sync::META_KEYS['pending_eagers'], true );
		foreach ( $data as $eager ) {
			if ( empty( $eager['status'] ) && ! empty( $eager['transformation'] ) ) {
				$signature = md5( $eager['transformation'] );
				if ( isset( $pending_eagers[ $signature ] ) ) {
					unset( $pending_eagers[ $signature ] );
					$updated = true;
				}
				if ( ! in_array( $signature, $eagers ) ) {
					$eagers[] = $signature;
					$updated  = true;
				}
			}
		}
		// Update what was done.
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['video_eagers'], $eagers );
		$this->media->update_post_meta( $attachment_id, Sync::META_KEYS['pending_eagers'], $pending_eagers );
		if ( true === $updated ) {
			// Only update signature if something happened.
			$this->sync->set_signature_item( $attachment_id, 'eager_video' );
			// Remove processing flags.
			delete_post_meta( $attachment_id, Sync::META_KEYS['pending'] );
			delete_post_meta( $attachment_id, Sync::META_KEYS['syncing'] );
		}

		// Return true if pending is empty, indicating completed.
		return empty( $pending_eagers );
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
			$type = $this->sync->get_sync_type( $attachment_id, false );
			while ( $type ) {
				if ( isset( $stat[ $attachment_id ] ) && isset( $stat[ $attachment_id ][ $type ] ) ) {
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
			$attachment_id = $this->queue->get_post( $thread );
			while ( $attachment_id ) {
				$this->process_assets( $attachment_id );
				$this->queue->mark( $attachment_id, 'done' );
			}
			$this->queue->stop_maybe();
		}
	}
}
