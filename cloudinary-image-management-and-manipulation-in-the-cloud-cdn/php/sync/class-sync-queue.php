<?php
/**
 * Sync queuing to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;

/**
 * Class Sync_Queue.
 *
 * Queue assets for Cloudinary sync.
 */
class Sync_Queue {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   0.1
	 *
	 * @var     \Cloudinary\Plugin Instance of the global plugin.
	 */
	private $plugin;

	/**
	 * Holds the key for saving the queue.
	 *
	 * @var     string
	 */
	private static $queue_key = '_cloudinary_sync_queue';

	/**
	 * The cron frequency to ensure that the queue is progressing.
	 *
	 * @var int
	 */
	protected $cron_frequency;

	/**
	 * The cron offset since the last update.
	 *
	 * @var int
	 */
	protected $cron_start_offset;

	/**
	 * Holds the thread ID's
	 *
	 * @var array
	 */
	public $threads;

	/**
	 * Upload_Queue constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin = $plugin;

		$this->cron_frequency    = apply_filters( 'cloudinary_cron_frequency', 10 * MINUTE_IN_SECONDS );
		$this->cron_start_offset = apply_filters( 'cloudinary_cron_start_offset', MINUTE_IN_SECONDS );
		$this->threads           = apply_filters( 'cloudinary_queue_threads', array( 'thread_0', 'thread_1', 'thread_2' ) );
		$this->load_hooks();
	}

	/**
	 * Load the Upload Queue hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'cloudinary_resume_queue', array( $this, 'maybe_resume_queue' ) );
	}

	/**
	 * Get the current Queue.
	 *
	 * @return array|mixed
	 */
	public function get_queue() {
		wp_cache_delete( self::$queue_key, 'options' );
		$queue = get_option( self::$queue_key, array() );
		if ( empty( $queue ) ) {
			$queue = $this->build_queue();
		}

		return $queue;
	}

	/**
	 * Set the queue.
	 *
	 * @param array $queue The queue array to set.
	 *
	 * @return bool
	 */
	public function set_queue( $queue ) {

		return update_option( self::$queue_key, $queue, false );
	}

	/**
	 * Get a set of pending items.
	 *
	 * @param string $thread The thread ID.
	 *
	 * @return bool
	 */
	public function get_post( $thread ) {
		$id = false;
		if ( $this->is_running() && in_array( $thread, $this->threads, true ) ) {
			$queue = $this->get_queue();
			if ( ! empty( $queue[ $thread ] ) ) {
				$id                    = array_shift( $queue[ $thread ] );
				$queue['processing'][] = $id;
				$queue['last_update']  = current_time( 'timestamp' );

				if ( ! empty( $queue['run_status'][ $thread ]['last_update'] ) ) {
					$queue['run_status'][ $thread ]['posts'][] = current_time( 'timestamp' ) - $queue['run_status'][ $thread ]['last_update'];
					$queue['run_status'][ $thread ]['average'] = round( array_sum( $queue['run_status'][ $thread ]['posts'] ) / count( $queue['run_status'][ $thread ]['posts'] ), 2 );
				}
				$queue['run_status'][ $thread ]['last_update'] = current_time( 'timestamp' );

				$this->set_queue( $queue );
			}
		}

		return $id;
	}

	/**
	 * Check if a thread is running.
	 *
	 * @param string $thread Thread ID to check.
	 *
	 * @return bool
	 */
	protected function thread_running( $thread ) {
		$running = false;
		if ( in_array( $thread, $this->threads, true ) ) {
			$queue = $this->get_queue();
			$now   = current_time( 'timestamp' );
			if ( $this->is_running() && ! empty( $queue[ $thread ] ) && $now - $queue['run_status'][ $thread ]['last_update'] < $this->cron_start_offset ) {
				$running = true;
			}
		}

		return $running;
	}

	/**
	 * Mark an id as done or error.
	 *
	 * @param int    $id   The post ID.
	 * @param string $type The type of marking to apply.
	 */
	public function mark( $id, $type = 'done' ) {
		$queue                = $this->get_queue();
		$queue['last_update'] = current_time( 'timestamp' );
		$key                  = array_search( (int) $id, $queue['processing'], true );
		if ( false !== $key ) {
			unset( $queue['processing'][ $key ] );
			if ( ! in_array( $id, $queue[ $type ], true ) ) {
				$queue[ $type ][] = $id;
			}

		}

		$this->set_queue( $queue );
	}

	/**
	 * Check if the queue is running.
	 *
	 * @return bool
	 */
	public function is_running() {
		$queue = $this->get_queue();

		return ! empty( $queue['started'] );
	}

	/**
	 * Gets the current upload sync queue status.
	 *
	 * @return array
	 */
	public function get_queue_status() {
		$queue   = $this->validate_queue();
		$pending = 0;
		foreach ( $this->threads as $thread ) {
			$pending += count( $queue[ $thread ] );
		}
		$done       = count( $queue['done'] );
		$processing = count( $queue['processing'] );
		$error      = count( $queue['error'] );
		$total      = $done + $pending + $processing + $error;
		$completed  = $done;
		$file       = null;
		if ( ! empty( $queue['processing'][0] ) ) {
			$file = get_attached_file( $queue['processing'][0] );
		}
		$percent = 100;
		if ( $completed < $total ) {
			$percent = round( ( $completed + $error ) / ( $total ) * 100, 1 );
		}

		$return = array(
			'total'        => $total,
			'processing'   => $processing,
			'current_file' => $file ? basename( $file ) : null,
			'pending'      => $pending + $processing,
			'done'         => $completed,
			'error'        => $queue['error'],
			'percent'      => $percent,
		);
		if ( ! empty( $queue['started'] ) ) {
			$return['started'] = $queue['started'];
		}

		// Auto Stop.
		if ( 100 === $return['percent'] ) {
			$this->stop_queue();
		}

		$return['is_running'] = $this->is_running();

		return $return;
	}

	/**
	 * Validate the queue is up to date and populate with unsynced assets.
	 *
	 * @return array Validated Queue.
	 */
	public function validate_queue() {

		$queue = $this->get_queue();
		if ( ! empty( $queue['processing'] ) ) {
			foreach ( $queue['processing'] as $attachment_id ) {
				if ( $this->plugin->get_component( 'sync' )->is_synced( $attachment_id ) ) {
					$this->mark( $attachment_id, 'done' );
				}
			}
			// Get queue to get new version with marked processing.
			$queue = $this->get_queue();
		}
		$args = array(
			'post_type'           => 'attachment',
			'post_mime_type'      => array( 'image', 'video' ),
			'post_status'         => 'inherit',
			'posts_per_page'      => 1000, // phpcs:ignore
			'fields'              => 'ids',
			'meta_query'          => array( // phpcs:ignore
				'relation' => 'AND',
				array(
					'key'     => Sync::META_KEYS['sync_error'],
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => Sync::META_KEYS['public_id'],
					'compare' => 'NOT EXISTS',
				),
			),
			'ignore_sticky_posts' => false,
			'no_found_rows'       => true,
		);

		$attachments = new \WP_Query( $args );
		$ids         = $attachments->get_posts();
		// Reset Threads.
		foreach ( $this->threads as $thread ) {
			$queue[ $thread ]               = array();
			$queue['run_status'][ $thread ] = array();
		}
		// Add items to pending queue.
		if ( ! empty( $ids ) ) {
			$chunk_size = ceil( count( $ids ) / count( $this->threads ) );
			$chunks     = array_chunk( $ids, $chunk_size );
			foreach ( $chunks as $index => $chunk ) {
				$queue[ $this->threads[ $index ] ] = $chunk;
				// Check thread is still running.
				if ( $this->is_running() && ! $this->thread_running( $this->threads[ $index ] ) ) {
					$this->start_thread( $this->threads[ $index ] );
				}
			}
		}

		$this->set_queue( $queue );

		return $queue;
	}

	/**
	 * Build the upload sync queue.
	 */
	public function build_queue() {

		// Transform attachments.
		$return = array(
			'done'       => array(),
			'processing' => array(),
			'error'      => array(),
			'run_status' => array(),
		);
		foreach ( $this->threads as $thread ) {
			$return[ $thread ]               = array();
			$return['run_status'][ $thread ] = array();
		}

		$this->set_queue( $return );
		$this->validate_queue();

		return $return;
	}

	/**
	 * Maybe stop the queue.
	 */
	public function stop_maybe() {
		$status = $this->get_queue_status();
		if ( empty( $status['pending'] ) ) {
			$this->stop_queue();
		}
	}

	/**
	 * Stop the queue by removing the started flag.
	 */
	public function stop_queue() {
		$queue = $this->get_queue();
		if ( ! empty( $queue['started'] ) ) {
			unset( $queue['started'] );
			unset( $queue['last_update'] );
			$this->set_queue( $queue );
		}

		wp_unschedule_hook( 'cloudinary_resume_queue' );
	}

	/**
	 * Start the queue by setting the started flag.
	 *
	 * @return array
	 */
	public function start_queue() {
		$queue = $this->get_queue();
		if ( ! empty( $queue['processing'] ) ) {
			// In case it stopped mid process, push back to the  first thread.
			$queue['thread_0'] = array_merge( $queue['thread_0'], $queue['processing'] );
		}
		// Count how many are pending.
		$status = $this->get_queue_status();
		if ( empty( $status['pending'] ) ) {
			// Dont start if theres nothing pending.
			return $status;
		}
		// Mark as started.
		$queue['started']     = current_time( 'timestamp' );
		$queue['last_update'] = current_time( 'timestamp' );

		$this->set_queue( $queue );

		foreach ( $this->threads as $thread ) {
			if ( ! empty( $queue[ $thread ] ) ) {
				$this->start_thread( $thread );
				sleep( 2 ); // Slight pause to prevent server overload.
			}
		}
		$this->schedule_resume();

		return $status;
	}

	/**
	 * Start a thread to process.
	 *
	 * @param int $thread Thread ID.
	 */
	public function start_thread( $thread ) {

		$this->plugin->components['api']->background_request( 'queue', array( 'thread' => $thread ) );
	}


	/**
	 * Get a threads queue.
	 *
	 * @param int $thread Thread ID.
	 *
	 * @return array
	 */
	public function get_thread_queue( $thread ) {
		$queue  = $this->get_queue();
		$return = array();
		if ( in_array( $thread, $this->threads, true ) && ! empty( $queue[ $thread ] ) ) {
			$return = $queue[ $thread ];
		}

		return $return;
	}

	/**
	 * Schedule a resume queue check.
	 */
	protected function schedule_resume() {
		$now = current_time( 'timestamp' );
		wp_schedule_single_event( $now + $this->cron_frequency, 'cloudinary_resume_queue' );
	}

	/**
	 * Maybe resume the queue.
	 * This is a fallback mechanism to resume the queue when it stops unexpectedly.
	 *
	 * @return void
	 */
	public function maybe_resume_queue() {
		$stopped = array();
		if ( $this->is_running() ) {
			// Check each thread.
			foreach ( $this->threads as $thread ) {
				if ( ! $this->thread_running( $thread ) ) {
					// Possible that thread has stopped.
					$stopped[] = $thread;
				}
			}

			if ( count( $stopped ) === count( $this->threads ) ) {
				// All threads have stopped. Stop Queue to prevent overload in case of a slow sync.
				$this->stop_queue();
				sleep( 5 ); // give it 5 seconds to allow the stop and maybe threads to catchup.
				// Start a new sync.
				$this->start_queue();
			} elseif ( ! empty( $stopped ) ) {
				// Just start the threads that have stopped.
				array_map( array( $this, 'start_thread' ), $stopped );
				$this->schedule_resume();
			} else {
				$this->schedule_resume();
			}
		}
	}
}
