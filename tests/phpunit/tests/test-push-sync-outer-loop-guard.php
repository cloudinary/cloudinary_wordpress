<?php
/**
 * Tests for the outer loop guard in Cloudinary\Sync\Push_Sync::process_assets().
 *
 * process_assets() loops over whatever sync type get_sync_type() hands back and runs it. If the
 * same type comes back a second time in that same pass, its state isn't advancing, so the loop
 * breaks. Before this fix that break left the attachment unsynced, unqueued and un-errored --
 * build_queue() would just pick it straight back up on the next run, repeating the same stuck
 * request forever (GitHub issue #1301). These confirm the break now also flags a sync error, so
 * build_queue()'s own `_cld_error NOT EXISTS` check excludes it afterwards.
 *
 * @package Cloudinary
 */

use Cloudinary\Connect;
use Cloudinary\Sync;

/**
 * Covers the loop-prevention branch in Push_Sync::process_assets().
 */
class Test_Push_Sync_Outer_Loop_Guard extends WP_UnitTestCase {

	/**
	 * A real, deliverable attachment to run process_assets() against.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * @param WP_UnitTest_Factory $factory The test factory.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$attachment_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * register_sync_type() writes directly into the Sync component singleton, which outlives any
	 * one test, and nothing in the plugin ever needs to remove a registered type -- so the lowest-
	 * priority 'test_stuck_type' registered below would otherwise leak into every other test in
	 * the same PHPUnit run (and always win, being the lowest priority), including Test_Sync_Loop_
	 * Guard's. Reflection is the only way at sync_base_struct from the outside to undo it.
	 *
	 * @return void
	 */
	public function tear_down() {
		$sync       = \Cloudinary\get_plugin_instance()->get_component( 'sync' );
		$reflection = new ReflectionProperty( $sync, 'sync_base_struct' );
		$reflection->setAccessible( true );
		$struct = $reflection->getValue( $sync );
		unset( $struct['test_stuck_type'] );
		$reflection->setValue( $sync, $struct );
		$sync->setup_sync_types();

		parent::tear_down();
	}

	/**
	 * Registers a sync type that never resolves -- its 'sync' callback runs but never saves a
	 * matching signature -- so get_sync_type() keeps handing it back. Given the lowest possible
	 * priority, it is always the type process_assets() picks, regardless of every real type's own
	 * state, isolating the outer loop's own behaviour from the rest of the sync system (which is
	 * covered separately in Test_Sync_Loop_Guard).
	 *
	 * This also does the manager wiring that normally only happens once the site is "connected"
	 * -- which the PHPUnit bootstrap never is -- so that process_assets() and build_queue() have
	 * what they read directly ($managers['media'] etc.). It fakes that with a syntactically valid
	 * but make-believe `cloudinary_url`, with the usage-stats transient pre-seeded so
	 * Connect::setup()'s usage_stats() finds it non-empty and skips the one live API call it would
	 * otherwise make; everything downstream (Media's, Sync's and Delivery's own setup()) then runs
	 * as it would on a real connected site, without reaching the network itself.
	 *
	 * @return \Cloudinary\Sync
	 */
	protected function register_never_resolving_sync_type() {
		$plugin  = \Cloudinary\get_plugin_instance();
		$connect = $plugin->get_component( 'connect' );

		$plugin->settings->set_param( 'connected', true );
		set_transient( Connect::META_KEYS['usage'], array( 'placeholder' => true ), HOUR_IN_SECONDS );
		$plugin->settings->set_value( 'cloudinary_url', 'cloudinary://123456789012345:abcdefghijklmnopqrstuvwxyzABCDEF@test-cloud' );
		$connect->setup();

		do_action( 'cloudinary_connected', $plugin ); // Delivery::init() etc.

		$plugin->get_component( 'media' )->setup();
		$plugin->get_component( 'sync' )->setup();
		$plugin->get_component( 'delivery' )->setup();

		$sync = $plugin->get_component( 'sync' );
		$sync->register_assets();

		$sync->register_sync_type(
			'test_stuck_type',
			array(
				'asset_state' => 0,
				'generate'    => '__return_true',
				'validate'    => '__return_true',
				'priority'    => -1,
				'sync'        => '__return_null', // Runs, but never saves a signature.
				'realtime'    => false,
			)
		);
		$sync->setup_sync_types();

		return $sync;
	}

	/**
	 * @return void
	 */
	public function test_flags_a_sync_error_when_a_type_repeats() {
		$this->register_never_resolving_sync_type();

		$push = \Cloudinary\get_plugin_instance()->get_component( 'sync' )->managers['push'];
		$stat = $push->process_assets( self::$attachment_id );

		$error = get_post_meta( self::$attachment_id, Sync::META_KEYS['sync_error'], true );
		$this->assertNotEmpty( $error, 'Loop prevention should flag a sync error.' );

		$this->assertCount(
			1,
			$stat[ self::$attachment_id ],
			'The loop should have broken after the type repeated once, not kept running.'
		);
	}

	/**
	 * Once errored, build_queue()'s own meta_query (`_cld_error` NOT EXISTS) is what keeps a
	 * stuck attachment from being queued again -- confirm the flag this guard sets actually
	 * satisfies that, end to end.
	 *
	 * @return void
	 */
	public function test_flagged_attachment_is_excluded_from_the_next_queue_build() {
		$this->register_never_resolving_sync_type();

		$sync = \Cloudinary\get_plugin_instance()->get_component( 'sync' );
		$sync->managers['push']->process_assets( self::$attachment_id );

		$queue = $sync->managers['queue'];
		$queue->build_queue();

		$this->assertSame(
			0,
			$queue->get_last_built_total(),
			'The flagged attachment should not be re-queued.'
		);
	}
}
