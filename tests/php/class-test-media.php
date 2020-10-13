<?php
/**
 * Tests for Media class.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Connect\Api;
use Cloudinary\Media\Global_Transformations;

/**
 * Tests for Media class.
 *
 * @group   media
 *
 * @package Cloudinary
 */
class Test_Media extends \WP_UnitTestCase {

	/**
	 * Holds the plugin instance.
	 *
	 * @var plugin instance.
	 */
	private $plugin;

	/**
	 * Holds the media instance.
	 *
	 * @var media
	 */
	private $media;

	/**
	 * Path to test images.
	 *
	 * @var string
	 */
	public $image_path;

	/**
	 * Path to test videos.
	 *
	 * @var string
	 */
	public $video_path;

	public $synced_image;
	public $unsynced_image;
	public $synced_video;
	public $unsynced_video;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {


		$this->plugin = new Plugin();
		$this->plugin->init();

		add_image_size( 'test_size', 100, 100, false );
		$this->image_path                           = WP_TESTS_DIR . '/data/images/';
		$this->video_path                           = WP_TESTS_DIR . '/data/uploads/';
		$this->plugin->components['connect']->usage = array(
			'max_image_size' => 1000000000,
			'max_video_size' => 1000000000,
		);
		$this->plugin->components['connect']->config_from_url( 'cloudinary://api-key:api-secret@cloud-folder' );
		$this->plugin->components['connect']->api = new Api( $this->plugin->components['connect'] );
		$this->plugin->config['connect']          = true;
		$this->plugin->config['settings']         = array(
			'general'                      => array(
				'cloudinary_folder'   => 'test_folder',
				'bytes_step'          => 2000,
				'breakpoints'         => 5,
				'min_width'           => 1000,
				'video_autoplay_mode' => 'scroll',
			),
			'global_transformations'       => array(
				'image_format'   => 'jpg',
				'image_quality'  => 'auto',
				'image_freeform' => 'e_blue:40',
			),
			'global_video_transformations' => array(
				'video_format'  => 'mp4',
				'video_quality' => 'auto',
			),
		);
		$this->media                              = new Media( $this->plugin );
		$this->media->setup();
		$this->media->global_transformations = new Global_Transformations( $this->media );
		set_current_screen( 'upload.php' );
		parent::setUp();
	}

	public function test_cloudinary_id() {
		$unsyn_id      = $this->get_image( false );
		$cloudinary_id = $this->media->cloudinary_id( $unsyn_id );
		$this->assertFalse( $cloudinary_id );

		$sync_id       = $this->get_image( true );
		$cloudinary_id = $this->media->cloudinary_id( $sync_id );
		$this->assertSame( 'test_folder/canola.jpg', $cloudinary_id );
	}

	public function test_is_media() {
		$image = $this->factory()->attachment->create_upload_object( $this->image_path . 'canola.jpg' );
		$video = $this->factory()->attachment->create_upload_object( $this->video_path . 'small-video.mp4' );
		$post  = $this->factory()->post->create_object( array(
			'post_type'    => 'post',
			'post_content' => 'content',
			'post_title'   => 'title',
		) );

		$this->assertTrue( $this->media->is_media( $image ) );
		$this->assertTrue( $this->media->is_media( $video ) );
		$this->assertFalse( $this->media->is_media( $post ) );

	}


	public function test_uncropped_url() {
		$attachment = $this->get_image();
		$size_url   = wp_get_attachment_image_url( $attachment, 'thumbnail' );
		$full       = wp_get_attachment_url( $attachment );
		$uncropped  = $this->media->uncropped_url( $size_url );
		$this->assertSame( $full, $uncropped );

		$notcropped = $this->media->uncropped_url( $full );
		$this->assertSame( $full, $notcropped );
	}

	/**
	 * includes get_id_from_sync_key()
	 *
	 */
	public function test_get_id_from_url() {

		// Test unsynced image.
		$attachment = $this->get_image();
		$url        = wp_get_attachment_image_url( $attachment, 'full' );
		$id         = $this->media->get_id_from_url( $url );
		$this->assertSame( $attachment, $id );

		// Test synced image.
		$attachment = $this->get_image( true );
		set_current_screen( 'post.php' );
		$url = wp_get_attachment_image_url( $attachment, 'full' );
		$id  = $this->media->get_id_from_url( $url );
		$this->assertSame( $attachment, $id );

		// Test CLD url.
		$url    = 'https://res.cloudinary.com/cloud-folder/image/upload/v1/test_folder/test-image.jpg';
		$new_id = $this->media->get_id_from_url( $url );
		$this->assertSame( $attachment + 1, $new_id );
		$synced = get_post_meta( $new_id, md5( 'test_folder/test-image' ), true );
		$this->assertNotEmpty( $synced );

		// Test CLD url + transformations.
		$url        = 'https://res.cloudinary.com/cloud-folder/image/upload/e_blue:500/v1/test_folder/test-image.jpg';
		$another_id = $this->media->get_id_from_url( $url );
		$this->assertSame( $new_id + 1, $another_id );
		$synced = get_post_meta( $another_id, md5( 'test_folder/test-image[{"effect":"blue:500"}]' ), true );
		$this->assertNotEmpty( $synced );

		// Test Local url + transformations.
		$image      = $this->get_image();
		$url        = wp_get_attachment_image_url( $image, 'full' ) . '?cld_parms=e_blue:500';
		$another_id = $this->media->get_id_from_url( $url );
		$this->assertSame( $image, $another_id );

	}

	public function test_get_size_from_url() {
		$url  = '../image-150x150.jpg';
		$crop = $this->media->get_size_from_url( $url );
		$this->assertSame( array(
			150,
			150,
		), $crop );

		// Cld url.
		$url  = 'https://res.cloudinary.com/cloud-folder/image/upload/c_fill,w_150,h_150,e_blue:500/v1/test_folder/test-image.jpg';
		$crop = $this->media->get_size_from_url( $url );
		$this->assertSame( array(
			'150',
			'150',
		), $crop );
	}

	public function test_get_crop() {
		$attachment = $this->get_image();
		// Cropped size.
		$url  = wp_get_attachment_image_url( $attachment, 'thumbnail' );
		$crop = $this->media->get_crop( $url, $attachment );
		$this->assertSame( array(
			'wpsize'  => 'thumbnail',
			'width'   => 150,
			'height'  => 150,
			'crop'    => 'fill',
			'gravity' => 'auto',
		), $crop );

		// Un-cropped size, additional size.
		$url  = wp_get_attachment_image_url( $attachment, 'test_size' );
		$crop = $this->media->get_crop( $url, $attachment );
		$this->assertSame( array(
			'wpsize' => 'test_size',
			'width'  => 100,
			'height' => 56,
			'crop'   => 'scale',
		), $crop );

		// Test return false.
		$crop = $this->media->get_crop( '', 0 );
		$this->assertFalse( $crop );
	}

	public function test_set_transformation() {

		$test = array();
		$this->media->set_transformation( $test, 'effect', 'blue:500' );
		$expect = array(
			array(
				'effect' => 'blue:500',
			),
		);
		$this->assertSame( $expect, $test );
		$expect = array(
			array(
				'effect' => 'blue:500',
			),
			array(
				'angle' => '45',
			),
		);
		$this->media->set_transformation( $test, 'angle', '45' );
		$this->assertSame( $expect, $test );

		$expect = array(
			array(
				'effect' => 'blue:100',
			),
			array(
				'angle' => '45',
			),
		);

		$this->media->set_transformation( $test, 'effect', 'blue:100' );
		$this->assertSame( $expect, $test );

		$expect = array(
			array(
				'effect' => 'blue:100',
			),
			array(
				'angle'  => '45',
				'format' => 'jpg',
			),
		);
		$this->media->set_transformation( $test, 'format', 'jpg', 1 );
		$this->assertSame( $expect, $test );

	}

	public function test_get_transformation() {
		$expect = array(
			array(
				'effect' => 'blue:100',
			),
			array(
				'angle'  => '45',
				'format' => 'jpg',
			),
		);
		$index  = $this->media->get_transformation( $expect, 'angle' );
		$this->assertSame( 1, $index );

		$index = $this->media->get_transformation( $expect, 'effect' );
		$this->assertSame( 0, $index );

		$index = $this->media->get_transformation( $expect, 'tree' );
		$this->assertFalse( $index );
	}


	public function test_get_crop_from_transformation() {
		$test = array(
			array(
				'effect' => 'blue:100',
			),
			array(
				'crop'  => 'scale',
				'width' => '100',
			),
			array(
				'angle'  => '45',
				'format' => 'jpg',
			),
		);
		$crop = $this->media->get_crop_from_transformation( $test );
		$this->assertSame( $test[1], $crop );


		$test = array(
			array(
				'effect' => 'blue:100',
			),
			array(
				'overlay' => 'test',
				'width'   => '100',
			),
			array(
				'crop'  => 'scale',
				'width' => '100',
			),
			array(
				'angle'  => '45',
				'format' => 'jpg',
			),
		);
		$crop = $this->media->get_crop_from_transformation( $test );
		$this->assertSame( $test[2], $crop );
		$crop = $this->media->get_crop_from_transformation( $test, true );
		$this->assertSame( array(
			'crop'   => 'scale',
			'width'  => '100',
			'height' => 1,
		), $crop );

		// false.
		$crop = $this->media->get_crop_from_transformation( array() );
		$this->assertFalse( $crop );

	}

	/**
	 * Test for get_transformations_from_string() method.
	 *
	 *
	 * @see   Media::get_transformations_from_string()
	 */
	public function test_get_transformations_from_string() {
		$string = 'c_fill,w_100,h_100/e_blue:500/wpsize';
		$result = $this->media->get_transformations_from_string( $string );

		$this->assertArrayHasKey( 0, $result );
		$this->assertArrayHasKey( 1, $result );
		$this->assertArrayNotHasKey( 2, $result );

		$this->assertArrayHasKey( 'crop', $result[0] );
		$this->assertArrayHasKey( 'width', $result[0] );
		$this->assertArrayHasKey( 'height', $result[0] );

		$this->assertSame( 'fill', $result[0]['crop'] );
		$this->assertSame( '100', $result[0]['width'] );
		$this->assertSame( '100', $result[0]['height'] );

		$this->assertArrayHasKey( 'effect', $result[1] );
		$this->assertSame( 'blue:500', $result[1]['effect'] );


	}


	public function test_attachment_url() {

		$video = $this->get_video();
		$url   = $this->media->attachment_url( 'invalid', $video );
		$this->assertSame( 'invalid', $url );

		$video = $this->get_video( true );
		$url   = $this->media->attachment_url( 'invalid', $video );
		$this->assertSame( 'https://res.cloudinary.com/cloud-folder/video/upload/v1/test_folder/small-video.mp4', $url );
	}


	public function test_balance_crop() {
		$size       = array(
			'width'  => 200,
			'height' => 400,
		);
		$shift_size = array(
			'width' => 100,
		);
		$balance    = $this->media->balance_crop( $size, $shift_size );
		$this->assertSame( 200, $balance['height'] );

		$shift_size = array(
			'height' => 600,
		);
		$balance    = $this->media->balance_crop( $size, $shift_size );
		$this->assertSame( 400, $balance['width'] );

	}

	public function test_filtering() {
		remove_all_actions( 'wp_insert_post_data' );

		$image = $this->get_image( true );
		update_post_meta( $image, '_transformations', array( array( 'crop' => 'scale', 'width' => 150, 'height' => 150 ) ) );
		$html = wp_get_attachment_image( $image, 'full' );
		$this->assertSame( '<img width="640" height="480" src="https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/v1/test_folder/canola.jpg" class="attachment-full size-full" alt="" srcset="https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/$wpsize_!_cld_full!,w_640,h_480,c_scale/v1/test_folder/canola.jpg 640w, https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/$wpsize_!medium!,w_300,h_225,c_scale/v1/test_folder/canola.jpg 300w, https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/$wpsize_!test_size!,w_100,h_75,c_scale/v1/test_folder/canola.jpg 100w, https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/v1/test_folder/canola.jpg 150w" sizes="(max-width: 640px) 100vw, 640px" />', $html );
		update_post_meta( $image, '_cloudinary_breakpoints', array(
			array(
				'width'  => 150,
				'height' => 150,
			),
		) );
		$html = wp_get_attachment_image( $image, 'full' );
		$this->assertSame( '<img width="640" height="480" src="https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/v1/test_folder/canola.jpg" class="attachment-full size-full" alt="" srcset="https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/v1/test_folder/canola.jpg 640w, https://res.cloudinary.com/cloud-folder/image/upload/c_scale,w_150,h_150/w_150,h_150,c_scale/v1/test_folder/canola.jpg 150w" sizes="(max-width: 640px) 100vw, 640px" />', $html );
		$this->synced_image = null;


		$image = $this->get_image( true );
		$data  = array(
			'post_content' => $this->make_thumb_image_tag( $image ),
		);
		add_action( 'wp_insert_post_data', array( $this->media->filter, 'filter_out_cloudinary' ) );
		$local = apply_filters( 'wp_insert_post_data', $data );
		$cld   = trim( apply_filters( 'the_content', wp_unslash( $local['post_content'] ) ) );
		$this->assertSame( wp_unslash( $data['post_content'] ), $cld );

		$image = $this->get_image();
		$data  = array(
			'post_content' => $this->make_thumb_image_tag( $image ),
		);
		$local = apply_filters( 'wp_insert_post_data', $data );
		$cld   = trim( apply_filters( 'the_content', wp_unslash( $local['post_content'] ) ) );
		$this->assertSame( wp_unslash( $data['post_content'] ), $cld );

		$image = $this->get_image();
		$data  = array(
			'post_content' => '<p>' . wp_get_attachment_image( $image, 'full' ) . '</p>',
		);
		$local = apply_filters( 'wp_insert_post_data', $data );
		$this->go_to( home_url() );
		$cld = trim( apply_filters( 'the_content', wp_unslash( $local['post_content'] ) ) );
		$this->assertSame( wp_unslash( $data['post_content'] ), $cld );

		$image = $this->get_image( true );
		$data  = array(
			'post_content' => '<p>' . wp_get_attachment_image( $image, 'full' ) . '</p>',
		);
		$local = apply_filters( 'wp_insert_post_data', $data );
		$cld   = trim( apply_filters( 'the_content', wp_unslash( $local['post_content'] ) ) );

		//$this->assertSame( wp_unslash( $data['post_content'] ), $cld );

		$tag   = '<p><img src="img.jpg"></p>';
		$data  = array(
			'post_content' => $tag,
		);
		$local = apply_filters( 'wp_insert_post_data', $data );
		$out   = trim( apply_filters( 'the_content', wp_unslash( $local['post_content'] ) ) );
		$this->assertSame( $tag, $out );

		$html = '<p>[video width="1920" height="1080" mp4="video.mp4" id="1"][/video]</p>';
		$res  = $this->media->filter->get_video_shortcodes( $html );
		$this->assertNotEmpty( $res );
		$this->assertArrayHasKey( 'args', $res[0] );

		$html = '<video poster="poster.jpg"></video>';
		$res  = $this->media->filter->get_poster_from_tag( $html );
		$this->assertSame( 'poster.jpg', $res );

		$html = '<img src="image.jpg" class="wp-image size-thumbnail">';
		$res  = $this->media->filter->get_size_from_image_tag( $html );
		$this->assertSame( 'thumbnail', $res );

		$html = '<img src="image-150x150.jpg" class="wp-image">';
		$res  = $this->media->filter->get_size_from_image_tag( $html );
		$this->assertSame( array(
			150,
			150,
		), $res );

		$html = '<img src="image.jpg" width="150" height="150" class="wp-image">';
		$res  = $this->media->filter->get_crop_from_image_tag( $html );
		$this->assertSame( array(
			'150',
			'150',
		), $res );

		$html = '<p>[video width="1920" height="1080" mp4="video.mp4" id="1"][/video]</p>';
		$res  = $this->media->filter->filter_video_shortcodes( $html );
		$this->assertSame( '<p>[video width="1920" height="1080" mp4="" id="1"][/video]</p>', $res );

		$video = $this->get_video( true );
		$html  = '<p>[video width="1920" height="1080" transformations="e_reverse" mp4="video.mp4" id="' . $video . '"][/video]</p>';
		$res   = $this->media->filter->filter_video_shortcodes( $html );
		$this->assertSame( '<p>[video width="1920" height="1080" transformations="e_reverse" mp4="https://res.cloudinary.com/cloud-folder/video/upload/e_reverse/v1/test_folder/small-video.mp4" id="' . $video . '"][/video]</p>', $res );

		$url  = wp_get_attachment_url( $video );
		$html = '<p>[video width="1920" height="1080" transformations="e_reverse" mp4="' . $url . '"][/video]</p>';
		$res  = $this->media->filter->filter_video_shortcodes( $html );
		$this->assertSame( '<p>[video width="1920" height="1080" transformations="e_reverse" mp4="https://res.cloudinary.com/cloud-folder/video/upload/v1/test_folder/small-video.mp4"][/video]</p>', $res );

		$html = '<p>[video width="1920" height="1080" mp4="no-vid.mp4"][/video]</p>';
		$res  = $this->media->filter->filter_video_shortcodes( $html );
		$this->assertSame( '<p>[video width="1920" height="1080" mp4="no-vid.mp4"][/video]</p>', $res );

		$html = '<p>[video width="1920" height="1080" mp4="' . $url . '"][/video]</p>';
		$res  = $this->media->filter->filter_video_shortcodes( $html );
		$this->assertSame( '<p>[video width="1920" height="1080" mp4="https://res.cloudinary.com/cloud-folder/video/upload/v1/test_folder/small-video.mp4"][/video]</p>', $res );


		$html = '<p>[video width="1920" height="1080" mp4="https://res.cloudinary.com/cloud-folder/video/upload/e_reverse/v1/test_folder/small-video.mp4"][/video]</p>';
		$res  = $this->media->filter->filter_video_embeds( $html, $video, array( 'transformations' => array( array( 'effect' => 'reverse' ) ) ) );
		$this->assertSame( '<p>[video width="1920" height="1080" mp4="https://res.cloudinary.com/cloud-folder/video/upload/e_reverse/v1/test_folder/small-video.mp4" id="' . $video . '" transformations="e_reverse"][/video]</p>', $res );

		$data = wp_prepare_attachment_for_js( $video );
		$this->assertArrayHasKey( 'public_id', $data );

		$video = $this->get_video( true );
		$url   = 'https://res.cloudinary.com/cloud-folder/video/upload/e_reverse/e_noise/v1/test_folder/small-video.mp4';
		$test  = array(
			'post_content' => '<video src="' . $url . '" class="wp-image-' . $video . '">',
		);
		$data  = $this->media->filter->filter_out_cloudinary( $test );
		$this->assertSame( 1, substr_count( $data['post_content'], 'cld_params=e_reverse/e_noise' ) );

		$url  = 'https://res.cloudinary.com/cloud-folder/video/upload/e_reverse/e_noise/v1/test_folder/small-video.mp4';
		$test = '<img src="' . $url . '" class="wp-image-' . $video . '">';
		$data = $this->media->filter->filter_out_local( $test );
		$this->assertSame( $test, $data );

		$image = $this->get_image();
		$url   = wp_get_attachment_image_url( $image, 'full' ) . '?cld_params=e_blue:40';
		$test  = '<img src="' . $url . '" poster="' . $url . '" class="wp-image-' . $video . '">';
		$data  = $this->media->filter->filter_out_local( $test );
		$this->assertNotSame( $test, $data );
	}

	public function make_thumb_image_tag( $image ) {

		$url     = wp_get_attachment_image_url( $image, 'thumbnail' );
		$content = sprintf( '<div><img poster="%s" src="%s" alt="" width="150" height="150" class="alignnone size-thumbnail wp-image-%d" /></div>', $url, $url, $image );

		return $content;
	}

	public function make_full_image_tag( $image ) {

		$url     = wp_get_attachment_image_url( $image, 'thumbnail' );
		$content = sprintf( '<div><img src="%s" alt="" width="150" height="150" class="alignnone size-thumbnail wp-image-%d" /></div>', $url, $image );

		return $content;
	}


	public function get_image( $sync = false ) {
		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );
		if ( true === $sync ) {
			if ( empty( $this->synced_image ) ) {
				$this->synced_image = $this->factory()->attachment->create_upload_object( $this->image_path . 'canola.jpg' );
				$signature          = $this->plugin->components['sync']->generate_signature( $this->synced_image );

				update_post_meta( $this->synced_image, '_public_id', 'test_folder/canola' );
				update_post_meta( $this->synced_image, '_sync_signature', $signature );
				update_post_meta( $this->synced_image, md5( 'test_folder/canola' ), true );
			}

			return $this->synced_image;
		}
		if ( empty( $this->unsynced_image ) ) {
			$this->unsynced_image = $this->factory()->attachment->create_upload_object( $this->image_path . '33772.jpg' );
		}


		return $this->unsynced_image;
	}

	public function get_video( $sync = false ) {
		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );
		if ( true === $sync ) {
			if ( empty( $this->synced_video ) ) {
				$this->synced_video = $this->factory()->attachment->create_upload_object( $this->video_path . 'small-video.mp4' );
				$signature          = $this->plugin->components['sync']->generate_signature( $this->synced_video );

				update_post_meta( $this->synced_video, '_public_id', 'test_folder/small-video' );
				update_post_meta( $this->synced_video, '_sync_signature', $signature );
				update_post_meta( $this->synced_video, md5( 'test_folder/small-video' ), true );
			}

			return $this->synced_video;
		}
		if ( empty( $this->unsynced_video ) ) {
			$this->unsynced_video = $this->factory()->attachment->create_upload_object( $this->video_path . 'small-video.mov' );
		}


		return $this->unsynced_video;
	}

	public function mock_remote_request( $r ) {
		return null;
	}


}
