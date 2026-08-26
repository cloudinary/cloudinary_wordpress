<?php
/**
 * Tests for Cloudinary\Sync\Upload_Sync.
 *
 * These cover Upload_Sync::is_matching_existing_asset(), the check that decides whether a
 * Cloudinary asset blocking an upload (existing: true) is safe to overwrite. It should only
 * be treated as this attachment's own orphaned upload -- not an unrelated asset that happens
 * to share the same derived public ID, e.g. WordPress reusing a filename across months (see
 * GitHub issue #1241).
 *
 * The rest of upload_asset() talks to the Cloudinary API over HTTP and is covered by the
 * Playwright suite in tests/e2e.
 *
 * @package Cloudinary
 */

use Cloudinary\Sync\Upload_Sync;

/**
 * Covers the existing-asset ownership check in Upload_Sync.
 */
class Test_Upload_Sync extends WP_UnitTestCase {

	/**
	 * A real attachment backed by a file on disk, so filesize() has something to read.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * The on-disk size, in bytes, of the attachment's file.
	 *
	 * @var int
	 */
	protected static $attachment_bytes;

	/**
	 * Create a real attachment, backed by a real file, once for all tests.
	 *
	 * @param WP_UnitTest_Factory $factory The test factory.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$file = DIR_TESTDATA . '/images/canola.jpg';

		self::$attachment_id    = $factory->attachment->create_upload_object( $file );
		self::$attachment_bytes = filesize( get_attached_file( self::$attachment_id ) );
	}

	/**
	 * Build an Upload_Sync instance.
	 *
	 * is_matching_existing_asset() only calls core get_attached_file()/filesize(), never touches
	 * $media/$sync/$connect, so the component doesn't need setup() to have wired those up.
	 *
	 * @return Upload_Sync
	 */
	protected function get_upload_sync() {
		return new Upload_Sync( \Cloudinary\get_plugin_instance() );
	}

	/**
	 * An existing asset whose byte size matches the local file is treated as this attachment's
	 * own orphaned upload, so it's safe to overwrite.
	 *
	 * @return void
	 */
	public function test_matches_when_existing_asset_bytes_equal_the_local_file() {
		$result = array( 'bytes' => self::$attachment_bytes );

		$this->assertTrue(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * An existing asset with a different byte size is a different, unrelated asset -- the
	 * collision this attachment must not overwrite.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_existing_asset_bytes_differ() {
		$result = array( 'bytes' => self::$attachment_bytes + 1 );

		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * Without a `bytes` field to compare against, there's no basis to treat the collision as
	 * this attachment's own asset, so it must not be overwritten.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_result_has_no_bytes_field() {
		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, array() )
		);
	}

	/**
	 * Without a local file to compare against, there's no basis for a match either.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_the_attachment_has_no_local_file() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );

		$result = array( 'bytes' => self::$attachment_bytes );

		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( $post_id, $result )
		);
	}
}
