/**
 * Helpers for asserting on emitted analytics events in e2e specs.
 *
 * The `.wp-env/mu-plugins/analytics-capture.php` mu-plugin intercepts every
 * outgoing `pre_http_request` to the Cloudinary analytics collector and logs
 * the JSON payload, one event per line. These helpers read that log back via
 * a small WP-CLI command it registers, so tests can assert on server-side
 * events that never touch the browser's network stack (Playwright's
 * `page.route()` can't see them — they're PHP-side `wp_remote_post` calls).
 */

const { wpCli } = require( './wizard' );

/**
 * Reads all analytics events captured so far.
 *
 * @param {Object}  [options]
 * @param {boolean} [options.clear] Clear the log after reading it.
 * @return {Object[]} Parsed event payloads, oldest first.
 */
function readAnalyticsEvents( { clear = false } = {} ) {
	const args = [ 'cloudinary', 'analytics-events' ];
	if ( clear ) {
		args.push( '--clear' );
	}

	const out = wpCli( args );
	if ( ! out ) {
		return [];
	}

	return out
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => JSON.parse( line ) );
}

/**
 * Clears the analytics capture log.
 */
function clearAnalyticsEvents() {
	wpCli( [ 'cloudinary', 'analytics-events', '--clear' ] );
}

/**
 * Finds captured events matching a given `event_name`.
 *
 * @param {Object[]} events    Events, as returned by `readAnalyticsEvents()`.
 * @param {string}   eventName The `event_name` to filter for.
 * @return {Object[]} Matching events, oldest first.
 */
function findAnalyticsEvents( events, eventName ) {
	return events.filter( ( event ) => event.event_name === eventName );
}

module.exports = {
	readAnalyticsEvents,
	clearAnalyticsEvents,
	findAnalyticsEvents,
};
