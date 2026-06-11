/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { ensureCloudinaryConnected } = require( './utils/connection' );
const { wpCli, getCliContainer } = require( './utils/wizard' );

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

/**
 * Set cloudinary_media_display.video_player without disturbing other
 * keys, bootstrapping the option if it does not yet exist.
 *
 * We cannot use `wp option patch` here: in a fresh wp-env the
 * `cloudinary_media_display` option is unset, so `get_option` returns
 * boolean false and `patch` errors with "Cannot create key ... on data
 * type boolean". A merge-based `update_option` handles the missing,
 * boolean, and populated-array cases uniformly — `(array) false` is [].
 *
 * The PHP snippet contains spaces and quotes, so it cannot go through
 * the space-joined `wpCli` helper; we build the docker command here.
 *
 * @param {string} value 'wp' or 'cld'.
 */
function setVideoPlayer( value ) {
	const php =
		'update_option("cloudinary_media_display", array_merge((array) get_option("cloudinary_media_display", array()), array("video_player" => "' +
		value +
		'")));';
	const cmd = [
		'docker',
		'exec',
		getCliContainer(),
		'wp',
		'eval',
		`'${ php }'`,
		'--allow-root',
	].join( ' ' );

	execSync( cmd, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } );
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
			// Read both in a single evaluate so a missing <source> does
			// not trigger Playwright's actionability wait (and so real
			// errors are not swallowed by a blanket catch).
			const url = await video.evaluate(
				( el ) =>
					el.getAttribute( 'src' ) ||
					el.querySelector( 'source' )?.getAttribute( 'src' ) ||
					null
			);
			expect(
				url,
				'video element should expose a src on <video> or <source>'
			).toBeTruthy();

			expectCloudinaryUrl( url, cloudName );
		} );
	} );

	test.describe( 'with Cloudinary player', () => {
		test.beforeAll( () => {
			// Flip the video_player setting from 'wp' (default) to
			// 'cld' so the plugin renders a player.cloudinary.com
			// iframe instead of a native <video>. Reverted in afterAll
			// so subsequent specs see the original setting.
			setVideoPlayer( 'cld' );
		} );

		test.afterAll( () => {
			setVideoPlayer( 'wp' );
		} );

		test( 'renders a Cloudinary player iframe pointing at our cloud', async ( {
			page,
		} ) => {
			expect(
				created,
				'post + attachment should be created'
			).not.toBeNull();

			await page.goto( created.postLink );

			// With video_player=cld, the plugin substitutes the
			// native <video> markup for an iframe pointing at the
			// Cloudinary player. See build_video_embed() in
			// php/media/class-video.php.
			const iframe = page
				.locator( 'figure.wp-block-embed.is-type-video iframe' )
				.first();
			await expect(
				iframe,
				'Cloudinary player iframe should be emitted in place of <video>'
			).toBeAttached();

			const src = await iframe.getAttribute( 'src' );
			expect( src, 'iframe should have a src' ).toBeTruthy();

			const parsed = new URL( src );
			expect( parsed.host, `host of ${ src }` ).toBe(
				'player.cloudinary.com'
			);
			expect( parsed.pathname, `pathname of ${ src }` ).toBe( '/embed/' );
			expect(
				parsed.searchParams.get( 'cloud_name' ),
				'iframe src cloud_name param'
			).toBe( cloudName );
			expect(
				parsed.searchParams.get( 'public_id' ),
				'iframe src public_id param should be non-empty'
			).toBeTruthy();
		} );
	} );
} );
