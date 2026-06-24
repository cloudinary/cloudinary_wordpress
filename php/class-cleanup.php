<?php
/**
 * Shared cleanup routines for uninstall and destructive deactivation.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Media\Global_Transformations;

/**
 * Class Cleanup
 */
class Cleanup {

	/**
	 * Option key used to flag an in-progress cleanup operation.
	 *
	 * @var string
	 */
	const CLEANING_KEY = 'cloudinary_cleaning_up';

	/**
	 * The plugin instance.
	 *
	 * @var Plugin|null
	 */
	protected $plugin;

	/**
	 * The plugin settings object.
	 *
	 * @var Settings|null
	 */
	protected $settings;

	/**
	 * Cleanup constructor.
	 *
	 * @param Plugin|null   $plugin   Plugin instance.
	 * @param Settings|null $settings Settings instance.
	 */
	public function __construct( Plugin $plugin = null, Settings $settings = null ) {
		$this->plugin = $plugin;

		if ( null !== $settings ) {
			$this->settings = $settings;
		} elseif ( $plugin instanceof Plugin && $plugin->settings instanceof Settings ) {
			$this->settings = $plugin->settings;
		}
	}

	/**
	 * Run the full cleanup routine.
	 *
	 * @param Plugin|null   $plugin   Plugin instance.
	 * @param Settings|null $settings Settings instance.
	 */
	public static function run( Plugin $plugin = null, Settings $settings = null ) {
		$cleanup = new self( $plugin, $settings );
		$cleanup->cleanup_user_data();
		$cleanup->cleanup_post_meta();
		$cleanup->cleanup_term_meta();
		$cleanup->cleanup_post_type();
		$cleanup->drop_tables();
		$cleanup->cleanup_options();
		$cleanup->cleanup_cron();
		$cleanup->cleanup_legacy_cron();
	}

	/**
	 * Cleanup Cloudinary's user data related.
	 */
	protected function cleanup_user_data() {
		$user_meta_keys = array(
			'_cld_ui_state',
		);

		foreach ( $user_meta_keys as $key ) {
			// Inspired on https://developer.wordpress.org/reference/functions/delete_post_meta_by_key/.
			delete_metadata( 'user', null, $key, '', true );
		}
	}

	/**
	 * Cleanup Cloudinary's post meta related.
	 */
	protected function cleanup_post_meta() {
		$post_meta_keys = array_merge(
			Sync::META_KEYS,
			array(
				Global_Transformations::META_FEATURED_IMAGE_KEY,
				Global_Transformations::META_ORDER_KEY . '_terms',
				Delivery::META_CACHE_KEY,
			)
		);

		foreach ( $post_meta_keys as $key ) {
			delete_post_meta_by_key( $key );
		}
	}

	/**
	 * Cleanup Cloudinary's term meta related.
	 */
	protected function cleanup_term_meta() {
		$term_meta_keys = array(
			'cloudinary_transformations_image_freeform',
			'cloudinary_transformations_video_freeform',
		);

		foreach ( $term_meta_keys as $key ) {
			// Inspired on https://developer.wordpress.org/reference/functions/delete_post_meta_by_key/.
			delete_metadata( 'term', null, $key, '', true );
		}
	}

	/**
	 * Cleanup Cloudinary's post types related.
	 */
	protected function cleanup_post_type() {
		global $wpdb;

		$post_types = array(
			Assets::POST_TYPE_SLUG,
		);

		foreach ( $post_types as $type ) {
			$wpdb->delete( //phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->posts,
				array( 'post_type' => $type ),
				array( '%s' )
			);
		}
	}

	/**
	 * Drop Cloudinary's tables.
	 */
	protected function drop_tables() {
		global $wpdb;

		$tables = array(
			Utils::get_relationship_table(),
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table};" ); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Cleanup Cloudinary's options related.
	 */
	protected function cleanup_options() {
		if ( $this->settings instanceof Settings ) {
			$all = $this->settings->get_param( 'settings' );
			if ( is_array( $all ) ) {
				foreach ( $all as $slug => $setting ) {
					$this->settings->delete( $slug );
				}
			}
		}

		if ( $this->plugin instanceof Plugin ) {
			$sync = $this->plugin->get_component( 'sync' );
			if ( $sync instanceof Sync && ! empty( $sync->managers['queue'] ) ) {
				$queue       = $sync->managers['queue'];
				$all_threads = $queue->get_threads( 'all' );
				foreach ( $all_threads as $threads ) {
					foreach ( $threads as $thread ) {
						$queue->reset_thread_queue( $thread );
						delete_post_meta_by_key( $thread );
					}
				}
			}
		}

		$storage_keys = array();
		if ( $this->settings instanceof Settings ) {
			$storage_keys = (array) $this->settings->get_storage_keys();
		}

		$option_keys = array_merge(
			$storage_keys,
			array(
				'cloudinary_setup',
				'cloudinary_main_cache_page',
				'_cld_disable_http_upload',
				Report::REPORT_KEY,
				Media::GLOBAL_VIDEO_TRANSFORMATIONS,
				self::CLEANING_KEY,
			)
		);

		$option_keys = array_unique( array_filter( $option_keys ) );
		foreach ( $option_keys as $key ) {
			delete_option( $key );
			delete_transient( $key );
		}
	}

	/**
	 * Remove all cron-related tasks.
	 *
	 * @return void
	 */
	protected function cleanup_cron() {
		// Get the Cron instance.
		$cron_instance = Cron::get_instance();
		$schedule      = $cron_instance->get_schedule();

		// Unregister all registered schedules.
		if ( ! empty( $schedule ) ) {
			foreach ( array_keys( $schedule ) as $schedule_name ) {
				$cron_instance->unregister_schedule( $schedule_name );
			}
		}

		// Remove any lock files or objects used by the Locker instance.
		$cron_instance->cleanup_locker();

		// Delete the cron schedule option saved in database.
		delete_option( Cron::CRON_META_KEY );
	}

	/**
	 * Cleanup legacy cron jobs.
	 *
	 * @return void
	 */
	protected function cleanup_legacy_cron() {
		wp_clear_scheduled_hook( 'cloudinary_status' );

		$jobs = array(
			'cloudinary_cleanup_event',
			'cloudinary_rest_api_connectivity',
			'cloudinary_resume_queue',
			'cloudinary_resume_upgrade',
			'cloudinary_sync_items',
		);

		foreach ( $jobs as $job ) {
			$time = wp_next_scheduled( $job );
			if ( false !== $time ) {
				wp_clear_scheduled_hook( $job );
			}
		}
	}
}
