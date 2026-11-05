<?php
/**
 * Tests for Cloudinary\Sync\Download_Sync.
 *
 * These cover the asset download endpoint used by the media library import:
 * - a failed download is returned as an error instead of reported as a success, and does not
 *   leave the primed file behind;
 * - handle_failed_download() logs the failure, and only deletes an attachment that is still
 *   waiting for its first download and that the current user can delete;
 * - the endpoint is only available for an attachment the current user can edit.
 *
 * HTTP requests are short-circuited with the pre_http_request filter, so no request leaves the
 * test environment.
 *
 * @package Cloudinary
 */

use Cloudinary\REST_API;
use Cloudinary\Sync;
use Cloudinary\Sync\Download_Sync;

/**
 * Covers the failure paths and permissions of Download_Sync.
 */
class Test_Download_Sync extends WP_UnitTestCase {

	/**
	 * A transformed Cloudinary URL, as posted by the media library widget.
	 */
	const SOURCE_URL = 'https://res.cloudinary.com/demo/image/upload/e_sepia/v1/folder/sample.jpg';

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * An author, who can upload files and edit or delete only their own attachments.
	 *
	 * @var int
	 */
	protected static $author_id;

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
	 * Create the users once for all tests.
	 *
	 * @param WP_UnitTest_Factory $factory The test factory.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Restore the PHP error log, remove the temporary log file and reset the REST server.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( $this->error_log_file ) {
			ini_set( 'error_log', (string) $this->previous_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
			wp_delete_file( $this->error_log_file );
			$this->error_log_file = null;
		}

		global $wp_rest_server;
		$wp_rest_server = null;

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
	 * @param int $author_id The attachment author.
	 *
	 * @return int
	 */
	protected function create_pending_attachment( $author_id = 0 ) {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'sample',
				'post_author'    => $author_id ? $author_id : self::$admin_id,
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
	 * Read the temporary PHP error log.
	 *
	 * @return string
	 */
	protected function get_error_log() {
		return (string) file_get_contents( $this->error_log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Call handle_failed_download(), which ends the request through wp_send_json_error().
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $error         The error.
	 *
	 * @return void
	 */
	protected function call_handle_failed_download( $attachment_id, $error ) {
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

		try {
			$this->get_download_sync()->handle_failed_download( $attachment_id, $error );
			$this->fail( 'handle_failed_download() should end the request.' );
		} catch ( WPDieException $e ) {
			// Expected: the JSON error has been sent.
			unset( $e );
		}
	}

	/**
	 * Build a request to the asset download endpoint.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return WP_REST_Request
	 */
	protected function asset_request( $attachment_id ) {
		$request = new WP_REST_Request( 'POST', '/' . REST_API::BASE . '/asset' );
		$request->set_param( 'attachment_id', $attachment_id );
		$request->set_param( 'src', self::SOURCE_URL );

		return $request;
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
	 * An exception thrown during the download is returned as an error, and does not leave the
	 * primed file behind.
	 *
	 * @return void
	 */
	public function test_download_asset_removes_primed_file_on_exception() {
		$attachment_id = $this->create_pending_attachment();
		add_filter(
			'pre_http_request',
			static function () {
				throw new Exception( 'Unexpected failure' );
			}
		);
		$files_before = $this->get_upload_files();

		$result = $this->get_download_sync()->download_asset( $attachment_id, self::SOURCE_URL );

		$this->assertWPError( $result );
		$this->assertSame( 'Unexpected failure', $result->get_error_message() );
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
		wp_set_current_user( self::$admin_id );
		$attachment_id = $this->create_pending_attachment();
		$this->capture_error_log();
		$this->expectOutputString( '{"success":false,"data":"deny or ACL failure"}' );

		$this->call_handle_failed_download( $attachment_id, 'deny or ACL failure' );

		$this->assertNull( get_post( $attachment_id ) );

		$log = $this->get_error_log();
		$this->assertStringContainsString( 'failed to import asset into attachment ' . $attachment_id, $log );
		$this->assertStringContainsString( 'public_id: folder/sample', $log );
		$this->assertStringContainsString( 'Deleting the attachment.', $log );
		$this->assertStringContainsString( 'Error: deny or ACL failure', $log );
	}

	/**
	 * Deleting the attachment of a failed import leaves the asset on Cloudinary alone.
	 *
	 * @return void
	 */
	public function test_handle_failed_download_does_not_delete_asset_on_cloudinary() {
		wp_set_current_user( self::$admin_id );
		$attachment_id = $this->create_pending_attachment();
		$this->capture_error_log();
		$this->expectOutputRegex( '/"success":false/' );

		// Make sure the hook that deletes assets on Cloudinary is registered, as on a connected site.
		$delete_sync = \Cloudinary\get_plugin_instance()->get_component( 'sync' )->managers['delete'];
		$callback    = array( $delete_sync, 'delete_asset' );
		if ( false === has_action( 'delete_attachment', $callback ) ) {
			$delete_sync->setup();
		}
		$priority = has_action( 'delete_attachment', $callback );

		// Record whether the hook is still attached while the attachment is deleted, and any request.
		$hooked_during_delete = null;
		add_action(
			'delete_attachment',
			static function () use ( &$hooked_during_delete, $callback ) {
				$hooked_during_delete = has_action( 'delete_attachment', $callback );
			},
			1
		);
		$requests = array();
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$requests ) {
				$requests[] = $url;

				return new WP_Error( 'http_request_blocked', 'Blocked in tests.' );
			},
			10,
			3
		);

		$this->call_handle_failed_download( $attachment_id, 'deny or ACL failure' );

		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFalse( $hooked_during_delete );
		$this->assertSame( array(), $requests );
		$this->assertSame( $priority, has_action( 'delete_attachment', $callback ) );
	}

	/**
	 * An author can import into, and so delete on failure, the attachment they just created.
	 *
	 * @return void
	 */
	public function test_handle_failed_download_deletes_own_pending_attachment_as_author() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->create_pending_attachment( self::$author_id );
		$this->capture_error_log();
		$this->expectOutputRegex( '/"success":false/' );

		$this->call_handle_failed_download( $attachment_id, 'deny or ACL failure' );

		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * An attachment that already has a file is not deleted.
	 *
	 * @return void
	 */
	public function test_handle_failed_download_keeps_attachment_with_a_file() {
		wp_set_current_user( self::$admin_id );
		$attachment_id = $this->create_pending_attachment();
		update_post_meta( $attachment_id, '_wp_attached_file', '2026/11/sample.jpg' );
		$this->capture_error_log();
		$this->expectOutputRegex( '/"success":false/' );

		$this->call_handle_failed_download( $attachment_id, 'deny or ACL failure' );

		$this->assertNotNull( get_post( $attachment_id ) );
		$this->assertStringContainsString( 'Keeping the attachment.', $this->get_error_log() );
	}

	/**
	 * An attachment the current user cannot delete is not deleted.
	 *
	 * @return void
	 */
	public function test_handle_failed_download_keeps_attachment_user_cannot_delete() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->create_pending_attachment( self::$admin_id );
		$this->capture_error_log();
		$this->expectOutputRegex( '/"success":false/' );

		$this->call_handle_failed_download( $attachment_id, 'deny or ACL failure' );

		$this->assertNotNull( get_post( $attachment_id ) );
	}

	/**
	 * An error from the remote server can't add lines to the log.
	 *
	 * @return void
	 */
	public function test_handle_failed_download_sanitizes_logged_error() {
		wp_set_current_user( self::$admin_id );
		$attachment_id = $this->create_pending_attachment();
		$this->capture_error_log();
		$this->expectOutputString( '{"success":false,"data":"deny Cloudinary: forged line"}' );

		$this->call_handle_failed_download( $attachment_id, "deny\nCloudinary: forged line" );

		$this->assertSame( 1, substr_count( trim( $this->get_error_log() ), "\n" ) + 1 );
	}

	/**
	 * The endpoint is available for an attachment the user can edit.
	 *
	 * @return void
	 */
	public function test_permission_allows_editable_attachment() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->create_pending_attachment( self::$author_id );

		$this->assertTrue( $this->get_download_sync()->rest_can_upload_files( $this->asset_request( $attachment_id ) ) );
	}

	/**
	 * The endpoint is not available for another user's attachment the user can't edit.
	 *
	 * @return void
	 */
	public function test_permission_denies_attachment_user_cannot_edit() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->create_pending_attachment( self::$admin_id );

		$this->assertFalse( $this->get_download_sync()->rest_can_upload_files( $this->asset_request( $attachment_id ) ) );
	}

	/**
	 * The endpoint is not available without a valid attachment.
	 *
	 * @return void
	 */
	public function test_permission_denies_missing_or_non_attachment_id() {
		wp_set_current_user( self::$admin_id );
		$post_id       = self::factory()->post->create();
		$download_sync = $this->get_download_sync();

		$this->assertFalse( $download_sync->rest_can_upload_files( $this->asset_request( 0 ) ) );
		$this->assertFalse( $download_sync->rest_can_upload_files( $this->asset_request( $post_id ) ) );
	}

	/**
	 * Through the REST route, an author can't make another user's attachment be deleted.
	 *
	 * @return void
	 */
	public function test_rest_route_rejects_attachment_user_cannot_edit() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->create_pending_attachment( self::$admin_id );
		$this->mock_http_response( $this->http_response( 401 ) );

		$response = rest_get_server()->dispatch( $this->asset_request( $attachment_id ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertNotNull( get_post( $attachment_id ) );
	}
}
