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
 * Split a `srcset` attribute into its candidate URLs.
 *
 * @param {string|null} srcset The raw attribute value.
 * @return {string[]} Candidate URLs, without width/density descriptors.
 */
function srcsetUrls( srcset ) {
	if ( ! srcset ) {
		return [];
	}
	return srcset
		.split( ',' )
		.map( ( candidate ) => candidate.trim().split( /\s+/ )[ 0 ] )
		.filter( Boolean );
}

module.exports = {
	expectCloudinaryUrl,
	srcsetUrls,
};
