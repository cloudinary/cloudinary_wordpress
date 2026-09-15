/**
 * External dependencies
 */
const { defineConfig, devices } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

// Load env vars from a project-root .env file so devs don't have to
// re-export CLOUDINARY_E2E_URL in every shell. The file is gitignored.
// Real shell env vars take precedence (override: false). `quiet: true`
// suppresses dotenv's promotional banner.
require( 'dotenv' ).config( {
	path: path.join( process.cwd(), '.env' ),
	override: false,
	quiet: true,
} );

const STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ||
	path.join( process.cwd(), 'artifacts/storage-states/admin.json' );

// @wordpress/e2e-test-utils-playwright reads WP_BASE_URL from the environment
// rather than from Playwright's `baseURL`, and falls back to
// http://localhost:8889 (see its build/config.js). Setting the variable here
// keeps the URL defined in one place: RequestUtils, the storage state and the
// browser contexts all agree, and a stale localhost default cannot send
// requests around the proxy.
const BASE_URL =
	process.env.WP_BASE_URL || 'https://tests.cloudinary.local.wpenv.net';

process.env.WP_BASE_URL = BASE_URL;

// The local environment is served over HTTPS by the proxy in .wp-env/proxy/,
// using a certificate from the locally generated CA. Chromium trusts it via the
// OS keychain (`npm run env:install-cert`), but Playwright's Node-side
// APIRequestContext -- which globalSetup uses to authenticate -- ships its own
// CA bundle and ignores the keychain.
//
// NODE_EXTRA_CA_CERTS is read once when Node starts, so it cannot be set from
// here; the test:e2e npm scripts export it instead. Fail loudly rather than let
// the run die later inside globalSetup with an opaque TLS error.
const LOCAL_CA_PATH = path.join( process.cwd(), '.wp-env/certs/rootCA.pem' );

if (
	BASE_URL.startsWith( 'https://' ) &&
	! process.env.NODE_EXTRA_CA_CERTS &&
	fs.existsSync( LOCAL_CA_PATH )
) {
	throw new Error(
		'NODE_EXTRA_CA_CERTS is not set, so Node cannot verify the local HTTPS certificate.\n' +
			'Run the suite with `npm run test:e2e`, which sets it for you.'
	);
}

module.exports = defineConfig( {
	testDir: '.',
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : 'list',
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	// Spec files are spread across workers; tests within one file still run
	// in order (fullyParallel is off), which the delivery specs' shared
	// beforeAll/afterAll state relies on. Specs tagged @serial mutate
	// site-wide state (connection, plugin activation) and are run in a
	// second, single-worker pass by `npm run test:e2e`; see package.json.
	// Analytics specs are safe to run concurrently because tests/e2e/fixtures.js
	// gives each worker its own analytics capture log.
	workers: 3,
	timeout: 60_000,
	expect: {
		timeout: 10_000,
	},
	outputDir: path.join( process.cwd(), 'artifacts/test-results' ),
	globalSetup: require.resolve( './global-setup.js' ),
	use: {
		baseURL: BASE_URL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		storageState: STORAGE_STATE_PATH,
		actionTimeout: 10_000,
		navigationTimeout: 15_000,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
