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

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );

/**
 * Settings pinned for every test so that the server writes the final
 * delivery URL into `src`. With lazy load on, `src` is an SVG placeholder,
 * and with breakpoints on, the URL also carries breakpoint sizes.
 *
 * @type {Record<string, string>}
 */
const BASE_SETTINGS = {
	image_optimization: 'on',
	image_freeform: '',
	use_lazy_load: 'off',
	enable_breakpoints: 'off',
};

/**
 * Every setting the spec writes, restored in afterAll.
 *
 * @type {string[]}
 */
const MANAGED_SETTINGS = [
	...Object.keys( BASE_SETTINGS ),
	'image_format',
	'image_quality',
];

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
 * Save plugin settings through the settings API.
 *
 * @param {Record<string, string>} values Setting slug to value.
 */
function saveSettings( values ) {
	wpEvalFile( `
		$settings = get_plugin_instance()->settings;
		foreach ( json_decode( '${ JSON.stringify(
			values
		) }', true ) as $slug => $value ) {
			$settings->get_setting( $slug )->save_value( $value );
		}
	` );
}

/**
 * Read the current value of the managed settings.
 *
 * @return {Record<string, string>} Setting slug to value.
 */
function readSettings() {
	return JSON.parse(
		wpEvalFile( `
			$settings = get_plugin_instance()->settings;
			$values   = array();
			foreach ( json_decode( '${ JSON.stringify(
				MANAGED_SETTINGS
			) }', true ) as $slug ) {
				$values[ $slug ] = $settings->get_setting( $slug )->get_value();
			}
			echo wp_json_encode( $values );
		` )
	);
}

/**
 * Upload the fixture, embed it in a published post and sync it.
 *
 * Each call gets a fresh attachment, so the image is synced with the
 * settings that are active at that time. The timestamp in the filename
 * gives a fresh public ID, so no CDN cache from an earlier run applies.
 *
 * @param {Object} requestUtils RequestUtils fixture.
 * @return {Promise<{ attachmentId: number, postLink: string }>} Created objects.
 */
async function uploadSyncedImagePost( requestUtils ) {
	const media = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/media',
		headers: {
			'Content-Type': 'image/jpeg',
			'Content-Disposition': `attachment; filename="global-transformations-${ Date.now() }.jpg"`,
		},
		data: fs.readFileSync( FIXTURE_PATH ),
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
			title: `Cloudinary global transformations e2e ${ Date.now() }`,
			content,
		},
	} );
	createdIds.push( post.id );

	// Same reason as the image delivery spec: sync synchronously instead
	// of waiting for the auto-sync queue.
	wpCli( [ 'cloudinary', 'sync' ] );

	return { attachmentId: media.id, postLink: post.link };
}

/**
 * Open the post and return the delivery URL of the inline image.
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
	await expect( img ).toHaveAttribute(
		'src',
		/^https:\/\/res\.cloudinary\.com\//
	);

	const src = await img.getAttribute( 'src' );
	expectCloudinaryUrl( src, cloudName );
	return src;
}

/**
 * Collect the transformation parameters from a Cloudinary delivery URL.
 *
 * Transformations are the path segments between the delivery type and the
 * version segment (`v123`). Each segment is a comma separated list, e.g.
 * `w_1200,h_900,c_scale/f_auto,q_auto`.
 *
 * @param {string} url Delivery URL.
 * @return {string[]} Parameters such as `f_auto` or `q_60`.
 */
function transformationParams( url ) {
	const segments = new URL( url ).pathname.split( '/' ).filter( Boolean );
	// Drop the cloud name and the delivery type (`images` or `image/upload`).
	const start = 'image' === segments[ 1 ] ? 3 : 2;
	const params = [];
	for ( const segment of segments.slice( start ) ) {
		if ( /^v\d+$/.test( segment ) ) {
			break;
		}
		params.push( ...segment.split( ',' ) );
	}
	return params;
}

/**
 * Filter parameters by their prefix.
 *
 * @param {string[]} params Transformation parameters.
 * @param {string}   prefix Parameter prefix such as `f_`.
 * @return {string[]} Matching parameters.
 */
function withPrefix( params, prefix ) {
	return params.filter( ( param ) => param.startsWith( prefix ) );
}

// @serial: needs real credentials in `cloudinary_connect` for `wp cloudinary
// sync`, while every analytics spec overwrites that option with fake ones.
test.describe(
	'Cloudinary global image transformations',
	{ tag: '@serial' },
	() => {
		test.beforeAll( () => {
			( { cloudName } = ensureCloudinaryConnected() );
			previousSettings = readSettings();
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

			// WP-CLI rather than REST for the same reason as the image
			// delivery spec: `force=true` on a `?rest_route=` URL is fragile.
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
		} );

		test( 'non-default format and quality appear as f_ and q_', async ( {
			page,
			requestUtils,
		} ) => {
			saveSettings( {
				...BASE_SETTINGS,
				image_format: 'webp',
				image_quality: '60',
			} );

			const { attachmentId, postLink } =
				await uploadSyncedImagePost( requestUtils );
			const params = transformationParams(
				await renderedSrc( page, postLink, attachmentId )
			);

			expect( withPrefix( params, 'f_' ) ).toEqual( [ 'f_webp' ] );
			expect( withPrefix( params, 'q_' ) ).toEqual( [ 'q_60' ] );
		} );

		test( 'reverting to the defaults replaces the explicit values with auto', async ( {
			page,
			requestUtils,
		} ) => {
			// Set the non-default values first, so this test proves the
			// revert and does not only read the defaults.
			saveSettings( {
				...BASE_SETTINGS,
				image_format: 'webp',
				image_quality: '60',
			} );
			saveSettings( { image_format: 'auto', image_quality: 'auto' } );

			const { attachmentId, postLink } =
				await uploadSyncedImagePost( requestUtils );
			const params = transformationParams(
				await renderedSrc( page, postLink, attachmentId )
			);

			expect( withPrefix( params, 'f_' ) ).toEqual( [ 'f_auto' ] );
			expect( withPrefix( params, 'q_' ) ).toEqual( [ 'q_auto' ] );
		} );

		test( '"Not set" omits the f_ and q_ parameters', async ( {
			page,
			requestUtils,
		} ) => {
			saveSettings( {
				...BASE_SETTINGS,
				image_format: 'none',
				image_quality: 'none',
			} );

			const { attachmentId, postLink } =
				await uploadSyncedImagePost( requestUtils );
			const params = transformationParams(
				await renderedSrc( page, postLink, attachmentId )
			);

			expect( withPrefix( params, 'f_' ) ).toEqual( [] );
			expect( withPrefix( params, 'q_' ) ).toEqual( [] );
		} );
	}
);
