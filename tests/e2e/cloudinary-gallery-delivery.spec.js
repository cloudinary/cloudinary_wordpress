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
const { expectCloudinaryUrl, srcsetUrls } = require( './utils/delivery' );
const { wpCli } = require( './utils/wizard' );

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );

const IMAGE_COUNT = 3;
const INITIAL_COLUMNS = 3;
const CONTAINER = 'cld-gallery-e2e';

// The Cloudinary Gallery Widget is loaded from product-gallery.cloudinary.com
// and renders on `window.load`, so allow for a slow third-party fetch.
const WIDGET_TIMEOUT = 30_000;

let cloudName;

/**
 * State shared by the tests in this file. The layout test edits the post
 * created in beforeAll, so the tests run in order (see `mode: 'serial'`).
 *
 * @type {{ postId: number, postLink: string, attachmentIds: number[], publicIds: string[] }|null}
 */
let created = null;

/**
 * Build the serialized `cloudinary/gallery` block. The inner markup must
 * match the block's `save()` output exactly, or the editor flags the block
 * as invalid when the layout test opens the post.
 *
 * @param {Object[]} selectedImages Response of the plugin's `image_data` endpoint.
 * @param {Object}   layout         Layout attributes.
 * @param {string}   layout.mode    `expanded` or `classic`.
 * @param {number}   layout.columns Column count (expanded mode only).
 * @return {string} Block markup.
 */
function galleryBlock( selectedImages, { mode, columns } ) {
	const attributes = {
		container: CONTAINER,
		selectedImages,
		displayProps_mode: mode,
		displayProps_columns: columns,
	};

	return (
		`<!-- wp:cloudinary/gallery ${ JSON.stringify( attributes ) } -->\n` +
		`<div class="${ CONTAINER }"></div>\n` +
		`<!-- /wp:cloudinary/gallery -->`
	);
}

/**
 * Open the published post and wait for the Gallery Widget to finish
 * rendering every image in the main viewer.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<import('@playwright/test').Locator>} The widget root.
 */
async function openGallery( page ) {
	await page.goto( created.postLink );

	const gallery = page.locator(
		`.${ CONTAINER } [data-test="gallery-wrap"]`
	);
	await expect(
		gallery,
		'the Gallery Widget should render into the block container'
	).toBeVisible( { timeout: WIDGET_TIMEOUT } );

	await expect(
		gallery.locator(
			'[data-test="gallery-viewer-wrap"] [data-test="image-wrap"][data-ready="true"]'
		)
	).toHaveCount( IMAGE_COUNT, { timeout: WIDGET_TIMEOUT } );

	return gallery;
}

/**
 * Read the `src` and `srcset` attributes of the given images.
 *
 * @param {import('@playwright/test').Locator} images
 * @return {Promise<Array<{ src: string|null, srcset: string|null }>>} Attributes per image.
 */
async function collectImageUrls( images ) {
	return images.evaluateAll( ( nodes ) =>
		nodes.map( ( img ) => ( {
			src: img.getAttribute( 'src' ),
			srcset: img.getAttribute( 'srcset' ),
		} ) )
	);
}

// @serial: needs real credentials in `cloudinary_connect` to sync assets,
// while every analytics spec overwrites that option with fake ones.
test.describe( 'Cloudinary Gallery block delivery', { tag: '@serial' }, () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { requestUtils } ) => {
		( { cloudName } = ensureCloudinaryConnected() );

		const file = fs.readFileSync( FIXTURE_PATH );
		const attachmentIds = [];
		for ( let i = 0; i < IMAGE_COUNT; i++ ) {
			const media = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/media',
				headers: {
					'Content-Type': 'image/jpeg',
					'Content-Disposition': `attachment; filename="gallery-e2e-${ Date.now() }-${ i }.jpg"`,
				},
				data: file,
			} );
			attachmentIds.push( media.id );
		}

		// Same endpoint the block editor calls when images are picked in
		// the media modal. It uploads each attachment to Cloudinary if it
		// is not synced yet and returns the `selectedImages` attribute.
		const selectedImages = await requestUtils.rest( {
			method: 'POST',
			path: '/cloudinary/v1/image_data',
			data: { images: attachmentIds.map( ( id ) => ( { id } ) ) },
		} );

		expect(
			selectedImages,
			'every attachment should sync to Cloudinary'
		).toHaveLength( IMAGE_COUNT );

		const post = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				status: 'publish',
				title: `Cloudinary gallery e2e ${ Date.now() }`,
				content: galleryBlock( selectedImages, {
					mode: 'expanded',
					columns: INITIAL_COLUMNS,
				} ),
			},
		} );

		created = {
			postId: post.id,
			postLink: post.link,
			attachmentIds,
			publicIds: selectedImages.map( ( image ) => image.publicId ),
		};
	} );

	test.afterAll( () => {
		if ( ! created ) {
			return;
		}
		const { postId, attachmentIds } = created;
		created = null;

		// WP-CLI rather than REST for the same reason as the image delivery
		// spec: `force=true` on a `?rest_route=` URL is fragile.
		for ( const id of [ postId, ...attachmentIds ] ) {
			try {
				wpCli( [ 'post', 'delete', String( id ), '--force' ] );
			} catch ( e ) {
				console.warn( `Cleanup of post ${ id } failed:`, e.message );
			}
		}
	} );

	test( 'renders exactly N images, all served from Cloudinary', async ( {
		page,
	} ) => {
		const gallery = await openGallery( page );

		await expect( gallery ).toHaveAttribute( 'mode', 'expanded' );

		const viewerImages = gallery.locator(
			'[data-test="gallery-viewer-wrap"] img'
		);
		await expect(
			viewerImages,
			`the gallery should show exactly ${ IMAGE_COUNT } images`
		).toHaveCount( IMAGE_COUNT );

		// Every <img> the widget renders, viewer and any thumbnails alike,
		// must come from Cloudinary.
		const attrs = await collectImageUrls( gallery.locator( 'img' ) );
		const urls = attrs.flatMap( ( { src, srcset } ) => [
			src,
			...srcsetUrls( srcset ),
		] );

		expect(
			attrs.every( ( { src } ) => !! src ),
			'every gallery image should have a src'
		).toBe( true );

		for ( const url of urls ) {
			expectCloudinaryUrl( url, cloudName );
		}

		// Each selected image appears once, identified by its public ID as
		// the last path segment of the delivery URL.
		const viewerUrls = ( await collectImageUrls( viewerImages ) ).map(
			( { src } ) => new URL( src ).pathname.split( '/' ).pop()
		);
		expect( viewerUrls.sort() ).toEqual(
			created.publicIds.map( ( id ) => id.split( '/' ).pop() ).sort()
		);

		// Expanded with 3 columns and 3 images: one row, no carousel or
		// thumbnail strip.
		const tops = await gallery
			.locator( '[data-test="gallery-viewer-wrap"] button.assetWrapper' )
			.evaluateAll( ( nodes ) =>
				nodes.map( ( n ) =>
					Math.round( n.getBoundingClientRect().top )
				)
			);
		expect(
			new Set( tops ).size,
			`${ IMAGE_COUNT } images in ${ INITIAL_COLUMNS } columns should fit one row`
		).toBe( Math.ceil( IMAGE_COUNT / INITIAL_COLUMNS ) );
		await expect( gallery.locator( '[data-test="carousel"]' ) ).toHaveCount(
			0
		);
		await expect(
			gallery.locator( '[data-test="gallery-thumbnail-wrap"]' )
		).toHaveCount( 0 );
	} );

	test( 'changing the layout and republishing changes the markup', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.editPost( created.postId );

		// Select the block through the data store. Clicking it in the canvas
		// would hit the widget's own buttons inside the block.
		const clientId = await page.evaluate( () => {
			const block = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.find( ( b ) => b.name === 'cloudinary/gallery' );
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectBlock( block.clientId );
			return block.clientId;
		} );
		expect( clientId, 'the gallery block should load' ).toBeTruthy();

		const isValid = await page.evaluate(
			( id ) =>
				window.wp.data.select( 'core/block-editor' ).getBlock( id )
					.isValid,
			clientId
		);
		expect( isValid, 'the gallery block should not be invalid' ).toBe(
			true
		);

		await editor.openDocumentSettingsSidebar();

		const settings = page.getByRole( 'region', {
			name: 'Editor settings',
		} );
		await settings
			.locator( 'button.radio-select', { hasText: 'Classic' } )
			.click();
		await expect(
			settings.locator( 'button.radio-select--active' )
		).toContainText( 'Classic' );

		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Save', exact: true } )
			.click();
		await expect(
			page
				.getByRole( 'button', { name: 'Dismiss this notice' } )
				.filter( { hasText: 'updated' } )
		).toBeVisible();

		const gallery = await openGallery( page );

		await expect( gallery ).toHaveAttribute( 'mode', 'classic' );

		// Classic shows one image at a time in a carousel with a thumbnail
		// strip, and no expanded scroll viewer.
		await expect(
			gallery.locator(
				'[data-test="gallery-viewer-wrap"] [data-test="carousel"]'
			)
		).toBeVisible();
		await expect(
			gallery.locator( '[data-test="scroll-viewer-wrap"]' )
		).toHaveCount( 0 );
		await expect(
			gallery.locator(
				'[data-test="gallery-thumbnail-wrap"] [data-test="thumbnails-wrap"]'
			)
		).toHaveCount( IMAGE_COUNT );

		const attrs = await collectImageUrls( gallery.locator( 'img' ) );
		for ( const { src, srcset } of attrs ) {
			for ( const url of [ src, ...srcsetUrls( srcset ) ] ) {
				expectCloudinaryUrl( url, cloudName );
			}
		}
	} );
} );
