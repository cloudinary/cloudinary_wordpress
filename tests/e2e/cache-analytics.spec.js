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

const CACHE_POINT_PATH = '/e2e-test-cache-point/';

/**
 * Creates a real "cache point" (non-media asset parent) post via the same
 * Assets::create_asset_parent() the app itself uses, so the REST endpoints
 * under test have a genuine parent to resolve.
 */
function createCachePoint() {
	wpEvalFile( `
		$assets = get_plugin_instance()->get_component( 'assets' );
		$assets->create_asset_parent( '${ CACHE_POINT_PATH }', '1' );
	` );
}

/**
 * Reads the analytics endpoint + nonce the page's cldData exposes, and
 * returns the shared REST base (…/cloudinary/v1) for building requests.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{restBase: string, nonce: string}>} The REST base URL and nonce.
 */
async function getRestContext( page ) {
	const nonce = await page.evaluate( () => window.cldData?.analytics?.nonce );
	const endpoint = await page.evaluate(
		() => window.cldData?.analytics?.endpoint
	);
	return { restBase: endpoint.replace( /\/events$/, '' ), nonce };
}

test.describe( 'Non-media cache analytics', () => {
	test.beforeEach( () => {
		fakeCloudinaryConnected();
		clearAnalyticsEvents();
	} );

	test( 'viewing a cache point emits cache_items_viewed', async ( {
		admin,
		page,
	} ) => {
		createCachePoint();
		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );
		const { restBase, nonce } = await getRestContext( page );

		const response = await page.request.post( `${ restBase }/show_cache`, {
			headers: { 'X-WP-Nonce': nonce },
			data: { ID: CACHE_POINT_PATH },
		} );
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 300 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'cache_items_viewed'
		);
		expect( events.length ).toBe( 1 );
	} );

	test( 'toggling a cached item emits cache_items_toggled', async ( {
		admin,
		page,
	} ) => {
		const attachmentId = wpEvalFile( `
			$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'e2e-cache-toggle-test' ) );
			echo $id;
		` );

		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );
		const { restBase, nonce } = await getRestContext( page );

		const response = await page.request.post(
			`${ restBase }/disable_cache_items`,
			{
				headers: { 'X-WP-Nonce': nonce },
				data: { ids: [ Number( attachmentId ) ], state: 'disable' },
			}
		);
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 300 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'cache_items_toggled'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].enabled ).toBe( false );
		expect( events[ 0 ].item_count ).toBe( 1 );
	} );

	test( 'purging a single cache point emits asset_cache_purged', async ( {
		admin,
		page,
	} ) => {
		// rest_purge_all() resolves the `parent` param via
		// Assets::get_param(), which is only ever populated by
		// activate_parent() — an in-memory (non-persisted) call made during
		// Assets::activate_parents() for paths configured 'on' in settings.
		// create_asset_parent() alone (used by the other tests here, which
		// go through get_asset_parent() — a real, DB-backed lookup instead)
		// isn't enough for this specific endpoint. Rather than configure a
		// custom path setting, target one of the plugin's own default
		// non-media paths, which is already active for exactly this reason.
		const realCachePoint = 'wp-content/uploads/';

		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );
		const { restBase, nonce } = await getRestContext( page );

		const response = await page.request.post( `${ restBase }/purge_all`, {
			headers: { 'X-WP-Nonce': nonce },
			data: { parent: realCachePoint, count: false },
		} );
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 300 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'asset_cache_purged'
		);
		expect( events.length ).toBe( 1 );
	} );

	test( '"Purge all" emits all_cache_purged', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cloudinary' );
		const { restBase, nonce } = await getRestContext( page );

		const response = await page.request.post( `${ restBase }/purge_all`, {
			headers: { 'X-WP-Nonce': nonce },
			data: { count: false },
		} );
		expect( response.ok() ).toBeTruthy();
		await page.waitForTimeout( 300 );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'all_cache_purged'
		);
		expect( events.length ).toBe( 1 );
	} );

	test( 'uploading a non-media asset emits cache_uploaded', async () => {
		// Directly fires the cloudinary_uploaded_asset action Assets listens
		// to, filtered to non-media assets via is_asset_type() — a real
		// upload needs a genuine cache-point child asset and a live
		// Cloudinary round-trip, which this isolates from.
		wpEvalFile( `
			$assets = get_plugin_instance()->get_component( 'assets' );
			$parent_id = $assets->create_asset_parent( '${ CACHE_POINT_PATH }', '1' );
			$child_id  = wp_insert_post( array(
				'post_type'   => 'cloudinary_asset',
				'post_parent' => $parent_id,
				'post_status' => 'inherit',
				'post_title'  => 'e2e-cache-uploaded-test',
			) );
			do_action( 'cloudinary_uploaded_asset', $child_id, array( 'public_id' => 'e2e_test' ) );
		` );

		const events = findAnalyticsEvents(
			readAnalyticsEvents(),
			'cache_uploaded'
		);
		expect( events.length ).toBe( 1 );
		expect( events[ 0 ].status ).toBe( 'success' );
		expect( events[ 0 ].item_count ).toBe( 1 );
	} );
} );
