<?php
/**
 * Analytics egress capture — local/e2e dev helper mu-plugin.
 *
 * Intercepts outgoing POSTs to the Cloudinary custom-events collector
 * (analytics-api.cloudinary.com) and appends each payload as a JSONL entry
 * to a log file, so e2e tests and manual QA can assert on emitted events
 * without hitting the real collector.
 *
 * @package Cloudinary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the path to the capture log file.
 *
 * @return string
 */
function cld_analytics_capture_log_path() {
	$upload = wp_upload_dir();

	return $upload['basedir'] . '/analytics-capture.log';
}

add_filter( 'pre_http_request', 'cld_analytics_capture_intercept', 10, 3 );

/**
 * Logs outgoing analytics events, letting the request proceed unmodified.
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

	return $preempt;
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
