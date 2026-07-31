/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { wpCli } = require( './utils/wizard' );
const { fakeCloudinaryConnected } = require( './utils/connection' );
const {
	clearAnalyticsEvents,
	findAnalyticsEvents,
	readAnalyticsEvents,
} = require( './utils/analytics' );

const PLUGIN_SLUG = 'cloudinary_wordpress';

const SEL = {
	deactivateLink:
		'tr[data-plugin*="cloudinary"] .cld-deactivate-link, tr[data-plugin*="cloudinary"] .cld-deactivate',
	modal: '#cloudinary-deactivation',
	cancelButton: '.cloudinary-deactivation button[data-action="cancel"]',
	skipButton: '.cloudinary-deactivation button[data-action="deactivate"]',
};

test.describe( 'Deactivation analytics', () => {
	test.beforeEach( async () => {
		// Fake a connected state (no live Cloudinary credentials required)
		// so the connected/reason-picker modal — rather than the
		// not-connected "contact me" modal — is what renders.
		fakeCloudinaryConnected();
		clearAnalyticsEvents();
		wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
	} );

	test.afterEach( () => {
		// "Skip and deactivate" genuinely deactivates the plugin; restore it
		// so later specs in the suite aren't affected.
		wpCli( [ 'plugin', 'activate', PLUGIN_SLUG ] );
	} );

	test( 'opening the modal emits deactivation_modal_viewed', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'plugins.php' );

		await page.locator( SEL.deactivateLink ).first().click();
		await expect( page.locator( SEL.modal ) ).toBeVisible();

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'deactivation_modal_viewed'
		);

		expect(
			events.length,
			'Expected a deactivation_modal_viewed event when the modal opened.'
		).toBeGreaterThan( 0 );

		const event = events[ events.length - 1 ];
		expect( event.event_category ).toBe( 'deactivation' );
		expect( event.is_connected ).toBe( true );

		// Close without actually deactivating.
		await page.locator( SEL.cancelButton ).click();
	} );

	test( '"Skip and deactivate" emits deactivation_skipped', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'plugins.php' );

		await page.locator( SEL.deactivateLink ).first().click();
		await expect( page.locator( SEL.modal ) ).toBeVisible();

		await page.locator( SEL.skipButton ).click();

		// The click navigates to the real WP deactivate link (after a short
		// delay to let the fire-and-forget analytics request go out), so
		// wait for that navigation rather than any in-page state change.
		await page.waitForURL( /plugins\.php/, { timeout: 15_000 } );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'deactivation_skipped'
		);

		expect(
			events.length,
			'Expected a deactivation_skipped event before the deactivate navigation.'
		).toBeGreaterThan( 0 );
		expect( events[ events.length - 1 ].event_category ).toBe(
			'deactivation'
		);
	} );
} );
