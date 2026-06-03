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

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-video.mp4' );

let cloudName;

/**
 * Per-test scratch space populated by beforeEach.
 *
 * @type {{ postId: number, attachmentId: number, postLink: string }|null}
 */
let created = null;

/**
 * Assert that a given URL is served by Cloudinary under the expected
 * cloud name. We intentionally do not assert specific transformations —
 * those are an implementation detail of the plugin and may change.
 *
 * @param {string} rawUrl        The URL to validate.
 * @param {string} expectedCloud The cloud name parsed from CLOUDINARY_E2E_URL.
 */
function expectCloudinaryUrl( rawUrl, expectedCloud ) {
	let parsed;
	try {
		parsed = new URL( rawUrl );
	} catch ( e ) {
		throw new Error( `URL is not parseable: ${ rawUrl }` );
	}
	expect( parsed.host, `host of ${ rawUrl }` ).toBe( 'res.cloudinary.com' );
	expect(
		parsed.pathname.startsWith( `/${ expectedCloud }/` ),
		`pathname of ${ rawUrl } should start with /${ expectedCloud }/`
	).toBe( true );
}

test.describe( 'Cloudinary video delivery', () => {
	test.beforeAll( () => {
		( { cloudName } = ensureCloudinaryConnected() );
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		// Upload the fixture video via REST.
		const file = fs.readFileSync( FIXTURE_PATH );
		const media = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/media',
			headers: {
				'Content-Type': 'video/mp4',
				'Content-Disposition': 'attachment; filename="test-video.mp4"',
			},
			data: file,
		} );

		const attachmentId = media.id;
		const sourceUrl = media.source_url;

		// Create a published post containing a single core/video block
		// that references the just-uploaded attachment.
		const content =
			`<!-- wp:video {"id":${ attachmentId }} -->\n` +
			`<figure class="wp-block-video"><video controls src="${ sourceUrl }"></video></figure>\n` +
			`<!-- /wp:video -->`;

		const post = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				status: 'publish',
				title: `Cloudinary video e2e ${ Date.now() }`,
				content,
			},
		} );

		created = {
			postId: post.id,
			attachmentId,
			postLink: post.link,
		};

		// The plugin's URL rewriting depends on the asset being synced
		// to Cloudinary. Drive the sync synchronously here so the
		// front-end visit sees rewritten URLs.
		wpCli( [ 'cloudinary', 'sync' ] );
	} );

	test.afterEach( async () => {
		if ( ! created ) {
			return;
		}
		const { postId, attachmentId } = created;
		created = null;

		// Best-effort cleanup via WP-CLI. Matches the image spec; we
		// do not use the REST API because this wp-env runs without
		// pretty permalinks, which makes `?force=true` fragile.
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

	test.describe( 'with default WP player', () => {
		test( 'serves video from a core/video block via Cloudinary', async ( {
			page,
		} ) => {
			expect(
				created,
				'post + attachment should be created'
			).not.toBeNull();

			await page.goto( created.postLink );

			// Locate the core/video block on the rendered page.
			const video = page.locator( 'figure.wp-block-video video' ).first();
			await expect(
				video,
				'core/video block should render a <video> element'
			).toBeAttached();

			// With video_player=wp (the default), the plugin rewrites the
			// video URL server-side via str_replace on the rendered block
			// HTML. The URL lands in either:
			//   - the <video src="..."> attribute, or
			//   - a <source src="..."> child element.
			// Read both and validate whichever is present.
			const videoSrc = await video.getAttribute( 'src' );
			const sourceSrc = await video
				.locator( 'source' )
				.first()
				.getAttribute( 'src' )
				.catch( () => null );

			const url = videoSrc || sourceSrc;
			expect(
				url,
				'video element should expose a src on <video> or <source>'
			).toBeTruthy();

			expectCloudinaryUrl( url, cloudName );
		} );
	} );
} );
