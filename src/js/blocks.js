/**
 * Internal dependencies
 */
import Video from './components/video';
import Featured from './components/featured-image';
import Terms from './components/terms-inspector';
import EnforceContentChangeOnSave from './components/enforce-content-change-on-save';

// jQuery, because reasons.
window.$ = window.jQuery;

// Global Constants
export const cloudinaryBlocks = {
	Video,
	Featured,
	Terms,
	EnforceContentChangeOnSave,
};
