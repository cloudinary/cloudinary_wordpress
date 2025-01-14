<?php
/**
 * Deactivate class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Media\Global_Transformations;
use Cloudinary\Sync\Storage;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Screen;

/**
 * Class Deactivation.
 *
 * Deals with feedback on plugin deactivation for future improvements.
 *
 * @package Cloudinary
 */
class Deactivation {

	/**
	 * The internal endpoint to capture the administrator feedback.
	 *
	 * @var string
	 */
	protected static $internal_endpoint = 'feedback';

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the plugin settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Cleaning data key.
	 *
	 * @var string
	 */
	const CLEANING_KEY = 'cloudinary_cleaning_up';

	/**
	 * Initiate the plugin deactivation.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'load_hooks' ) );
		add_action( 'current_screen', array( $this, 'maybe_load_hooks' ) );
		add_action( 'cloudinary_init_settings', array( $this, 'settings_init' ) );
	}

	/**
	 * Add hooks on init.
	 *
	 * These will always be loaded.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'cloudinary_api_rest_endpoints', array( $this, 'rest_endpoint' ) );
		add_action( 'cloudinary_cleanup_event', array( $this, 'cleanup' ) );
		add_filter( 'plugin_action_links_' . $this->plugin->plugin_file, array( $this, 'tag_deactivate' ) );
	}

	/**
	 * Conditional load hooks.
	 *
	 * Only available on plugins listing page.
	 *
	 * @return void
	 */
	public function maybe_load_hooks() {
		$current_screen = get_current_screen();

		if ( $current_screen instanceof WP_Screen && 'plugins' === $current_screen->base ) {
			add_action( 'admin_head-plugins.php', array( $this, 'markup' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	/**
	 * Init Settings.
	 */
	public function settings_init() {
		$this->settings = $this->plugin->settings;
	}

	/**
	 * Get the reasons for deactivation.
	 *
	 * @return array
	 */
	protected function get_reasons() {
		return array(
			array(
				'id'   => 'difficult_setup',
				'text' => __( 'Set up is too difficult', 'cloudinary' ),
			),
			array(
				'id'   => 'documentation',
				'text' => __( 'Lack of documentation', 'cloudinary' ),
			),
			array(
				'id'   => 'missing_features',
				'text' => __( 'Not the features I wanted', 'cloudinary' ),
			),
			array(
				'id'   => 'different_plugin',
				'text' => __( 'Found a better plugin', 'cloudinary' ),
			),
			array(
				'id'   => 'compatibility_reason',
				'text' => __( 'Incompatible with theme or plugin', 'cloudinary' ),
			),
			array(
				'id'   => 'other_reason',
				'text' => __( 'Other', 'cloudinary' ),
			),
		);
	}

	/**
	 * Get the action option for deactivation.
	 *
	 * @return array
	 */
	protected function get_options() {
		return array(
			array(
				'id'      => 'keep_data',
				'text'    => __( 'Keep plugin data as it is', 'cloudinary' ),
				'default' => true,
			),
			array(
				'id'   => 'uninstall',
				'text' => __( 'Remove all plugin data and settings', 'cloudinary' ),
			),
		);
	}

	/**
	 * Outputs the feedback form.
	 *
	 * @return void
	 */
	public function markup() {
		if ( $this->plugin->get_component( 'connect' )->is_connected() ) {
			$this->render_connected();
		} else {
			$this->render_not_connected();
		}
	}

	/**
	 * Renders connected accounts deactivation modal.
	 */
	public function render_connected() {
		$report_label = sprintf(
		// translators: The System Report link tag.
			__( 'Share a %s with Cloudinary to help improve the plugin.', 'cloudinary' ),
			sprintf(
			// translators: The System Report link and label.
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				'https://cloudinary.com/documentation/wordpress_integration#system_report',
				__( 'System Report', 'cloudinary' )
			)
		);

		$is_cloudinary_only = 'cld' === $this->plugin->settings->get_value( 'offload' );
		?>
		<div id="cloudinary-deactivation" class="cld-modal" data-cloudinary-only="<?php echo esc_attr( $is_cloudinary_only ); ?>">
			<div class="cloudinary-deactivation cld-modal-box">
				<?php if ( $is_cloudinary_only ) : ?>
					<div class="modal-header" id="modal-header">
						<p class="warning">
							<?php esc_html_e( 'Caution: Your storage setting is currently set to "Cloudinary only", disabling the plugin will result in broken links to media assets. Are you sure you want to continue?', 'cloudinary' ); ?>
						</p>
						<input type="checkbox" id="cld-bypass-cloudinary-only" name="bypass-cloudinary-only">
						<label for="cld-bypass-cloudinary-only">
							<?php esc_html_e( 'I understand and I want to proceed', 'cloudinary' ); ?>
						</label>
					</div>
				<?php endif; ?>
				<div class="modal-body" id="modal-body">
					<p>
						<?php esc_html_e( 'Before you deactivate the plugin, would you quickly give us your reason for doing so?', 'cloudinary' ); ?>
					</p>
					<ul class="reasons">
						<?php foreach ( $this->get_reasons() as $reason ) : ?>
							<li>
								<input type="radio" name="reason" value="<?php echo esc_attr( $reason['id'] ); ?>" id="reason-<?php echo esc_attr( $reason['id'] ); ?>"/>
								<label for="reason-<?php echo esc_attr( $reason['id'] ); ?>">
									<?php echo esc_html( $reason['text'] ); ?>
								</label>
								<label for="more-<?php echo esc_attr( $reason['id'] ); ?>" class="more">
									<?php esc_html_e( 'Additional details:', 'cloudinary' ); ?><br>
									<textarea name="reason-more" id="more-<?php echo esc_attr( $reason['id'] ); ?>" rows="5"></textarea>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p>
						<?php esc_html_e( 'Please, choose one option what we should do with the plugin’s settings', 'cloudinary' ); ?>
					</p>
					<ul class="data">
						<?php foreach ( $this->get_options() as $option ) : ?>
							<?php
							$checked = '';
							if ( ! empty( $option['default'] ) ) {
								$checked = 'checked';
							}
							?>
							<li>
								<input type="radio" name="option" <?php echo esc_attr( $checked ); ?> value="<?php echo esc_attr( $option['id'] ); ?>" id="option-<?php echo esc_attr( $option['id'] ); ?>"/>
								<label for="option-<?php echo esc_attr( $option['id'] ); ?>">
									<?php echo esc_html( $option['text'] ); ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p>
						<input type="checkbox" id="cld-report" name="report">
						<label for="cld-report">
							<?php echo wp_kses_post( $report_label ); ?>
						</label>
					</p>
					<p style="display:none">
						<input type="checkbox" id="cld-contact" name="contact">
						<label for="cld-contact">
							<?php esc_html_e( 'Allow Cloudinary to contact me regarding deactivation of the plugin.', 'cloudinary' ); ?>
						</label>
					</p>
				</div>
				<div class="modal-footer" id="modal-footer">
					<button class="button button-link deactivate-close" data-action="deactivate">
						<?php esc_html_e( 'Skip and deactivate', 'cloudinary' ); ?>
					</button>
					<button class="button cancel-close" data-action="cancel">
						<?php esc_html_e( 'Cancel', 'cloudinary' ); ?>
					</button>
					<button class="button button-primary" data-action="submit">
						<?php esc_html_e( 'Submit and deactivate', 'cloudinary' ); ?>
					</button>
					<span class="modal-processing hidden">
						<?php esc_html_e( 'Sending…', 'cloudinary' ); ?>
					</span>
					<div class="clear"></div>
				</div>
				<div id="modal-uninstall" class="modal-uninstall">
					<p><?php esc_html_e( 'Uninstall has been started and the plugin will automatically be deactivated once complete.', 'cloudinary' ); ?></p>
					<div class="modal-footer">
						<button class="button button-primary cancel-close" data-action="close">
							<?php esc_html_e( 'Close', 'cloudinary' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders not connected accounts deactivation modal.
	 */
	public function render_not_connected() {
		$user = wp_get_current_user();
		?>
		<div id="cloudinary-deactivation" class="cld-modal">
			<div class="cloudinary-deactivation cld-modal-box">
				<div class="modal-body" id="modal-body">
					<p>
						<?php esc_html_e( "We noticed you didn't connect your account. Maybe we can help?", 'cloudinary' ); ?><br>
						<?php esc_html_e( 'Place your email below and our support will get back to you as soon as possible.', 'cloudinary' ); ?>
					</p>
					<p>
						<label for="email"><?php esc_html_e( 'Your email address', 'cloudinary' ); ?></label><br>
						<input type="email" id="email" placeholder="<?php esc_attr_e( 'Your email address', 'cloudinary' ); ?>" value="<?php echo esc_attr( $user->user_email ); ?>">
					</p>
				</div>
				<div class="modal-footer" id="modal-footer">
					<button class="button cancel-close" data-action="cancel">
						<?php esc_html_e( 'Cancel', 'cloudinary' ); ?>
					</button>
					<button class="button button-secondary" data-action="deactivate">
						<?php esc_html_e( 'Deactivate', 'cloudinary' ); ?>
					</button>
					<button class="button button-primary" data-action="contact">
						<?php esc_html_e( 'Contact me', 'cloudinary' ); ?>
					</button>
					<span class="modal-processing hidden">
						<?php esc_html_e( 'Sending…', 'cloudinary' ); ?>
					</span>
					<div class="clear"></div>
				</div>
				<div id="modal-uninstall" class="modal-uninstall">
					<p><?php esc_html_e( 'Uninstall has been started and the plugin will automatically be deactivated once complete.', 'cloudinary' ); ?></p>
					<div class="modal-footer">
						<button class="button button-primary cancel-close">
							<?php esc_html_e( 'Close', 'cloudinary' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueues deactivation script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'cloudinary-deactivation', $this->plugin->dir_url . 'js/deactivate.js', array(), $this->plugin->version, true );
		wp_localize_script(
			'cloudinary-deactivation',
			'CLD_Deactivate',
			array(
				'endpoint' => Utils::rest_url( REST_API::BASE . '/' . self::$internal_endpoint ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Registers deactivation feedback endpoint.
	 *
	 * @param array $endpoints The registered endpoints.
	 *
	 * @return array
	 */
	public function rest_endpoint( $endpoints ) {
		$endpoints[ self::$internal_endpoint ] = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_callback' ),
			'args'                => array(),
			'permission_callback' => function () {
				return Utils::user_can( 'deactivate', 'activate_plugins' );
			},
		);

		return $endpoints;
	}

	/**
	 * Uploads the System Report to the Cloud.
	 *
	 * @return array|WP_Error
	 */
	public function upload_report() {
		require_once ABSPATH . '/wp-admin/includes/file.php';

		$report = $this->plugin->get_component( 'report' )->get_report_data();
		$temp   = get_temp_dir() . $report['filename'];
		file_put_contents( $temp, wp_json_encode( $report['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		$args = array(
			'file'          => $temp,
			'public_id'     => $report['filename'],
			'resource_type' => 'raw',
			'type'          => 'upload',
		);

		return $this->plugin->get_component( 'connect' )->api->upload( $temp, $args, array(), false );
	}

	/**
	 * Processes the feedback and dispatches it to Cloudinary services.
	 *
	 * @param WP_REST_Request $request The Rest Request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function rest_callback( WP_REST_Request $request ) {
		$reason  = $request->get_param( 'reason' );
		$more    = $request->get_param( 'more' );
		$report  = filter_var( $request->get_param( 'report' ), FILTER_VALIDATE_BOOLEAN );
		$contact = filter_var( $request->get_param( 'contact' ), FILTER_VALIDATE_BOOLEAN );
		$data    = $request->get_param( 'dataHandling' );

		if (
			! in_array(
				$reason,
				array_column( $this->get_reasons(), 'id' ),
				true
			)
		) {
			$reason = 'no_reason_provided';
		}

		$args = array(
			'reason'    => sanitize_text_field( $reason ),
			'free_text' => sanitize_textarea_field( $more ),
		);

		if ( $report ) {
			$report = $this->upload_report();

			if ( ! empty( $report['secure_url'] ) ) {
				$args['report'] = $report['secure_url'];
			}

			$args['contact'] = $contact;
		}

		if ( filter_var( $request->get_param( 'email' ), FILTER_VALIDATE_EMAIL ) ) {
			$args['reason'] = 'not_connected';
			$args['email']  = filter_var( $request->get_param( 'email' ), FILTER_SANITIZE_EMAIL );
		}

		$args['version'] = $this->plugin->version;

		$url = add_query_arg( array_filter( $args ), CLOUDINARY_ENDPOINTS_DEACTIVATION );

		$response = wp_remote_get( $url );

		if ( 'uninstall' === $data ) {
			$this->cleanup();
		}

		deactivate_plugins( $this->plugin->plugin_file );

		return rest_ensure_response(
			wp_remote_retrieve_response_code( $response )
		);
	}

	/**
	 * Add a deactivate class to the deactivate link to trigger a warning if storage is only on Cloudinary.
	 *
	 * @param array $actions The actions for the plugin.
	 *
	 * @return array
	 */
	public function tag_deactivate( $actions ) {
		if ( empty( $actions['deactivate'] ) ) {
			return $actions;
		}

		if ( 'cld' === $this->settings->get_value( 'offload' ) ) {
			$actions['deactivate'] = str_replace( '<a ', '<a class="cld-deactivate" ', $actions['deactivate'] );
		} else {
			$actions['deactivate'] = str_replace( '<a ', '<a class="cld-deactivate-link" ', $actions['deactivate'] );
		}

		if ( get_option( self::CLEANING_KEY ) ) {
			$actions['deactivate'] = __( 'Data clean up. The plugin will self deactivate once complete. ', 'cloudinary' );
		}

		return $actions;
	}

	/**
	 * Cleanup Cloudinary data.
	 */
	protected function cleanup() {
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'cloudinary_cleanup_event' );
		add_option( self::CLEANING_KEY, true, '', false );
		$this->cleanup_user_data();
		$this->cleanup_post_meta();
		$this->cleanup_term_meta();
		$this->cleanup_post_type();
		$this->drop_tables();
		$this->cleanup_options();
		$this->cleanup_cron();
		$this->cleanup_legacy_cron();

		// If we got this far, let's remove the cron event.
		wp_clear_scheduled_hook( 'cloudinary_cleanup_event' );
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
	 * Cleanup Cloudinary's options related.
	 */
	protected function cleanup_options() {
		$all = $this->settings->get_param( 'settings' );
		foreach ( $all as $slug => $setting ) {
			$this->settings->delete( $slug );
		}

		$queue       = $this->plugin->get_component( 'sync' )->managers['queue'];
		$all_threads = $queue->get_threads( 'all' );
		foreach ( $all_threads as $threads ) {
			foreach ( $threads as $thread ) {
				$queue->reset_thread_queue( $thread );
				delete_post_meta_by_key( $thread );
			}
		}

		$option_keys = array_merge(
			$this->settings->get_storage_keys(),
			array(
				'cloudinary_setup',
				'cloudinary_main_cache_page',
				'_cld_disable_http_upload',
				Report::REPORT_KEY,
				Media::GLOBAL_VIDEO_TRANSFORMATIONS,
				self::CLEANING_KEY,
			)
		);

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
