<?php
/**
 * Tests for Cloudinary\Sync\Download_Sync.
 *
 * These cover how a failed download from Cloudinary is reported: download_asset() and
 * import_asset() must return the error rather than report success, must not leave the primed
 * file behind, and handle_failed_download() must log the failure before deleting the attachment.
 *
 * HTTP requests are short-circuited with the pre_http_request filter, so no request leaves the
 * test environment.
 *
 * @package Cloudinary
 */

use Cloudinary\Sync;
use Cloudinary\Sync\Download_Sync;

/**
 * Covers the failure paths of Download_Sync.
 */
class Test_Download_Sync extends WP_UnitTestCase {

	/**
	 * A transformed Cloudinary URL, as posted by the media library widget.
	 */
	const SOURCE_URL = 'https://res.cloudinary.com/demo/image/upload/e_sepia/v1/folder/sample.jpg';

	/**
	 * Path to the temporary PHP error log used by a test, if any.
	 *
	 * @var string|null
	 */
	protected $error_log_file;

	/**
	 * The error_log ini value before the test changed it.
	 *
	 * @var string|false
	 */
	protected $previous_error_log;

	/**
	 * Restore the PHP error log and remove the temporary log file.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( $this->error_log_file ) {
			ini_set( 'error_log', (string) $this->previous_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
			wp_delete_file( $this->error_log_file );
			$this->error_log_file = null;
		}

		parent::tear_down();
	}

	/**
	 * Build a fully wired Download_Sync instance, reusing the plugin's real components.
	 *
	 * @return Download_Sync
	 */
	protected function get_download_sync() {
		$download_sync = new Download_Sync( \Cloudinary\get_plugin_instance() );
		$download_sync->setup();

		return $download_sync;
	}

	/**
	 * Create an attachment with no file, the way the media library import creates one before
	 * the asset is downloaded.
	 *
	 * @return int
	 */
	protected function create_pending_attachment() {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'sample',
			)
		);
		\Cloudinary\get_plugin_instance()->get_component( 'media' )->update_post_meta( $attachment_id, Sync::META_KEYS['public_id'], 'folder/sample' );

		return $attachment_id;
	}

	/**
	 * Make every HTTP request return the given response.
	 *
	 * @param array|WP_Error $response The response to return.
	 *
	 * @return void
	 */
	protected function mock_http_response( $response ) {
		add_filter(
			'pre_http_request',
			static function () use ( $response ) {
				return $response;
			}
		);
	}

	/**
	 * Build an HTTP response array with an empty body.
	 *
	 * @param int   $code    The HTTP status code.
	 * @param array $headers The response headers.
	 *
	 * @return array
	 */
	protected function http_response( $code, $headers = array() ) {
		return array(
			'headers'  => $headers,
			'body'     => '',
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * List the files in the current uploads folder, where download_asset() primes its file.
	 *
	 * @return string[]
	 */
	protected function get_upload_files() {
		$files = glob( trailingslashit( wp_upload_dir()['path'] ) . '*' );

		return false === $files ? array() : $files;
	}

	/**
	 * Send PHP's error log to a temporary file for the duration of the test.
	 *
	 * @return void
	 */
	protected function capture_error_log() {
		$this->error_log_file     = wp_tempnam( 'cloudinary-error-log' );
		$this->previous_error_log = ini_set( 'error_log', $this->error_log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	}

	/**
	 * A non-200 response returns the Cloudinary error header as a WP_Error.
	 *
	 * @return void
	 */
	public function test_download_asset_returns_error_on_non_200_response() {
		$attachment_id = $this->create_pending_attachment();
		$this->mock_http_response( $this->http_response( 401, array( 'x-cld-error' => 'deny or ACL failure' ) ) );

		$result = $this->get_download_sync()->download_asset( $attachment_id, self::SOURCE_URL );

		$this->assertWPError( $result );
		$this->assertSame( 'deny or ACL failure', $result->get_error_message() );
		$this->assertEmpty( get_attached_file( $attachment_id ) );
	}

	/**
	 * A non-200 response without a Cloudinary error header returns a generic error.
	 *
	 * @return void
	 */
	public function test_download_asset_returns_generic_error_without_error_header() {
		$attachment_id = $this->create_pending_attachment();
		$this->mock_http_response( $this->http_response( 503 ) );

		$result = $this->get_download_sync()->download_asset( $attachment_id, self::SOURCE_URL );

		$this->assertWPError( $result );
		$this->assertSame( 'Could not download the Cloudinary asset.', $result->get_error_message() );
	}

	/**
	 * A non-200 response does not leave the primed file in the uploads folder.
	 *
	 * @return void
	 */
	public function test_download_asset_removes_primed_file_on_non_200_response() {
		$attachment_id = $this->create_pending_attachment();
		$this->mock_http_response( $this->http_response( 401 ) );
		$files_before = $this->get_upload_files();

		$this->get_download_sync()->download_asset( $attachment_id, self::SOURCE_URL );

		$this->assertSame( $files_before, $this->get_upload_files() );
	}

	/**
	 * A transport error is returned as is, and does not leave the primed file behind.
	 *
	 * @return void
	 */
	public function test_download_asset_returns_transport_error_and_removes_primed_file() {
		$attachment_id = $this->create_pending_attachment();
		$this->mock_http_response( new WP_Error( 'http_request_failed', 'Could not resolve host' ) );
		$files_before = $this->get_upload_files();

		$result = $this->get_download_sync()->download_asset( $attachment_id, self::SOURCE_URL );

		$this->assertWPError( $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( $files_before, $this->get_upload_files() );
	}

	/**
	 * import_asset() returns the download error instead of reporting success.
	 *
	 * @return void
	 */
	public function test_import_asset_returns_download_error() {
		$attachment_id = $this->create_pending_attachment();
		$this->mock_http_response( $this->http_response( 401, array( 'x-cld-error' => 'deny or ACL failure' ) ) );

		$result = $this->get_download_sync()->import_asset( $attachment_id, self::SOURCE_URL, array() );

		$this->assertWPError( $result );
		$this->assertSame( 'deny or ACL failure', $result->get_error_message() );
	}

	/**
	 * handle_failed_download() logs the failure, deletes the attachment and sends the error.
	 *
	 * @return void
	 */
	public function test_handle_failed_download_logs_deletes_and_sends_error() {
		$attachment_id = $this->create_pending_attachment();
		$this->capture_error_log();

		// wp_send_json_error() ends the request through wp_die(); make that throw instead.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new WPDieException( 'wp_die called' );
				};
			},
			PHP_INT_MAX
		);
		$this->expectOutputString( '{"success":false,"data":"deny or ACL failure"}' );

		try {
			$this->get_download_sync()->handle_failed_download( $attachment_id, 'deny or ACL failure' );
			$this->fail( 'handle_failed_download() should end the request.' );
		} catch ( WPDieException $e ) {
			// Expected: the JSON error has been sent.
			unset( $e );
		}

		$this->assertNull( get_post( $attachment_id ) );

		$log = (string) file_get_contents( $this->error_log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( 'failed to import asset into attachment ' . $attachment_id, $log );
		$this->assertStringContainsString( 'public_id: folder/sample', $log );
		$this->assertStringContainsString( 'Error: deny or ACL failure', $log );
	}
}
