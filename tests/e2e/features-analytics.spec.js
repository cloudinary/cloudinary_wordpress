/**
 * External dependencies
 */
const { test, expect } = require( './fixtures' );

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

test.describe( 'Extensions & gallery analytics', () => {
	test.beforeEach( () => {
		fakeCloudinaryConnected();
		clearAnalyticsEvents();
	} );

	test( 'toggling an extension emits extension_toggled', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );

		// The Extensions panel is a collapsible sidebar widget, closed by
		// default — an unrelated accordion UI this test has no reason to
		// drive. A native checkbox .click() (bypassing Playwright's
		// visibility check) both flips .checked and fires 'change', which
		// is exactly what the app's own listener binds to.
		const toggle = page.locator( '[data-extension="media-library"]' );
		await expect( toggle ).toHaveCount( 1 );
		const wasChecked = await toggle.isChecked();
		await toggle.evaluate( ( el ) => el.click() );
		// extensions.js debounces the change handler by 1000ms before it
		// actually fires the tracked toggle call.
		await page.waitForTimeout( 1200 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'extension_toggled'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].extension_id ).toBe( 'media-library' );
		expect( events[ 0 ].enabled ).toBe( ! wasChecked );

		// Restore original state so this test doesn't change the site's
		// active extensions as a side effect.
		await toggle.evaluate( ( el ) => el.click() );
	} );

	test( 'saving gallery settings emits gallery_configured', async () => {
		// Drives Admin::save_settings() directly with a synthetic
		// gallery_config payload matching what the React gallery settings
		// panel serializes into its hidden field — the same shape the
		// implementation was verified manually against, avoiding having to
		// drive the full React UI for one settings save. The tag is unique
		// per run so this never coincidentally no-ops against a value left
		// over from a previous run (save_settings() skips unchanged keys).
		const uniqueTag = `e2e-${ Date.now() }`;
		wpEvalFile( `
			$admin  = get_plugin_instance()->get_component( 'admin' );
			$method = new \\ReflectionMethod( $admin, 'save_settings' );
			$method->setAccessible( true );

			$config = wp_json_encode( array(
				'displayProps' => array( 'mode' => 'expand' ),
				'mediaAssets'  => array( array( 'tag' => '${ uniqueTag }' ), array( 'tag' => 'b' ) ),
			) );
			$method->invoke( $admin, 'gallery', array( 'gallery_config' => $config ) );
		` );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'gallery_configured'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].layout ).toBe( 'expand' );
		expect( events[ 0 ].media_count ).toBe( 2 );
	} );
} );
