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
	parseSrcset,
	srcsetUrls,
} = require( './utils/delivery' );
const { wpCli, wpEvalFile } = require( './utils/wizard' );

const FIXTURE_PATH = path.join( __dirname, 'fixtures', 'test-image.jpg' );

/**
 * Intrinsic width of the fixture image.
 *
 * @type {number}
 */
const FIXTURE_WIDTH = 1200;

/**
 * Breakpoint settings that give widths core WordPress never produces for a
 * 1200x900 image (its sizes are 1200, 1024, 768 and 300). A width from this
 * set in `srcset` can only come from the plugin's breakpoints.
 *
 * @type {{ max_width: number, min_width: number, pixel_step: number }}
 */
const BREAKPOINTS = {
	max_width: 1100,
	min_width: 500,
	pixel_step: 300,
};

/**
 * Widths `Responsive_Breakpoints::apply_breakpoints()` builds for
 * BREAKPOINTS: the image's own width, then max down to min in steps.
 *
 * @type {number[]}
 */
const EXPECTED_WIDTHS = [ FIXTURE_WIDTH, 1100, 800, 500 ];

/**
 * Every setting the spec writes. Pinned in each test so the result does not
 * depend on the order the tests run in or on state left by other specs.
 *
 * @type {string[]}
 */
const MANAGED_SETTINGS = [
	'enable_breakpoints',
	'use_lazy_load',
	'lazy_placeholder',
	'max_width',
	'min_width',
	'pixel_step',
];

let cloudName;

/** @type {Record<string, string|number>|null} */
let previousSettings = null;

/**
 * Objects created in beforeAll and removed in afterAll.
 *
 * @type {{ postId: number, attachmentId: number, postLink: string }|null}
 */
let created = null;

/**
 * Save plugin settings through the settings API and return the old values.
 *
 * @param {Record<string, string|number>} values Setting slug to value.
 * @return {Record<string, string|number>} Setting slug to the value before the write.
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
 * Read the current value of the managed settings.
 *
 * @return {Record<string, string|number>} Setting slug to value.
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
 * Apply the breakpoint and lazy-load toggles, with the fixed breakpoint
 * range and the blur placeholder.
 *
 * @param {Object}  options
 * @param {boolean} options.breakpoints Enable responsive breakpoints.
 * @param {boolean} options.lazyLoad    Enable lazy loading.
 */
function configure( { breakpoints, lazyLoad } ) {
	saveSettings( {
		...BREAKPOINTS,
		enable_breakpoints: breakpoints ? 'on' : 'off',
		use_lazy_load: lazyLoad ? 'on' : 'off',
		lazy_placeholder: 'blur',
	} );
}

/**
 * Parse the width descriptors out of a `srcset` attribute.
 *
 * @param {string|null} srcset The raw attribute value.
 * @return {number[]} Widths in source order.
 */
function srcsetWidths( srcset ) {
	return parseSrcset( srcset )
		.map( ( candidate ) => candidate.descriptor )
		.filter( ( descriptor ) => /^\d+w$/.test( descriptor ) )
		.map( ( descriptor ) => parseInt( descriptor, 10 ) );
}

/**
 * Load the post with JavaScript disabled and return the attributes of the
 * inline image as the server rendered them.
 *
 * The lazy-load script rewrites `src` and `srcset` once it runs, so the
 * server markup can only be read reliably without it.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {string}                             baseURL
 * @return {Promise<{ attrs: Record<string, string>, html: string }>} Image attributes and the page HTML.
 */
async function serverMarkup( browser, baseURL ) {
	const context = await browser.newContext( {
		baseURL,
		javaScriptEnabled: false,
	} );
	try {
		const page = await context.newPage();
		await page.goto( created.postLink );
		const img = page
			.locator( `.wp-block-image img.wp-image-${ created.attachmentId }` )
			.first();
		await expect(
			img,
			'inline image block should render in post content'
		).toBeAttached();

		const attrs = await img.evaluate( ( el ) =>
			Object.fromEntries(
				[ ...el.attributes ].map( ( a ) => [ a.name, a.value ] )
			)
		);
		return { attrs, html: await page.content() };
	} finally {
		await context.close();
	}
}

// @serial: needs real credentials in `cloudinary_connect` for `wp cloudinary
// sync`, while every analytics spec overwrites that option with fake ones.
test.describe(
	'Cloudinary responsive breakpoints and lazy load',
	{ tag: '@serial' },
	() => {
		test.beforeAll( async ( { requestUtils } ) => {
			( { cloudName } = ensureCloudinaryConnected() );
			previousSettings = readSettings();

			// A timestamped filename gives every run a fresh public ID,
			// so no CDN cache from an earlier run can interfere.
			const media = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/media',
				headers: {
					'Content-Type': 'image/jpeg',
					'Content-Disposition': `attachment; filename="breakpoints-${ Date.now() }.jpg"`,
				},
				data: fs.readFileSync( FIXTURE_PATH ),
			} );

			const content =
				`<!-- wp:image {"id":${ media.id }} -->\n` +
				`<figure class="wp-block-image"><img src="${ media.source_url }" alt="" class="wp-image-${ media.id }"/></figure>\n` +
				`<!-- /wp:image -->`;

			const post = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: {
					status: 'publish',
					title: `Cloudinary breakpoints e2e ${ Date.now() }`,
					content,
				},
			} );

			created = {
				postId: post.id,
				attachmentId: media.id,
				postLink: post.link,
			};

			// Same reason as the image delivery spec: sync synchronously
			// instead of waiting for the auto-sync queue.
			wpCli( [ 'cloudinary', 'sync' ] );
		} );

		test.afterAll( () => {
			if ( previousSettings ) {
				saveSettings( previousSettings );
				previousSettings = null;
			}
			if ( ! created ) {
				return;
			}
			const { postId, attachmentId } = created;
			created = null;

			// WP-CLI rather than REST for the same reason as the image
			// delivery spec: `force=true` on a `?rest_route=` URL is fragile.
			for ( const id of [ postId, attachmentId ] ) {
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

		test( 'configured min/max/step produce matching srcset widths', async ( {
			browser,
			baseURL,
		} ) => {
			// Breakpoints are only written to `srcset` on the server when
			// lazy loading is off; with lazy load on, the script picks
			// the size at runtime instead.
			configure( { breakpoints: true, lazyLoad: false } );

			const { attrs } = await serverMarkup( browser, baseURL );

			expect(
				attrs[ 'data-responsive' ],
				'breakpoints should mark the image as responsive'
			).toBeDefined();
			expect( srcsetWidths( attrs.srcset ) ).toEqual( EXPECTED_WIDTHS );

			const urls = srcsetUrls( attrs.srcset );
			expect( urls ).toHaveLength( EXPECTED_WIDTHS.length );
			for ( const [ i, url ] of urls.entries() ) {
				expectCloudinaryUrl( url, cloudName );
				// The first candidate is the original `src`; the rest
				// carry the breakpoint width as a transformation.
				if ( i > 0 ) {
					expect( url, `transformation of ${ url }` ).toContain(
						`w_${ EXPECTED_WIDTHS[ i ] }`
					);
				}
			}

			expect( attrs.sizes ).toBe(
				`(max-width: ${ FIXTURE_WIDTH }px) 100vw, ${ FIXTURE_WIDTH }px`
			);
		} );

		test( 'disabling breakpoints removes the breakpoint srcset', async ( {
			browser,
			baseURL,
		} ) => {
			configure( { breakpoints: false, lazyLoad: false } );

			const { attrs } = await serverMarkup( browser, baseURL );

			expect(
				attrs[ 'data-responsive' ],
				'the responsive marker should be gone'
			).toBeUndefined();

			// Core may still emit its own srcset from the registered
			// image sizes. None of the breakpoint-only widths may appear.
			const widths = srcsetWidths( attrs.srcset );
			for ( const width of EXPECTED_WIDTHS.slice( 1 ) ) {
				expect(
					widths,
					`srcset should not contain breakpoint width ${ width }w`
				).not.toContain( width );
			}
		} );

		test( 'lazy load renders the placeholder markup', async ( {
			browser,
			baseURL,
			page,
		} ) => {
			configure( { breakpoints: true, lazyLoad: true } );

			const { attrs, html } = await serverMarkup( browser, baseURL );

			expect( attrs[ 'data-cloudinary' ] ).toBe( 'lazy' );
			expect(
				attrs.src,
				'src should be the inline SVG placeholder'
			).toMatch( /^data:image\/svg\+xml/ );
			expect( attrs[ 'data-size' ] ).toMatch( /^\d+ \d+$/ );
			expect( attrs.onload ).toContain( 'CLDBind' );
			expect( attrs[ 'data-public-id' ] ).toBeTruthy();
			expect( attrs[ 'data-delivery' ] ).toBe( 'upload' );

			// The breakpoint srcset is not rendered on the server; the
			// script sizes the image at runtime from data-responsive.
			expect( attrs.srcset ).toBeUndefined();
			expect( attrs[ 'data-responsive' ] ).toBeDefined();

			// The inline loader config carries the blur placeholder
			// transformation.
			const config = /var CLDLB = (\{.*?\});/.exec( html );
			expect(
				config,
				'the CLDLB loader config should be printed'
			).not.toBeNull();
			const parsed = JSON.parse( config[ 1 ] );
			expect( parsed.use_lazy_load ).toBe( 'on' );
			expect( parsed.lazy_placeholder ).toBe( 'blur' );
			expect( parsed.placeholder ).toContain( 'e_blur:2000' );

			// With JavaScript on, the script swaps in the Cloudinary URL
			// once the image is in view.
			await page.goto( created.postLink );
			const img = page
				.locator(
					`.wp-block-image img.wp-image-${ created.attachmentId }`
				)
				.first();
			await img.scrollIntoViewIfNeeded();
			await expect( img ).toHaveAttribute(
				'src',
				/^https:\/\/res\.cloudinary\.com\//
			);
			expectCloudinaryUrl( await img.getAttribute( 'src' ), cloudName );
		} );

		test( 'disabling lazy load removes the placeholder markup', async ( {
			browser,
			baseURL,
		} ) => {
			configure( { breakpoints: false, lazyLoad: false } );

			const { attrs, html } = await serverMarkup( browser, baseURL );

			expect( attrs[ 'data-cloudinary' ] ).toBeUndefined();
			expect( attrs[ 'data-size' ] ).toBeUndefined();
			expect( attrs.onload || '' ).not.toContain( 'CLDBind' );
			expect( attrs[ 'data-srcset' ] ).toBeUndefined();
			expect( html ).not.toContain( 'var CLDLB' );

			// The real Cloudinary URL is in `src` straight from the server.
			expectCloudinaryUrl( attrs.src, cloudName );
		} );
	}
);
