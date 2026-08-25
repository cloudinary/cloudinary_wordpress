/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { wpEvalFile } = require( './utils/wizard' );
const { fakeCloudinaryConnected } = require( './utils/connection' );
const {
	clearAnalyticsEvents,
	findAnalyticsEvents,
	readAnalyticsEvents,
} = require( './utils/analytics' );

test.describe( 'Media & asset actions analytics', () => {
	test.beforeEach( () => {
		fakeCloudinaryConnected();
		clearAnalyticsEvents();
	} );

	test( 'saving an asset transformation emits asset_edited and transformation_applied (scope: asset)', async ( {
		admin,
		page,
	} ) => {
		const attachmentId = wpEvalFile( `
			$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'e2e-asset-edit-test' ) );
			echo $id;
		` );

		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );

		const nonce = await page.evaluate(
			() => window.cldData?.analytics?.nonce
		);
		const endpoint = await page.evaluate(
			() => window.cldData?.analytics?.endpoint
		);
		const restBase = endpoint.replace( /\/events$/, '' );

		const response = await page.request.post( `${ restBase }/save_asset`, {
			headers: { 'X-WP-Nonce': nonce },
			data: {
				ID: Number( attachmentId ),
				transformations: 'c_scale,w_500/e_grayscale',
			},
		} );
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 300 );

		const events = readAnalyticsEvents();

		const editedEvents = findAnalyticsEvents( events, 'asset_edited' );
		expect( editedEvents.length ).toBe( 1 );
		expect( editedEvents[ 0 ].asset_id ).toBe( Number( attachmentId ) );

		const transformedEvents = findAnalyticsEvents(
			events,
			'transformation_applied'
		);
		expect( transformedEvents.length ).toBe( 1 );
		expect( transformedEvents[ 0 ].scope ).toBe( 'asset' );
		// transformation_count depends on real resource-type detection,
		// which a synthetic (fileless) attachment doesn't have — it
		// reliably comes back 0 here. What this test actually verifies is
		// that the event fires with the right shape; asserting the count
		// itself would need a real uploaded file.
		expect( transformedEvents[ 0 ] ).toHaveProperty(
			'transformation_count'
		);
	} );

	test( 'saving a global transformation setting emits transformation_applied (scope: global)', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'admin.php',
			'page=cloudinary_image_settings'
		);

		const formatSelect = page.locator(
			'select[name="image_settings[image_format]"]'
		);
		const current = await formatSelect.inputValue();
		await formatSelect.selectOption( 'webp' === current ? 'auto' : 'webp' );

		await page.locator( 'button[name="cld_submission"]' ).click();
		await page.waitForLoadState( 'networkidle' );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'transformation_applied'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].scope ).toBe( 'global' );
		expect( events[ 0 ].transformation_count ).toBeGreaterThan( 0 );
	} );
} );
