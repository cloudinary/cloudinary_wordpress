<?php
/**
 * Unit tests for Cloudinary\Connect.
 *
 * Covers sanitize_connection_url() and the CLOUDINARY_VARIABLE_REGEX constant
 * which are pure-PHP helpers with no WordPress runtime dependencies.
 *
 * Run with:
 *   vendor/bin/phpunit --testdox
 *
 * @package Cloudinary
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for Connect::sanitize_connection_url() and CLOUDINARY_VARIABLE_REGEX.
 */
class Test_Connect extends TestCase {

	// -----------------------------------------------------------------------
	// sanitize_connection_url()
	// -----------------------------------------------------------------------

	/** @test */
	public function it_returns_a_plain_url_unchanged() {
		$url = 'cloudinary://123456789012345:AbCdEfGhIjKlMnOpQrStUvWxYz@mycloud';
		$this->assertSame( $url, Cloudinary\Connect::sanitize_connection_url( $url ) );
	}

	/** @test */
	public function it_strips_uppercase_cloudinary_url_prefix() {
		$raw      = 'CLOUDINARY_URL=cloudinary://123:secret@cloud';
		$expected = 'cloudinary://123:secret@cloud';
		$this->assertSame( $expected, Cloudinary\Connect::sanitize_connection_url( $raw ) );
	}

	/** @test */
	public function it_strips_lowercase_cloudinary_url_prefix() {
		$raw      = 'cloudinary_url=cloudinary://123:secret@cloud';
		$expected = 'cloudinary://123:secret@cloud';
		$this->assertSame( $expected, Cloudinary\Connect::sanitize_connection_url( $raw ) );
	}

	/** @test */
	public function it_strips_mixed_case_cloudinary_url_prefix() {
		$raw      = 'Cloudinary_URL=cloudinary://123:secret@cloud';
		$expected = 'cloudinary://123:secret@cloud';
		$this->assertSame( $expected, Cloudinary\Connect::sanitize_connection_url( $raw ) );
	}

	/** @test */
	public function it_trims_leading_and_trailing_whitespace() {
		$raw      = '  cloudinary://123:secret@cloud  ';
		$expected = 'cloudinary://123:secret@cloud';
		$this->assertSame( $expected, Cloudinary\Connect::sanitize_connection_url( $raw ) );
	}

	/** @test */
	public function it_trims_whitespace_around_a_prefixed_url() {
		$raw      = "  CLOUDINARY_URL=cloudinary://123:secret@cloud\n";
		$expected = 'cloudinary://123:secret@cloud';
		$this->assertSame( $expected, Cloudinary\Connect::sanitize_connection_url( $raw ) );
	}

	/** @test */
	public function it_returns_an_empty_string_for_empty_input() {
		$this->assertSame( '', Cloudinary\Connect::sanitize_connection_url( '' ) );
	}

	/** @test */
	public function it_casts_non_string_input_to_string() {
		// null cast to (string) is ''; prefix-only strings become empty.
		$this->assertSame( '', Cloudinary\Connect::sanitize_connection_url( null ) );
	}

	/** @test */
	public function it_does_not_strip_prefix_that_appears_mid_string() {
		// The regex is anchored to ^, so an embedded occurrence is left alone.
		$raw = 'cloudinary://123:CLOUDINARY_URL=secret@cloud';
		$this->assertSame( $raw, Cloudinary\Connect::sanitize_connection_url( $raw ) );
	}

	// -----------------------------------------------------------------------
	// CLOUDINARY_VARIABLE_REGEX
	// -----------------------------------------------------------------------

	/**
	 * Helper: run the regex against a (pre-sanitized) URL.
	 *
	 * @param string $url URL to test.
	 * @return bool
	 */
	private function matches_regex( $url ) {
		return (bool) preg_match(
			'~' . Cloudinary\Connect::CLOUDINARY_VARIABLE_REGEX . '~',
			$url
		);
	}

	/** @test */
	public function regex_accepts_a_valid_url_without_prefix() {
		$this->assertTrue( $this->matches_regex( 'cloudinary://123456:AbC-dEf_0@mycloud' ) );
	}

	/** @test */
	public function regex_accepts_a_valid_url_with_uppercase_prefix() {
		// The regex itself still allows the prefix; sanitize_connection_url() removes
		// it before storage, but the regex must tolerate it during the transition.
		$this->assertTrue( $this->matches_regex( 'CLOUDINARY_URL=cloudinary://123456:AbC@mycloud' ) );
	}

	/** @test */
	public function regex_rejects_a_url_missing_the_cloudinary_scheme() {
		$this->assertFalse( $this->matches_regex( 'https://123456:secret@mycloud' ) );
	}

	/** @test */
	public function regex_rejects_a_url_with_non_numeric_api_key() {
		$this->assertFalse( $this->matches_regex( 'cloudinary://NOT_A_NUMBER:secret@mycloud' ) );
	}

	/** @test */
	public function regex_rejects_a_url_missing_the_cloud_name() {
		// Missing host part – '@' at end with nothing following.
		$this->assertFalse( $this->matches_regex( 'cloudinary://123:secret@' ) );
	}

	/** @test */
	public function regex_rejects_an_empty_string() {
		$this->assertFalse( $this->matches_regex( '' ) );
	}

	// -----------------------------------------------------------------------
	// Integration: sanitize then validate
	// -----------------------------------------------------------------------

	/** @test */
	public function sanitized_prefixed_url_passes_regex_validation() {
		$raw       = 'CLOUDINARY_URL=cloudinary://987654321:MySecret_Key-01@production';
		$sanitized = Cloudinary\Connect::sanitize_connection_url( $raw );
		$this->assertTrue( $this->matches_regex( $sanitized ) );
	}

	/** @test */
	public function sanitized_whitespace_padded_url_passes_regex_validation() {
		$raw       = "  cloudinary://111222333:pass@staging  \n";
		$sanitized = Cloudinary\Connect::sanitize_connection_url( $raw );
		$this->assertTrue( $this->matches_regex( $sanitized ) );
	}

	/** @test */
	public function malformed_url_fails_even_after_sanitization() {
		// Missing API secret.
		$raw       = 'CLOUDINARY_URL=cloudinary://123456@cloud';
		$sanitized = Cloudinary\Connect::sanitize_connection_url( $raw );
		$this->assertFalse( $this->matches_regex( $sanitized ) );
	}
}
