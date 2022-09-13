/**
 * External dependencies
 */
import 'loading-attribute-polyfill';
import './components/taxonomies';
/**
 * Internal dependencies
 */
import Settings from './components/settings-page';
import GlobalTransformations from './components/global-transformations';
import MediaLibrary from './components/media-library';
import Notices from './components/notices';
import UI from './components/ui';
import Wizard from './components/wizard';
import Storage from "./components/storage";
import Extensions from "./components/extensions";
import Tabs from "./components/tabs";

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
import '../css/images/wizard-welcome.jpg';
import '../css/images/document.svg';
import '../css/images/arrow.svg';
import '../css/images/documentation.jpg';
import '../css/images/request.jpg';
import '../css/images/report.jpg';
import '../css/images/confetti.png';
import '../css/images/circular-loader.svg';
import '../css/images/dam-icon.svg';


// jQuery, because reasons.
window.$ = window.jQuery;

// Global Constants
export const cloudinary = {
	UI,
	Settings,
	GlobalTransformations,
	MediaLibrary,
	Notices,
	Wizard,
	Storage,
	Extensions,
	Tabs
};
