/**
 * Internal dependencies
 */
import Video from './components/video';
import Featured from './components/featured-image';
import Terms from './components/terms-inspector';

window.addEventListener( 'load', () => Terms._init() );
// Global Constants
export const cloudinaryBlocks = {
	Video,
	Featured,
	Terms,
};
