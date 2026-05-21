/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { ensureCloudinaryConnected } = require( './utils/connection' );

let cloudName;

test.describe( 'Cloudinary image delivery', () => {
	test.beforeAll( () => {
		( { cloudName } = ensureCloudinaryConnected() );
	} );

	test( 'serves featured image and inline image via Cloudinary', async () => {
		// Sentinel assertion: will be replaced in Task 5.
		expect( cloudName, 'cloudName should be parsed from env' ).toBeTruthy();
		expect( false, 'placeholder — to be implemented' ).toBe( true );
	} );
} );
