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
} = require( './utils/wizard' );

// Selectors that come from ui-definitions/components/wizard.php.
// Centralising them here makes the spec easier to maintain when the
// wizard markup changes.
const SEL = {
	connectionInput: 'input#connect\\.cloudinary_url',
	connectionSuccess: '#connection-success',
	connectionError: '#connection-error',
	connectionWorking: '#connection-working',
	tab1: '#tab-1',
	tab2: '#tab-2',
	tab3: '#tab-3',
	tab4: '#tab-4',
	nextBtn: 'button[data-navigate="next"]',
	completeLink: '#complete-wizard',
	wizardWrap: '.cld-wizard',
};

test.describe( 'Cloudinary wizard setup (WPP-1201)', () => {
	test.beforeEach( async ( { context } ) => {
		// Clear server-side state via WP-CLI.
		resetCloudinaryConnection();
		// Clear browser localStorage; the wizard persists its
		// progress under `_cld_wizard` and reads it back on init,
		// which would override our fresh server state.
		await context.addInitScript( () => {
			window.localStorage.removeItem( '_cld_wizard' );
		} );
	} );

	test( 'rejects an invalid connection string', async ( { admin, page } ) => {
		await visitWizard( admin );

		// Tab 1 is the welcome screen. Click Next to reach the connect tab.
		await expect( page.locator( SEL.tab1 ) ).toBeVisible();
		await page.locator( SEL.nextBtn ).click();
		await expect( page.locator( SEL.tab2 ) ).toBeVisible();

		// Type a clearly-malformed connection string. The wizard
		// debounces input and then calls /cloudinary/v1/test_connection.
		await page
			.locator( SEL.connectionInput )
			.fill( 'cloudinary://wrong:credential@invalidcloud' );

		// Error indicator gains the `active` class. The live API call
		// can take a few seconds; allow up to 15s.
		await expect( page.locator( SEL.connectionError ) ).toHaveClass(
			/\bactive\b/,
			{ timeout: 15_000 }
		);
		await expect( page.locator( SEL.connectionSuccess ) ).not.toHaveClass(
			/\bactive\b/
		);

		// Next must remain disabled (native disabled attribute).
		await expect( page.locator( SEL.nextBtn ) ).toBeDisabled();
	} );

	test( 'accepts a valid connection string and completes the wizard', async ( {
		admin,
		page,
	} ) => {
		const cloudinaryUrl = getCloudinaryUrlFromEnv();

		await visitWizard( admin );

		// Tab 1 → Tab 2.
		await page.locator( SEL.nextBtn ).click();
		await expect( page.locator( SEL.tab2 ) ).toBeVisible();

		// Provide real credentials.
		await page.locator( SEL.connectionInput ).fill( cloudinaryUrl );

		// Wait for the success indicator. Live API call + debounce
		// can take ~3–10s.
		await expect( page.locator( SEL.connectionSuccess ) ).toHaveClass(
			/\bactive\b/,
			{ timeout: 30_000 }
		);
		await expect( page.locator( SEL.connectionError ) ).not.toHaveClass(
			/\bactive\b/
		);
		await expect( page.locator( SEL.nextBtn ) ).toBeEnabled();

		// Tab 2 → Tab 3.
		await page.locator( SEL.nextBtn ).click();
		await expect( page.locator( SEL.tab3 ) ).toBeVisible();

		// Tab 3 → Tab 4. This click triggers the /save_wizard REST
		// call. While in flight, the Next button text changes to
		// "Setting up Cloudinary" and is briefly disabled. We don't
		// assert on the transient state; we just wait for tab 4.
		await page.locator( SEL.nextBtn ).click();
		await expect( page.locator( SEL.tab4 ) ).toBeVisible( {
			timeout: 30_000,
		} );

		// Final affordance: the "Go to plugin dashboard" link.
		await expect( page.locator( SEL.completeLink ) ).toBeVisible();
		await expect( page.locator( SEL.completeLink ) ).toHaveAttribute(
			'href',
			/page=cloudinary/
		);
	} );

	test( 'persists connection state so the wizard does not reappear', async ( {
		admin,
		page,
	} ) => {
		const cloudinaryUrl = getCloudinaryUrlFromEnv();

		// Walk the wizard to completion (same flow as the previous
		// test, intentionally duplicated so each test stands alone
		// and can be debugged in isolation).
		await visitWizard( admin );
		await page.locator( SEL.nextBtn ).click();
		await page.locator( SEL.connectionInput ).fill( cloudinaryUrl );
		await expect( page.locator( SEL.connectionSuccess ) ).toHaveClass(
			/\bactive\b/,
			{ timeout: 30_000 }
		);
		await page.locator( SEL.nextBtn ).click(); // → tab 3
		await expect( page.locator( SEL.tab3 ) ).toBeVisible();
		await page.locator( SEL.nextBtn ).click(); // → tab 4
		await expect( page.locator( SEL.tab4 ) ).toBeVisible( {
			timeout: 30_000,
		} );

		// Visit the plugin's main entry point. When connected, the
		// plugin renders the dashboard (NOT the wizard chrome).
		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );

		// Section param should not have flipped to wizard.
		await expect( page ).not.toHaveURL( /section=wizard/ );

		// Wizard wrapper element should be absent on the dashboard.
		await expect( page.locator( SEL.wizardWrap ) ).toHaveCount( 0 );
	} );
} );
