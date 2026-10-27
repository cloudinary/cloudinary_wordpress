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
 * Cloudinary URLs carry commas inside the transformation segment
 * (`w_800,h_600,c_scale`), so splitting on `,` breaks them apart. A
 * candidate ends at its descriptor, or at a comma followed by whitespace
 * when it has none.
 *
 * @param {string|null} srcset The raw attribute value.
 * @return {{ url: string, descriptor: string }[]} Candidates in source order.
 */
function parseSrcset( srcset ) {
	if ( ! srcset ) {
		return [];
	}
	const candidates = [];
	const pattern = /\s*(\S+?)(?:\s+(\d+(?:\.\d+)?[wx]))?\s*(?:,(?=\s)|,?$)/gy;
	let match;
	while ( pattern.lastIndex < srcset.length ) {
		match = pattern.exec( srcset );
		if ( ! match || ! match[ 0 ] ) {
			break;
		}
		candidates.push( { url: match[ 1 ], descriptor: match[ 2 ] || '' } );
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
