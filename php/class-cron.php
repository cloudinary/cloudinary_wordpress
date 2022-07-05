<?php
/**
 * Cron class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * Class Cron
 */
class Cron {

	/**
	 * Holds the registered processes.
	 *
	 * @var array
	 */
	protected $processes = array();

	/**
	 * Holds the cron schedule.
	 *
	 * @var array
	 */
	protected $schedule = array();

	/**
	 * Holds the instance initialization time.
	 *
	 * @var int
	 */
	protected $init_time;

	/**
	 * Holds the meta key for the cron schedule.
	 */
	const CRON_META_KEY = 'cloudinary_cron_schedule';

	/**
	 * Holds the cron watcher interval.
	 *
	 * @var int
	 */
	const DAEMON_WATCHER_INTERVAL = 20;

	/**
	 * Cron constructor.
	 */
	public function __construct() {
		$this->init_time = time();
		$this->init();
	}

	/**
	 * Initialize the cron.
	 */
	public function init() {
		$this->load_schedule();
		//add_action( 'cloudinary_cron_job', array( $this, 'run_queue' ) );
		//add_action( 'cloudinary_cron_daemon', array( $this, 'daemon_watcher' ) );
		//		add_action( 'shutdown', array( $this, 'process_schedule' ) );
		add_action( 'shutdown', array( $this, 'start_daemon' ) );
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Register REST endpoints.
	 */
	public function rest_endpoints( $endpoints ) {
		$endpoints['cron_watch'] = array(
			'method'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'daemon_watcher' ),
			'permission_callback' => '__return_true',
		);
		$endpoints['cron_process'] = array(
			'method'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'run_queue' ),
			'permission_callback' => '__return_true',
		);

		return $endpoints;
	}

	/**
	 * Load the saved schedule.
	 */
	protected function load_schedule() {
		$this->schedule = get_option( self::CRON_META_KEY, array() );
		foreach ( $this->schedule as &$item ) {
			$item['active'] = false;
		}
	}

	/**
	 * Register a new cron process.
	 *
	 * @param string   $name     Name of the process.
	 * @param callable $callback Callback to run.
	 * @param int      $interval Interval in seconds.
	 * @param int      $offset   First call offset in seconds, or 0 for now.
	 */
	public static function register_process( $name, $callback, $interval = 60, $offset = 0 ) {
		$cron                     = self::get_instance();
		$cron->processes[ $name ] = array(
			'callback' => $callback,
			'interval' => $interval,
			'offset'   => $offset,
		);
		$cron->register_schedule( $name );
	}

	/**
	 * Registered cron process's schedule and set it as active.
	 *
	 * @param string $name Name of the process.
	 */
	public function register_schedule( $name ) {
		if ( ! isset( $this->schedule[ $name ] ) ) {
			$process                 = $this->processes[ $name ];
			$runtime                 = $this->init_time + $process['offset'];
			$this->schedule[ $name ] = array(
				'last_run' => $runtime,
				'next_run' => $runtime,
				'timeout'  => 0,
			);
		}
		$this->schedule[ $name ]['active'] = true;
	}

	/**
	 * Unregister a cron process from the schedule.
	 *
	 * @param string $name Name of the process.
	 */
	public function unregister_schedule( $name ) {
		if ( isset( $this->schedule[ $name ] ) ) {
			unset( $this->schedule[ $name ] );
		}
	}

	/**
	 * Update the cron schedule with the last run time and the next run time.
	 *
	 * @param string $name Name of the process to update.
	 */
	public function update_schedule( $name ) {
		$this->schedule[ $name ]['last_run'] = $this->init_time;
		$this->schedule[ $name ]['next_run'] = $this->init_time + $this->processes[ $name ]['interval'];
	}

	/**
	 * Save the cron schedule.
	 */
	public function save_schedule() {
		update_option( self::CRON_META_KEY, $this->schedule );
	}

	/**
	 * Process the cron schedule.
	 */
	public function process_schedule() {
		$queue = array();
		foreach ( $this->schedule as $name => $schedule ) {
			// Remove schedules that are not active.
			if ( ! $schedule['active'] ) {
				$this->unregister_schedule( $name );
				continue;
			}
			// Queue the process if it's time to run.
			if (  $this->init_time >= $schedule['next_run'] ) {
				$queue[] = $name;
				$this->update_schedule( $name );
				$this->lock_schedule_process( $name );
			}
		}
		// Run the queued processes.
		if ( ! empty( $queue ) ) {
			$this->push_queue( $queue );
		}
	}

	/**
	 * Push the queue to the cron.
	 */
	protected function push_queue( $queue ) {

		$this->save_schedule();
		$instance = get_plugin_instance();
		$rest = $instance->get_component( 'api' );
		$rest->background_request( 'cron_process', array( 'queue' => $queue ), 'POST' );
		//wp_schedule_single_event( $this->init_time, 'cloudinary_cron_job', array( $queue ) );
		//spawn_cron(); // This is a failsafe to trigger the cron on this run, rather than waiting for the next page load.
	}

	/**
	 * Start cron daemon.
	 */
	public function start_daemon() {
		$check = get_transient( 'cloudinary_cron_daemon' );
		if ( ! empty ( $check ) ) {
			return;
		}

		set_transient( 'cloudinary_cron_daemon', '1', self::DAEMON_WATCHER_INTERVAL );
		$instance = get_plugin_instance();
		$rest = $instance->get_component( 'api' );
		$rest->background_request( 'cron_watch', array(), 'GET' );
	}

	/**
	 * Lock a cron schedule process.
	 *
	 * @param string $name Name of the process to lock.
	 */
	protected function lock_schedule_process( $name ) {
		$this->schedule[ $name ]['timeout'] = $this->init_time + 60;    // 60 seconds.
	}

	/**
	 * Unlock the cron schedule process.
	 *
	 * @param string $name Name of the process to unlock.
	 */
	protected function unlock_schedule_process( $name ) {
		$this->schedule[ $name ]['timeout'] = 0;
	}

	/**
	 * Run the queue.
	 *
	 * @param WP_REST_Request $request Queue to run.
	 */
	public function run_queue( \WP_REST_Request $request ) {
		$queue = $request->get_param( 'queue' );
		//error_log( 'Running queue ' . print_r( $queue, true ) );
		foreach ( $queue as $name ) {
			$process = $this->processes[ $name ];
			// translators: variable is process name.
			$action_message = sprintf( __( 'Cloudinary Cron Process Start: %s', 'cloudinary' ), $name );
			//do_action( '_cloudinary_cron_action', $action_message );
			$data = $process['callback']( $name );
			if ( ! empty( $data ) ) {
				// translators: variable is process result.
				$result = sprintf( __( 'Result: %s', 'cloudinary' ), $data );
				//do_action( '_cloudinary_cron_action', $result );
			}
			// translators: variable is process name.
			$result = sprintf( __( 'Cloudinary Cron Process End: %s', 'cloudinary' ), $name );
			//do_action( '_cloudinary_cron_action', $result );
			$this->unlock_schedule_process( $name );
		}
		$this->save_schedule();
	}

	/**
	 * Cron daemon watcher.
	 */
	public function daemon_watcher() {
		$tag = uniqid();
		//error_log( 'Cloudinary Cron Daemon Watcher start' );
		$start_time = $this->init_time;
		while ( $this->init_time < $start_time + self::DAEMON_WATCHER_INTERVAL ) {
			$this->init_time = time();
			$this->process_schedule();
		//	error_log( $tag );
			sleep( 1 );
		}
		//error_log( 'Cloudinary Cron Daemon Watcher end' );

	}

	/**
	 * Get the instance of the class.
	 *
	 * @return Cron
	 */
	public static function get_instance() {
		static $instance;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}
