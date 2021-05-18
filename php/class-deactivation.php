<?php
/**
 * Deactivate class for Cloudinary.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use WP_REST_Server;
use WP_REST_Request;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Response;

/**
 * Class Deactivation.
 *
 * Deals with feedback on plugin deactivation for future improvements.
 *
 * @package Cloudinary
 */
class Deactivation {
	/**
	 * The Cloudinary endpoint to submit the feedback.
	 *
	 * @var string
	 */
	protected static $cld_endpoint = 'https://analytics-api.cloudinary.com/wp_deactivate_reason';

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
	 * Initiate the plugin deactivation.
	 *
	 * @param Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'load_hooks' ) );
		add_action( 'current_screen', array( $this, 'maybe_load_hooks' ) );
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

		if ( ! empty( $current_screen->base ) && 'plugins' === $current_screen->base ) {
			add_thickbox();

			add_action( 'admin_head-plugins.php', array( $this, 'markup' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	/**
	 * Get the reasons for deactivation.
	 *
	 * @return array
	 */
	protected function get_reasons() {
		return array(
			array(
				'id'   => 'dont_understand_value',
				'text' => __( 'I don’t get any value from the plugin.', 'cloudinary' ),
			),
			array(
				'id'   => 'dont_know_how',
				'text' => __( 'I don’t know how to use the plugin.', 'cloudinary' ),
			),
			array(
				'id'   => 'temporary',
				'text' => __( 'This is temporary. I’ll use the plugin again soon.', 'cloudinary' ),
			),
			array(
				'id'   => 'technical_problems',
				'text' => __( 'I encountered technical issues with the plugin.', 'cloudinary' ),
				'more' => true,
			),
			array(
				'id'   => 'other_plugins',
				'text' => __( 'I use another plugin that works better for me.', 'cloudinary' ),
				'more' => true,
			),
			array(
				'id'   => 'other_reason',
				'text' => __( 'Other.', 'cloudinary' ),
				'more' => true,
			),
		);
	}

	/**
	 * Outputs the feedback form.
	 *
	 * @return void
	 */
	public function markup() {
		$report_label = sprintf(
			// translators: The System Report link tag.
			__( 'Share a %s with Cloudinary to help improve the plugin.', 'cloudinary' ),
			sprintf(
				// translators: The System Report link and label.
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				'https://cloudinary.com/documentation/wordpress_integration#system_report',
				'System Report'
			)
		);

		?>
<a href="#TB_inline?&width=520&height=390&inlineId=cloudinary-deactivation" class="thickbox" id="cld-deactivation-link" title="<?php esc_attr_e( 'Tell us how to improve!', 'cloudinary' ); ?>" style="display: none;"><?php esc_html_e( 'Deactivation feedback', 'cloudinary' ); ?>></a>
<div id="cloudinary-deactivation" style="display: none;">
	<div class="cloudinary-deactivation">
		<div class="modal-body">
			<p>
				<?php esc_html_e( 'Please select a reason for deactivating so we can make our plugin better:', 'cloudinary' ); ?>
			</p>
			<ul>
			<?php foreach ( $this->get_reasons() as $reason ) : ?>
				<li>
					<input type="radio" name="reason" value="<?php echo esc_attr( $reason['id'] ); ?>" id="reason-<?php echo esc_attr( $reason['id'] ); ?>"/>
					<label for="reason-<?php echo esc_attr( $reason['id'] ); ?>">
						<?php echo esc_html( $reason['text'] ); ?>
					</label>
					<?php if ( ! empty( $reason['more'] ) ) : ?>
						<label for="more-<?php echo esc_attr( $reason['id'] ); ?>" class="more">
							<?php esc_html_e( 'Additional details:', 'cloudinary' ); ?><br>
							<textarea name="reason-more" id="more-<?php echo esc_attr( $reason['id'] ); ?>" cols="50" rows="5"></textarea>
						</label>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
		<div class="modal-footer">
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
			<button class="button button-primary" disabled="disabled">
				<?php esc_html_e( 'Submit and deactivate', 'cloudinary' ); ?>
			</button>
			<button class="button button-link">
				<?php esc_html_e( 'Skip and deactivate', 'cloudinary' ); ?>
			</button>
			<span class="modal-processing hidden">
				<?php esc_html_e( 'Sending…', 'cloudinary' ); ?>
			</span>
			<div class="clear"></div>
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
				'endpoint' => rest_url( REST_API::BASE . '/' . self::$internal_endpoint ),
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
			'permission_callback' => function() {
				return current_user_can( 'activate_plugins' );
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

		if ( empty( $reason ) ) {
			return rest_ensure_response( 200 );
		}

		if (
			! in_array(
				$reason,
				array_column( $this->get_reasons(), 'id' ),
				true
			)
		) {
			return rest_ensure_response( 418 );
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

		$url = add_query_arg( $args, self::$cld_endpoint );

		$response = wp_safe_remote_get( $url );

		return rest_ensure_response(
			wp_remote_retrieve_response_code( $response )
		);
	}
}
