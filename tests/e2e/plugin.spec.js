/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Cloudinary plugin', () => {
	test( 'is listed and active on the Plugins screen', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const pluginRow = page
			.locator(
				'tr[data-slug="cloudinary-image-management-and-manipulation-in-the-cloud-cdn"], tr[data-plugin*="cloudinary"]'
			)
			.first();

		await expect(
			pluginRow,
			'Cloudinary plugin row should appear on the Plugins screen.'
		).toBeVisible();

		await expect(
			pluginRow,
			'Cloudinary plugin row should be marked active.'
		).toHaveClass( /active/ );
	} );

	test( 'registers a top-level Cloudinary admin menu', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'index.php' );

		const menuLink = page.locator(
			'#adminmenu a.toplevel_page_cloudinary'
		);

		await expect(
			menuLink,
			'Cloudinary top-level menu should be registered.'
		).toBeVisible();

		await menuLink.click();

		await expect( page ).toHaveURL( /admin\.php\?page=cloudinary/ );
	} );
} );
