<?php
/**
 * Tests for Cloudinary\Media::update_post_meta() / delete_post_meta().
 *
 * These read the whole `_cloudinary` meta blob, change one key, and write the whole blob back.
 * Two of these running concurrently for the same attachment -- e.g. a bulk-sync thread and an
 * autosync thread both reaching it -- can race: whichever finishes writing last overwrites the
 * other's change outright, with nothing logged. That silently stalled signature is what GitHub
 * issue #1301 reported, so these confirm the compare-and-swap retry actually merges a concurrent
 * write instead of clobbering it.
 *
 * @package Cloudinary
 */

use Cloudinary\Media;

/**
 * Covers the concurrency handling in Media::update_post_meta() and delete_post_meta().
 */
class Test_Media_Concurrent_Meta extends WP_UnitTestCase {

	/**
	 * A real attachment to write `_cloudinary` meta against.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * Create a real attachment once for all tests.
	 *
	 * @param WP_UnitTest_Factory $factory The test factory.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$attachment_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * Start each test with no `_cloudinary` meta, so "the row doesn't exist yet" and "the row
	 * already exists" are both exercised from a clean, known state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_post_meta( self::$attachment_id, '_cloudinary' );
	}

	/**
	 * @return Media
	 */
	protected function get_media() {
		return \Cloudinary\get_plugin_instance()->get_component( 'media' );
	}

	/**
	 * Simulates a second process's write landing in the DB in between this call's read and its
	 * own write, by performing it from inside a filter that fires at exactly that point.
	 *
	 * @param string $hook          The filter to inject the concurrent write at.
	 * @param string $concurrent_key The meta sub-key the "other process" writes.
	 * @param mixed  $concurrent_val The value the "other process" writes.
	 *
	 * @return void
	 */
	protected function inject_concurrent_write( $hook, $concurrent_key, $concurrent_val ) {
		$attachment_id = self::$attachment_id;
		$did_run       = false;

		add_filter(
			$hook,
			function ( $check, $object_id, $meta_key ) use ( $attachment_id, $concurrent_key, $concurrent_val, &$did_run ) {
				if ( ! $did_run && $object_id === $attachment_id && '_cloudinary' === $meta_key ) {
					$did_run = true;
					wp_cache_delete( $attachment_id, 'post_meta' );
					$meta                     = get_post_meta( $attachment_id, '_cloudinary', true );
					$meta                     = is_array( $meta ) ? $meta : array();
					$meta[ $concurrent_key ]  = $concurrent_val;
					if ( metadata_exists( 'post', $attachment_id, '_cloudinary' ) ) {
						update_post_meta( $attachment_id, '_cloudinary', $meta );
					} else {
						add_post_meta( $attachment_id, '_cloudinary', $meta, true );
					}
				}

				return $check;
			},
			10,
			3
		);
	}

	/**
	 * Writing a brand new key, with no concurrent writer, behaves exactly as before.
	 *
	 * @return void
	 */
	public function test_writes_normally_with_no_concurrent_writer() {
		$media = $this->get_media();

		$this->assertNotFalse( $media->update_post_meta( self::$attachment_id, 'public_id', 'solo-value' ) );
		$this->assertSame( 'solo-value', $media->get_post_meta( self::$attachment_id, 'public_id', true ) );
	}

	/**
	 * The race this issue reports: the `_cloudinary` row doesn't exist yet, and a concurrent
	 * process creates it (with a different key) in the gap between this call's read and its
	 * write. Before the fix, the write landed via a plain update_post_meta() with no compare-and-
	 * swap, so it was free to overwrite the concurrent row outright. It should instead retry and
	 * merge, so both keys survive.
	 *
	 * @return void
	 */
	public function test_merges_a_concurrent_write_on_first_creation() {
		$this->inject_concurrent_write( 'add_post_metadata', 'version', 'from-concurrent-writer' );

		$media = $this->get_media();
		$media->update_post_meta( self::$attachment_id, 'public_id', 'from-this-writer' );

		$meta = $media->get_post_meta( self::$attachment_id );
		$this->assertSame( 'from-this-writer', $meta['public_id'] );
		$this->assertSame( 'from-concurrent-writer', $meta['version'] );
	}

	/**
	 * Same race, but against an attachment that's already partway synced, so the `_cloudinary`
	 * row already exists and the write goes through update_post_meta()'s compare-and-swap
	 * ($prev_value) path instead of add_post_meta()'s.
	 *
	 * @return void
	 */
	public function test_merges_a_concurrent_write_on_an_existing_row() {
		$media = $this->get_media();
		$media->update_post_meta( self::$attachment_id, 'folder_sync', true );

		$this->inject_concurrent_write( 'update_post_metadata', 'remote_size', '1024' );

		$media->update_post_meta( self::$attachment_id, 'public_id', 'from-this-writer' );

		$meta = $media->get_post_meta( self::$attachment_id );
		$this->assertSame( 'from-this-writer', $meta['public_id'] );
		$this->assertSame( '1024', $meta['remote_size'] );
		$this->assertTrue( $meta['folder_sync'] );
	}

	/**
	 * delete_post_meta() reads, removes the key and writes the same shared blob back, so it has
	 * the identical race -- confirm it also merges rather than clobbering.
	 *
	 * @return void
	 */
	public function test_delete_merges_a_concurrent_write() {
		$media = $this->get_media();
		$media->update_post_meta( self::$attachment_id, 'to_delete', 'gone-soon' );

		$this->inject_concurrent_write( 'update_post_metadata', 'remote_size', '2048' );

		$media->delete_post_meta( self::$attachment_id, 'to_delete' );

		$meta = $media->get_post_meta( self::$attachment_id );
		$this->assertArrayNotHasKey( 'to_delete', $meta );
		$this->assertSame( '2048', $meta['remote_size'] );
	}

	/**
	 * Writing the same value that a concurrent writer just set is a no-op, not a conflict --
	 * confirms the early-exit path doesn't needlessly retry or error.
	 *
	 * @return void
	 */
	public function test_no_retry_needed_when_value_already_matches() {
		$media = $this->get_media();
		$media->update_post_meta( self::$attachment_id, 'public_id', 'already-set' );

		$this->assertTrue( $media->update_post_meta( self::$attachment_id, 'public_id', 'already-set' ) );
	}
}
