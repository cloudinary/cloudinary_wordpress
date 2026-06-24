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

module.exports = {
	parseCloudName,
	ensureCloudinaryConnected,
};
