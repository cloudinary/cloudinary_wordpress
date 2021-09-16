/**
 * External dependencies
 */
import 'loading-attribute-polyfill';
import './components/taxonomies';
/**
 * Internal dependencies
 */
import Settings from './components/settings-page';
import Widget from './components/widget';
import GlobalTransformations from './components/global-transformations';
import TermsOrder from './components/terms-order';
import MediaLibrary from './components/media-library';
import Notices from './components/notices';
import UI from './components/ui';
import Wizard from './components/wizard';

import '../css/main.scss';

// include images.
import '../css/images/bandwidth.svg';
import '../css/images/star.svg';
import '../css/images/cloud.svg';
import '../css/images/crop.svg';
import '../css/images/gallery.svg';
import '../css/images/image.svg';
import '../css/images/units.svg';
import '../css/images/units-plus.svg';
import '../css/images/requests.svg';
import '../css/images/responsive.svg';
import '../css/images/learn.svg';
import '../css/images/logo-icon.svg';
import '../css/images/transformation.svg';
import '../css/images/upload.svg';
import '../css/images/video.svg';
import '../css/images/connection-string.png';
import '../css/images/sample.webp';
import '../css/images/wizard-welcome.png';
import '../css/images/document.svg';
import '../css/images/arrow.svg';

// jQuery, because reasons.
window.$ = window.jQuery;

// Global Constants
export const cloudinary = {
	UI,
	Settings,
	Widget,
	GlobalTransformations,
	TermsOrder,
	MediaLibrary,
	Notices,
	Wizard
};
