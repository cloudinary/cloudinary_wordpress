/**
 * Assertions shared by the front-end delivery specs.
 */

const { expect } = require( '../fixtures' );

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
	expectCloudinaryUrl,
	parseSrcset,
	srcsetUrls,
};
