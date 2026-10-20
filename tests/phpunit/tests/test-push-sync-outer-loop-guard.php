<?php
/**
 * Tests for the outer loop guard in Cloudinary\Sync\Push_Sync::process_assets().
 *
 * process_assets() loops over whatever sync type get_sync_type() hands back and runs it. If the
 * same type comes back a second time in that same pass, its state isn't advancing, so the loop
 * breaks. Before this fix that break left the attachment unsynced, unqueued and un-errored --
 * build_queue() would just pick it straight back up on the next run, repeating the same stuck
 * request forever (GitHub issue #1301).
 *
 * A single repeat can be a transient hiccup rather than a genuinely stuck attachment, so the fix
 * doesn't flag a sync error the first time: it counts consecutive passes that ended this way
 * (persisted in postmeta, across requests) and only errors once that count reaches a limit,
 * clearing it again the moment a pass completes without repeating. These cover both ends of that:
 * early repeats that still self-heal, and a sync error once the limit is actually reached -- at
 * which point build_queue()'s own `_cld_error NOT EXISTS` check excludes the attachment.
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
	 * A single repeated pass is not enough to flag a permanent error: the loop still breaks (so
	 * the request doesn't hang), but the attachment is left to be picked up again, the same as
	 * before this fix, since one repeat alone doesn't prove it's genuinely stuck.
	 *
	 * @return void
	 */
	public function test_a_single_repeat_does_not_flag_a_sync_error() {
		$this->register_never_resolving_sync_type();

		$push = \Cloudinary\get_plugin_instance()->get_component( 'sync' )->managers['push'];
		$stat = $push->process_assets( self::$attachment_id );

		$this->assertEmpty( get_post_meta( self::$attachment_id, Sync::META_KEYS['sync_error'], true ) );
		$this->assertSame( '1', get_post_meta( self::$attachment_id, Sync::META_KEYS['loop_count'], true ) );
		$this->assertCount(
			1,
			$stat[ self::$attachment_id ],
			'The loop should have broken after the type repeated once, not kept running.'
		);
	}

	/**
	 * Once the same repeat has happened on enough consecutive passes (the default is 3, matching
	 * the filter's default), it's no longer a plausible one-off: flag the sync error, naming the
	 * type that wouldn't advance.
	 *
	 * @return void
	 */
	public function test_flags_a_sync_error_once_the_strike_limit_is_reached() {
		$this->register_never_resolving_sync_type();
		$push = \Cloudinary\get_plugin_instance()->get_component( 'sync' )->managers['push'];

		$push->process_assets( self::$attachment_id );
		$push->process_assets( self::$attachment_id );
		$this->assertEmpty(
			get_post_meta( self::$attachment_id, Sync::META_KEYS['sync_error'], true ),
			'Two strikes should not have flagged it yet.'
		);

		$push->process_assets( self::$attachment_id );

		$error = get_post_meta( self::$attachment_id, Sync::META_KEYS['sync_error'], true );
		$this->assertNotEmpty( $error, 'The third strike should flag a sync error.' );
		$this->assertStringContainsString( 'test_stuck_type', $error );
		$this->assertEmpty(
			get_post_meta( self::$attachment_id, Sync::META_KEYS['loop_count'], true ),
			'The strike count should be cleared once it has done its job.'
		);
	}

	/**
	 * A pass that completes without repeating -- the attachment recovering on its own after an
	 * earlier, transient repeat -- must clear the strike count, not leave it to accumulate with
	 * unrelated future repeats.
	 *
	 * @return void
	 */
	public function test_a_successful_pass_resets_the_strike_count() {
		$sync = $this->register_never_resolving_sync_type();
		$push = $sync->managers['push'];

		$push->process_assets( self::$attachment_id );
		$this->assertSame( '1', get_post_meta( self::$attachment_id, Sync::META_KEYS['loop_count'], true ) );

		// Make the type resolve this time, instead of repeating.
		$sync->register_sync_type(
			'test_stuck_type',
			array(
				'asset_state' => 0,
				'generate'    => '__return_true',
				'validate'    => '__return_true',
				'priority'    => -1,
				'sync'        => function ( $attachment_id ) use ( $sync ) {
					$sync->set_signature_item( $attachment_id, 'test_stuck_type' );
				},
				'realtime'    => false,
			)
		);
		$sync->setup_sync_types();

		$push->process_assets( self::$attachment_id );

		$this->assertEmpty( get_post_meta( self::$attachment_id, Sync::META_KEYS['loop_count'], true ) );
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
		$push = $sync->managers['push'];
		$push->process_assets( self::$attachment_id );
		$push->process_assets( self::$attachment_id );
		$push->process_assets( self::$attachment_id );

		$queue = $sync->managers['queue'];
		$queue->build_queue();

		$this->assertSame(
			0,
			$queue->get_last_built_total(),
			'The flagged attachment should not be re-queued.'
		);
	}
}
