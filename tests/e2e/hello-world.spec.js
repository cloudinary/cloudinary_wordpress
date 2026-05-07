/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Hello World', () => {
	test( 'front page loads with a non-empty title', async ( { page } ) => {
		const response = await page.goto( '/' );

		expect(
			response,
			'Expected a response from the home page.'
		).not.toBeNull();
		expect( response.status() ).toBeLessThan( 400 );

		const title = await page.title();
		expect( title.trim().length ).toBeGreaterThan( 0 );
	} );

	test( 'admin dashboard is reachable for logged-in admin', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'index.php' );

		await expect(
			page.locator( '#wpadminbar' ),
			'Admin bar should render on wp-admin.'
		).toBeVisible();
	} );
} );
