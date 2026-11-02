/**
 * Assertions shared by the front-end delivery specs.
 */

const { expect } = require( '../fixtures' );

/**
 * Default Cloudinary delivery host.
 *
 * @type {string}
 */
const CLOUDINARY_HOST = 'res.cloudinary.com';

/**
 * Assert that a given image URL is served by Cloudinary under the
 * expected cloud name. We intentionally do not assert specific
 * transformations — those are an implementation detail of the plugin
 * and may change.
 *
 * Pass an object to check delivery through a custom domain. With
 * `privateCdn` (the plugin's default for a custom domain), the path does
 * not start with the cloud name.
 *
 * @param {string}                                                            rawUrl   The src or srcset candidate.
 * @param {string|{ cloudName: string, host?: string, privateCdn?: boolean }} expected The cloud name parsed from
 *                                                                                     CLOUDINARY_E2E_URL, or the
 *                                                                                     expected host and path form.
 */
function expectCloudinaryUrl( rawUrl, expected ) {
	const {
		cloudName,
		host = CLOUDINARY_HOST,
		privateCdn = false,
	} = 'string' === typeof expected ? { cloudName: expected } : expected;

	let parsed;
	try {
		parsed = new URL( rawUrl );
	} catch ( e ) {
		throw new Error( `Image URL is not parseable: ${ rawUrl }` );
	}
	expect( parsed.host, `host of ${ rawUrl }` ).toBe( host );
	expect(
		parsed.pathname.startsWith( `/${ cloudName }/` ),
		`pathname of ${ rawUrl } should ${
			privateCdn ? 'not ' : ''
		}start with /${ cloudName }/`
	).toBe( ! privateCdn );
}

/**
 * Send requests for a fake custom domain on to res.cloudinary.com, so
 * images delivered through it still load.
 *
 * Cloudinary serves a custom domain at the same path, with the cloud name
 * at the start of the path only when the domain is not a private CDN.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object}                          options
 * @param {string}                          options.cname      The fake custom domain.
 * @param {string}                          options.cloudName  The cloud name.
 * @param {boolean}                         options.privateCdn Whether the path leaves out the cloud name.
 * @return {Promise<void>}
 */
async function routeCnameToCloudinary(
	page,
	{ cname, cloudName, privateCdn }
) {
	await page.route( `https://${ cname }/**`, ( route ) => {
		const url = new URL( route.request().url() );
		const prefix = privateCdn ? `/${ cloudName }` : '';
		return route.continue( {
			url: `https://${ CLOUDINARY_HOST }${ prefix }${ url.pathname }${ url.search }`,
		} );
	} );
}

/**
 * Parse a `srcset` attribute into its candidates.
 *
 * Follows the HTML parsing rule: a URL is a run of non-whitespace
 * characters, and trailing commas on it end the candidate. Otherwise the
 * descriptor runs up to the next comma. Commas inside a URL, such as in a
 * Cloudinary transformation segment (`w_800,h_600,c_scale`), stay part of
 * the URL.
 *
 * @param {string|null} srcset The raw attribute value.
 * @return {{ url: string, descriptor: string }[]} Candidates in source order.
 */
function parseSrcset( srcset ) {
	const candidates = [];
	const input = srcset || '';
	const urlPattern = /[\s,]*(\S+)/y;
	const descriptorPattern = /\s*([^,]*?)\s*(?:,|$)/y;
	for (
		let urlMatch = urlPattern.exec( input );
		urlMatch;
		urlMatch = urlPattern.exec( input )
	) {
		let url = urlMatch[ 1 ];
		let descriptor = '';
		if ( url.endsWith( ',' ) ) {
			url = url.replace( /,+$/, '' );
		} else {
			descriptorPattern.lastIndex = urlPattern.lastIndex;
			descriptor = descriptorPattern.exec( input )[ 1 ];
			urlPattern.lastIndex = descriptorPattern.lastIndex;
		}
		candidates.push( { url, descriptor } );
	}
	return candidates;
}

/**
 * Split a `srcset` attribute into its candidate URLs.
 *
 * @param {string|null} srcset The raw attribute value.
 * @return {string[]} Candidate URLs, without width/density descriptors.
 */
function srcsetUrls( srcset ) {
	return parseSrcset( srcset ).map( ( candidate ) => candidate.url );
}

module.exports = {
	CLOUDINARY_HOST,
	expectCloudinaryUrl,
	parseSrcset,
	routeCnameToCloudinary,
	srcsetUrls,
};
