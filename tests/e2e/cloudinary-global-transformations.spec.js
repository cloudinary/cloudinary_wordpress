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
 * The image post shared by all tests, set in beforeAll.
 *
 * @type {{ attachmentId: number, postLink: string }|null}
 */
let created = null;

/**
 * IDs of the attachment and post, removed in afterAll. Each ID is recorded
 * as soon as it exists, so a setup that fails part way is still cleaned up.
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
 * The timestamp in the filename gives a fresh public ID, so no CDN cache
 * from an earlier run applies.
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
 * Open the post and return the f_ and q_ parameters of the inline image's
 * delivery URL.
 *
 * The plugin adds the global format and quality when it renders the page,
 * not when it syncs the image, so a settings change shows on the next
 * render of the same image.
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{ format: string[], quality: string[] }>} Parameters by prefix.
 */
async function renderedParams( page ) {
	await page.goto( created.postLink );

	const img = page
		.locator( `.wp-block-image img.wp-image-${ created.attachmentId }` )
		.first();
	await expect( img ).toHaveAttribute(
		'src',
		/^https:\/\/res\.cloudinary\.com\//
	);

	const src = await img.getAttribute( 'src' );
	expectCloudinaryUrl( src, cloudName );

	const params = transformationParams( src );
	return {
		format: withPrefix( params, 'f_' ),
		quality: withPrefix( params, 'q_' ),
	};
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
		test.beforeAll( async ( { requestUtils } ) => {
			( { cloudName } = ensureCloudinaryConnected() );
			previousSettings = readSettings();
			saveSettings( BASE_SETTINGS );
			created = await uploadSyncedImagePost( requestUtils );
		} );

		test.afterAll( () => {
			if ( previousSettings ) {
				saveSettings( previousSettings );
				previousSettings = null;
			}
			created = null;
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
		} ) => {
			saveSettings( { image_format: 'webp', image_quality: '60' } );

			expect( await renderedParams( page ) ).toEqual( {
				format: [ 'f_webp' ],
				quality: [ 'q_60' ],
			} );
		} );

		test( 'reverting to the defaults replaces the explicit values with auto', async ( {
			page,
		} ) => {
			// Render with the non-default values first. Without this
			// step the test would pass even if the plugin ignored the
			// settings and always printed the defaults.
			saveSettings( { image_format: 'webp', image_quality: '60' } );
			expect( await renderedParams( page ) ).toEqual( {
				format: [ 'f_webp' ],
				quality: [ 'q_60' ],
			} );

			saveSettings( { image_format: 'auto', image_quality: 'auto' } );
			expect( await renderedParams( page ) ).toEqual( {
				format: [ 'f_auto' ],
				quality: [ 'q_auto' ],
			} );
		} );

		test( '"Not set" omits the f_ and q_ parameters', async ( {
			page,
		} ) => {
			saveSettings( { image_format: 'none', image_quality: 'none' } );

			expect( await renderedParams( page ) ).toEqual( {
				format: [],
				quality: [],
			} );
		} );
	}
);
