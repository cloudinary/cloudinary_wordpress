/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( './fixtures' );

/**
 * Internal dependencies
 */
const { expectCloudinaryUrl } = require( './utils/delivery' );
const {
	connectLive,
	deletePostsLive,
	syncLive,
} = require( './utils/live-site' );

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );

const OVERLAY_TEXT = 'CldE2EOverlay';
const CROP = 'c_crop,h_120,w_160';

let cloudName;

/**
 * State shared by the tests in this file. Every test edits the same
 * attachment, so the tests run in order (see `mode: 'serial'`).
 *
 * @type {{ postId: number, postLink: string, attachmentId: number }|null}
 */
let created = null;

/**
 * Locate an Edit Asset field by its setting slug. The plugin's UI builds
 * IDs such as `edit_asset.edit_affects.text_overlay_text`, which contain
 * dots, so an attribute selector is used instead of `#id`.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          slug Setting slug.
 * @return {import('@playwright/test').Locator} The input.
 */
function assetField( page, slug ) {
	return page.locator( `[id="edit_asset.edit_affects.${ slug }"]` );
}

/**
 * Open the Edit Asset page for the test attachment and wait for the
 * preview script to initialise.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openEditAsset( page ) {
	await page.goto(
		`/wp-admin/admin.php?page=cloudinary&section=edit-asset&asset=${ created.attachmentId }`
	);

	// `asset-edit.js` appends the preview image on `window.load`; once it
	// is there, the save handlers are bound.
	await expect(
		page.locator( '#cld-asset-edit img' ),
		'the asset preview should render'
	).toBeVisible();
}

/**
 * Click a save button on the Edit Asset page and wait for the REST call.
 *
 * @param {import('@playwright/test').Page}    page
 * @param {import('@playwright/test').Locator} button
 * @return {Promise<Object>} The `save_asset` response body.
 */
async function saveAsset( page, button ) {
	const [ response ] = await Promise.all( [
		page.waitForResponse(
			( res ) =>
				res.url().includes( 'save_asset' ) &&
				res.request().method() === 'POST'
		),
		button.click(),
	] );
	expect( response.ok(), 'save_asset should succeed' ).toBe( true );

	const body = await response.json();
	expect(
		body.note,
		'the plugin should accept the transformation as valid'
	).toBeUndefined();

	return body;
}

/**
 * Open the published post and read the delivery attributes of the inline
 * image once the lazy-loader has swapped in the Cloudinary URL. `src` in
 * the result is the URL the browser loaded.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{ src: string, transformations: string }>} Delivery data.
 */
async function readDelivery( page ) {
	await page.goto( created.postLink );

	const img = page
		.locator( `.wp-block-image img.wp-image-${ created.attachmentId }` )
		.first();
	await expect( img, 'the inline image should render' ).toBeVisible();
	await img.scrollIntoViewIfNeeded();

	// The lazy-loader either writes the delivery URL to `src` or, when the
	// theme emits a srcset, restores `data-srcset` and leaves the SVG
	// placeholder in `src`. `currentSrc` is the URL the browser picked in
	// both cases.
	const currentSrc = () => img.evaluate( ( node ) => node.currentSrc );
	await expect
		.poll( currentSrc, {
			message: 'the lazy-loader should swap in the Cloudinary URL',
		} )
		.toMatch( /^https:\/\/res\.cloudinary\.com\// );

	const src = await currentSrc();
	expectCloudinaryUrl( src, cloudName );

	return {
		src,
		transformations:
			( await img.getAttribute( 'data-transformations' ) ) || '',
	};
}

// @live: needs real credentials in `cloudinary_connect` to sync assets,
// while every analytics spec overwrites that option with fake ones.
test.describe( 'Cloudinary per-asset editing', { tag: '@live' }, () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { requestUtils } ) => {
		( { cloudName } = connectLive() );

		const media = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/media',
			headers: {
				'Content-Type': 'image/jpeg',
				'Content-Disposition': `attachment; filename="asset-edit-e2e-${ Date.now() }.jpg"`,
			},
			data: fs.readFileSync( FIXTURE_PATH ),
		} );

		const content =
			`<!-- wp:image {"id":${ media.id }} -->\n` +
			`<figure class="wp-block-image"><img src="${ media.source_url }" alt="" class="wp-image-${ media.id }"/></figure>\n` +
			`<!-- /wp:image -->`;

		const post = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				status: 'publish',
				title: `Cloudinary asset edit e2e ${ Date.now() }`,
				content,
			},
		} );

		created = {
			postId: post.id,
			postLink: post.link,
			attachmentId: media.id,
		};

		// The Edit Asset page and the front-end rewrite both need the
		// asset to be synced to Cloudinary.
		syncLive();
	} );

	test.afterAll( () => {
		if ( ! created ) {
			return;
		}
		const { postId, attachmentId } = created;
		created = null;

		deletePostsLive( [ postId, attachmentId ] );
	} );

	test( 'text overlay updates the delivery URL and reverts when removed', async ( {
		page,
	} ) => {
		const baseline = await readDelivery( page );
		expect( baseline.src ).not.toContain( 'l_text:' );

		await openEditAsset( page );
		await assetField( page, 'text_overlay_text' ).fill( OVERLAY_TEXT );
		const saved = await saveAsset(
			page,
			page.locator( '#cld-asset-save-text-overlay' )
		);
		expect( saved.text_overlay ).toContain( OVERLAY_TEXT );

		const withOverlay = await readDelivery( page );
		for ( const value of [
			withOverlay.transformations,
			withOverlay.src,
		] ) {
			expect( value ).toContain( `l_text:Arial_20:${ OVERLAY_TEXT }` );
			expect( value ).toContain( 'fl_layer_apply' );
		}

		// "Reset All" only clears the form; saving persists the empty overlay.
		await openEditAsset( page );
		await expect( assetField( page, 'text_overlay_text' ) ).toHaveValue(
			OVERLAY_TEXT
		);
		await page.locator( '#cld-asset-remove-text-overlay' ).click();
		await expect( assetField( page, 'text_overlay_text' ) ).toHaveValue(
			''
		);
		const cleared = await saveAsset(
			page,
			page.locator( '#cld-asset-save-text-overlay' )
		);
		expect( cleared.text_overlay ).toBe( '' );

		expect( await readDelivery( page ) ).toEqual( baseline );
	} );

	test( 'crop updates the delivery URL and reverts when removed', async ( {
		page,
	} ) => {
		const baseline = await readDelivery( page );
		expect( baseline.src ).not.toContain( CROP );

		await openEditAsset( page );
		const input = assetField( page, 'transformations' );
		await input.fill( CROP );
		const saved = await saveAsset(
			page,
			page.locator( '#cld-asset-edit-save' )
		);
		expect( saved.transformations ).toBe( CROP );

		const withCrop = await readDelivery( page );
		expect( withCrop.transformations ).toContain( CROP );
		expect( withCrop.src ).toContain( CROP );

		await openEditAsset( page );
		await expect( input ).toHaveValue( CROP );
		await input.fill( '' );
		const cleared = await saveAsset(
			page,
			page.locator( '#cld-asset-edit-save' )
		);
		expect( cleared.transformations ).toBe( '' );

		expect( await readDelivery( page ) ).toEqual( baseline );
	} );
} );
