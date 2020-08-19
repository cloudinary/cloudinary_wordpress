/* global window */
/**
 * Main JS.
 */

// Components
import settings from './components/settings-page';
import sync from './components/sync';
import widget from './components/widget';
import Global_Transformations from './components/global-transformations';
import Terms_Order from './components/terms_order';
import Media_Library from './components/media-library';

import '../../css/src/main.scss';

// jQuery, because reasons.
const $ = window.$ = window.jQuery;

// Global Constants
export const cloudinary = {
	settings,
	sync,
	widget,
	Global_Transformations,
	Terms_Order,
	Media_Library,
};

