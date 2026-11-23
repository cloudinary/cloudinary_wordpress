<?php
/**
 * Tests for the one-time feature config snapshot event.
 *
 * @package Cloudinary
 */

use Cloudinary\Analytics;
use Cloudinary\Sync;
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
	 * Restores the plugin's install marker.
	 *
	 * @param string|false $db_version The value before the test.
	 *
	 * @return void
	 */
	protected function restore_db_version( $db_version ) {
		if ( false === $db_version ) {
			delete_option( Sync::META_KEYS['db_version'] );
		} else {
			update_option( Sync::META_KEYS['db_version'], $db_version );
		}
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
		$this->assertSame( 'upgrade', $snapshots[0]['trigger'] );
		$this->assertSame( 'first-cloud', get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
	}

	/**
	 * "Max images" is reported as the settings page shows it, derived from
	 * the other responsive fields, rather than as stored.
	 *
	 * @return void
	 */
	public function test_breakpoints_are_counted_like_the_settings_page() {
		$settings = get_plugin_instance()->settings;
		$original = array();
		$values   = array(
			'max_width'   => 2048,
			'min_width'   => 200,
			'pixel_step'  => 200,
			'breakpoints' => 42,
		);
		try {
			foreach ( $values as $key => $value ) {
				$original[ $key ] = $settings->get_value( $key );
				$settings->get_setting( $key )->set_value( $value );
			}

			$snapshot = $this->analytics->get_feature_snapshot();

			$settings->get_setting( 'pixel_step' )->set_value( 500 );
			$coarser = $this->analytics->get_breakpoint_count();
		} finally {
			// The settings live on the plugin singleton, shared by every test.
			foreach ( $original as $key => $value ) {
				$settings->get_setting( $key )->set_value( $value );
			}
		}

		// 2048, 1848, … 248.
		$this->assertSame( 10, $snapshot['breakpoints'] );
		// 2048, 1548, 1048, 548.
		$this->assertSame( 4, $coarser );
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

		$snapshots = $this->snapshots();
		$this->assertCount( 2, $snapshots );
		$this->assertSame( 'setup', $snapshots[1]['trigger'] );
		$this->assertSame( 'second-cloud', get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
	}

	/**
	 * A site seen unconnected before it is connected sends a setup snapshot.
	 *
	 * @return void
	 */
	public function test_connecting_after_an_unconnected_admin_load_is_a_setup() {
		get_plugin_instance()->settings->set_param( 'connected', false );
		$this->analytics->maybe_send_feature_snapshot();
		$this->connect_as( 'first-cloud' );
		$this->analytics->maybe_send_feature_snapshot();

		$snapshots = $this->snapshots();
		$this->assertCount( 1, $snapshots );
		$this->assertSame( 'setup', $snapshots[0]['trigger'] );
	}

	/**
	 * A fresh install's first snapshot is a setup, even when the site is
	 * connected from the start through the connection string constant.
	 *
	 * @return void
	 */
	public function test_fresh_install_is_a_setup() {
		$db_version = get_option( Sync::META_KEYS['db_version'] );
		delete_option( Sync::META_KEYS['db_version'] );
		Analytics::stash_activation();
		delete_transient( Analytics::PENDING_ACTIVATION );
		$this->restore_db_version( $db_version );

		$this->analytics->maybe_send_feature_snapshot();

		$snapshots = $this->snapshots();
		$this->assertCount( 1, $snapshots );
		$this->assertSame( 'setup', $snapshots[0]['trigger'] );
	}

	/**
	 * An upgrade or reactivation doesn't mark the site as not set up.
	 *
	 * @return void
	 */
	public function test_upgrade_activation_leaves_the_flag_unset() {
		$db_version = get_option( Sync::META_KEYS['db_version'] );
		update_option( Sync::META_KEYS['db_version'], '1.0.0' );
		Analytics::stash_activation();
		delete_transient( Analytics::PENDING_ACTIVATION );
		$this->restore_db_version( $db_version );

		$this->assertFalse( get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
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
		$this->assertSame( '', get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
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
		// A concurrent request has stored the flag, but this one's option
		// caches were loaded before that: get_option() sees no flag, and
		// add_option() skips its own existence check, so its upsert hits the
		// existing row. The upsert must change nothing and nothing be sent.
		add_option( Analytics::FEATURE_SNAPSHOT_FLAG, 'first-cloud', '', true );
		$alloptions = wp_load_alloptions();
		unset( $alloptions[ Analytics::FEATURE_SNAPSHOT_FLAG ] );
		wp_cache_set( 'alloptions', $alloptions, 'options' );
		wp_cache_set( 'notoptions', array( Analytics::FEATURE_SNAPSHOT_FLAG => true ), 'options' );

		$this->assertFalse( get_option( Analytics::FEATURE_SNAPSHOT_FLAG ) );
		$this->analytics->maybe_send_feature_snapshot();
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		$this->assertCount( 0, $this->snapshots() );
	}

	/**
	 * When another request already claimed a site marked as not set up, this
	 * one doesn't send.
	 *
	 * @return void
	 */
	public function test_only_the_request_that_updates_the_flag_sends() {
		// This request read the "not set up" marker, but a concurrent one has
		// since stored the cloud name: the update_option() gate must fail.
		add_option( Analytics::FEATURE_SNAPSHOT_FLAG, 'first-cloud', '', true );
		add_filter( 'pre_option_' . Analytics::FEATURE_SNAPSHOT_FLAG, '__return_empty_string' );
		$this->analytics->maybe_send_feature_snapshot();
		remove_filter( 'pre_option_' . Analytics::FEATURE_SNAPSHOT_FLAG, '__return_empty_string' );

		$this->assertCount( 0, $this->snapshots() );
	}
}
