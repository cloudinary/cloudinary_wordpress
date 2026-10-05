<?php
/**
 * Tests for Cloudinary\Media::filter_attachment_image_src().
 *
 * This covers the wp_get_attachment_image_src filter Cloudinary hooks so it keeps the last
 * word on that specific, commonly-targeted core hook (see WPP-1183): it must bow out cheaply
 * when a URL is already a Cloudinary URL or there is no image at all, only correct an attachment
 * that actually has a real image representation (an image, or a preview-capable format like PDF/
 * PSD -- anything else showing here with icon=true is a generic mime icon, not something to
 * replace with the raw asset URL), correct a local URL when the attachment is actually
 * deliverable and synced regardless of the caller's $icon argument (WP_Media_List_Table's
 * list-mode thumbnail column passes icon=true for every attachment, real images included -- see
 * wp-admin/includes/class-wp-media-list-table.php), and otherwise leave the result alone.
 *
 * cloudinary_id() and cloudinary_url() are stubbed out (Test_Attachment_Image_Src_Media below)
 * rather than driven through the real sync/signature pipeline, matching the approach in
 * test-image-conversion.php: no Cloudinary account is needed, and full sync is covered by the
 * Playwright suite in tests/e2e.
 *
 * @package Cloudinary
 */

/**
 * Covers Media::filter_attachment_image_src().
 */
class Test_Attachment_Image_Src extends WP_UnitTestCase {

	/**
	 * A real attachment backed by a file on disk, with real metadata (width/height), so
	 * Delivery::is_deliverable() passes.
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
	 * Build a Test_Attachment_Image_Src_Media instance wired to the real, already-booted plugin.
	 *
	 * @return Test_Attachment_Image_Src_Media
	 */
	protected function get_media() {
		$media           = new Test_Attachment_Image_Src_Media( \Cloudinary\get_plugin_instance() );
		$media->base_url = 'https://res.cloudinary.com/test-cloud';

		return $media;
	}

	/**
	 * A URL that already lives on the configured Cloudinary domain is returned untouched -- the
	 * cheap bow-out path the ticket calls for, taken before any sync/delivery check runs.
	 *
	 * @return void
	 */
	public function test_already_cloudinary_url_is_returned_unchanged() {
		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'sample.jpg';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/should-not-be-used.jpg';
		$image                      = array( 'https://res.cloudinary.com/test-cloud/image/upload/v1/sample.jpg', 100, 100, true );

		$result = $media->filter_attachment_image_src( $image, self::$attachment_id, 'thumbnail', false );

		$this->assertSame( $image, $result );
	}

	/**
	 * $icon is the caller's permission to fall back to a mime-type icon if image_downsize()
	 * found nothing -- WordPress passes the original argument through to the filter unchanged,
	 * regardless of whether $image is actually a real image or a fallback icon (see
	 * wp_get_attachment_image_src() in wp-includes/media.php). WP_Media_List_Table's list-mode
	 * thumbnail column calls wp_get_attachment_image() with icon=true for every attachment, real
	 * synced images included, so icon=true must not by itself block correction.
	 *
	 * @return void
	 */
	public function test_icon_true_does_not_block_correcting_a_real_synced_image() {
		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'sample.jpg';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/sample.jpg';
		$image                      = array( 'http://example.org/wp-content/uploads/canola.jpg', 100, 100, true );

		$result = $media->filter_attachment_image_src( $image, self::$attachment_id, 'thumbnail', true );

		$this->assertSame( $media->stub_cloudinary_url, $result[0] );
	}

	/**
	 * A genuine mime-icon fallback (no cloudinary_id to correct it to, e.g. an unsynced or
	 * non-deliverable attachment) is still left alone -- not because of the $icon flag, but
	 * because there is nothing to swap it for.
	 *
	 * @return void
	 */
	public function test_icon_fallback_is_unchanged_when_nothing_to_correct_it_to() {
		$bare_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		// wp_attachment_is()/wp_attachment_is_image() return false unconditionally without a
		// real _wp_attached_file, regardless of post_mime_type -- see wp-includes/post.php.
		update_post_meta( $bare_id, '_wp_attached_file', 'bare.jpg' );

		$media = $this->get_media();
		$image = array( 'http://example.org/wp-includes/images/media/default.png', 48, 64, false );

		$result = $media->filter_attachment_image_src( $image, $bare_id, 'thumbnail', true );

		$this->assertSame( $image, $result );
	}

	/**
	 * A synced non-image, non-preview attachment (audio, zip, docx, ...) requested with icon=true
	 * must keep its generic mime icon. Delivery::is_deliverable() treats every non-image,
	 * non-video attachment as deliverable, so without a type check here, this would otherwise get
	 * "corrected" to the raw asset's Cloudinary URL -- breaking the resulting <img> tag (caught in
	 * PR review: WP_Media_List_Table's list view requests icon=true for every attachment type).
	 *
	 * @return void
	 */
	public function test_synced_non_image_attachment_with_icon_is_returned_unchanged() {
		$zip_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/zip',
			)
		);
		update_post_meta( $zip_id, '_wp_attached_file', 'archive.zip' );

		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'archive.zip';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/raw/upload/archive.zip';
		$image                      = array( 'http://example.org/wp-includes/images/media/archive.png', 48, 64, false );

		$result = $media->filter_attachment_image_src( $image, $zip_id, 'thumbnail', true );

		$this->assertSame( $image, $result );
	}

	/**
	 * A synced preview-capable format (PDF/PSD) is the deliberate carve-out: it has a real image
	 * preview to correct to, so icon=true must not block it either, mirroring the image case.
	 *
	 * @return void
	 */
	public function test_synced_preview_only_attachment_with_icon_is_corrected() {
		$pdf_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			)
		);
		update_post_meta( $pdf_id, '_wp_attached_file', 'document.pdf' );

		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'document.pdf';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/document.jpg';
		$image                      = array( 'http://example.org/wp-includes/images/media/document.png', 48, 64, false );

		$result = $media->filter_attachment_image_src( $image, $pdf_id, 'thumbnail', true );

		$this->assertSame( $media->stub_cloudinary_url, $result[0] );
	}

	/**
	 * A false (no image) result has nothing to correct and must pass through unchanged.
	 *
	 * @return void
	 */
	public function test_false_image_is_returned_unchanged() {
		$media = $this->get_media();

		$this->assertFalse( $media->filter_attachment_image_src( false, self::$attachment_id, 'thumbnail', false ) );
	}

	/**
	 * A video attachment is left untouched -- caught by the image/preview-only type check before
	 * ever reaching the deliverable/sync checks (a video has no image representation to correct
	 * to here; its own URL is handled by attachment_url() instead).
	 *
	 * @return void
	 */
	public function test_video_attachment_is_returned_unchanged() {
		$video_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'video/mp4',
			)
		);
		update_post_meta( $video_id, '_wp_attached_file', 'video.mp4' );

		$media = $this->get_media();
		$image = array( 'http://example.org/wp-content/uploads/video.mp4', 640, 360, true );

		$result = $media->filter_attachment_image_src( $image, $video_id, 'thumbnail', false );

		$this->assertSame( $image, $result );
	}

	/**
	 * An image attachment with no generated metadata is not deliverable (no width/height to
	 * hand Cloudinary), so the local URL passes through unchanged.
	 *
	 * @return void
	 */
	public function test_non_deliverable_attachment_is_returned_unchanged() {
		$bare_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_post_meta( $bare_id, '_wp_attached_file', 'bare.jpg' );

		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'sample.jpg';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/should-not-be-used.jpg';
		$image                      = array( 'http://example.org/wp-content/uploads/bare.jpg', 100, 100, true );

		$result = $media->filter_attachment_image_src( $image, $bare_id, 'thumbnail', false );

		$this->assertSame( $image, $result );
	}

	/**
	 * A deliverable attachment that has no Cloudinary ID (not synced) is left untouched -- there
	 * is nothing to correct it to.
	 *
	 * @return void
	 */
	public function test_no_cloudinary_id_returns_image_unchanged() {
		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = false;
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/should-not-be-used.jpg';
		$image                      = array( 'http://example.org/wp-content/uploads/canola.jpg', 100, 100, true );

		$result = $media->filter_attachment_image_src( $image, self::$attachment_id, 'thumbnail', false );

		$this->assertSame( $image, $result );
	}

	/**
	 * The actual fix: a local URL on a deliverable, synced attachment is corrected to the
	 * Cloudinary URL, with width/height/is-intermediate preserved from the original array.
	 *
	 * @return void
	 */
	public function test_local_url_is_corrected_to_cloudinary_url() {
		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'sample.jpg';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/sample.jpg';
		$image                      = array( 'http://example.org/wp-content/uploads/canola.jpg', 100, 100, true );

		$result = $media->filter_attachment_image_src( $image, self::$attachment_id, 'thumbnail', false );

		$this->assertSame( $media->stub_cloudinary_url, $result[0] );
		$this->assertSame( 100, $result[1] );
		$this->assertSame( 100, $result[2] );
		$this->assertTrue( $result[3] );
	}

	/**
	 * Regression test for a PR review finding: Utils::is_saving_metadata() must reflect a meta
	 * write happening right now, not one that happened earlier in the request. It used to read
	 * did_action(), which is cumulative for the whole request -- so an unrelated post/term/user
	 * meta write anywhere earlier (a view counter, a session plugin, anything) would permanently
	 * block correction for the rest of the page once these filters started running on the front
	 * end. Writing unrelated meta here, before calling the filter, catches a regression back to
	 * that behaviour.
	 *
	 * @return void
	 */
	public function test_earlier_unrelated_meta_write_does_not_block_correction() {
		$other_post_id = self::factory()->post->create();
		update_post_meta( $other_post_id, 'unrelated_counter', 1 );

		$media                      = $this->get_media();
		$media->stub_cloudinary_id  = 'sample.jpg';
		$media->stub_cloudinary_url = 'https://res.cloudinary.com/test-cloud/image/upload/sample.jpg';
		$image                      = array( 'http://example.org/wp-content/uploads/canola.jpg', 100, 100, true );

		$result = $media->filter_attachment_image_src( $image, self::$attachment_id, 'thumbnail', false );

		$this->assertSame( $media->stub_cloudinary_url, $result[0] );
	}
}

/**
 * A Media subclass with cloudinary_id()/cloudinary_url() stubbed out, so
 * filter_attachment_image_src() can be exercised without driving the real sync/signature
 * pipeline (which needs a live Cloudinary account -- see tests/e2e for that coverage).
 */
class Test_Attachment_Image_Src_Media extends \Cloudinary\Media {

	/**
	 * Canned cloudinary_id() return value.
	 *
	 * @var string|false
	 */
	public $stub_cloudinary_id = false;

	/**
	 * Canned cloudinary_url() return value.
	 *
	 * @var string|false
	 */
	public $stub_cloudinary_url = false;

	/**
	 * Stubbed to avoid the real sync/signature pipeline.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string|false
	 */
	public function cloudinary_id( $attachment_id ) {
		return $this->stub_cloudinary_id;
	}

	/**
	 * Stubbed to avoid the real sync/signature pipeline.
	 *
	 * @param int          $attachment_id             The attachment ID.
	 * @param array|string $size                      The requested size.
	 * @param array        $transformations           Transformations to apply.
	 * @param string|null  $cloudinary_id             A forced Cloudinary ID.
	 * @param bool         $overwrite_transformations Whether to overwrite transformations.
	 *
	 * @return string|false
	 */
	public function cloudinary_url( $attachment_id, $size = array(), $transformations = array(), $cloudinary_id = null, $overwrite_transformations = false ) {
		return $this->stub_cloudinary_url;
	}
}
