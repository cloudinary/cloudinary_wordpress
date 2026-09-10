/**
 * External dependencies
 */
const { test, expect } = require( './fixtures' );

/**
 * Internal dependencies
 */
const { wpCli, wpEvalFile } = require( './utils/wizard' );
const { fakeCloudinaryConnected } = require( './utils/connection' );
const {
	clearAnalyticsEvents,
	findAnalyticsEvents,
	readAnalyticsEvents,
} = require( './utils/analytics' );

const SEL = {
	// The Connect page has one `cld_submission` button per panel; scope to
	// the sync_media panel specifically since there are several on the page.
	saveSyncButton: 'button[name="cld_submission"][value="sync_media"]',
	autoSyncOff: '#connect\\.auto_sync_off',
	autoSyncOn: '#connect\\.auto_sync_on',
};

/**
 * Clears sync-queue run state so tests don't leak into each other — e.g. a
 * real bulk_sync_started REST call kicks off a genuine background sync
 * thread (via a non-blocking loopback request) that would otherwise keep
 * running, hitting the real Cloudinary API, and racing later tests.
 */
function resetSyncQueueState() {
	for ( const opt of [
		'_cloudinary_bulk_sync_enabled',
		'_cloudinary_sync_run_started',
		'_cloudinary_sync_run_tally_synced',
		'_cloudinary_sync_run_tally_errors',
		'_cloudinary_sync_queue',
	] ) {
		try {
			wpCli( [ 'option', 'delete', opt ] );
		} catch ( e ) {
			// Option not present; that's fine.
		}
	}
}

test.describe( 'Asset sync analytics', () => {
	test.beforeEach( () => {
		fakeCloudinaryConnected();
		clearAnalyticsEvents();
		resetSyncQueueState();
	} );

	test.afterEach( () => {
		// Stop any real background sync thread the bulk_sync_started test
		// may have kicked off before the next test starts.
		resetSyncQueueState();
	} );

	test( 'starting a bulk sync emits bulk_sync_started', async ( {
		admin,
		page,
	} ) => {
		// Preconditions rest_start_sync() needs: bulk sync enabled, at least
		// one delivery type on, and an unsynced attachment for build_queue()
		// to find. auto_sync is also turned off here: fakeCloudinaryConnected()
		// bypasses Connect::verify_connection(), which is what normally turns
		// it off on a real connect, so it's left at its 'on' default -- and a
		// background autosync thread (kicked off by admin.visitAdminPage()
		// below) can otherwise race build_queue() and claim the attachment
		// this test just inserted before the manual sync gets to it.
		wpCli( [ 'option', 'update', '_cloudinary_bulk_sync_enabled', '1' ] );
		wpEvalFile( `
			get_plugin_instance()->settings->get_setting( 'image_delivery' )->save_value( 'on' );
			get_plugin_instance()->settings->get_setting( 'auto_sync' )->save_value( 'off' );
			wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'e2e-sync-test' ) );
		` );

		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );

		const nonce = await page.evaluate(
			() => window.cldData?.analytics?.nonce
		);
		const endpoint = await page.evaluate(
			() => window.cldData?.analytics?.endpoint
		);
		const restBase = endpoint.replace( /\/events$/, '' );

		const response = await page.request.post( `${ restBase }/sync`, {
			headers: { 'X-WP-Nonce': nonce },
			data: { type: 'queue' },
		} );
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 500 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'bulk_sync_started'
		);

		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].trigger ).toBe( 'manual' );
		expect( events[ 0 ].asset_count ).toBeGreaterThan( 0 );
	} );

	test( 'a completed sync run emits asset_sync_failed and sync_completed', async () => {
		// Drives Sync_Queue's run-tracking state machine directly via the
		// same public/protected API a real bulk-sync run exercises
		// (Push_Sync::rest_start_sync() -> mark_run_started(), each
		// Sync::log_sync_result() call -> tally_run_result(), and the
		// queue draining -> shutdown_queue()/track_run_completed()).
		// A real end-to-end run needs actual Cloudinary uploads to complete
		// the queue, which is slow and flaky in CI; this exercises the exact
		// same code path the plan verified manually against wp-env.
		wpEvalFile( `
			$plugin = get_plugin_instance();
			$sync    = $plugin->get_component( 'sync' );
			$queue   = $sync->managers['queue'];

			$queue->mark_run_started( 'queue' );
			$attachment_id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'e2e-sync-result-test' ) );

			// One success, one failure.
			$sync->log_sync_result( $attachment_id, 'file', array( 'public_id' => 'e2e_test_public_id' ) );
			$sync->log_sync_result( $attachment_id, 'options', new \\WP_Error( '420', 'Rate limited' ) );

			$reflection = new \\ReflectionMethod( $queue, 'shutdown_queue' );
			$reflection->setAccessible( true );
			$reflection->invoke( $queue, 'queue' );
		` );

		const events = readAnalyticsEvents();

		const failedEvents = findAnalyticsEvents( events, 'asset_sync_failed' );
		expect( failedEvents.length ).toBe( 1 );
		expect( failedEvents[ 0 ].http_status ).toBe( 420 );

		const completedEvents = findAnalyticsEvents( events, 'sync_completed' );
		expect( completedEvents.length ).toBe( 1 );
		expect( completedEvents[ 0 ].total_synced ).toBe( 1 );
		expect( completedEvents[ 0 ].total_errors ).toBe( 1 );
	} );

	test( 'changing the sync method emits sync_settings_changed', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cloudinary_connect' );

		// Toggle to whichever value isn't already selected, so this doesn't
		// depend on auto_sync's starting state (a no-op save — same value in,
		// same value out — is skipped by save_settings()'s diff check).
		const offRadio = page.locator( SEL.autoSyncOff );
		const offAlreadyChecked = await offRadio.isChecked();
		const targetRadio = offAlreadyChecked
			? page.locator( SEL.autoSyncOn )
			: offRadio;
		const expectedValue = offAlreadyChecked ? 'on' : 'off';

		await targetRadio.check();
		await page.locator( SEL.saveSyncButton ).click();
		await page.waitForLoadState( 'networkidle' );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'sync_settings_changed'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].setting_key ).toBe( 'auto_sync' );
		expect( events[ 0 ].new_value ).toBe( expectedValue );
	} );
} );
