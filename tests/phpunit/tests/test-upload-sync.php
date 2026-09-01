<?php
/**
 * Tests for Cloudinary\Sync\Upload_Sync.
 *
 * These cover Upload_Sync::is_matching_existing_asset(), the check that decides whether a
 * Cloudinary asset blocking an upload (existing: true) is safe to overwrite. It should only
 * be treated as this attachment's own orphaned upload -- not an unrelated asset that happens
 * to share the same derived public ID, e.g. WordPress reusing a filename across months (see
 * GitHub issue #1241), and not an unrelated attachment that happens to hold byte-identical
 * content and already owns that public ID.
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
	 * Mark an attachment as linked to a public ID, the way a completed upload_asset() call
	 * would via its trackable postmeta key -- what get_linked_attachments() looks up.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $public_id     The public ID.
	 *
	 * @return void
	 */
	protected function link_attachment_to_public_id( $attachment_id, $public_id ) {
		update_post_meta( $attachment_id, '_' . md5( $public_id ), true );
	}

	/**
	 * An existing asset whose byte size matches the local file is treated as this attachment's
	 * own orphaned upload, so it's safe to overwrite. No etag in the result falls back to the
	 * byte comparison alone.
	 *
	 * @return void
	 */
	public function test_matches_when_existing_asset_bytes_equal_the_local_file() {
		$result = array(
			'bytes'     => self::$attachment_bytes,
			'public_id' => 'canola',
		);

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
		$result = array(
			'bytes'     => self::$attachment_bytes + 1,
			'public_id' => 'canola',
		);

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
	 * Without a `public_id` field, there's no way to check who else might already be linked to
	 * it, so it must not be overwritten either.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_result_has_no_public_id_field() {
		$result = array( 'bytes' => self::$attachment_bytes );

		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * Without a local file to compare against, there's no basis for a match either.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_the_attachment_has_no_local_file() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );

		$result = array(
			'bytes'     => self::$attachment_bytes,
			'public_id' => 'canola',
		);

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
			'bytes'     => self::$attachment_bytes,
			'etag'      => md5_file( get_attached_file( self::$attachment_id ) ),
			'public_id' => 'canola',
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
			'bytes'     => self::$attachment_bytes,
			'etag'      => 'not-the-real-hash',
			'public_id' => 'canola',
		);

		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * Byte-identical content is not proof of ownership: if another attachment is already
	 * tracked as linked to this public ID, overwriting it would clobber that attachment's
	 * context and advance its version out from under it, even though the bytes line up.
	 *
	 * @return void
	 */
	public function test_does_not_match_when_another_attachment_already_owns_the_public_id() {
		$other_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->link_attachment_to_public_id( $other_id, 'shared-id' );

		$result = array(
			'bytes'     => self::$attachment_bytes,
			'etag'      => md5_file( get_attached_file( self::$attachment_id ) ),
			'public_id' => 'shared-id',
		);

		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
		);
	}

	/**
	 * This attachment being the one already tracked as linked to the public ID is the PR #1182
	 * scenario itself (a prior successful upload whose local public_id record was then lost) --
	 * still safe to overwrite.
	 *
	 * @return void
	 */
	public function test_matches_when_this_attachment_is_the_only_one_linked_to_the_public_id() {
		$this->link_attachment_to_public_id( self::$attachment_id, 'canola' );

		$result = array(
			'bytes'     => self::$attachment_bytes,
			'public_id' => 'canola',
		);

		$this->assertTrue(
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
			$this->get_upload_sync()->is_matching_existing_asset( $id, array( 'bytes' => $original_bytes, 'public_id' => 'canola-original' ) )
		);
		// The attached (scaled) file's size is not what was actually uploaded.
		$this->assertFalse(
			$this->get_upload_sync()->is_matching_existing_asset( $id, array( 'bytes' => $scaled_bytes, 'public_id' => 'canola-original' ) )
		);
	}

	/**
	 * A vip:// stream wrapper path is resolved without hashing it: doing so would pull the
	 * whole object over the network, and a failed read (false from md5_file()) would wrongly
	 * read as a content mismatch. Byte size alone is what's checked there.
	 *
	 * @return void
	 */
	public function test_matches_on_a_vip_path_by_bytes_alone_even_with_a_wrong_etag() {
		add_filter( 'cloudinary_use_original_image', '__return_false' );
		add_filter( 'get_attached_file', array( $this, 'filter_attached_file_to_vip_path' ), 10, 2 );

		try {
			$result = array(
				'bytes'     => self::$attachment_bytes,
				'etag'      => 'not-the-real-hash',
				'public_id' => 'canola',
			);

			$this->assertTrue(
				$this->get_upload_sync()->is_matching_existing_asset( self::$attachment_id, $result )
			);
		} finally {
			remove_filter( 'get_attached_file', array( $this, 'filter_attached_file_to_vip_path' ), 10 );
			remove_filter( 'cloudinary_use_original_image', '__return_false' );
		}
	}

	/**
	 * Rewrites an attached file path onto a fake vip:// stream wrapper, keeping filesize()
	 * resolvable (a plain file underneath) while making the path itself look VIP-hosted.
	 *
	 * @param string $file          The attached file path.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function filter_attached_file_to_vip_path( $file, $attachment_id ) {
		if ( (int) $attachment_id !== (int) self::$attachment_id ) {
			return $file;
		}
		if ( ! in_array( 'vip', stream_get_wrappers(), true ) ) {
			stream_wrapper_register( 'vip', 'Test_Upload_Sync_Vip_Stream_Wrapper' );
		}
		Test_Upload_Sync_Vip_Stream_Wrapper::$real_path = $file;

		return 'vip://canola.jpg';
	}
}

/**
 * A minimal stream wrapper standing in for VIP's, backed by a real local file.
 *
 * Only url_stat() is implemented: it's all is_matching_existing_asset() needs for
 * file_exists()/filesize() to resolve. md5_file() is deliberately never exercised through this
 * path in the test -- that's the whole point of the vip:// short-circuit being tested.
 */
class Test_Upload_Sync_Vip_Stream_Wrapper {

	/**
	 * The stream context resource, set automatically by PHP; must be declared or its creation is
	 * a deprecated dynamic property under PHPUnit's convertDeprecationsToExceptions.
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * The real, local file path this wrapper reads from.
	 *
	 * @var string
	 */
	public static $real_path;

	/**
	 * Stat the underlying real file, so file_exists()/filesize() resolve.
	 *
	 * @return array|false
	 */
	public function url_stat() {
		return @stat( self::$real_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
