<?php
/**
 * Tests for Media class.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Connect\Api;

/**
 * Tests for Media class.
 *
 * @group   sync
 *
 * @package Cloudinary
 */
class Test_Sync extends \WP_UnitTestCase {

	/**
	 * Holds the plugin instance.
	 *
	 * @var plugin instance.
	 */
	private $plugin;

	/**
	 * Holds the media instance.
	 *
	 * @var sync
	 */
	private $sync;

	/**
	 * Path to test images.
	 *
	 * @var string
	 */
	public $image_path;

	public $mock_type = 'image';
	public $mock_value = null;

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

	public $server;

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
				'cloudinary_folder' => 'test_folder',
				'bytes_step'        => 2000,
				'breakpoints'       => 5,
				'min_width'         => 1000,
			),
			'global_transformations'       => array(
				'image_format'  => 'jpg',
				'image_quality' => 'auto',
			),
			'global_video_transformations' => array(
				'video_format'  => 'mp4',
				'video_quality' => 'auto',
			),
		);
		$this->plugin->components['media']        = new Media( $this->plugin );
		$this->plugin->components['media']->setup();
		$this->sync = new Sync( $this->plugin );
		$this->sync->setup();
		set_current_screen( 'upload.php' );
		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );

		parent::setUp();
	}


	public function test_syncing() {
		$image = $this->get_image( true );
		wp_delete_attachment( $image, true );
		$id = $this->plugin->components['media']->cloudinary_id( $image );
		$this->assertFalse( $id );
	}


	public function test_push_attachments() {
		$this->go_to( home_url() );

		$this->plugin->components['connect']->usage['max_image_size'] = 1;
		$image                                                        = $this->get_image();
		$result                                                       = $this->sync->managers['push']->push_attachments( array( $image ) );
		$this->assertArrayHasKey( 'fail', $result );
		$this->assertNotEmpty( $result['fail'] );
		$this->assertSame( 'File size exceeds plan limitations.', $result['fail'][0] );

		$this->plugin->components['connect']->usage['max_image_size'] = 1000000;
		$image                                                        = $this->get_image();
		$result                                                       = $this->sync->managers['push']->push_attachments( array( $image ) );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertNotEmpty( $result['success'] );

		$synced_image        = $this->get_image( true );
		$meta                = get_post_meta( $synced_image, '_sync_signature', true );
		$meta['breakpoints'] = 'none';
		update_post_meta( $synced_image, '_sync_signature', $meta );
		$result = $this->sync->managers['push']->push_attachments( array( $synced_image ) );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertNotEmpty( $result['success'] );
		$new_meta = get_post_meta( $synced_image, '_sync_signature', true );
		$this->assertNotSame( $new_meta['breakpoints'], $meta['breakpoints'] );
		$this->synced_image = null;

		$synced_video = $this->get_video( true );
		delete_post_meta( $synced_video, '_sync_signature' );
		$result = $this->sync->managers['push']->push_attachments( array( $synced_video ) );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertNotEmpty( $result['success'] );

		$result = $this->sync->managers['push']->push_attachments( array( $image ) );
		$this->assertArrayHasKey( 'fail', $result );
		$this->assertNotEmpty( $result['fail'] );

		$this->unsynced_image = null;
		$image                = $this->get_image();
		$this->mock_type      = 'error';
		$result               = $this->sync->managers['push']->push_attachments( array( $image ) );
		$this->assertArrayHasKey( 'fail', $result );
		$this->assertNotEmpty( $result['fail'] );
		$this->assertSame( 'test error', $result['fail'][0] );
		$this->mock_type = 'image';
	}


	public function test_prep_on_demand_upload() {
		$image = $this->get_image();
		delete_post_meta( $image, '_cloudinary_pending' );
		$this->sync->managers['upload']->prep_on_demand_upload( false, $image );
		$this->assertTrue( $this->sync->managers['upload']->is_pending( $image ) );

		$this->assertNull( $this->mock_value );
		$this->mock_type = 'background';
		$this->sync->managers['upload']->init_background_upload();
		$this->assertNotNull( $this->mock_value );
		$this->mock_value = null;

	}

	public function test_prepare_upload() {

		$res = $this->sync->managers['push']->prepare_upload( 0 );
		$this->assertSame( 'attachment_post_get', $res->get_error_code() );

		$post = $this->factory()->post->create_object( array( 'post_title' => 'test', 'post_content' => 'test' ) );
		$res  = $this->sync->managers['push']->prepare_upload( $post );
		$this->assertSame( 'attachment_post_expected', $res->get_error_code() );

		$post = $this->factory()->post->create_object( array( 'post_title' => 'test', 'post_content' => 'test', 'post_type' => 'attachment' ) );
		$res  = $this->sync->managers['push']->prepare_upload( $post );
		$this->assertSame( 'attachment_no_file', $res->get_error_code() );

		$this->plugin->components['connect']->usage['max_image_size'] = 1;
		$image                                                        = $this->get_image();
		$res                                                          = $this->sync->managers['push']->prepare_upload( $image );
		$this->assertSame( 'upload_error', $res->get_error_code() );

		$this->plugin->components['connect']->usage['max_image_size'] = 10000000;
		$image                                                        = $this->get_image();
		delete_post_meta( $image, '_wp_attachment_metadata' );
		$res = $this->sync->managers['push']->prepare_upload( $image );
		$this->assertSame( 1024, $res['breakpoints']['responsive_breakpoints']['max_width'] );
		wp_delete_attachment( $image );
		$this->unsynced_image = null;

		$image = $this->get_image();
		update_post_meta( $image, '_transformations', array( array( 'effect' => 'blue:40' ) ) );
		$res = $this->sync->managers['push']->prepare_upload( $image );
		$this->assertSame( 'e_blue:40', $res['breakpoints']['responsive_breakpoints']['transformation'] );

	}


	public function test_queue() {
		$media   = array(
			$this->get_image(),
			$this->get_video(),
		);
		$test    = $this->sync->managers['queue']->get_queue();
		$started = $this->sync->managers['queue']->is_running();
		$this->assertFalse( $started );

		$this->assertNotEmpty( $test );
		$this->assertArrayHasKey( 'pending', $test );
		$this->sync->managers['queue']->start_queue();
		$post = $this->sync->managers['queue']->get_post();
		$this->assertTrue( in_array( $post, $media, true ) );
		$this->sync->managers['queue']->mark( $post );

		$post  = $this->sync->managers['queue']->get_post();
		$this->assertTrue( in_array( $post, $media, true ) );

		$queue = $this->sync->managers['queue']->get_queue_status();
		$this->assertSame( 50.0, $queue['percent'] );

		wp_delete_attachment( $post );
		$this->sync->managers['queue']->mark( $post, 'error' );
		$new = $this->sync->managers['queue']->get_queue_status();
		$this->assertSame( 1, $new['done'] );

		$this->sync->managers['queue']->stop_queue();
		$queue = $this->sync->managers['queue']->get_queue();
		$this->assertArrayNotHasKey( 'started', $queue );

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

		$return = null;
		switch ( $this->mock_type ) {
			case 'image':
				$image  = array(
					'public_id'              => 'test_image',
					'version'                => '12345',
					'responsive_breakpoints' => array(
						array(
							'breakpoints' => array(
								array(
									'width'  => 150,
									'height' => 150,
								),
							),
						),
					),
				);
				$return = array( 'body' => wp_json_encode( $image ) );
				break;
			case 'error':
				$return = new \WP_Error( 'error', 'test error' );
				break;
			case 'background':
				$this->mock_value = wp_rand( 0, 1000 );
				break;
		}


		return $return;
	}


}
