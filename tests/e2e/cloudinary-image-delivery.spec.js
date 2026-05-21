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

	test( 'serves featured image and inline image via Cloudinary', async () => {
		// Placeholder — assertions added in Task 5.
		expect( created, 'post + attachment should be created' ).not.toBeNull();
		expect( created.postLink ).toMatch( /^https?:\/\// );
		expect( false, 'placeholder — to be implemented' ).toBe( true );
	} );
} );
