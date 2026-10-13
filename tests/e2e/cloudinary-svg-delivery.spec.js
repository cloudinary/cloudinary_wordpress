/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( './fixtures' );

/**
 * Internal dependencies
 */
const { ensureCloudinaryConnected } = require( './utils/connection' );
const { expectCloudinaryUrl } = require( './utils/delivery' );
const { wpCli, wpEvalFile } = require( './utils/wizard' );

const VALID_FIXTURE = path.join( __dirname, 'fixtures', 'svg-valid.svg' );
const UNSAFE_FIXTURE = path.join( __dirname, 'fixtures', 'svg-unsafe.svg' );

/**
 * Settings the spec depends on.
 *
 * `image_format` and `image_quality` are pinned because the analytics specs
 * flip them and leave them changed. With a fixed global format such as
 * `webp`, the first render after sync delivers the SVG as `f_webp` without
 * `fl_sanitize`, because `Media::get_cloudinary_id()` swaps the extension
 * for the global format.
 *
 * @type {Record<string, string>}
 */
const SETTINGS = {
	svg_support: 'on',
	image_format: 'auto',
	image_quality: 'auto',
};

let cloudName;

/** @type {Record<string, string>|null} */
let previousSettings = null;

/**
 * Attachments and posts created by the current test, removed in afterEach.
 *
 * @type {number[]}
 */
let createdIds = [];

/**
 * Save plugin settings and return their previous values.
 *
 * Goes through the settings API rather than `wp option patch` so the values
 * land in the same place the settings screen saves them.
 *
 * @param {Record<string, string>} values Setting slug to value.
 * @return {Record<string, string>} Setting slug to the value before the write.
 */
function saveSettings( values ) {
	return JSON.parse(
		wpEvalFile( `
			$settings = get_plugin_instance()->settings;
			$previous = array();
			foreach ( json_decode( '${ JSON.stringify(
				values
			) }', true ) as $slug => $value ) {
				$setting           = $settings->get_setting( $slug );
				$previous[ $slug ] = $setting->get_value();
				$setting->save_value( $value );
			}
			echo wp_json_encode( $previous );
		` )
	);
}

/**
 * Upload an SVG fixture, embed it in a published post and sync it.
 *
 * The filename carries a timestamp so every run gets a fresh public ID.
 * A reused public ID could be served from a CDN cache filled by an
 * earlier run with different content.
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @param {string} fixturePath  Path to the SVG fixture.
 * @param {string} name         Filename prefix.
 * @return {Promise<{ attachmentId: number, postLink: string, media: Object }>} Created objects.
 */
async function uploadSvgPost( requestUtils, fixturePath, name ) {
	const media = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/media',
		// Skip `missing_image_sizes`: core reads width/height from the
		// metadata, which an SVG does not have until it syncs, and the
		// resulting PHP warning corrupts the JSON when WP_DEBUG displays
		// errors.
		params: { _fields: 'id,mime_type,source_url' },
		headers: {
			'Content-Type': 'image/svg+xml',
			'Content-Disposition': `attachment; filename="${ name }-${ Date.now() }.svg"`,
		},
		data: fs.readFileSync( fixturePath ),
	} );
	createdIds.push( media.id );

	const content =
		`<!-- wp:image {"id":${ media.id }} -->\n` +
		`<figure class="wp-block-image"><img src="${ media.source_url }" alt="" class="wp-image-${ media.id }"/></figure>\n` +
		`<!-- /wp:image -->`;

	const post = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/posts',
		data: {
			status: 'publish',
			title: `Cloudinary SVG e2e ${ Date.now() }`,
			content,
		},
	} );
	createdIds.push( post.id );

	// Same reason as the image delivery spec: sync synchronously instead
	// of waiting for the auto-sync queue.
	wpCli( [ 'cloudinary', 'sync' ] );

	return { attachmentId: media.id, postLink: post.link, media };
}

/**
 * Read the sync state of an attachment from the plugin.
 *
 * Uses `been_synced()` rather than `is_synced()`: the latter also compares
 * the `relation` signature, which changes between the WP-CLI sync and the
 * next request for every asset type, so it is false here even for a JPEG.
 *
 * @param {number} attachmentId Attachment ID.
 * @return {{ synced: boolean, publicId: string }} Sync state.
 */
function syncState( attachmentId ) {
	return JSON.parse(
		wpEvalFile( `
			$plugin = get_plugin_instance();
			$media  = $plugin->get_component( 'media' );
			echo wp_json_encode( array(
				'synced'   => $media->has_public_id( ${ attachmentId } ) && $plugin->get_component( 'sync' )->been_synced( ${ attachmentId } ),
				'publicId' => $media->get_public_id( ${ attachmentId } ),
			) );
		` )
	);
}

/**
 * Open the post and return the Cloudinary URL the plugin rendered for the
 * attachment's image block.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          postLink     Post permalink.
 * @param {number}                          attachmentId Attachment ID.
 * @return {Promise<string>} The `src` of the rendered image.
 */
async function renderedSrc( page, postLink, attachmentId ) {
	await page.goto( postLink );

	const img = page
		.locator( `.wp-block-image img.wp-image-${ attachmentId }` )
		.first();
	await img.scrollIntoViewIfNeeded();

	// Lazy loading can leave a placeholder in `src` until the JS swaps in
	// the delivery URL, so wait for the Cloudinary URL to appear.
	await expect(
		img,
		'the SVG should be delivered from Cloudinary'
	).toHaveAttribute( 'src', /^https:\/\/res\.cloudinary\.com\// );

	return img.getAttribute( 'src' );
}

/**
 * Fetch a delivery URL and return its content type and body.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @param {string}                                       url
 * @return {Promise<{ contentType: string, body: string }>} Response details.
 */
async function fetchAsset( request, url ) {
	const response = await request.get( url );
	expect( response.status(), `status of ${ url }` ).toBe( 200 );
	return {
		contentType: response.headers()[ 'content-type' ] || '',
		body: await response.text(),
	};
}

// @serial: needs real credentials in `cloudinary_connect` for `wp cloudinary
// sync`, while every analytics spec overwrites that option with fake ones.
test.describe( 'Cloudinary SVG upload and delivery', { tag: '@serial' }, () => {
	test.beforeAll( () => {
		( { cloudName } = ensureCloudinaryConnected() );

		// SVG support is off by default. Without it WordPress rejects the
		// upload and the plugin does not sync or sanitize SVGs.
		previousSettings = saveSettings( SETTINGS );
	} );

	test.afterAll( () => {
		if ( previousSettings ) {
			saveSettings( previousSettings );
			previousSettings = null;
		}
	} );

	test.afterEach( () => {
		const ids = createdIds;
		createdIds = [];

		// WP-CLI rather than REST for the same reason as the image delivery
		// spec: `force=true` on a `?rest_route=` URL is fragile.
		for ( const id of ids ) {
			try {
				wpCli( [ 'post', 'delete', String( id ), '--force' ] );
			} catch ( e ) {
				console.warn( `Cleanup of post ${ id } failed:`, e.message );
			}
		}
	} );

	test( 'a valid SVG uploads, syncs and is delivered as an SVG', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		const { attachmentId, postLink, media } = await uploadSvgPost(
			requestUtils,
			VALID_FIXTURE,
			'svg-valid'
		);

		expect( media.mime_type, 'WordPress should accept the SVG' ).toBe(
			'image/svg+xml'
		);

		const state = syncState( attachmentId );
		expect( state.synced, 'the SVG should sync to Cloudinary' ).toBe(
			true
		);
		expect(
			state.publicId,
			'the SVG should have a public ID'
		).toBeTruthy();

		// The attachment URL the REST API returns after sync.
		const synced = await requestUtils.rest( {
			path: `/wp/v2/media/${ attachmentId }`,
			params: { _fields: 'source_url' },
		} );
		expectCloudinaryUrl( synced.source_url, cloudName );
		expect(
			new URL( synced.source_url ).pathname,
			'the attachment URL should point at the SVG asset'
		).toMatch( /\.svg$/ );

		// The URL the front end renders must serve SVG, not a raster
		// conversion of it.
		const src = await renderedSrc( page, postLink, attachmentId );
		expectCloudinaryUrl( src, cloudName );

		const { contentType, body } = await fetchAsset( request, src );
		expect( contentType ).toContain( 'image/svg+xml' );
		expect( body ).toMatch( /<svg[\s>]/ );
		expect( body, 'the SVG content should be intact' ).toContain(
			'<circle'
		);
	} );

	test( 'an SVG with inline script is sanitized on upload', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		// Guard against a fixture edit that would make this test pass
		// vacuously.
		const fixture = fs.readFileSync( UNSAFE_FIXTURE, 'utf8' );
		expect( fixture ).toContain( '<script' );
		expect( fixture ).toContain( 'onload=' );

		const { attachmentId, postLink, media } = await uploadSvgPost(
			requestUtils,
			UNSAFE_FIXTURE,
			'svg-unsafe'
		);

		expect( media.mime_type ).toBe( 'image/svg+xml' );
		expect(
			syncState( attachmentId ).synced,
			'the unsafe SVG should still sync'
		).toBe( true );

		const src = await renderedSrc( page, postLink, attachmentId );
		expectCloudinaryUrl( src, cloudName );

		const { contentType, body } = await fetchAsset( request, src );
		expect( contentType ).toContain( 'image/svg+xml' );
		expect( body, 'the delivered asset should still be an SVG' ).toMatch(
			/<svg[\s>]/
		);
		expect( body, 'the <script> element should be stripped' ).not.toMatch(
			/<script/i
		);
		expect( body, 'inline event handlers should be stripped' ).not.toMatch(
			/\son\w+\s*=/i
		);
	} );
} );
