<?php
/**
 * Sync queuing to Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary\Sync;

use Cloudinary\Sync;
use Cloudinary\Settings\Setting;
use Cloudinary\Utils;

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
	protected $plugin;
	/**
	 * Holds the Sync instance.
	 *
	 * @since   2.5
	 *
	 * @var     Sync
	 */
	protected $sync;

	/**
	 * Holds the key for saving the queue.
	 *
	 * @var     string
	 */
	private static $queue_key = '_cloudinary_sync_queue';

	/**
	 * Holds the key for bulk queue state.
	 *
	 * @var     string
	 */
	private static $queue_enabled = '_cloudinary_bulk_sync_enabled';

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
	 * Holds the queue threads.
	 *
	 * @var array
	 */
	public $queue_threads = array();

	/**
	 * Holds all the threads.
	 *
	 * @var array
	 */
	public $threads;

	/**
	 * Holds the list of autosync threads.
	 *
	 * @var array
	 */
	protected $autosync_threads = array();

	/**
	 * Upload_Queue constructor.
	 *
	 * @param \Cloudinary\Plugin $plugin The plugin.
	 */
	public function __construct( \Cloudinary\Plugin $plugin ) {
		$this->plugin            = $plugin;
		$this->cron_frequency    = apply_filters( 'cloudinary_cron_frequency', 10 * MINUTE_IN_SECONDS );
		$this->cron_start_offset = apply_filters( 'cloudinary_cron_start_offset', MINUTE_IN_SECONDS );
		$this->load_hooks();
	}

	/**
	 * Setup the sync queue.
	 *
	 * @param Sync $sync The sync instance.
	 */
	public function setup( $sync ) {
		$this->sync = $sync;

		/**
		 * Filter the amount of background threads to process for manual syncing.
		 *
		 * @hook   cloudinary_queue_threads
		 *
		 * @param $count {int} The number of manual sync threads to use.
		 *
		 * @return {int}
		 */
		$queue_threads_count = apply_filters( 'cloudinary_queue_threads', 2 );
		for ( $i = 0; $i < $queue_threads_count; ++$i ) {
			$this->queue_threads[] = 'queue_sync_thread_' . $i;
		}

		/**
		 * Filter the amount of background threads to process for auto syncing.
		 *
		 * @hook   cloudinary_autosync_threads
		 *
		 * @param $count {int} The number of autosync threads to use.
		 *
		 * @return {int}
		 */
		$autosync_thread_count = apply_filters( 'cloudinary_autosync_threads', 2 );
		for ( $i = 0; $i < $autosync_thread_count; ++$i ) {
			$this->autosync_threads[] = 'auto_sync_thread_' . $i;
		}
		$this->threads = array_merge( $this->queue_threads, $this->autosync_threads );

		// Catch Queue actions.
		// Enable sync queue.
		if ( filter_input( INPUT_GET, 'enable-bulk', FILTER_VALIDATE_BOOLEAN ) ) {
			$this->bulk_sync( true );
			wp_safe_redirect( $this->sync->settings->get_component()->get_url() );
			exit;
		}
		// Stop sync queue.
		if ( filter_input( INPUT_GET, 'disable-bulk', FILTER_VALIDATE_BOOLEAN ) ) {
			$this->bulk_sync( false );
			wp_safe_redirect( $this->sync->settings->get_component()->get_url() );
			exit;
		}

		// Periodically restart auto-sync.
		if ( 'on' === $this->plugin->settings->get_value( 'auto_sync' ) && empty( get_transient( '_autosync_check' ) ) ) {
			set_transient( '_autosync_check', true, $this->cron_frequency );
			$this->start_threads( 'autosync' );
		}
	}

	/**
	 * Prepare and push the bulk sync start.
	 *
	 * @param bool $start Flag to start or stop the queue.
	 */
	protected function bulk_sync( $start ) {
		if ( true === $start ) {
			update_option( self::$queue_enabled, true, false );
		} else {
			delete_option( self::$queue_enabled );
		}
		$params = array(
			'type' => 'queue',
		);
		$this->plugin->components['api']->background_request( 'sync', $params );
	}

	/**
	 * Check if the sync is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return get_option( self::$queue_enabled, false );
	}

	/**
	 * Load the Upload Queue hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'cloudinary_resume_queue', array( $this, 'maybe_resume_queue' ) );
		add_action( 'cloudinary_settings_save_setting_auto_sync', array( $this, 'change_setting_state' ), 10, 3 );
	}

	/**
	 * Filter the setting in order to disable the bulk sync if the autosync is disabled.
	 *
	 * @param mixed   $new_value     The new value.
	 * @param mixed   $current_value The current value.
	 * @param Setting $setting       The setting object.
	 *
	 * @return mixed
	 */
	public function change_setting_state( $new_value, $current_value, $setting ) {
		// shutdown queues if needed.
		if ( 'on' === $current_value && 'off' === $new_value ) {
			if ( $this->is_running() ) {
				$this->shutdown_queue( 'queue' );
				add_settings_error( $setting->get_option_name(), 'disabled_sync', __( 'Bulk sync has been disabled.', 'cloudinary' ), 'warning' );
			}
			// Shutdown autosync queue.
			$this->shutdown_queue( 'autosync' );
		}

		return $new_value;
	}

	/**
	 * Get the current Queue.
	 *
	 * @param string $type The type of queue to get.
	 *
	 * @return array
	 */
	public function get_queue( $type = 'queue' ) {
		$default = array(
			'threads' => array(),
			'running' => false,
		);
		switch ( $type ) {
			case 'queue':
				wp_cache_delete( self::$queue_key, 'options' );
				$return = get_option( self::$queue_key, $default );
				break;
			case 'autosync':
				$return            = $default;
				$return['running'] = $this->is_running( 'autosync' );
				if ( true === $return['running'] ) {
					foreach ( $this->autosync_threads as $thread ) {
						if ( 2 <= $this->get_thread_state( $thread ) ) {
							$return['threads'][] = $thread;
						}
					}
				}
				break;
			default:
				$return = $default;
				break;
		}

		$return = wp_parse_args( $return, $default );

		return $return;
	}

	/**
	 * Get a set of pending items.
	 *
	 * @param string $thread The thread ID.
	 *
	 * @return int|false
	 */
	public function get_post( $thread ) {

		$return = false;
		if ( ( $this->is_running( $this->get_thread_type( $thread ) ) ) ) {
			$thread_queue = $this->get_thread_queue( $thread );
			// translators: variable is thread name and queue size.
			$action_message = sprintf( __( '%1$s : Queue size :  %2$s.', 'cloudinary' ), $thread, $thread_queue['count'] );
			do_action( '_cloudinary_queue_action', $action_message, $thread );
			if ( empty( $thread_queue['next'] ) ) {
				// Nothing left to sync.
				return $return;
			}
			$return               = $thread_queue['next'];
			$thread_queue['next'] = 0;
			$thread_queue['ping'] = time();
			$this->set_thread_queue( $thread, $thread_queue );
		}

		return $return;
	}

	/**
	 * Check if the queue is running.
	 *
	 * @param string $type Queue type to check if is running.
	 *
	 * @return bool
	 */
	public function is_running( $type = 'queue' ) {
		if ( 'autosync' === $type ) {
			return true; // Autosync always runs, however if off, auto sync queue building is off.
		}
		$queue = $this->get_queue();

		return $queue['running'];
	}


	/**
	 * The number of optimized media assets.
	 *
	 * @return int
	 */
	public static function get_optimized_assets() {
		global $wpdb;

		$wpdb->cld_table = Utils::get_relationship_table();

		return (int) $wpdb->get_var( "SELECT COUNT( DISTINCT post_id ) as total FROM {$wpdb->cld_table} WHERE sync_type = 'media';" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}


	/**
	 * Get the data from _cloudinary_relationships. We need to get a count of all assets, which have public_id's, group by type etc.
	 *
	 * @return array
	 */
	protected function query_unsynced_data() {
		global $wpdb;

		$cached = get_transient( Sync::META_KEYS['dashboard_cache'] );
		if ( empty( $cached ) ) {
			$return                       = array();
			$wpdb->cld_table              = Utils::get_relationship_table();
			$query_string                 = "SELECT COUNT( DISTINCT post_id ) as total FROM {$wpdb->cld_table} JOIN {$wpdb->posts} on( {$wpdb->cld_table}.post_id = {$wpdb->posts}.ID ) WHERE {$wpdb->posts}.post_type = 'attachment'";
			$return['total_assets']       = (int) $wpdb->get_var( $query_string ); // phpcs:ignore WordPress.DB
			$return['unoptimized_assets'] = (int) $wpdb->get_var( $query_string . ' AND public_id IS NULL' ); // phpcs:ignore WordPress.DB

			$asset_sizes           = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT
			SUM( meta_value ) as total, meta_key as type
		FROM
		{$wpdb->cld_table} AS cld
		RIGHT JOIN {$wpdb->postmeta} AS wp ON ( cld.post_id = wp.post_id )
		WHERE
			meta_key IN ( %s,%s )
		GROUP BY meta_key",
					Sync::META_KEYS['local_size'],
					Sync::META_KEYS['remote_size']
				),
				ARRAY_A
			);
			$return['asset_sizes'] = array(
				'local'  => 0,
				'remote' => 0,
			);
			foreach ( $asset_sizes as $set ) {
				$type                           = Sync::META_KEYS['local_size'] === $set['type'] ? 'local' : 'remote';
				$return['asset_sizes'][ $type ] = (int) $set['total'];
			}

			$threads                = $this->autosync_threads;
			$primary_queue          = array_shift( $threads );
			$return['total_queued'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s;", $primary_queue ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$return['errors']       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s;", Sync::META_KEYS['sync_error'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			set_transient( Sync::META_KEYS['dashboard_cache'], $return, 15 );
			$cached = $return;
		}

		return $cached;
	}

	/**
	 * Get the sync status.
	 *
	 * @return array
	 */
	public function get_total_synced_media() {

		$data = $this->query_unsynced_data();

		if ( empty( $data['asset_sizes'] ) || empty( $data['asset_sizes']['local'] ) ) {
			return array(); // Nothing - don't do calculations.
		}
		// Lets calculate byte sizes.
		$total_local_size  = $data['asset_sizes']['local'];
		$total_remote_size = $data['asset_sizes']['remote'];
		$total_assets      = $data['total_assets'];
		$total_unoptimized = $data['unoptimized_assets'] - $data['total_queued']; // Queued is removed from unoptimized count.
		$total_optimized   = $data['total_assets'] - $data['unoptimized_assets'];
		$total_queued      = $data['total_queued'];
		$errors_count      = $data['errors'];
		// Remove negative.
		if ( 0 > $total_unoptimized ) {
			$total_unoptimized = 0;
		}
		$unoptimized_text = __( 'All assets optimized.', 'cloudinary' );
		if ( 0 < $total_queued && 0 === $total_unoptimized ) {
			$unoptimized_text = __( 'Optimizing assets.', 'cloudinary' );
		}
		// Prepare the package.
		$return = array(
			// Original sizes.
			'original_size'           => $total_local_size,
			'original_size_percent'   => 0 !== $total_assets ? '100%' : '0%', // The original will always be 100%, as it's the comparison to optimized.
			'original_size_hr'        => size_format( $total_local_size ),

			// Optimized size. We use the `original_size` to determine the percentage between for the progress bar.
			'optimized_size'          => $total_remote_size,
			'optimized_size_percent'  => $total_local_size > 0 ? round( abs( $total_remote_size ) / abs( $total_local_size ) * 100 ) . '%' : '0%', // This is the percentage difference.
			'optimized_diff_percent'  => $total_local_size > 0 ? round( ( $total_local_size - $total_remote_size ) / $total_local_size * 100 ) . '%' : '0%', // We use this for the "Size saved.." status text.
			'optimized_size_hr'       => size_format( $total_remote_size ), // This is the formatted byte size.

			// Optimized is the % optimized vs unoptimized.
			'optimized_percent'       => $total_assets > 0 ? round( $total_optimized / $total_assets, 4 ) : 0,
			'optimized_percent_hr'    => $total_assets > 0 ? round( $total_optimized / $total_assets * 100, 1 ) . '%' : '0%',
			'optimized_info'          => __( 'Optimized assets', 'cloudinary' ),

			// Error size: No mockups on what to display here.
			'error_count'             => $errors_count,
			// translators: placeholders are the number of errors.
			'error_count_hr'          => 0 === $errors_count ? '' : sprintf( _n( '%s error with assets', '%s errors with assets', $errors_count, 'cloudinary' ), number_format_i18n( $errors_count ) ),
			'error_clean_up'          => 0 === $errors_count ? '' : __( 'Fix Sync Errors', 'cloudinary' ),

			// Number of assets.
			'total_assets'            => $total_assets, // This is a count of the assets in _cloudinary_relationships.
			'total_unoptimized'       => $total_optimized, // This is the number of assets that dont have a public_id in _cloudinary_relationships.

			// Status text.
			// translators: placeholders are the number of assets unoptimized.
			'unoptimized_status_text' => 0 === $total_unoptimized ? $unoptimized_text : sprintf( _n( '%s asset excluded from optimization.', '%s assets excluded from optimization.', $total_unoptimized, 'cloudinary' ), number_format_i18n( $total_unoptimized ) ),
			// translators: placeholders are the number of assets unoptimized.
			'optimized_status_text'   => 0 !== $total_queued ? sprintf( __( '%1$s assets of %2$s currently syncing with Cloudinary.', 'cloudinary' ), number_format_i18n( $total_queued ), number_format_i18n( $total_assets ) ) : '', // This will be shown when items are pending (check queue).
		);

		return $return;
	}

	/**
	 * Build the upload sync queue.
	 */
	public function build_queue() {

		$args = array(
			'post_type'           => 'attachment',
			'post_mime_type'      => array(),
			'post_status'         => 'inherit',
			'paged'               => 1,
			'posts_per_page'      => 100,
			'fields'              => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_query'          => array(
				'relation' => 'AND',
				array(
					'key'     => Sync::META_KEYS['sync_error'],
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => Sync::META_KEYS['cloudinary'],
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => Sync::META_KEYS['queued'],
					'compare' => 'NOT EXISTS',
				),
			),
			'ignore_sticky_posts' => false,
			'no_found_rows'       => true,
		);

		if ( 'on' === $this->plugin->settings->get_value( 'image_delivery' ) ) {
			$args['post_mime_type'][] = 'image';
		}

		if ( 'on' === $this->plugin->settings->get_value( 'video_delivery' ) ) {
			$args['post_mime_type'][] = 'video';
		}

		/**
		 * Filter the params for the query used to build a queue.
		 *
		 * @hook   cloudinary_build_queue_query
		 * @since  2.7.6
		 *
		 * @param $args {array} The arguments for the query.
		 *
		 * @return {array}
		 */
		$args = apply_filters( 'cloudinary_build_queue_query', $args );

		if ( empty( $args['post_mime_type'] ) ) {
			$action_message = __( 'No mime types to query.', 'cloudinary' );
			do_action( '_cloudinary_queue_action', $action_message );

			return;
		}

		// translators: variable is page number.
		$action_message = __( 'Building Queue.', 'cloudinary' );
		do_action( '_cloudinary_queue_action', $action_message );

		$query = new \WP_Query( $args );
		if ( ! $query->have_posts() ) {
			// translators: variable is page number.
			$action_message = __( 'No posts', 'cloudinary' );
			do_action( '_cloudinary_queue_action', $action_message );

			return;
		}
		$ids = array();
		do {
			$ids  = array_merge( $ids, $query->get_posts() );
			$args = $query->query_vars;
			++$args['paged'];
			$query = new \WP_Query( $args );
		} while ( $query->have_posts() );

		$threads          = $this->add_to_queue( $ids );
		$queue            = array();
		$queue['total']   = array_sum( $threads );
		$queue['threads'] = array_keys( $threads );
		$queue['started'] = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		wp_cache_delete( self::$queue_enabled, 'options' );
		$queue['running'] = get_option( self::$queue_enabled );
		// Set the queue option.
		update_option( self::$queue_key, $queue, false );
	}

	/**
	 * Maybe stop the queue.
	 *
	 * @param string $type The type to maybe stop.
	 */
	public function stop_maybe( $type = 'queue' ) {
		$queue = $this->get_queue( $type );
		foreach ( $queue['threads'] as $thread ) {
			if ( 2 <= $this->get_thread_state( $thread ) ) {
				return; // Only 1 thread still needs to be running.
			}
		}
		// Stop the queue.
		$this->stop_queue( $type );
		// Restart the queue to make sure there are no new items added after the last start.
		$this->start_queue( $type );
	}

	/**
	 * Shuts down the queue and disable sync bulk.
	 *
	 * @param string $type The type of queue to shutdown.
	 */
	protected function shutdown_queue( $type = 'queue' ) {
		if ( 'queue' === $type ) {
			delete_option( self::$queue_enabled );
		} elseif ( 'autosync' === $type ) {
			// Remove pending flag.
			delete_post_meta_by_key( Sync::META_KEYS['pending'] );
		}
		$this->stop_queue( $type );
	}

	/**
	 * Stop the current queue cycle. Will restart once cycle is freed up.
	 *
	 * @param string $type The type of queue to stop.
	 */
	public function stop_queue( $type = 'queue' ) {

		// translators: variable is queue type.
		$action_message = sprintf( __( 'Stopping queue:  %s.', 'cloudinary' ), $type );
		do_action( '_cloudinary_queue_action', $action_message );
		if ( 'queue' === $type ) {
			delete_post_meta_by_key( Sync::META_KEYS['queued'] );
		} else {
			delete_post_meta_by_key( Sync::META_KEYS['pending'] );
		}
		$threads = $this->get_threads( $type );
		foreach ( $threads as $thread ) {
			$this->reset_thread_queue( $thread );
			delete_post_meta_by_key( $thread );
		}

		if ( 'queue' === $type ) {
			delete_option( self::$queue_key );
			wp_unschedule_hook( 'cloudinary_resume_queue' );
		}
	}

	/**
	 * Start the queue by setting the started flag.
	 *
	 * @param string $type The type of queue to start.
	 *
	 * @return bool
	 */
	public function start_queue( $type = 'queue' ) {
		$started = false;
		if ( ! $this->is_running( $type ) ) {
			if ( 'queue' === $type ) {
				$this->build_queue();
				$this->schedule_resume();
			}
			$started = $this->start_threads( $type );
			if ( ! $started ) {
				$this->shutdown_queue( $type );
			}
		} else {
			// translators: variable is queue type.
			$action_message = sprintf( __( 'Queue:  %s - not running.', 'cloudinary' ), $type );
			do_action( '_cloudinary_queue_action', $action_message );
		}

		return $started;
	}

	/**
	 * Check if thread is autosync thread.
	 *
	 * @param string $thread Thread name.
	 *
	 * @return bool
	 */
	public function is_autosync_thread( $thread ) {
		return in_array( $thread, $this->autosync_threads, true );
	}

	/**
	 * Start all threads.
	 *
	 * @param string $type The type of threads to start.
	 *
	 * @return bool
	 */
	public function start_threads( $type = 'queue' ) {
		$queue           = $this->get_queue( $type );
		$threads_started = false;
		foreach ( $queue['threads'] as $thread ) {
			if ( 2 !== $this->start_thread( $thread ) ) {
				$this->reset_thread_queue( $thread );
				continue;
			}
			$threads_started = true;
			usleep( 500 ); // Slight pause to prevent server overload.
		}

		return $threads_started;
	}

	/**
	 * Start a thread to process.
	 *
	 * @param string $thread Thread ID.
	 *
	 * @return int State of thread.
	 */
	public function start_thread( $thread ) {
		// Check thread is still running.
		$sync_state = $this->get_thread_state( $thread );
		if ( 3 === $sync_state ) {
			// translators: variable is thread name.
			$action_message = sprintf( __( 'Starting thread %s.', 'cloudinary' ), $thread );
			do_action( '_cloudinary_queue_action', $action_message, $thread );
			$this->plugin->components['api']->background_request( 'queue', array( 'thread' => $thread ) );
			$sync_state = 2; // Set as started.
		}

		return $sync_state;
	}

	/**
	 * Get the option name for a thread.
	 *
	 * @param string $thread Thread name.
	 *
	 * @return string
	 */
	protected function get_thread_option( $thread ) {
		return self::$queue_key . '_' . $thread;
	}

	/**
	 * Get the thread type fora thread name..
	 *
	 * @param string $thread Thread name.
	 *
	 * @return string
	 */
	public function get_thread_type( $thread ) {

		return $this->is_autosync_thread( $thread ) ? 'autosync' : 'queue';
	}

	/**
	 * Get a threads queue.
	 *
	 * @param string $thread Thread ID.
	 *
	 * @return array
	 */
	public function get_thread_queue( $thread ) {
		$return = array();
		if ( in_array( $thread, $this->threads, true ) ) {
			$thread_option = $this->get_thread_option( $thread );
			$default       = array(
				'ping' => 0, // set to 0 to ready to start.
				'next' => 0,
			);
			wp_cache_delete( $thread_option, 'options' );
			$return = get_option( $thread_option );
			if ( empty( $return ) ) {
				// Set option to remove notoption and default fro  cache.
				$this->set_thread_queue( $thread, $default );
				$return = $default;
			}
			$return = array_merge( $return, $this->get_thread_queue_details( $thread ) );
		}

		return $return;
	}

	/**
	 * Get the count of posts and the next post to be synced.
	 *
	 * @param string $thread Thread name.
	 *
	 * @return array
	 */
	protected function get_thread_queue_details( $thread ) {

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'cache_results'  => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => $thread,
					'compare' => 'EXISTS',
				),
			),
		);

		/**
		 * Filter the params for the query used to get thread queue details.
		 *
		 * @hook   cloudinary_thread_queue_details_query
		 * @since  2.7.6
		 *
		 * @param $args   {array}  The arguments for the query.
		 * @param $thread {string} The thread name.
		 *
		 * @return {array}
		 */
		$args = apply_filters( 'cloudinary_thread_queue_details_query', $args, $thread );

		$query = new \WP_Query( $args );

		$return = array(
			'count' => 0,
			'next'  => 0,
		);
		if ( ! empty( $query->have_posts() ) ) {
			$return['count'] = $query->found_posts;
			$return['next']  = $query->next_post();
		}

		return $return;
	}

	/**
	 * Add to a threads queue.
	 *
	 * @param int   $thread         Thread ID.
	 * @param array $attachment_ids The ID to add.
	 */
	public function add_to_thread_queue( $thread, array $attachment_ids ) {

		if ( in_array( $thread, $this->threads, true ) ) {
			foreach ( $attachment_ids as $id ) {
				$previous_thread = null;
				if ( metadata_exists( 'post', $id, Sync::META_KEYS['queued'] ) ) {
					if ( 'queue' === $this->get_thread_type( $thread ) ) {
						continue;
					}
					$previous_thread = get_post_meta( $id, Sync::META_KEYS['queued'], true );
					delete_post_meta( $id, $previous_thread, true );
					delete_post_meta( $id, Sync::META_KEYS['queued'], $previous_thread );
				}
				add_post_meta( $id, Sync::META_KEYS['queued'], $thread, true );
				add_post_meta( $id, $thread, true, true );
			}
		}
	}

	/**
	 * Set the threads queue;
	 *
	 * @param string $thread       The thread to set.
	 * @param array  $thread_queue The queue to set.
	 */
	protected function set_thread_queue( $thread, $thread_queue ) {
		update_option( $this->get_thread_option( $thread ), $thread_queue, false );
	}

	/**
	 * Get threads of a type.
	 *
	 * @param string $type The type to get.
	 *
	 * @return array
	 */
	public function get_threads( $type = 'queue' ) {
		$types = array(
			'queue'    => $this->queue_threads,
			'autosync' => $this->autosync_threads,
		);

		if ( 'all' === $type ) {
			return $types;
		}

		if ( isset( $types[ $type ] ) ) {
			return $types[ $type ];
		}

		return array();
	}

	/**
	 * Add to the autosync queue.
	 *
	 * @param array  $attachment_ids Array of IDs to add to autosync.
	 * @param string $type           The type of queue to add to.
	 *
	 * @return array
	 */
	public function add_to_queue( array $attachment_ids, $type = 'queue' ) {

		$been_synced    = array_filter( $attachment_ids, array( $this->sync, 'been_synced' ) );
		$new_items      = array_diff( $attachment_ids, $been_synced );
		$threads        = $this->get_threads( $type );
		$new_thread     = array_shift( $threads );
		$active_threads = array();
		if ( ! empty( $new_items ) ) {
			$this->add_to_thread_queue( $new_thread, $new_items );
			$active_threads[ $new_thread ] = count( $new_items );
		}

		$attachment_ids = $been_synced;
		if ( ! empty( $attachment_ids ) ) {
			$chunk_size = ceil( count( $attachment_ids ) / count( $threads ) );
			$chunks     = array_chunk( $attachment_ids, $chunk_size );
			foreach ( $chunks as $index => $chunk ) {
				$thread = array_shift( $threads );
				$this->add_to_thread_queue( $thread, $chunk );
				$active_threads[ $thread ] = count( $chunk );
			}
		}

		return $active_threads;
	}

	/**
	 * Reset a threads queue.
	 *
	 * @param string $thread Thread name.
	 */
	public function reset_thread_queue( $thread ) {
		delete_option( $this->get_thread_option( $thread ) );
	}

	/**
	 * Schedule a resume queue check.
	 */
	protected function schedule_resume() {
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		wp_schedule_single_event( $now + $this->cron_frequency, 'cloudinary_resume_queue' );
	}

	/**
	 * Get the state of the thread.
	 *
	 * @param string $thread Thread name to check.
	 *
	 * @return int  0 = disabled, 1 = ended, 2 = active, 3 = stalled/ready to start.
	 */
	public function get_thread_state( $thread ) {

		$return = 0; // Default state is disabled.

		if ( $this->is_running( $this->get_thread_type( $thread ) ) ) {
			$thread_queue = $this->get_thread_queue( $thread );
			$offset       = time() - $thread_queue['ping'];
			$return       = 3; // If autosync is running, default is ready/stalled.
			if ( empty( $thread_queue['next'] ) && empty( $thread_queue['count'] ) ) {
				$return = 1; // Queue is empty, so nothing to sync, set as ended.
			} elseif ( ! empty( $thread_queue['ping'] ) && $offset < $this->cron_start_offset ) {
				$return = 2; // If the last ping is within the time frame, it's still active.
			}
		}

		return $return;
	}

	/**
	 * Maybe resume the queue.
	 * This is a fallback mechanism to resume the queue when it stops unexpectedly.
	 *
	 * @return void
	 */
	public function maybe_resume_queue() {

		do_action( '_cloudinary_queue_action', __( 'Resuming Maybe', 'cloudinary' ) );
		$stopped = array();
		if ( $this->is_running() ) {
			// Check each thread.
			foreach ( $this->threads as $thread ) {
				if ( 3 === $this->get_thread_state( $thread ) ) {
					// Possible that thread has stopped.
					$stopped[] = $thread;
					// translators: variable is thread name.
					$action_message = sprintf( __( 'Thread %s Stopped.', 'cloudinary' ), $thread );
					do_action( '_cloudinary_queue_action', $action_message, $thread );
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
