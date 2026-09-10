<?php
/**
 * Tests for Cloudinary CDN URL generation and image tag rebuilding.
 *
 * These cover the pieces of the delivery pipeline that can run without a
 * synced attachment: the CDN URL build in Cloudinary\Connect\Api, and the
 * tag rebuild helpers that Cloudinary\Delivery::rebuild_tag() uses.
 *
 * No Cloudinary account is needed. The URL build path reads only the
 * cloud_name, cname and private_cdn credentials, never the API key or
 * secret, so a stub connection is enough. Delivery against a real cloud is
 * covered by the Playwright suite in tests/e2e.
 *
 * @package Cloudinary
 */

use Cloudinary\Connect\Api;
use Cloudinary\UI\Component;
use Cloudinary\Utils;

/**
 * Covers CDN URL generation and image tag attribute handling.
 */
class Test_Image_Conversion extends WP_UnitTestCase {

	/**
	 * The cloud name used across the tests.
	 *
	 * @var string
	 */
	const CLOUD_NAME = 'test-cloud';

	/**
	 * Build an Api instance backed by fake credentials.
	 *
	 * Api::__construct() has no type hint on its first argument and only
	 * calls get_credentials() on it, so a stub is enough.
	 *
	 * @param array $credentials Credentials to override the defaults with.
	 *
	 * @return Api
	 */
	protected function get_api( array $credentials = array() ) {
		$credentials = array_merge(
			array(
				'cloud_name'  => self::CLOUD_NAME,
				'private_cdn' => 'false',
				'cname'       => '',
			),
			$credentials
		);

		$connect = new Test_Image_Conversion_Connect( $credentials );

		return new Api( $connect, '3.3.5' );
	}

	/**
	 * An uploaded image gets a CDN URL under the configured cloud name.
	 *
	 * @return void
	 */
	public function test_cloudinary_url_delivers_from_the_cdn() {
		$url = $this->get_api()->cloudinary_url( 'sample' );

		$this->assertSame( 'res.cloudinary.com', wp_parse_url( $url, PHP_URL_HOST ) );
		$this->assertStringStartsWith(
			'/' . self::CLOUD_NAME . '/',
			wp_parse_url( $url, PHP_URL_PATH )
		);
		$this->assertStringEndsWith( '/sample', $url );
	}

	/**
	 * Transformations are compiled into the URL path.
	 *
	 * @return void
	 */
	public function test_cloudinary_url_includes_the_transformations() {
		$url = $this->get_api()->cloudinary_url(
			'sample',
			array(
				'transformation' => array(
					array(
						'crop'   => 'fill',
						'width'  => 300,
						'height' => 200,
					),
				),
			)
		);

		$this->assertStringContainsString( 'c_fill,w_300,h_200', $url );
	}

	/**
	 * A custom CNAME replaces the default delivery host.
	 *
	 * @return void
	 */
	public function test_cloudinary_url_uses_a_custom_cname() {
		$url = $this->get_api()->cloudinary_url( 'sample' );

		$this->assertSame( 'res.cloudinary.com', wp_parse_url( $url, PHP_URL_HOST ) );

		$cname_url = $this->get_api(
			array(
				'cname'       => 'media.example.com',
				'private_cdn' => 'true',
			)
		)->cloudinary_url( 'sample' );

		$this->assertSame( 'media.example.com', wp_parse_url( $cname_url, PHP_URL_HOST ) );
	}

	/**
	 * Transformation options map onto their Cloudinary short names, and
	 * unknown options are dropped rather than passed through.
	 *
	 * @return void
	 */
	public function test_generate_transformation_string_maps_known_options() {
		$transformation = Api::generate_transformation_string(
			array(
				array(
					'crop'    => 'scale',
					'width'   => 800,
					'quality' => 'auto',
					'nonsense' => 'dropped',
				),
			)
		);

		$this->assertStringContainsString( 'c_scale', $transformation );
		$this->assertStringContainsString( 'w_800', $transformation );
		$this->assertStringContainsString( 'q_auto', $transformation );
		$this->assertStringNotContainsString( 'dropped', $transformation );
	}

	/**
	 * Several transformation sets are joined into chained URL segments.
	 *
	 * @return void
	 */
	public function test_generate_transformation_string_chains_multiple_sets() {
		$transformation = Api::generate_transformation_string(
			array(
				array( 'width' => 800 ),
				array( 'effect' => 'sharpen' ),
			)
		);

		$this->assertSame( 'w_800/e_sharpen', $transformation );
	}

	/**
	 * An unknown resource type yields no transformations at all.
	 *
	 * @return void
	 */
	public function test_generate_transformation_string_ignores_unknown_types() {
		$this->assertSame(
			'',
			Api::generate_transformation_string( array( array( 'width' => 800 ) ), 'document' )
		);
	}

	/**
	 * A rebuilt image tag carries the CDN URL plus any added attributes.
	 *
	 * This mirrors what Delivery::rebuild_tag() does: build the tag with
	 * Component::build_tag(), then read it back with
	 * Utils::get_tag_attributes().
	 *
	 * @return void
	 */
	public function test_rebuilt_image_tag_keeps_the_cdn_url_and_added_attributes() {
		$cloudinary_url = $this->get_api()->cloudinary_url(
			'sample',
			array(
				'transformation' => array(
					array(
						'crop'  => 'fill',
						'width' => 300,
					),
				),
			)
		);

		$tag = Component::build_tag(
			'img',
			array(
				'src'     => $cloudinary_url,
				'alt'     => 'A sample image',
				'class'   => 'wp-image-123 cld-image',
				'loading' => 'lazy',
				'width'   => '300',
			)
		);

		$attributes = Utils::get_tag_attributes( $tag );

		$this->assertSame( $cloudinary_url, $attributes['src'] );
		$this->assertSame( 'lazy', $attributes['loading'] );
		$this->assertSame( 'A sample image', $attributes['alt'] );
		$this->assertSame( 'wp-image-123 cld-image', $attributes['class'] );
		$this->assertSame( '300', $attributes['width'] );
	}

	/**
	 * Class lists given as arrays are flattened into a class attribute.
	 *
	 * @return void
	 */
	public function test_rebuilt_image_tag_flattens_array_attributes() {
		$tag = Component::build_tag(
			'img',
			array(
				'src'   => 'https://res.cloudinary.com/' . self::CLOUD_NAME . '/images/v1/sample',
				'class' => array( 'wp-image-123', 'cld-image' ),
			)
		);

		$attributes = Utils::get_tag_attributes( $tag );

		$this->assertSame( 'wp-image-123 cld-image', $attributes['class'] );
	}

	/**
	 * The cloudinary_bypass_seo_url filter switches the delivery path from
	 * the SEO friendly form to the classic one. This also proves the
	 * WordPress hook system is live inside the test harness.
	 *
	 * @return void
	 */
	public function test_bypass_seo_url_filter_changes_the_delivery_path() {
		$seo_url = $this->get_api()->cloudinary_url( 'sample' );

		add_filter( 'cloudinary_bypass_seo_url', '__return_true' );
		$classic_url = $this->get_api()->cloudinary_url( 'sample' );
		remove_filter( 'cloudinary_bypass_seo_url', '__return_true' );

		$this->assertNotSame( $seo_url, $classic_url );
		$this->assertStringContainsString( '/image/upload/', $classic_url );
		$this->assertStringNotContainsString( '/image/upload/', $seo_url );
	}
}

/**
 * Minimal stand in for Cloudinary\Connect.
 *
 * Api only calls get_credentials() on the object it is given, so this
 * avoids booting the real connection, which would need an account.
 */
class Test_Image_Conversion_Connect {

	/**
	 * The fake credentials.
	 *
	 * @var array
	 */
	protected $credentials;

	/**
	 * Constructor.
	 *
	 * @param array $credentials The fake credentials.
	 */
	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Get the credentials.
	 *
	 * @return array
	 */
	public function get_credentials() {
		return $this->credentials;
	}
}
