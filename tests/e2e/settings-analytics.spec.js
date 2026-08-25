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

const SEL = {
	saveButton: 'button[name="cld_submission"]',
	noticeDismiss: '.notice-dismiss',
	specialOfferLink: '.cld-special-offer-link',
	// The special-offer sidebar panel's collapse toggle — its data-toggle
	// path is deterministic given the plugin's fixed sidebar content order.
	specialOfferToggle: '.cld-ui-collapse[data-toggle="sidebar.0.4.1"]',
};

test.describe( 'Settings & navigation analytics', () => {
	test.beforeEach( () => {
		fakeCloudinaryConnected();
		clearAnalyticsEvents();
	} );

	test( 'loading a settings page emits settings_page_viewed', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'admin.php',
			'page=cloudinary_image_settings'
		);
		await page.waitForLoadState( 'networkidle' );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'settings_page_viewed'
		);

		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].page ).toBe( 'image_settings' );
	} );

	test( 'saving a setting emits settings_saved', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'admin.php',
			'page=cloudinary_image_settings'
		);

		// Flip the image format select to force a real change.
		const formatSelect = page.locator(
			'select[name="image_settings[image_format]"]'
		);
		const current = await formatSelect.inputValue();
		const nextValue = 'webp' === current ? 'auto' : 'webp';
		await formatSelect.selectOption( nextValue );

		await page.locator( SEL.saveButton ).click();
		await page.waitForLoadState( 'networkidle' );

		const savedEvents = findAnalyticsEvents(
			readAnalyticsEvents(),
			'settings_saved'
		);
		expect( savedEvents.length ).toBe( 1 );
		expect( savedEvents[ 0 ].page ).toBe( 'image_settings' );
		expect( savedEvents[ 0 ].changed_keys ).toContain( 'image_format' );
	} );

	test( 'dismissing an admin notice emits notice_dismissed', async ( {
		admin,
		page,
	} ) => {
		// rest_dismiss_notice() doesn't validate that `token` corresponds to
		// a real rendered notice — it just sets a transient and tracks the
		// event — so hitting the REST route directly (what notices.js's own
		// dismiss handler does, via a plain AJAX POST) tests the actual
		// analytics wiring without depending on which notices happen to be
		// showing (the app's real ones are all duration=0 one-shot notices
		// that never call this endpoint at all).
		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );

		const nonce = await page.evaluate(
			() => window.cldData?.analytics?.nonce
		);
		const endpoint = await page.evaluate(
			() => window.cldData?.analytics?.endpoint
		);
		const restBase = endpoint.replace( /\/events$/, '' );

		const response = await page.request.post(
			`${ restBase }/dismiss_notice`,
			{
				headers: { 'X-WP-Nonce': nonce },
				data: { token: 'e2e_test_notice', duration: 3600 },
			}
		);
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 300 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'notice_dismissed'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].notice_id ).toBe( 'e2e_test_notice' );
	} );

	test( 'clicking the special-offer link emits special_offer_clicked', async ( {
		admin,
		page,
	} ) => {
		// The offer only renders for a Free-plan account.
		wpCli( [
			'option',
			'update',
			'_cloudinary_last_usage',
			'\'{"plan":"free"}\'',
			'--format=json',
		] );

		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );

		// The offer link sits inside nested collapsible sidebar panels,
		// closed by default — an unrelated accordion UI this test has no
		// reason to drive. The element and its click listener exist in the
		// DOM regardless of the panels' visual open/closed state, so a raw
		// DOM click (bypassing Playwright's visibility checks) is enough to
		// exercise the actual thing under test: the event wiring.
		const offerLink = page.locator( SEL.specialOfferLink );
		await expect( offerLink ).toHaveCount( 1 );
		await offerLink.evaluate( ( el ) => el.click() );
		await page.waitForTimeout( 300 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'special_offer_clicked'
		);
		expect( events.length ).toBeGreaterThan( 0 );
		expect( events[ 0 ].offer_id ).toBe( 'small_plan_29' );
	} );
} );
