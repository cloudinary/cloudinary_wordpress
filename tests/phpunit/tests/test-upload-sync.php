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
	 * Build a fully wired Upload_Sync instance.
	 *
	 * is_matching_existing_asset() reads the upload file path through $media, so setup() needs
	 * to have run to wire it -- the real Media component, already initialised by the plugin
	 * bootstrap, is reused rather than stubbed.
	 *
	 * @return Upload_Sync
	 */
	protected function get_upload_sync() {
		$upload_sync = new Upload_Sync( \Cloudinary\get_plugin_instance() );
		$upload_sync->setup();

		return $upload_sync;
	}

	/**
	 * An existing asset whose byte size matches the local file is treated as this attachment's
	 * own orphaned upload, so it's safe to overwrite. No etag in the result falls back to the
	 * byte comparison alone.
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

	/**
	 * Matching bytes plus a matching etag (the MD5 of the stored asset) confirms the content
	 * itself, not just its size.
	 *
	 * @return void
	 */
	public function test_matches_when_bytes_and_etag_both_match() {
		$result = array(
			'bytes' => self::$attachment_bytes,
			'etag'  => md5_file( get_attached_file( self::$attachment_id ) ),
		);

		$this->assertTrue(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * A byte size that coincidentally matches an unrelated file must not be enough on its own
	 * once an etag is available to rule it out.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_bytes_match_but_etag_differs() {
		$result = array(
			'bytes' => self::$attachment_bytes,
			'etag'  => 'not-the-real-hash',
		);

		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * Cloudinary uploads the unscaled original for a "-scaled" image (the file WordPress
	 * attaches for images over big_image_size_threshold is a downsized copy, not what was
	 * actually sent), so the check must compare against that original, not the attached file.
	 *
	 * @return void
	 */
	public function test_matches_using_the_unscaled_original_for_a_scaled_image() {
		$id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$original_file = get_attached_file( $id );
		$scaled_file   = dirname( $original_file ) . '/canola-scaled.jpg';

		// Stand in for the "-scaled" file WordPress would attach: same starting bytes, padded
		// so its size provably differs from the original left alongside it.
		copy( $original_file, $scaled_file );
		file_put_contents( $scaled_file, file_get_contents( $scaled_file ) . 'padding' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		update_attached_file( $id, $scaled_file );

		$metadata                   = wp_get_attachment_metadata( $id );
		$metadata['original_image'] = wp_basename( $original_file );
		wp_update_attachment_metadata( $id, $metadata );

		$original_bytes = filesize( $original_file );
		$scaled_bytes   = filesize( $scaled_file );

		$this->assertNotSame( $original_bytes, $scaled_bytes, 'Fixture files must differ in size for this test to be meaningful.' );

		// Cloudinary was sent the original -- its bytes must be what's compared against.
		$this->assertTrue(
			$this->get_upload_sync()->is_matching_existing_asset( $id, array( 'bytes' => $original_bytes ) )
		);
		// The attached (scaled) file's size is not what was actually uploaded.
		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( $id, array( 'bytes' => $scaled_bytes ) )
		);
	}
}
