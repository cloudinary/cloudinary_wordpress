<?php
/**
 * Cron class for the Cloudinary plugin.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Cron\Lock_Object;
use Cloudinary\Cron\Lock_File;
use Cloudinary\Settings\Setting;
use WP_REST_Request;

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
	 * Holds the cron settings.
	 *
	 * @var Setting
	 */
	protected $setting;

	/**
	 * Holds teh Locker instance.
	 *
	 * @var Lock_File|Lock_Object
	 */
	protected $locker;

	/**
	 * Holds the meta key for the cron schedule.
	 */
	const CRON_META_KEY = 'cloudinary_cron_schedule';

	/**
	 * Holds the slug for the cron system screen.
	 */
	const CRON_SLUG = 'cron_system';

	/**
	 * Holds the cron watcher interval in seconds.
	 *
	 * @var int
	 */
	public static $daemon_watcher_interval = 10;

	/**
	 * Cron constructor.
	 */
	public function __construct() {
		if ( wp_using_ext_object_cache() ) {
			$this->locker = new Lock_Object();
		} else {
			$this->locker = new Lock_File();
		}

		// Ensure it's safe.
		if ( self::$daemon_watcher_interval > ini_get( 'max_execution_time' ) ) {
			self::$daemon_watcher_interval = ini_get( 'max_execution_time' );
		}

		$this->init_time = time();

		$this->init();
		add_filter( 'cloudinary_admin_pages', array( $this, 'add_settings' ) );
	}

	/**
	 * Add cron management screen
	 *
	 * @param array $settings The array of settings before init.
	 *
	 * @return array
	 */
	public function add_settings( $settings ) {

		$enabled                     = apply_filters( 'cloudinary_feature_cron_manager', true );
		$settings[ self::CRON_SLUG ] = array(
			'page_title'          => __( 'Cron System', 'cloudinary' ),
			'requires_connection' => true,
			'section'             => self::CRON_SLUG,
			'slug'                => self::CRON_SLUG,
			'sidebar'             => true,
			'enabled'             => $enabled,
			'settings'            => array(
				array(
					'type'        => 'panel',
					'title'       => __( 'Cron Control', 'cloudinary' ),
					'option_name' => self::CRON_SLUG,
					array(
						'type'    => 'on_off',
						'title'   => __( 'Enable Cron', 'cloudinary' ),
						'default' => 'off',
						'slug'    => 'enable_cron',
					),
					array(
						'type'    => 'cron',
						'slug'    => 'tasks',
						'default' => array(
							'update_asset_paths' => 'off',
							'rest_api'           => 'off',
							'check_status'       => 'off',
						),
						'cron'    => $this,
					),
				),

			),
		);

		return $settings;
	}

	/**
	 * Initialize the cron.
	 */
	public function init() {
		$this->load_schedule();
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
		add_action( 'cloudinary_init_settings', array( $this, 'init_cron' ) );
	}

	/**
	 * Setup the cron.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function init_cron( $plugin ) {
		$this->setting = $plugin->settings->get_setting( self::CRON_SLUG );
		$tasks         = $plugin->settings->get_value( self::CRON_SLUG . '.tasks' );
		$status        = 'off';
		foreach ( $tasks as $task ) {
			if ( 'on' === $task ) {
				$status = 'on';
				break;
			}
		}
		if ( 'off' === $status ) {
			if ( $this->locker->has_lock_file() ) {
				$this->locker->delete_lock_file();
			}
		} else {
			add_action( 'shutdown', array( $this, 'start_daemon' ) );
		}
	}

	/**
	 * Remove Shutdown hook.
	 */
	public function remove_shutdown() {
		remove_action( 'shutdown', array( $this, 'start_daemon' ) );
	}

	/**
	 * Register REST endpoints.
	 *
	 * @param array $endpoints The endpoints to add to.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {
		$endpoints['cron_watch']   = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'daemon_watcher' ),
			'permission_callback' => array( 'Cloudinary\REST_API', 'validate_request' ),
		);
		$endpoints['cron_process'] = array(
			'method'              => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'run_queue' ),
			'permission_callback' => array( 'Cloudinary\REST_API', 'validate_request' ),
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
			$process    = $this->processes[ $name ];
			$next_run   = $this->init_time + $process['offset'];
			$now_second = (int) gmdate( 's', $next_run );
			if ( $now_second > 0 ) {
				$next_run = strtotime( '+' . ( 60 - $now_second ) . ' seconds' );
			}
			$this->schedule[ $name ] = array(
				'last_run' => null,
				'next_run' => $next_run,
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
	 * Get thew schedule.
	 *
	 * @return array
	 */
	public function get_schedule() {
		$schedule = array();
		foreach ( $this->schedule as $name => $item ) {
			if ( ! $item['active'] ) {
				continue;
			}
			$schedule[ $name ] = $item;
		}

		return $schedule;
	}

	/**
	 * Process the cron schedule.
	 */
	public function process_schedule() {
		$queue = array();
		$tasks = $this->setting->get_value( 'tasks' );

		foreach ( $this->schedule as $name => $schedule ) {

			// Remove schedules that are not active.
			if ( ! $schedule['active'] ) {
				$this->unregister_schedule( $name );
				continue;
			}

			// Default is on. So if it has not been set, default applies.
			$slug = sanitize_title( $name );
			if ( $this->locker->has_lock_file( $name ) || isset( $tasks[ $slug ] ) && 'off' === $tasks[ $slug ] ) {
				continue;
			}

			// Queue the process if it's time to run.
			if ( $this->init_time >= $schedule['next_run'] ) {
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
	 *
	 * @param array $queue The queue to push.
	 */
	protected function push_queue( $queue ) {

		$this->save_schedule();
		$instance = get_plugin_instance();
		$rest     = $instance->get_component( 'api' );
		$time     = time();
		$this->locker->set_lock_file( $time, wp_json_encode( $queue ) );
		$rest->background_request( 'cron_process', array( 'time' => $time ), 'GET' );
	}

	/**
	 * Start cron daemon.
	 */
	public function start_daemon() {
		// Can have parallel runs at times.
		if ( $this->locker->has_lock_file() ) {
			return;
		}

		$time = $this->locker->set_lock_file();
		$this->save_schedule();
		if ( $time ) {
			$instance = get_plugin_instance();
			$rest     = $instance->get_component( 'api' );
			$rest->background_request( 'cron_watch', array( 'time' => $time ), 'GET' );
		}
	}

	/**
	 * Lock a cron schedule process.
	 *
	 * @param string $name Name of the process to lock.
	 */
	protected function lock_schedule_process( $name ) {
		$this->locker->set_lock_file( sanitize_title( $name ), $this->init_time + 60 );
	}

	/**
	 * Unlock the cron schedule process.
	 *
	 * @param string $name Name of the process to unlock.
	 */
	protected function unlock_schedule_process( $name ) {
		$this->locker->delete_lock_file( sanitize_title( $name ) );
	}

	/**
	 * Run the queue.
	 *
	 * @param WP_REST_Request $request Queue to run.
	 */
	public function run_queue( WP_REST_Request $request ) {
		$this->remove_shutdown(); // Ensure we don't restart multi threads.

		$this->init_time = (int) $request->get_param( 'time' );
		$queue           = $this->locker->get_lock_file( $this->init_time );
		register_shutdown_function( array( $this, 'cleanup_failed_cron' ) );

		if ( ! empty( $queue ) ) {
			foreach ( $queue as $name ) {
				if ( ! isset( $this->processes[ $name ] ) ) {
					continue;
				}
				$process = $this->processes[ $name ];
				$data    = $process['callback']( $name );
				// @todo: Log data result.

				$this->unlock_schedule_process( $name );
			}
		}

		$this->locker->delete_lock_file( $this->init_time );
	}

	/**
	 * Cron daemon watcher.
	 *
	 * @param \WP_REST_Request $request The initial request.
	 */
	public function daemon_watcher( \WP_REST_Request $request ) {
		$start_time = $request->get_param( 'time' );

		// Validate this owns the lockfile.
		$lock_time = $this->locker->get_lock_file();
		if ( $start_time !== $lock_time ) {
			$this->remove_shutdown();
			exit;
		}
		$end_time = $this->init_time + self::$daemon_watcher_interval;
		while ( $this->init_time < $end_time ) {
			if ( ! $this->locker->has_lock_file() ) {
				return;
			}
			$this->init_time = time();
			$this->process_schedule();
			sleep( 1 );
		}
		$this->locker->delete_lock_file();
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

	/**
	 * Handle failed process.
	 */
	public function cleanup_failed_cron() {
		$this->locker->delete_lock_file( $this->init_time );
	}

	/**
	 * Allow cleanup of the locker.
	 *
	 * @return void
	 */
	public function cleanup_locker() {
		$this->locker->delete_lock_file();
	}
}
