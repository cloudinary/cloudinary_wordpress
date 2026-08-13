/**
 * External dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const {
	getCloudinaryUrlFromEnv,
	resetCloudinaryConnection,
	visitWizard,
	wpCli,
} = require( './utils/wizard' );
const { fakeCloudinaryConnected } = require( './utils/connection' );
const {
	clearAnalyticsEvents,
	findAnalyticsEvents,
	readAnalyticsEvents,
} = require( './utils/analytics' );

// Selectors that come from ui-definitions/components/wizard.php.
const SEL = {
	connectionInput: 'input#connect\\.cloudinary_url',
	connectionSuccess: '#connection-success',
	nextBtn: 'button[data-navigate="next"]',
	tab2: '#tab-2',
	tab3: '#tab-3',
	tab4: '#tab-4',
};

test.describe( 'Connection management analytics', () => {
	test.beforeEach( async ( { context } ) => {
		resetCloudinaryConnection();
		clearAnalyticsEvents();
		// Clear the wizard's persisted progress, same as wizard-setup.spec.js,
		// so a fresh server-side state isn't overridden by stale localStorage.
		await context.addInitScript( () => {
			window.localStorage.removeItem( '_cld_wizard' );
		} );
	} );

	test( 'completing the wizard emits connection_string_updated', async ( {
		admin,
		page,
	} ) => {
		const cloudinaryUrl = getCloudinaryUrlFromEnv();

		await visitWizard( admin );

		await page.locator( SEL.nextBtn ).click(); // Tab 1 -> Tab 2.
		await expect( page.locator( SEL.tab2 ) ).toBeVisible();

		await page.locator( SEL.connectionInput ).fill( cloudinaryUrl );
		await expect( page.locator( SEL.connectionSuccess ) ).toHaveClass(
			/\bactive\b/,
			{ timeout: 30_000 }
		);

		await page.locator( SEL.nextBtn ).click(); // Tab 2 -> Tab 3.
		await expect( page.locator( SEL.tab3 ) ).toBeVisible();

		// This is the save_wizard REST call that persists `cloudinary_connect`
		// via `Settings::save()`, which is what actually fires
		// `Connect::verify_connection()` (a `pre_update_option` filter) — the
		// same choke point the general "Connect" settings page's save and
		// disconnect actions go through outside the wizard.
		await page.locator( SEL.nextBtn ).click(); // Tab 3 -> Tab 4.
		await expect( page.locator( SEL.tab4 ) ).toBeVisible( {
			timeout: 30_000,
		} );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'connection_string_updated'
		);

		expect(
			events.length,
			'Expected a connection_string_updated event after the wizard persisted the connection.'
		).toBeGreaterThan( 0 );

		const event = events[ events.length - 1 ];
		expect( event.event_category ).toBe( 'connection' );
		expect( event.status ).toBe( 'success' );
		expect( event.http_status ).toBe( 200 );
	} );

	test( 'removing the connection string emits connection_disconnected', async () => {
		// Fake a connected state, then a real update_option() call to empty
		// the URL — the same pre_update_option_cloudinary_connect choke
		// point the Connect settings page's "Disconnect" button goes
		// through — fires verify_connection()'s empty-URL branch.
		fakeCloudinaryConnected();
		clearAnalyticsEvents();

		wpCli( [
			'option',
			'update',
			'cloudinary_connect',
			'\'{"cloudinary_url":""}\'',
			'--format=json',
		] );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'connection_disconnected'
		);
		expect( events.length ).toBe( 1 );
	} );
} );
