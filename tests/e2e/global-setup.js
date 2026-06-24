/**
 * External dependencies
 */
const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );
const path = require( 'path' );
const fs = require( 'fs' );

/**
 * Global setup: authenticate admin and persist storage state for tests.
 *
 * Uses the RequestUtils helper from @wordpress/e2e-test-utils-playwright
 * which is the same utility used by Gutenberg's e2e test suite.
 *
 * @param {import('@playwright/test').FullConfig} config Resolved Playwright config.
 */
module.exports = async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	if ( ! storageStatePath ) {
		throw new Error( 'storageState path must be a string.' );
	}

	fs.mkdirSync( path.dirname( storageStatePath ), { recursive: true } );

	const requestContext = await request.newContext( {
		baseURL: baseURL || 'http://localhost:8889',
	} );

	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
		user: {
			username: 'admin',
			password: 'password',
		},
	} );

	await requestUtils.setupRest();
	await requestContext.dispose();
};
