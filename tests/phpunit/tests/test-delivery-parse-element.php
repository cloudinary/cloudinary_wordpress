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
 * responsive breakpoints or SEO attributes). A src with transformations of its own (e.g. typed
 * into content by hand) is left alone, as rebuilding it from the attachment would drop them.
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
		$media                      = new Test_Delivery_Parse_Element_Media( $plugin );
		$media->base_url            = self::BASE_URL;
		$media->filter              = new \Cloudinary\Media\Filter( $media );
		$media->stub_cloudinary_url = self::BASE_URL . '/images/f_auto,q_auto/v1/canola/canola.jpg';

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
	 * A Cloudinary src with the plugin's own thumbnail crop is still tracked back.
	 *
	 * @return void
	 */
	public function test_cloudinary_src_with_thumbnail_crop_is_tracked_back() {
		$delivery = $this->get_delivery();
		$this->seed_known( $delivery );

		$element = $delivery->parse_element( '<img width="150" height="150" src="' . self::BASE_URL . '/images/w_150,h_150,c_thumb,g_auto/f_auto,q_auto/v1/canola/canola.jpg" alt="" />' );

		$this->assertSame( self::$attachment_id, $element['id'] );
	}

	/**
	 * A Cloudinary src with transformations of its own, e.g. typed into content by hand, is left
	 * alone, since rebuilding it from the attachment would drop them.
	 *
	 * @dataProvider data_custom_transformations
	 *
	 * @param string $transformations The transformations in the src.
	 *
	 * @return void
	 */
	public function test_cloudinary_src_with_custom_transformations_is_not_tracked_back( $transformations ) {
		$delivery = $this->get_delivery();
		$this->seed_known( $delivery );

		$src     = self::BASE_URL . '/images/' . $transformations . '/v1/canola/canola.jpg';
		$element = $delivery->parse_element( '<img src="' . $src . '" alt="" />' );

		$this->assertSame( 0, $element['id'] );
		$this->assertSame( $src, $element['atts']['src'] );
	}

	/**
	 * Transformations the plugin doesn't apply itself.
	 *
	 * @return array
	 */
	public function data_custom_transformations() {
		return array(
			'effect'      => array( 'w_300,h_225,c_scale/e_sepia/f_auto,q_auto' ),
			'manual crop' => array( 'c_crop,w_400,h_400,x_100,y_100/e_grayscale' ),
			'gravity'     => array( 'w_300,h_300,c_fill,g_face' ),
		);
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
 * Media with cloudinary_url() stubbed, to avoid the real sync/signature pipeline.
 */
class Test_Delivery_Parse_Element_Media extends \Cloudinary\Media {

	/**
	 * Canned cloudinary_url() return value.
	 *
	 * @var string|false
	 */
	public $stub_cloudinary_url = false;

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
