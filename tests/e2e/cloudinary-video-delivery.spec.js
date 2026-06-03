/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { ensureCloudinaryConnected } = require( './utils/connection' );

let cloudName;

test.describe( 'Cloudinary video delivery', () => {
	test.beforeAll( () => {
		( { cloudName } = ensureCloudinaryConnected() );
	} );

	test( 'serves video from a core/video block via Cloudinary', async () => {
		// Sentinel assertion: will be replaced in Task 4.
		expect( cloudName, 'cloudName should be parsed from env' ).toBeTruthy();
		expect( false, 'placeholder — to be implemented' ).toBe( true );
	} );
} );
