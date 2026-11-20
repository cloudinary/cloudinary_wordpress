<?php
/**
 * Tests for the one-time feature config snapshot event.
 *
 * @package Cloudinary
 */

use Cloudinary\Analytics;
use function Cloudinary\get_plugin_instance;

/**
 * Covers Analytics::maybe_send_feature_snapshot().
 */
class Test_Analytics_Feature_Snapshot extends WP_UnitTestCase {

	/**
	 * The analytics component.
	 *
	 * @var Analytics
	 */
	protected $analytics;

	/**
	 * Analytics events sent during the test.
	 *
	 * @var array[]
	 */
	protected $sent = array();

	/**
	 * The plugin's `connected` param before the test.
	 *
	 * @var mixed
	 */
	protected $original_connected;

	/**
	 * The connected cloud name before the test.
	 *
	 * @var string|null
	 */
	protected $original_cloud_name;

	/**
	 * Fakes a connected account and captures outgoing analytics events.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$plugin          = get_plugin_instance();
		$this->analytics = $plugin->get_component( 'analytics' );

		$this->original_connected  = $plugin->settings->get_param( 'connected', null );
		$this->original_cloud_name = $plugin->get_component( 'connect' )->get_cloud_name();
		$this->connect_as( 'first-cloud' );

		delete_option( Analytics::FEATURE_SNAPSHOT_FLAG );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->sent = array();
		add_filter( 'pre_http_request', array( $this, 'capture_event' ), 1, 3 );
	}

	/**
	 * Restores the connection state.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'capture_event' ), 1 );

		$plugin = get_plugin_instance();
		$plugin->settings->set_param( 'connected', $this->original_connected );
		$plugin->get_component( 'connect' )->set_credentials( array( 'cloud_name' => $this->original_cloud_name ) );

		parent::tear_down();
	}

	/**
	 * Captures analytics events instead of sending them.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $args    The request arguments.
	 * @param string      $url     The request URL.
	 *
	 * @return false|array
	 */
	public function capture_event( $preempt, $args, $url ) {
		if ( CLOUDINARY_ENDPOINTS_ANALYTICS !== $url ) {
			return $preempt;
		}
		$this->sent[] = json_decode( $args['body'], true );

		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Marks the plugin as connected to the given cloud.
	 *
	 * @param string $cloud_name The cloud name.
	 *
	 * @return void
	 */
	protected function connect_as( $cloud_name ) {
		$plugin = get_plugin_instance();
		$plugin->settings->set_param( 'connected', true );
		$plugin->get_component( 'connect' )->set_credentials( array( 'cloud_name' => $cloud_name ) );
	}

	/**
	 * Returns the captured snapshot events.
	 *
	 * @return array[]
	 */
	protected function snapshots() {
		return array_values(
			array_filter(
				$this->sent,
				static function ( $event ) {
					return 'feature_config_snapshot' === $event['event_name'];
				}
			)
		);
	}

	/**
	 * The snapshot is sent once per account, with the feature values.
	 *
	 * @return void
	 */
	public function test_sends_once_per_account() {
		$this->analytics->maybe_send_feature_snapshot();
		$this->analytics->maybe_send_feature_snapshot();

		$snapshots = $this->snapshots();
		$this->assertCount( 1, $snapshots );
		$this->assertSame( 'features', $snapshots[0]['event_category'] );
		$this->assertIsBool( $snapshots[0]['use_lazy_load'] );
		$this->assertArrayNotHasKey( 'cloudinary_url', $snapshots[0] );
		$this->assertSame( 'first-cloud', get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
	}

	/**
	 * Switching to another account sends a new snapshot.
	 *
	 * @return void
	 */
	public function test_sends_again_after_an_account_switch() {
		$this->analytics->maybe_send_feature_snapshot();
		$this->connect_as( 'second-cloud' );
		$this->analytics->maybe_send_feature_snapshot();

		$this->assertCount( 2, $this->snapshots() );
		$this->assertSame( 'second-cloud', get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
	}

	/**
	 * Nothing is sent before the site is connected.
	 *
	 * @return void
	 */
	public function test_not_sent_while_disconnected() {
		get_plugin_instance()->settings->set_param( 'connected', false );
		$this->analytics->maybe_send_feature_snapshot();

		$this->assertCount( 0, $this->snapshots() );
		$this->assertFalse( get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
	}

	/**
	 * With analytics disabled nothing is sent, and the snapshot is still sent
	 * once they are enabled again.
	 *
	 * @return void
	 */
	public function test_deferred_while_analytics_are_disabled() {
		add_filter( 'cloudinary_analytics_enabled', '__return_false' );
		$this->analytics->maybe_send_feature_snapshot();
		remove_filter( 'cloudinary_analytics_enabled', '__return_false' );

		$this->assertCount( 0, $this->snapshots() );

		$this->analytics->maybe_send_feature_snapshot();
		$this->assertCount( 1, $this->snapshots() );
	}

	/**
	 * Admin loads by non-administrators don't send the snapshot.
	 *
	 * @return void
	 */
	public function test_not_sent_for_non_administrators() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->analytics->maybe_send_feature_snapshot();

		$this->assertCount( 0, $this->snapshots() );
	}

	/**
	 * Admin-ajax requests don't send the snapshot.
	 *
	 * @return void
	 */
	public function test_not_sent_during_ajax_requests() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->analytics->maybe_send_feature_snapshot();
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertCount( 0, $this->snapshots() );
	}

	/**
	 * When another request already added the flag, this one doesn't send.
	 *
	 * @return void
	 */
	public function test_only_the_request_that_adds_the_flag_sends() {
		// Simulates a concurrent request having added the flag after this one
		// read it: the add_option() gate must fail and nothing be sent.
		add_filter( 'pre_option_' . Analytics::FEATURE_SNAPSHOT_FLAG, '__return_false' );
		add_option( Analytics::FEATURE_SNAPSHOT_FLAG, 'first-cloud' );
		$this->analytics->maybe_send_feature_snapshot();
		remove_filter( 'pre_option_' . Analytics::FEATURE_SNAPSHOT_FLAG, '__return_false' );

		$this->assertCount( 0, $this->snapshots() );
	}
}
