/* global window */
/**
 * Main JS.
 */

// Components
import Video from './components/video';
import Featured from './components/featured-image';


// jQuery, because reasons.
const $ = window.$ = window.jQuery;

// Global Constants
export const cloudinaryBlocks = {
	Video,
	Featured
};

