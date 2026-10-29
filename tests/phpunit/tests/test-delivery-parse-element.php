<?php
/**
 * Tests for Cloudinary\Delivery::parse_element().
 *
 * Since WPP-1183, image_downsize() and wp_get_attachment_image_src() return Cloudinary URLs on
 * the front end too, so tags built by theme code (wp_get_attachment_image() in a template, a
 * shortcode, a widget) reach Delivery with a Cloudinary src instead of a local one. Delivery
 * tracks such a src back to its attachment through the public ID; this covers that the track-back
 * resolves to the same full URL the known items are keyed by, rather than the relation's
 * content-relative sized_url, which never matched and left the tag unprocessed (no lazy loading,
 * responsive breakpoints or SEO attributes).
 *
 * The known items are seeded directly rather than driven through prepare_delivery() and the
 * relationship table, so no Cloudinary account is needed.
 *
 * @package Cloudinary
 */

/**
 * Covers Delivery::parse_element().
 */
class Test_Delivery_Parse_Element extends WP_UnitTestCase {

	/**
	 * The Cloudinary base URL used for the test media component.
	 *
	 * @var string
	 */
	const BASE_URL = 'https://res.cloudinary.com/test-cloud';

	/**
	 * A real attachment, so the tracked-back item points at an existing post.
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
	 * Build a Delivery instance using a media component pointed at the test Cloudinary domain.
	 *
	 * @return Test_Delivery_Parse_Element_Delivery
	 */
	protected function get_delivery() {
		$plugin          = \Cloudinary\get_plugin_instance();
		$media           = new \Cloudinary\Media( $plugin );
		$media->base_url = self::BASE_URL;
		$media->filter   = new \Cloudinary\Media\Filter( $media );

		$delivery = new Test_Delivery_Parse_Element_Delivery( $plugin );
		$delivery->set_media( $media );

		return $delivery;
	}

	/**
	 * Seed the known items the same way Delivery::set_usability() keys them: by public ID, and
	 * by the full URL built from the content URL and the relation's content-relative sized_url.
	 *
	 * @param Test_Delivery_Parse_Element_Delivery $delivery The delivery instance.
	 *
	 * @return string The full URL the item is known by.
	 */
	protected function seed_known( $delivery ) {
		$file = get_post_meta( self::$attachment_id, '_wp_attached_file', true );
		$item = array(
			'post_id'    => self::$attachment_id,
			'public_id'  => 'canola',
			'sized_url'  => '/uploads/' . $file,
			'sync_type'  => 'media',
			'format'     => 'jpg',
			'width'      => 640,
			'height'     => 480,
			'post_state' => 'enable',
		);
		$url  = \Cloudinary\Utils::clean_url( content_url() ) . $item['sized_url'];

		$delivery->known['canola'] = $item;
		$delivery->known[ $url ]   = $item;

		return $url;
	}

	/**
	 * An img tag with a Cloudinary src for a known public ID is tracked back to its attachment.
	 *
	 * @return void
	 */
	public function test_cloudinary_src_is_tracked_back_to_its_attachment() {
		$delivery = $this->get_delivery();
		$url      = $this->seed_known( $delivery );

		$element = $delivery->parse_element( '<img width="300" height="225" src="' . self::BASE_URL . '/images/w_300,h_225,c_scale/f_auto,q_auto/v1/canola/canola.jpg?_i=AA" class="attachment-medium size-medium" alt="" />' );

		$this->assertSame( self::$attachment_id, $element['id'] );
		$this->assertSame( $url, $element['atts']['src'] );
		$this->assertSame( 'canola.jpg', $element['atts']['data-public-id'] );
	}

	/**
	 * A Cloudinary src for a public ID Delivery doesn't know is left alone.
	 *
	 * @return void
	 */
	public function test_unknown_cloudinary_src_is_not_tracked_back() {
		$delivery = $this->get_delivery();
		$this->seed_known( $delivery );

		$src     = self::BASE_URL . '/images/w_300,h_225,c_scale/v1/other/other.jpg';
		$element = $delivery->parse_element( '<img width="300" height="225" src="' . $src . '" alt="" />' );

		$this->assertSame( 0, $element['id'] );
		$this->assertSame( $src, $element['atts']['src'] );
	}
}

/**
 * Delivery with a settable media component, since init() only wires the real one once connected.
 */
class Test_Delivery_Parse_Element_Delivery extends \Cloudinary\Delivery {

	/**
	 * Set the media component.
	 *
	 * @param \Cloudinary\Media $media The media component.
	 *
	 * @return void
	 */
	public function set_media( $media ) {
		$this->media = $media;
	}
}
