/**
 * Internal dependencies
 */
import Video from './components/video';
import Featured from './components/featured-image';
import Terms from './components/terms-inspector';
import MaybeReloadAfterSave from './components/maybe-reload-after-save';

// jQuery, because reasons.
window.$ = window.jQuery;

// Global Constants
export const cloudinaryBlocks = {
	Video,
	Featured,
	Terms,
	MaybeReloadAfterSave,
};
