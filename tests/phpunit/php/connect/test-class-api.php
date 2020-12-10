<?php
/**
 * Tests for api class.
 *
 * @group   api
 * @package Cloudinary
 */

namespace Cloudinary;

use Cloudinary\Connect\Api;

/**
 * Tests for API class.
 *
 * @group   api
 * @package Cloudinary
 */
class Test_Api extends \WP_UnitTestCase {

	/**
	 * The api object.
	 *
	 * @var \Cloudinary\Connect\Api
	 */
	public $api;

	/**
	 * Holds the instance of the plugin.
	 *
	 * @var \Cloudinary\Plugin
	 */
	public $plugin;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = get_plugin_instance();
		$this->plugin->init();
		$this->set_credentials();
		$this->api = new Api( $this->plugin->components['connect'], $this->plugin->version );
	}

	/**
	 * Set credentials
	 */
	public function set_credentials() {
		$this->plugin->components['connect']->config_from_url( 'cloudinary://api-key:api-secret@cloud-folder' );
	}

	/**
	 * Test generate_transformation_string.
	 */
	public function test_generate_transformation_string() {

		$tests = array(
			array(
				'expect'          => 'c_fill,w_100,h_100',
				'transformations' => array(
					array(
						'crop'   => 'fill',
						'width'  => '100',
						'height' => '100',
					),
				),
			),
			array(
				'expect'          => 'c_fill,w_100,h_100/e_blue:500',
				'transformations' => array(
					array(
						'crop'   => 'fill',
						'width'  => '100',
						'height' => '100',
					),
					array(
						'effect' => 'blue:500',
					),
				),
			),
			array(
				'expect'          => 'c_fill,w_100,h_100/a_45/f_webp,q_auto',
				'transformations' => array(
					array(
						'crop'   => 'fill',
						'width'  => '100',
						'height' => '100',
					),
					array(
						'angle' => '45',
					),
					array(
						'fetch_format' => 'webp',
						'quality'      => 'auto',
					),
				),
			),
		);

		foreach ( $tests as $test ) {
			$string = $this->api->generate_transformation_string( $test['transformations'] );
			$this->assertSame( $test['expect'], $string );
		}

	}

	/**
	 * Test cloudinary_url.
	 */
	public function test_cloudinary_url() {
		$tests = array(
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/video/upload/v1/video-test.mov',
				'cloudinary_id' => 'video-test.mov',
				'options'       => array(
					'resource_type' => 'video',
				),
			),
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/video/upload/v1/video-test.mov',
				'cloudinary_id' => 'video-test.mov',
				'options'       => array(
					'resource_type' => 'video',
				),
			),
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/images/c_fill,w_100,h_100/a_45/f_webp,q_auto/v1234567/wpfolder/2008/09/image-test/image-test.jpg',
				'cloudinary_id' => 'wpfolder/2008/09/image-test.jpg',
				'options'       => array(
					'version'        => '1234567',
					'transformation' => array(
						array(
							'crop'   => 'fill',
							'width'  => '100',
							'height' => '100',
						),
						array(
							'angle' => '45',
						),
						array(
							'fetch_format' => 'webp',
							'quality'      => 'auto',
						),
					),
				),
			),
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/images/a_45/f_webp,q_auto/v1234567/wpfolder/2008/09/image-test/image-test.jpg',
				'cloudinary_id' => 'wpfolder/2008/09/image-test.jpg',
				'options'       => array(
					'version'        => '1234567',
					'transformation' => array(
						array(
							'angle' => '45',
						),
						array(
							'fetch_format' => 'webp',
							'quality'      => 'auto',
						),
						array(
							'wpsize' => 'thumbnail',
						),
					),
				),
			),
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/images/a_45/f_webp,q_auto/v1234567/wpfolder/2008/09/image-test/image-test.jpg',
				'cloudinary_id' => 'wpfolder/2008/09/image-test.jpg',
				'clean'         => true,
				'size'          => array(
					'wpsize' => 'thumbnail',
					'clean'  => true,
				),
				'options'       => array(
					'version'        => '1234567',
					'transformation' => array(
						array(
							'angle' => '45',
						),
						array(
							'fetch_format' => 'webp',
							'quality'      => 'auto',
						),
					),
				),
			),
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/images/a_45/f_webp,q_auto/v1234567/wpfolder/2008/09/image-test/image-test.jpg',
				'cloudinary_id' => 'wpfolder/2008/09/image-test.jpg',
				'options'       => array(
					'version'        => '1234567',
					'transformation' => array(
						array(
							'angle' => '45',
						),
						array(
							'fetch_format' => 'webp',
							'quality'      => 'auto',
						),
						array(
							'wpsize' => 'thumbnail',
							'clean'  => true,
						),
					),
				),
			),
			array(
				'expect'        => 'https://res.cloudinary.com/cloud-folder/images/a_45/f_webp,q_auto/v1234567/wpfolder/2008/09/image-test/image-test.jpg',
				'cloudinary_id' => 'wpfolder/2008/09/image-test.jpg',
				'size'          => array(
					'wpsize' => 'thumbnail',
					'clean'  => true,
				),
				'options'       => array(
					'version'        => '1234567',
					'transformation' => array(
						array(
							'angle' => '45',
						),
						array(
							'fetch_format' => 'webp',
							'quality'      => 'auto',
						),
					),
				),
			),
		);

		foreach ( $tests as $test ) {
			$size = array();
			if ( ! empty( $test['size'] ) ) {
				$size = $test['size'];
			}
			$clean = false;
			if ( ! empty( $test['clean'] ) ) {
				$clean = true;
			}
			$string = $this->api->cloudinary_url( $test['cloudinary_id'], $test['options'], $size, $clean );
			$this->assertSame( $test['expect'], $string );
		}
	}

	/**
	 * Tests url()
	 */
	public function test_url() {
		$tests = array(
			array(
				'expect'   => 'api.cloudinary.com/v1_1/cloud-folder/image/upload',
				'resource' => 'image',
				'function' => 'upload',
				'endpoint' => true,
			),
			array(
				'expect'   => 'api.cloudinary.com/v1_1/cloud-folder/video/upload',
				'resource' => 'video',
				'function' => 'upload',
				'endpoint' => true,
			),
			array(
				'expect'   => 'res.cloudinary.com/cloud-folder/images',
				'resource' => 'image',
				'function' => 'upload',
				'endpoint' => false,
			),
			array(
				'expect'   => 'res.cloudinary.com/cloud-folder/upload',
				'resource' => null,
				'function' => 'upload',
				'endpoint' => false,
			),
			array(
				'expect'   => 'res.cloudinary.com/cloud-folder/video',
				'resource' => 'video',
				'function' => null,
				'endpoint' => false,
			),
			array(
				'expect'   => 'res.cloudinary.com/cloud-folder',
				'resource' => null,
				'function' => null,
				'endpoint' => false,
			),
		);
		foreach ( $tests as $test ) {
			$string = $this->api->url( $test['resource'], $test['function'], $test['endpoint'] );
			$this->assertSame( $test['expect'], $string );
		}
	}

	/**
	 * Tests usage()
	 */
	public function test_usage() {

		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );

		$result = $this->api->usage();
		$this->assertSame( 'https://api-key:api-secret@api.cloudinary.com/v1_1/cloud-folder/usage', $result['url'] );
		$this->assertSame( 'GET', $result['args']['method'] );
		$this->assertSame( 60, $result['args']['timeout'] );

	}

	/**
	 * Tests upload_large()
	 */
	public function test_upload_large() {

		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );

		$args   = array(
			'resource_type' => 'image',
		);
		$result = $this->api->upload( '/my/test/image.jpg', $args );
		$this->assertSame( 'https://api.cloudinary.com/v1_1/cloud-folder/image/upload', $result['url'] );
		$this->assertSame( 'POST', $result['args']['method'] );
		$this->assertSame( 60, $result['args']['timeout'] );
		$this->assertArrayHasKey( 'signature', $result['args']['body'] );
	}

	/**
	 * Tests explicit()
	 */
	public function test_explicit() {

		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );

		$args   = array(
			'resource_type' => 'image',
			'public_id'     => 'test_id',
		);
		$result = $this->api->explicit( $args );
		$this->assertSame( 'https://api.cloudinary.com/v1_1/cloud-folder/image/explicit', $result['url'] );
		$this->assertSame( 'POST', $result['args']['method'] );
		$this->assertSame( 60, $result['args']['timeout'] );
		$this->assertArrayHasKey( 'signature', $result['args']['body'] );
		$this->assertArrayHasKey( 'public_id', $result['args']['body'] );
	}

	/**
	 * Tests destroy()
	 */
	public function test_destroy() {

		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );

		$args   = array(
			'resource_type' => 'image',
			'public_id'     => 'test_id',
		);
		$result = $this->api->destroy( 'image', $args );
		$this->assertSame( 'https://api.cloudinary.com/v1_1/cloud-folder/image/destroy', $result['url'] );
		$this->assertSame( 'POST', $result['args']['method'] );
		$this->assertSame( 60, $result['args']['timeout'] );
		$this->assertArrayHasKey( 'signature', $result['args']['body'] );
		$this->assertArrayHasKey( 'public_id', $result['args']['body'] );
	}

	/**
	 * Tests context()
	 */
	public function test_context() {

		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );

		$args   = array(
			'resource_type' => 'image',
			'public_id'     => 'test_id',
			'context'       => 'test_context',
		);
		$result = $this->api->context( $args );
		$this->assertSame( 'https://api.cloudinary.com/v1_1/cloud-folder/image/context', $result['url'] );
		$this->assertSame( 'POST', $result['args']['method'] );
		$this->assertSame( 60, $result['args']['timeout'] );
		$this->assertArrayHasKey( 'signature', $result['args']['body'] );
		$this->assertArrayHasKey( 'public_ids', $result['args']['body'] );
	}

	/**
	 * Tests clean_args()
	 */
	public function test_clean_args() {

		$args   = array(
			'resource_type'  => 'image',
			'public_id'      => 'test_id',
			'append'         => true,
			'delete'         => false,
			'transformation' => array(
				'c_scale',
				'w_200',
			),
		);
		$result = $this->api->clean_args( $args );

		$this->assertSame( '["c_scale","w_200"]', $result['transformation'] );
		$this->assertSame( '1', $result['append'] );
		$this->assertSame( '0', $result['delete'] );
		$this->assertArrayHasKey( 'public_id', $result );
		$this->assertSame( 'test_id', $result['public_id'] );

	}

	/**
	 * Tests __Call()
	 */
	public function test_call() {

		add_filter( 'pre_http_request', array( $this, 'mock_remote_request' ), 10, 3 );
		$args   = array(
			'image',
			'post',
			array(
				'resource_type'  => 'image',
				'public_id'      => 'test_id',
				'append'         => true,
				'delete'         => false,
				'transformation' => array(
					'c_scale',
					'w_200',
				),
			),
		);
		$result = $this->api->__call( 'upload', $args );

		$this->assertSame( 'https://api.cloudinary.com/v1_1/cloud-folder/upload/image', $result['url'] );
		$this->assertSame( 'POST', $result['args']['method'] );
		$this->assertSame( 60, $result['args']['timeout'] );


	}

	/**
	 * Tests sign()
	 */
	public function test_sign() {

		$signatures = array(
			'9e436cbb61a0a9c238be5c7b3839b673e659b33e' => array(
				'resource_type' => 'image',
				'public_id'     => 'test_id',
				'append'        => true,
				'delete'        => false,
			),
			'20af7ab57e2f3ecc55e086312a91d543afe760b7' => array(
				'resource_type'  => 'image',
				'public_id'      => 'test_id',
				'transformation' => array(
					'c_scale',
					'w_200',
				),
			),
			'2c4c36a9f758b29e5706911973be3892ea09ecb5' => array(
				'resource_type' => 'image',
				'public_id'     => 'test_id',
				'blank'         => '',
			),
		);

		foreach ( $signatures as $signature => $args ) {
			$result = $this->api->sign( $args );
			$this->assertSame( $signature, $result );
		}

	}

	/**
	 * Mock the wp_remote_request.
	 *
	 * @param bool   $a   flag.
	 * @param array  $r   request options.
	 * @param string $url url to send.
	 *
	 * @return array
	 */
	public function mock_remote_request( $a, $r, $url ) {
		if ( 'https://:@api.cloudinary.com/v1_1/usage' === $url ) {
			return new \WP_Error();
		}
		$result = array(
			'url'  => $url,
			'args' => $r,
		);

		return array( 'body' => wp_json_encode( $result ) );
	}
}
