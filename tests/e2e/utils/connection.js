/**
 * Helpers for putting the Cloudinary plugin into a "connected" state
 * without driving the wizard UI.
 *
 * Setting the connection option directly via WP-CLI is faster and
 * keeps this spec decoupled from the wizard spec, which exercises
 * the UI path separately.
 */

const { wpCli, getCloudinaryUrlFromEnv } = require( './wizard' );

/**
 * Parse the cloud name out of a `cloudinary://key:secret@cloud_name` URL.
 *
 * @param {string} cloudinaryUrl
 * @return {string} The cloud_name segment.
 * @throws If the URL does not match the expected shape.
 */
function parseCloudName( cloudinaryUrl ) {
	const match = /^cloudinary:\/\/[^:]+:[^@]+@([A-Za-z0-9_-]+)/.exec(
		cloudinaryUrl
	);
	if ( ! match ) {
		throw new Error(
			`Could not parse cloud name from CLOUDINARY_E2E_URL: ${ cloudinaryUrl }`
		);
	}
	return match[ 1 ];
}

/**
 * Set the plugin's `cloudinary_connect` option directly so the plugin
 * is "connected" for the duration of the spec.
 *
 * Mirrors what the wizard saves on completion. We deliberately do
 * NOT pre-populate `cloudinary_connection_signature` or
 * `cloudinary_status`; the plugin will populate those on first need.
 *
 * @return {{ cloudName: string }} The cloud name extracted from the URL.
 */
function ensureCloudinaryConnected() {
	const cloudinaryUrl = getCloudinaryUrlFromEnv();
	const cloudName = parseCloudName( cloudinaryUrl );

	// Build the JSON payload the plugin expects.
	const payload = JSON.stringify( { cloudinary_url: cloudinaryUrl } );

	// `wp option update --format=json <name> <value>` requires the
	// value to be a valid JSON literal. Wrap in single quotes for the
	// docker-exec'd shell.
	wpCli( [
		'option',
		'update',
		'cloudinary_connect',
		`'${ payload }'`,
		'--format=json',
	] );

	return { cloudName };
}

/**
 * Fakes a "connected" state without calling the real Cloudinary API.
 *
 * Unlike `ensureCloudinaryConnected()`, this writes the connection + signature
 * options directly via `wp eval` (bypassing `update_option()`, so
 * `Connect::verify_connection()` — a `pre_update_option` filter — never
 * runs). Useful for flows that only need `Connect::is_connected()` to be
 * true (e.g. the deactivation modal's connected/not-connected branching) and
 * shouldn't depend on live Cloudinary credentials being available.
 *
 * @return {{ cloudName: string, cloudinaryUrl: string }} The fake cloud name and connection URL used.
 */
function fakeCloudinaryConnected() {
	const cloudName = 'e2e-fake-cloud';
	const cloudinaryUrl = `cloudinary://123456789012345:AbCdEfGhIjKlMnOpQrStUvWxYz1@${ cloudName }`;

	wpCli( [
		'eval',
		`'
		global $wpdb;
		$url = "${ cloudinaryUrl }";
		$wpdb->update( $wpdb->options, array( "option_value" => serialize( array( "cloudinary_url" => $url ) ) ), array( "option_name" => "cloudinary_connect" ) );
		update_option( "cloudinary_connection_signature", md5( $url ) );
		wp_cache_flush();
		'`,
	] );

	return { cloudName, cloudinaryUrl };
}

module.exports = {
	parseCloudName,
	ensureCloudinaryConnected,
	fakeCloudinaryConnected,
};
