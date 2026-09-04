<?php
/**
 * Analytics egress capture — local/e2e dev helper mu-plugin.
 *
 * Intercepts outgoing requests to any Cloudinary analytics-api.cloudinary.com
 * endpoint — the custom-events collector AND the older deactivation-reason
 * collector, both on the same host — and appends each payload as a JSONL
 * entry to a log file, so e2e tests and manual QA can assert on emitted
 * events. The request is fully preempted with a synthetic response: earlier
 * versions of this mu-plugin only logged the payload and let the request
 * proceed, which meant every local/CI test run was quietly leaking synthetic
 * events (and deactivation "feedback") into the real production collector.
 *
 * Two additions support running the e2e suite in parallel Playwright
 * workers against this single WordPress install: the capture log is
 * per-worker (see cld_analytics_capture_worker_marker()), and Admin API
 * calls made with the fake e2e credentials are answered locally (see
 * cld_e2e_fake_cloud_intercept()).
 *
 * @package Cloudinary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the e2e worker marker for the current request, if any.
 *
 * Playwright runs spec files in parallel workers against this single
 * WordPress install. Each worker tags its browser/REST traffic with an
 * `X-CLD-E2E-Worker` header and its WP-CLI calls with a `CLD_E2E_WORKER`
 * env var, so every worker gets its own capture log and one worker's
 * events (or `--clear`) can't leak into another worker's assertions.
 *
 * Requests without a marker (manual QA, fire-and-forget loopback threads
 * spawned by the sync queue) fall back to the shared, unsuffixed log.
 *
 * @return string Sanitized marker, or empty string when none is present.
 */
function cld_analytics_capture_worker_marker() {
	$marker = '';

	if ( ! empty( $_SERVER['HTTP_X_CLD_E2E_WORKER'] ) ) {
		$marker = wp_unslash( $_SERVER['HTTP_X_CLD_E2E_WORKER'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} elseif ( false !== getenv( 'CLD_E2E_WORKER' ) && '' !== getenv( 'CLD_E2E_WORKER' ) ) {
		$marker = getenv( 'CLD_E2E_WORKER' );
	}

	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $marker );
}

/**
 * Returns the path to the capture log file for the current worker.
 *
 * @return string
 */
function cld_analytics_capture_log_path() {
	$upload = wp_upload_dir();
	$marker = cld_analytics_capture_worker_marker();
	$suffix = '' !== $marker ? '-' . $marker : '';

	return $upload['basedir'] . '/analytics-capture' . $suffix . '.log';
}

add_filter( 'pre_http_request', 'cld_analytics_capture_intercept', 10, 3 );
add_filter( 'pre_http_request', 'cld_e2e_fake_cloud_intercept', 10, 3 );

/**
 * Cloud name used by `fakeCloudinaryConnected()` in tests/e2e/utils/connection.js.
 */
const CLD_E2E_FAKE_CLOUD = 'e2e-fake-cloud';

/**
 * Short-circuits Cloudinary Admin API calls made with the fake e2e
 * credentials.
 *
 * Analytics specs fake a connection so `Connect::is_connected()` is true.
 * The dashboard then still calls the real Admin API for usage stats and
 * per-day history (`Connect::history()` issues one request per day, and the
 * 401 responses it gets are never cached because `is_wp_error()` entries
 * are refetched). Each real round-trip is ~1s, so one `page=cloudinary`
 * load can exceed Playwright's navigation timeout, and parallel workers
 * multiply the load. Answer those calls locally with the same 401 the real
 * API would return so the plugin's error handling still runs.
 *
 * @param false|array|WP_Error $preempt     Whether to preempt the request.
 * @param array                $parsed_args Parsed request arguments.
 * @param string               $url         The request URL.
 *
 * @return false|array|WP_Error
 */
function cld_e2e_fake_cloud_intercept( $preempt, $parsed_args, $url ) {
	if ( false === strpos( $url, 'api.cloudinary.com/v1_1/' . CLD_E2E_FAKE_CLOUD . '/' ) ) {
		return $preempt;
	}

	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Invalid credentials (e2e fake cloud)' ) ) ),
		'response' => array(
			'code'    => 401,
			'message' => 'Unauthorized',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Logs outgoing analytics/deactivation-reason requests and preempts them
 * with a synthetic success response, so nothing actually reaches the real
 * collector during local dev or CI runs.
 *
 * @param false|array|WP_Error $preempt     Whether to preempt the request.
 * @param array                $parsed_args Parsed request arguments.
 * @param string               $url         The request URL.
 *
 * @return false|array|WP_Error
 */
function cld_analytics_capture_intercept( $preempt, $parsed_args, $url ) {
	if ( false === strpos( $url, 'analytics-api.cloudinary.com' ) ) {
		return $preempt;
	}

	$body    = isset( $parsed_args['body'] ) ? $parsed_args['body'] : '';
	$decoded = json_decode( $body, true );

	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		cld_analytics_capture_log_path(),
		wp_json_encode( null !== $decoded ? $decoded : $body ) . "\n",
		FILE_APPEND | LOCK_EX
	);

	return array(
		'headers'  => array(),
		'body'     => '',
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Prints the captured analytics events, one JSON object per line.
 *
 * ## OPTIONS
 *
 * [--clear]
 * : Empty the log after printing it.
 *
 * @param array $args       Positional arguments.
 * @param array $assoc_args Associative arguments.
 */
function cld_analytics_capture_wpcli_command( $args, $assoc_args ) {
	$log_file = cld_analytics_capture_log_path();

	if ( file_exists( $log_file ) ) {
		WP_CLI::line( file_get_contents( $log_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	}

	if ( isset( $assoc_args['clear'] ) ) {
		file_put_contents( $log_file, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'cloudinary analytics-events', 'cld_analytics_capture_wpcli_command' );
}
