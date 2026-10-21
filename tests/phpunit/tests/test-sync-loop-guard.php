<?php
/**
 * Tests for the recursion guard in Cloudinary\Sync::validate_sync_type().
 *
 * get_sync_type() and validate_sync_type() recurse into each other: a sync type is run (or its
 * signature saved), then get_sync_type() is re-checked for what's next. If that save doesn't
 * stick -- e.g. a filter blocks the `_cloudinary` meta write, or a concurrent request overwrites
 * it first, see GitHub issue #1301 -- get_sync_type() keeps handing back the same type and this
 * recurses without end, pinning a PHP worker and hammering the DB with the same queue request
 * over and over. These confirm it instead bails out to a sync error once a type clearly isn't
 * resolving.
 *
 * The guard counts how deep the current, still-unresolved recursion chain is, not how many times
 * validate_sync_type() has been entered overall, since a type that resolves immediately (no
 * recursion) can legitimately be checked many times across a long request without ever being
 * stuck -- confirmed below alongside the stuck case.
 *
 * @package Cloudinary
 */

use Cloudinary\Connect;
use Cloudinary\Sync;

/**
 * Covers the retry-limit circuit breaker in Sync::validate_sync_type().
 */
class Test_Sync_Loop_Guard extends WP_UnitTestCase {

	/**
	 * A real, deliverable attachment -- 'delivery' is the first sync type checked for any
	 * attachment, is realtime, and (unlike 'file') does no network calls, so it is exactly the
	 * type the live incident got stuck recursing on.
	 *
	 * A fresh one per test, not a shared static, for isolation: validate_sync_type()'s counter is
	 * a function-static keyed by attachment ID, so a fresh ID per test rules out one test's calls
	 * being counted against another's, on top of the counter's own per-chain reset.
	 *
	 * @var int
	 */
	protected $attachment_id;

	/**
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * register_sync_type() (used below for 'test_pending_type') writes directly into the Sync
	 * component singleton, which outlives any one test -- see the matching note in
	 * Test_Push_Sync_Outer_Loop_Guard, whose own custom type this would otherwise collide with if
	 * both ran in the same PHPUnit process.
	 *
	 * @return void
	 */
	public function tear_down() {
		$sync       = \Cloudinary\get_plugin_instance()->get_component( 'sync' );
		$reflection = new ReflectionProperty( $sync, 'sync_base_struct' );
		$reflection->setAccessible( true );
		$struct = $reflection->getValue( $sync );
		unset( $struct['test_pending_type'] );
		$reflection->setValue( $sync, $struct );
		$sync->setup_sync_types();

		parent::tear_down();
	}

	/**
	 * Sync-type registration (incl. 'delivery') and every component's manager wiring --
	 * Sync::$managers['media'], Media::$sync, Delivery::$sync, and so on -- only happen once the
	 * site is "connected". The PHPUnit bootstrap never connects to a real Cloudinary account, so
	 * this fakes one: a syntactically valid but make-believe `cloudinary_url`, with the usage-
	 * stats transient pre-seeded so Connect::setup()'s usage_stats() finds it non-empty and skips
	 * the one live API call it would otherwise make. Everything downstream of that (Media's,
	 * Sync's and Delivery's own setup()) then runs exactly as it would on a real connected site,
	 * without reaching the network itself.
	 *
	 * @return Sync
	 */
	protected function get_sync() {
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
		$sync->register_assets(); // Populates sync_base_struct / sync_types (incl. 'delivery').

		return $sync;
	}

	/**
	 * Blocks every write to `_cloudinary` meta for this attachment, the same way the issue's own
	 * reproduction filter does, so no sync type's signature can ever be saved.
	 *
	 * @return void
	 */
	protected function block_cloudinary_meta_writes() {
		$attachment_id = $this->attachment_id;

		add_filter(
			'update_post_metadata',
			function ( $check, $object_id, $meta_key ) use ( $attachment_id ) {
				if ( $object_id === $attachment_id && '_cloudinary' === $meta_key ) {
					return true; // Pretend the write succeeded; don't actually let it through.
				}

				return $check;
			},
			10,
			3
		);
		add_filter(
			'add_post_metadata',
			function ( $check, $object_id, $meta_key ) use ( $attachment_id ) {
				if ( $object_id === $attachment_id && '_cloudinary' === $meta_key ) {
					return true;
				}

				return $check;
			},
			10,
			3
		);
	}

	/**
	 * With a low retry limit and the write blocked, get_sync_type() must return (not recurse
	 * forever) and the attachment must come out flagged with a sync error naming the stuck type.
	 *
	 * @return void
	 */
	public function test_stops_and_flags_a_sync_error_once_the_retry_limit_is_hit() {
		$this->block_cloudinary_meta_writes();
		add_filter(
			'cloudinary_sync_type_retry_limit',
			function () {
				return 5;
			}
		);

		$sync = $this->get_sync();
		$type = $sync->get_sync_type( $this->attachment_id, false );

		$this->assertNull( $type, 'get_sync_type() should give up rather than recurse forever.' );

		$error = get_post_meta( $this->attachment_id, Sync::META_KEYS['sync_error'], true );
		$this->assertNotEmpty( $error );
		$this->assertStringContainsString( 'delivery', $error );
	}

	/**
	 * Once flagged, get_sync_type()'s own existing short-circuit for errored attachments must
	 * take over on the next call, rather than this re-entering (and re-counting against) the
	 * retry logic.
	 *
	 * @return void
	 */
	public function test_errored_attachment_short_circuits_on_the_next_call() {
		$this->block_cloudinary_meta_writes();
		add_filter( 'cloudinary_sync_type_retry_limit', fn() => 5 );

		$sync = $this->get_sync();
		$sync->get_sync_type( $this->attachment_id, false );

		$this->assertNull( $sync->get_sync_type( $this->attachment_id, false ) );
	}

	/**
	 * A healthy attachment -- no write ever blocked -- must resolve 'delivery' in well under the
	 * retry limit, so the breaker can never be mistaken for normal operation.
	 *
	 * @return void
	 */
	public function test_does_not_trip_during_normal_resolution() {
		add_filter( 'cloudinary_sync_type_retry_limit', fn() => 5 );

		$sync = $this->get_sync();
		$sync->get_sync_type( $this->attachment_id, false );

		$this->assertEmpty( get_post_meta( $this->attachment_id, Sync::META_KEYS['sync_error'], true ) );
	}

	/**
	 * A type that's simply pending -- not stuck, just not synced yet, and not realtime so nothing
	 * recurses -- can legitimately be checked many times in one long request (status rendered for
	 * several references to the same attachment, the process_assets() loop itself, and so on).
	 * None of those calls recurse into one another, so the guard must never mistake their total
	 * count for one unresolved chain, however many of them there are.
	 *
	 * @return void
	 */
	public function test_many_independent_calls_for_a_stable_pending_type_do_not_trip_the_guard() {
		$sync = $this->get_sync();
		add_filter( 'cloudinary_sync_type_retry_limit', fn() => 5 );

		// A type that never resolves but also never recurses: validate() is true and it isn't
		// realtime, so validate_sync_type() returns it directly every time, same as a type simply
		// waiting its turn. Lowest priority so it's always what get_sync_type() picks.
		$sync->register_sync_type(
			'test_pending_type',
			array(
				'asset_state' => 0,
				'generate'    => '__return_true',
				'validate'    => '__return_true',
				'priority'    => -1,
				'sync'        => '__return_null',
				'realtime'    => false,
			)
		);
		$sync->setup_sync_types();

		for ( $i = 0; $i < 20; $i++ ) {
			$type = $sync->get_sync_type( $this->attachment_id, false );
			$this->assertSame( 'test_pending_type', $type );
		}

		$this->assertEmpty(
			get_post_meta( $this->attachment_id, Sync::META_KEYS['sync_error'], true ),
			'20 independent, non-recursing calls (4x the 5-call limit) must not trip the guard.'
		);
	}
}
