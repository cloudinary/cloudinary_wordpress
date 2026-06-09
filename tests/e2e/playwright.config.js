/**
 * External dependencies
 */
const { defineConfig, devices } = require( '@playwright/test' );
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

module.exports = defineConfig( {
	testDir: '.',
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : 'list',
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	timeout: 60_000,
	expect: {
		timeout: 10_000,
	},
	outputDir: path.join( process.cwd(), 'artifacts/test-results' ),
	globalSetup: require.resolve( './global-setup.js' ),
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
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
