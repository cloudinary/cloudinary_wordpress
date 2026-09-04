/**
 * Shared Playwright test object for the e2e suite.
 *
 * Wraps `@wordpress/e2e-test-utils-playwright`'s `test` so every spec file
 * runs with a per-worker marker attached to all of its WordPress traffic.
 * The `.wp-env/mu-plugins/analytics-capture.php` mu-plugin uses that marker
 * to route captured analytics events into a per-worker log file, which is
 * what lets the analytics specs run in parallel workers against one shared
 * WordPress install without their `clearAnalyticsEvents()` calls and exact
 * event-count assertions stepping on each other.
 *
 * The marker travels two ways:
 *
 * - `X-CLD-E2E-Worker` request header, via the browser context's
 *   `extraHTTPHeaders`, so page loads and `page.request.*` REST calls made by
 *   the plugin's PHP side are attributed to this worker.
 * - `CLD_E2E_WORKER` env var on the Playwright worker process, which
 *   `utils/wizard.js`'s `wpCli()` / `wpEvalFile()` forward into their
 *   `docker exec` calls so WP-CLI reads and writes the same per-worker log.
 *
 * Specs should import `test` and `expect` from this module instead of from
 * the WordPress package directly.
 */

const base = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Header name the mu-plugin reads the worker marker from.
 *
 * @type {string}
 */
const WORKER_HEADER = 'X-CLD-E2E-Worker';

/**
 * Builds the marker for a given Playwright worker.
 *
 * `parallelIndex` is stable across worker restarts (e.g. after a retry) and
 * bounded by the configured `workers` count, unlike `workerIndex` which keeps
 * incrementing, so the number of per-worker log files stays small.
 *
 * @param {import('@playwright/test').WorkerInfo} workerInfo
 * @return {string} Marker such as `w0`.
 */
function markerForWorker( workerInfo ) {
	return `w${ workerInfo.parallelIndex }`;
}

const test = base.test.extend( {
	// Worker-scoped and auto so it runs before any test in the worker, and
	// before the worker-scoped `requestUtils` fixture from the WP package
	// resolves. Setting `process.env` here is safe because each Playwright
	// worker is its own process.
	cldE2EWorkerMarker: [
		async ( {}, provide, workerInfo ) => {
			const marker = markerForWorker( workerInfo );
			process.env.CLD_E2E_WORKER = marker;
			await provide( marker );
			delete process.env.CLD_E2E_WORKER;
		},
		{ scope: 'worker', auto: true },
	],

	// Merge the marker header into whatever `extraHTTPHeaders` the config
	// already provides, rather than replacing it.
	extraHTTPHeaders: async ( { extraHTTPHeaders }, provide, testInfo ) => {
		await provide( {
			...( extraHTTPHeaders || {} ),
			[ WORKER_HEADER ]: markerForWorker( testInfo ),
		} );
	},
} );

module.exports = {
	...base,
	test,
	expect: base.expect,
	WORKER_HEADER,
};
