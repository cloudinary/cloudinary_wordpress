/**
 * Internal dependencies
 */
import Video from './components/video';
import Featured from './components/featured-image';
import Terms from './components/terms-inspector';
import DisableUpdatePostButtonIfNoChanges from './components/update-post';

// jQuery, because reasons.
window.$ = window.jQuery;

// Global Constants
export const cloudinaryBlocks = {
	Video,
	Featured,
	Terms,
	DisableUpdatePostButtonIfNoChanges,
};
