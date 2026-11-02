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
 * Add custom domain parameters to a connection string.
 *
 * @param {string}  cloudinaryUrl        The `cloudinary://` connection string.
 * @param {Object}  options
 * @param {string}  [options.cname]      Custom delivery domain.
 * @param {boolean} [options.privateCdn] Set to false to keep the cloud name
 *                                       in the delivery path.
 * @return {string} The connection string with the parameters added.
 */
function withConnectionParams( cloudinaryUrl, { cname, privateCdn } ) {
	const params = new URLSearchParams();
	if ( cname ) {
		params.set( 'cname', cname );
	}
	if ( false === privateCdn ) {
		params.set( 'private_cdn', 'false' );
	}
	const query = params.toString();
	if ( ! query ) {
		return cloudinaryUrl;
	}
	return `${ cloudinaryUrl }${
		cloudinaryUrl.includes( '?' ) ? '&' : '?'
	}${ query }`;
}

/**
 * Set the plugin's `cloudinary_connect` option directly so the plugin
 * is "connected" for the duration of the spec.
 *
 * Mirrors what the wizard saves on completion. We deliberately do
 * NOT pre-populate `cloudinary_connection_signature` or
 * `cloudinary_status`; the plugin will populate those on first need.
 *
 * With `cname`, the custom domain is added to the connection string the
 * same way a user adds it in the settings. The plugin only checks that it
 * looks like a domain and never resolves it, so a fake domain works.
 *
 * @param {Object}  [options]
 * @param {string}  [options.cname]      Custom delivery domain.
 * @param {boolean} [options.privateCdn] Set to false to keep the cloud name
 *                                       in the delivery path.
 * @return {{ cloudName: string }} The cloud name extracted from the URL.
 */
function ensureCloudinaryConnected( options = {} ) {
	const cloudinaryUrl = withConnectionParams(
		getCloudinaryUrlFromEnv(),
		options
	);
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

	// The plugin tests a changed connection string against the API before
	// it saves it, and keeps the old one when the test fails. Fail here
	// rather than let a spec run against the wrong connection.
	const saved = JSON.parse(
		wpCli( [ 'option', 'get', 'cloudinary_connect', '--format=json' ] )
	);
	if ( saved?.cloudinary_url !== cloudinaryUrl ) {
		throw new Error(
			'The plugin did not save the new Cloudinary connection string. Check that CLOUDINARY_E2E_URL is valid.'
		);
	}

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
		$value = serialize( array( "cloudinary_url" => $url ) );
		// A raw upsert — not update_option(), which would fire the
		// pre_update_option_cloudinary_connect filter (verify_connection())
		// and attempt a real API call with these fake credentials. The row
		// may not exist at all (e.g. after a real plugin_uninstalled test
		// run deletes it), so a plain UPDATE would silently affect zero rows.
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)
			 ON DUPLICATE KEY UPDATE option_value = %s",
			"cloudinary_connect", $value, "yes", $value
		) );
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
