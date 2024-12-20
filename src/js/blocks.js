/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import Video from './components/video';
import Featured from './components/featured-image';
import Terms from './components/terms-inspector';

// jQuery, because reasons.
window.$ = window.jQuery;

// Register middleware for @wordpress/api-fetch to indicate the fetch is coming from the editor.
// Taken from https://github.com/Automattic/jetpack/blob/trunk/projects/plugins/jetpack/extensions/editor.js.
apiFetch.use( ( options, next ) => {
	// Skip explicit cors requests.
	if ( options.mode === 'cors' ) {
		return next( options );
	}

	// If a URL is set, skip if it's not same-origin.
	// @see https://html.spec.whatwg.org/multipage/origin.html#same-origin
	if ( options.url ) {
		try {
			const url = new URL( options.url, location.href );
			if (
				url.protocol !== location.protocol ||
				url.hostname !== location.hostname ||
				url.port !== location.port
			) {
				return next( options );
			}
		} catch {
			// Huh? Skip it.
			return next( options );
		}
	}

	// Ok, add header.
	if ( ! options.headers ) {
		options.headers = {};
	}
	options.headers[ 'x-cld-fetch-from-editor' ] = 'true';
	return next( options );
} );

// Global Constants
export const cloudinaryBlocks = {
	Video,
	Featured,
	Terms,
};
