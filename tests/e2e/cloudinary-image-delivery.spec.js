/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { ensureCloudinaryConnected } = require( './utils/connection' );
const { wpCli } = require( './utils/wizard' );

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );

let cloudName;

/**
 * Per-test scratch space populated by beforeEach.
 *
 * @type {{ postId: number, attachmentId: number, postLink: string }|null}
 */
let created = null;

/**
 * Assert that a given image URL is served by Cloudinary under the
 * expected cloud name. We intentionally do not assert specific
 * transformations — those are an implementation detail of the plugin
 * and may change.
 *
 * @param {string} rawUrl        The src or srcset candidate.
 * @param {string} expectedCloud The cloud name parsed from CLOUDINARY_E2E_URL.
 */
function expectCloudinaryUrl( rawUrl, expectedCloud ) {
	let parsed;
	try {
		parsed = new URL( rawUrl );
	} catch ( e ) {
		throw new Error( `Image URL is not parseable: ${ rawUrl }` );
	}
	expect( parsed.host, `host of ${ rawUrl }` ).toBe( 'res.cloudinary.com' );
	expect(
		parsed.pathname.startsWith( `/${ expectedCloud }/` ),
		`pathname of ${ rawUrl } should start with /${ expectedCloud }/`
	).toBe( true );
}

test.describe( 'Cloudinary image delivery', () => {
	test.beforeAll( () => {
		( { cloudName } = ensureCloudinaryConnected() );
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		// Upload the fixture image via REST.
		const file = fs.readFileSync( FIXTURE_PATH );
		const media = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/media',
			headers: {
				'Content-Type': 'image/jpeg',
				'Content-Disposition': 'attachment; filename="test-image.jpg"',
			},
			data: file,
		} );

		const attachmentId = media.id;
		const sourceUrl = media.source_url;

		// Create a published post that uses the attachment as both
		// featured image and an inline image block.
		const content =
			`<!-- wp:image {"id":${ attachmentId }} -->\n` +
			`<figure class="wp-block-image"><img src="${ sourceUrl }" alt="" class="wp-image-${ attachmentId }"/></figure>\n` +
			`<!-- /wp:image -->`;

		const post = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				status: 'publish',
				title: `Cloudinary e2e ${ Date.now() }`,
				content,
				featured_media: attachmentId,
			},
		} );

		created = {
			postId: post.id,
			attachmentId,
			postLink: post.link,
		};

		// The plugin's URL rewriting depends on the asset being synced
		// to Cloudinary. With auto_sync enabled (wizard default), the
		// first front-end render queues the sync but renders local
		// URLs. Driving `wp cloudinary sync` here makes the test
		// deterministic without relying on the cron-driven queue.
		wpCli( [ 'cloudinary', 'sync' ] );
	} );

	test.afterEach( async () => {
		if ( ! created ) {
			return;
		}
		const { postId, attachmentId } = created;
		created = null;

		// Best-effort cleanup via WP-CLI. We do not use the REST API
		// here because the test wp-env runs without pretty permalinks,
		// which makes appending `force=true` to a `?rest_route=`-style
		// URL fragile. WP-CLI is unambiguous and we already use it in
		// other helpers (see utils/wizard.js).
		try {
			wpCli( [ 'post', 'delete', String( postId ), '--force' ] );
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.warn( 'Post cleanup failed:', e.message );
		}
		try {
			wpCli( [ 'post', 'delete', String( attachmentId ), '--force' ] );
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.warn( 'Media cleanup failed:', e.message );
		}
	} );

	test( 'serves featured image and inline image via Cloudinary', async ( {
		page,
	} ) => {
		expect( created, 'post + attachment should be created' ).not.toBeNull();

		await page.goto( created.postLink );

		// Featured image: themes mark it with .wp-post-image.
		const featured = page.locator( 'img.wp-post-image' ).first();
		await expect(
			featured,
			'featured image should render on the post page'
		).toBeVisible();

		// Inline image from the_content. Scope to .wp-block-image so
		// the featured image (also tagged wp-image-<ID> by some themes)
		// is not double-counted.
		const inline = page
			.locator( `.wp-block-image img.wp-image-${ created.attachmentId }` )
			.first();
		await expect(
			inline,
			'inline image block should render in post content'
		).toBeVisible();

		// With the wizard's default settings, the plugin lazy-loads
		// images: the initial server-rendered markup carries a tiny
		// SVG placeholder in `src` and the real Cloudinary delivery
		// is encoded in `data-public-id` + `data-transformations` +
		// `data-version`. The JS then constructs the Cloudinary URL
		// and swaps it into `src` once the image scrolls into view.
		//
		// We assert two things per image:
		//   1. `data-public-id` is present — proves the plugin marked
		//      the element for Cloudinary delivery (this is its own
		//      explicit signal, independent of lazyload / theme markup).
		//   2. Any HTTP(S) URL attribute present (`src` after the JS
		//      swap, `srcset` when emitted by the theme) is served
		//      from `res.cloudinary.com/<cloud_name>/...`.
		const httpUrls = [];
		for ( const loc of [ featured, inline ] ) {
			await loc.scrollIntoViewIfNeeded();

			const publicId = await loc.getAttribute( 'data-public-id' );
			expect(
				publicId,
				'plugin should mark the image with data-public-id'
			).toBeTruthy();

			const src = await loc.getAttribute( 'src' );
			if ( src && /^https?:\/\//.test( src ) ) {
				httpUrls.push( src );
			}

			const srcset = await loc.getAttribute( 'srcset' );
			if ( srcset ) {
				const firstCandidate = srcset
					.split( ',' )[ 0 ]
					.trim()
					.split( /\s+/ )[ 0 ];
				if ( firstCandidate && /^https?:\/\//.test( firstCandidate ) ) {
					httpUrls.push( firstCandidate );
				}
			}
		}

		expect(
			httpUrls.length,
			'at least one image should expose a Cloudinary URL via src or srcset'
		).toBeGreaterThan( 0 );

		for ( const url of httpUrls ) {
			expectCloudinaryUrl( url, cloudName );
		}
	} );
} );
