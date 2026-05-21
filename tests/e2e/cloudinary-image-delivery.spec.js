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
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! created ) {
			return;
		}
		const { postId, attachmentId } = created;
		created = null;

		// Best-effort cleanup; don't let cleanup errors mask test failures.
		try {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/posts/${ postId }`,
				params: { force: true },
			} );
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.warn( 'Post cleanup failed:', e.message );
		}
		try {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/media/${ attachmentId }`,
				params: { force: true },
			} );
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

		// Featured image: most core themes mark it with .wp-post-image
		// inside the post header. Use a tolerant selector.
		const featured = page
			.locator( 'img.wp-post-image, .post-thumbnail img' )
			.first();
		await expect(
			featured,
			'featured image should render on the post page'
		).toBeVisible();

		// Inline image from the_content: the block editor adds
		// `wp-image-<ID>` to embedded images.
		const inline = page.locator(
			`article img.wp-image-${ created.attachmentId }`
		);
		await expect(
			inline,
			'inline image block should render in post content'
		).toHaveCount( 1 );

		// Read attributes from both and assert delivery URLs point at
		// Cloudinary under the configured cloud name.
		const candidates = [];

		for ( const loc of [ featured, inline ] ) {
			const src = await loc.getAttribute( 'src' );
			expect( src, 'image element should have a src' ).toBeTruthy();
			candidates.push( src );

			const srcset = await loc.getAttribute( 'srcset' );
			if ( srcset ) {
				const firstCandidate = srcset
					.split( ',' )[ 0 ]
					.trim()
					.split( /\s+/ )[ 0 ];
				if ( firstCandidate ) {
					candidates.push( firstCandidate );
				}
			}
		}

		for ( const url of candidates ) {
			expectCloudinaryUrl( url, cloudName );
		}
	} );
} );
