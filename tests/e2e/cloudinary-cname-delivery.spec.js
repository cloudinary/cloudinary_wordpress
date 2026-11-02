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
const {
	expectCloudinaryUrl,
	routeCnameToCloudinary,
	srcsetUrls,
} = require( './utils/delivery' );
const {
	collectImageUrls,
	galleryBlock,
	openGallery,
} = require( './utils/gallery' );
const { wpCli, wpEvalFile } = require( './utils/wizard' );

const IMAGE_FIXTURE = path.join( __dirname, 'fixtures', 'test-image.jpg' );
const VIDEO_FIXTURE = path.join( __dirname, 'fixtures', 'test-video.mp4' );

/**
 * A fake custom domain. The plugin only checks that it looks like a domain
 * and never resolves it. Requests to it are sent on to res.cloudinary.com
 * by `routeCnameToCloudinary()`, so the images still load.
 *
 * @type {string}
 */
const CNAME = 'cdn.e2e-cloudinary.test';

const IMAGE_COUNT = 3;

/**
 * Settings pinned for the spec, restored in afterAll. Without lazy load
 * the server writes the final URLs into `src` and `srcset`, and the
 * breakpoints make sure there is a Cloudinary `srcset` to check. The
 * Cloudinary player is what receives the custom domain for video.
 *
 * @type {Record<string, string>}
 */
const SETTINGS = {
	use_lazy_load: 'off',
	enable_breakpoints: 'on',
	video_player: 'cld',
};

let cloudName;

/** @type {Record<string, string>|null} */
let previousSettings = null;

/**
 * IDs of every attachment and post the spec creates, removed in afterAll.
 * Each ID is recorded as soon as it exists, so a setup that fails part way
 * is still cleaned up.
 *
 * @type {number[]}
 */
let createdIds = [];

/**
 * @type {{ imagePost: { link: string, attachmentId: number }, galleryPost: { link: string }, videoPost: { link: string } }|null}
 */
let created = null;

/**
 * Save plugin settings through the settings API and return the old values.
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
 * Upload a fixture file through REST and record its ID for cleanup.
 *
 * The timestamp in the filename gives a fresh public ID, so no CDN cache
 * from an earlier run applies.
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @param {string} fixturePath  Path to the fixture.
 * @param {string} contentType  MIME type of the fixture.
 * @param {string} filename     Filename for the upload.
 * @return {Promise<{ id: number, source_url: string }>} The created attachment.
 */
async function upload( requestUtils, fixturePath, contentType, filename ) {
	const media = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/media',
		headers: {
			'Content-Type': contentType,
			'Content-Disposition': `attachment; filename="${ filename }"`,
		},
		data: fs.readFileSync( fixturePath ),
	} );
	createdIds.push( media.id );
	return media;
}

/**
 * Publish a post and record its ID for cleanup.
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @param {string} title        Post title.
 * @param {string} content      Serialized block content.
 * @return {Promise<string>} The post permalink.
 */
async function publish( requestUtils, title, content ) {
	const post = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/posts',
		data: {
			status: 'publish',
			title: `${ title } ${ Date.now() }`,
			content,
		},
	} );
	createdIds.push( post.id );
	return post.link;
}

/**
 * Open the image post and return the URLs of the inline image.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<string[]>} The `src` and every `srcset` URL.
 */
async function imageBlockUrls( page ) {
	await page.goto( created.imagePost.link );

	const img = page
		.locator(
			`.wp-block-image img.wp-image-${ created.imagePost.attachmentId }`
		)
		.first();
	await expect( img ).toBeVisible();

	// The image only loads if the delivery URL works, which proves the
	// path the plugin built on the custom domain is a valid Cloudinary path.
	await expect
		.poll( () => img.evaluate( ( el ) => el.complete && el.naturalWidth ), {
			message: 'the image should load through the custom domain',
		} )
		.toBeGreaterThan( 0 );

	const { src, srcset } = await img.evaluate( ( el ) => ( {
		src: el.getAttribute( 'src' ),
		srcset: el.getAttribute( 'srcset' ),
	} ) );
	expect( src, 'the image should have a src' ).toBeTruthy();
	expect( srcset, 'the image should have a srcset' ).toBeTruthy();

	return [ src, ...srcsetUrls( srcset ) ];
}

// @serial: needs real credentials in `cloudinary_connect` to sync assets,
// while every analytics spec overwrites that option with fake ones. It also
// changes the connection string, which every other delivery spec reads.
test.describe(
	'Cloudinary delivery through a custom domain',
	{ tag: '@serial' },
	() => {
		test.describe.configure( { mode: 'serial' } );

		test.beforeAll( async ( { requestUtils } ) => {
			// Connect with the custom domain before the upload, as a site
			// that uses one would be.
			( { cloudName } = ensureCloudinaryConnected( { cname: CNAME } ) );
			previousSettings = saveSettings( SETTINGS );

			const stamp = Date.now();
			const images = [];
			for ( let i = 0; i < IMAGE_COUNT; i++ ) {
				images.push(
					await upload(
						requestUtils,
						IMAGE_FIXTURE,
						'image/jpeg',
						`cname-e2e-${ stamp }-${ i }.jpg`
					)
				);
			}
			const attachmentIds = images.map( ( media ) => media.id );

			// Same endpoint the block editor calls when images are picked
			// for a gallery. It syncs each attachment and returns the
			// block's `selectedImages` attribute.
			const selectedImages = await requestUtils.rest( {
				method: 'POST',
				path: '/cloudinary/v1/image_data',
				data: { images: attachmentIds.map( ( id ) => ( { id } ) ) },
			} );
			expect(
				selectedImages,
				'every attachment should sync to Cloudinary'
			).toHaveLength( IMAGE_COUNT );

			// The local URL from the upload response, as the editor would
			// insert it. The plugin replaces it with the delivery URL.
			const image = images[ 0 ];
			const imageLink = await publish(
				requestUtils,
				'Cloudinary CNAME image e2e',
				`<!-- wp:image {"id":${ image.id }} -->\n` +
					`<figure class="wp-block-image"><img src="${ image.source_url }" alt="" class="wp-image-${ image.id }"/></figure>\n` +
					`<!-- /wp:image -->`
			);

			const galleryLink = await publish(
				requestUtils,
				'Cloudinary CNAME gallery e2e',
				galleryBlock( selectedImages, {
					mode: 'expanded',
					columns: IMAGE_COUNT,
				} )
			);

			const video = await upload(
				requestUtils,
				VIDEO_FIXTURE,
				'video/mp4',
				`cname-e2e-${ stamp }.mp4`
			);
			const videoLink = await publish(
				requestUtils,
				'Cloudinary CNAME video e2e',
				`<!-- wp:video {"id":${ video.id }} -->\n` +
					`<figure class="wp-block-video"><video controls src="${ video.source_url }"></video></figure>\n` +
					`<!-- /wp:video -->`
			);

			// Same reason as the image delivery spec: sync synchronously
			// instead of waiting for the auto-sync queue.
			wpCli( [ 'cloudinary', 'sync' ] );

			created = {
				imagePost: { link: imageLink, attachmentId: image.id },
				galleryPost: { link: galleryLink },
				videoPost: { link: videoLink },
			};
		} );

		test.afterAll( () => {
			// Put the plain connection back first: every other delivery
			// spec in the serial run expects res.cloudinary.com URLs.
			try {
				ensureCloudinaryConnected();
			} finally {
				if ( previousSettings ) {
					saveSettings( previousSettings );
					previousSettings = null;
				}
				created = null;
				const ids = createdIds;
				createdIds = [];

				// WP-CLI rather than REST for the same reason as the image
				// delivery spec: `force=true` on a `?rest_route=` URL is
				// fragile.
				for ( const id of ids ) {
					try {
						wpCli( [ 'post', 'delete', String( id ), '--force' ] );
					} catch ( e ) {
						console.warn(
							`Cleanup of post ${ id } failed:`,
							e.message
						);
					}
				}
			}
		} );

		test( 'image block src and srcset use the custom domain', async ( {
			page,
		} ) => {
			await routeCnameToCloudinary( page, {
				cname: CNAME,
				cloudName,
				privateCdn: true,
			} );

			for ( const url of await imageBlockUrls( page ) ) {
				expectCloudinaryUrl( url, {
					host: CNAME,
					cloudName,
					privateCdn: true,
				} );
			}
		} );

		test( 'gallery widget images use the custom domain', async ( {
			page,
		} ) => {
			await routeCnameToCloudinary( page, {
				cname: CNAME,
				cloudName,
				privateCdn: true,
			} );

			// Waits until every image reports data-ready="true", which
			// needs the custom domain requests to succeed.
			const gallery = await openGallery(
				page,
				created.galleryPost.link,
				IMAGE_COUNT
			);

			await expect(
				gallery.locator( '[data-test="gallery-viewer-wrap"] img' ),
				`the gallery should show exactly ${ IMAGE_COUNT } images`
			).toHaveCount( IMAGE_COUNT );

			// Every <img> the widget renders, viewer and thumbnails alike.
			const attrs = await collectImageUrls( gallery.locator( 'img' ) );
			expect(
				attrs.every( ( { src } ) => !! src ),
				'every gallery image should have a src'
			).toBe( true );
			for ( const { src, srcset } of attrs ) {
				for ( const url of [ src, ...srcsetUrls( srcset ) ] ) {
					expectCloudinaryUrl( url, {
						host: CNAME,
						cloudName,
						privateCdn: true,
					} );
				}
			}
		} );

		test( 'Cloudinary video player receives the custom domain', async ( {
			page,
		} ) => {
			await page.goto( created.videoPost.link );

			const iframe = page
				.locator( 'figure.wp-block-embed.is-type-video iframe' )
				.first();
			await expect(
				iframe,
				'Cloudinary player iframe should be emitted in place of <video>'
			).toBeAttached();

			const params = new URL( await iframe.getAttribute( 'src' ) )
				.searchParams;
			expect( params.get( 'cloud_name' ) ).toBe( cloudName );
			expect( params.get( 'cloudinary[cname]' ) ).toBe( CNAME );
			expect( params.get( 'cloudinary[secure_distribution]' ) ).toBe(
				CNAME
			);
			expect( params.get( 'cloudinary[private_cdn]' ) ).toBe( 'true' );
		} );

		test( 'with private_cdn=false the path keeps the cloud name', async ( {
			page,
		} ) => {
			ensureCloudinaryConnected( { cname: CNAME, privateCdn: false } );
			await routeCnameToCloudinary( page, {
				cname: CNAME,
				cloudName,
				privateCdn: false,
			} );

			for ( const url of await imageBlockUrls( page ) ) {
				expectCloudinaryUrl( url, {
					host: CNAME,
					cloudName,
					privateCdn: false,
				} );
			}
		} );
	}
);
