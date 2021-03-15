/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	LayoutClassic,
	LayoutGridOneColumn,
	LayoutGridThreeColumn,
	LayoutGridTwoColumn,
	ShapeNone,
	ShapeRadius,
	ShapeRectangle,
	ShapeRound,
	ShapeSquare,
} from './icons';

export const LAYOUT_OPTIONS = [
	{
		value: { type: 'expanded', columns: 1 },
		icon: LayoutGridOneColumn,
		label: __( 'Expanded - 1 Column', 'cloudinary' ),
	},
	{
		value: { type: 'expanded', columns: 2 },
		icon: LayoutGridTwoColumn,
		label: __( 'Expanded - 2 Column', 'cloudinary' ),
	},
	{
		value: { type: 'expanded', columns: 3 },
		icon: LayoutGridThreeColumn,
		label: __( 'Expanded - 3 Column', 'cloudinary' ),
	},
	{
		value: { type: 'classic', columns: 1 },
		icon: LayoutClassic,
		label: __( 'Classic', 'cloudinary' ),
	},
];

export const ALLOWED_MEDIA_TYPES = [ 'image' ];

export const COLORS = [
	{ value: 'red', color: '#f00' },
	{ value: 'white', color: '#fff' },
	{ value: 'blue', color: '#00f' },
];

export const ASPECT_RATIOS = [
	{ label: __( '1:1', 'cloudinary' ), value: '1:1' },
	{ label: __( '3:4', 'cloudinary' ), value: '3:4' },
	{ label: __( '4:3', 'cloudinary' ), value: '4:3' },
	{ label: __( '4:6', 'cloudinary' ), value: '4:6' },
	{ label: __( '6:4', 'cloudinary' ), value: '6:4' },
	{ label: __( '5:7', 'cloudinary' ), value: '5:7' },
	{ label: __( '7:5', 'cloudinary' ), value: '7:5' },
	{ label: __( '8:5', 'cloudinary' ), value: '8:5' },
	{ label: __( '5:8', 'cloudinary' ), value: '5:8' },
	{ label: __( '9:16', 'cloudinary' ), value: '9:16' },
	{ label: __( '16:9', 'cloudinary' ), value: '16:9' },
];

export const FADE_TRANSITIONS = [
	{ label: __( 'None', 'cloudinary' ), value: 'none' },
	{ label: __( 'Fade', 'cloudinary' ), value: 'fade' },
	{ label: __( 'Slide', 'cloudinary' ), value: 'slide' },
];

export const NAVIGATION = [
	{ label: __( 'Always', 'cloudinary' ), value: 'always' },
	{ label: __( 'None', 'cloudinary' ), value: 'none' },
	{ label: __( 'MouseOver', 'cloudinary' ), value: 'mouseover' },
];

export const ZOOM_TYPE = [
	{ label: __( 'Inline', 'cloudinary' ), value: 'inline' },
	{ label: __( 'Flyout', 'cloudinary' ), value: 'flyout' },
	{ label: __( 'Popup', 'cloudinary' ), value: 'popup' },
];

export const ZOOM_VIEWER_POSITION = [
	{ label: __( 'Top', 'cloudinary' ), value: 'top' },
	{ label: __( 'Bottom', 'cloudinary' ), value: 'bottom' },
	{ label: __( 'Left', 'cloudinary' ), value: 'left' },
	{ label: __( 'Right', 'cloudinary' ), value: 'right' },
];

export const ZOOM_TRIGGER = [
	{ label: __( 'Click', 'cloudinary' ), value: 'click' },
	{ label: __( 'Hover', 'cloudinary' ), value: 'hover' },
];

export const CAROUSEL_LOCATION = [
	{ label: __( 'Left', 'cloudinary' ), value: 'left' },
	{ label: __( 'Right', 'cloudinary' ), value: 'right' },
	{ label: __( 'Top', 'cloudinary' ), value: 'top' },
	{ label: __( 'Bottom', 'cloudinary' ), value: 'bottom' },
];

export const CAROUSEL_STYLE = [
	{ label: __( 'Thumbnails', 'cloudinary' ), value: 'thumbnails' },
	{ label: __( 'Indicators', 'cloudinary' ), value: 'indicators' },
	{ label: __( 'None', 'cloudinary' ), value: 'none' },
];

export const NAVIGATION_BUTTON_SHAPE = [
	{
		value: 'round',
		icon: ShapeRound,
		label: __( 'Round', 'cloudinary' ),
	},
	{
		value: 'radius',
		icon: ShapeRadius,
		label: __( 'Radius', 'cloudinary' ),
	},
	{
		value: 'none',
		icon: ShapeNone,
		label: __( 'None', 'cloudinary' ),
	},
	{
		value: 'square',
		icon: ShapeSquare,
		label: __( 'Square', 'cloudinary' ),
	},
	{
		value: 'rectangle',
		icon: ShapeRectangle,
		label: __( 'Rectangle', 'cloudinary' ),
	},
];

export const INDICATOR_SHAPE = [
	{
		value: 'round',
		icon: ShapeRound,
		label: __( 'Round', 'cloudinary' ),
	},
	{
		value: 'radius',
		icon: ShapeRadius,
		label: __( 'Radius', 'cloudinary' ),
	},
	{
		value: 'square',
		icon: ShapeSquare,
		label: __( 'Square', 'cloudinary' ),
	},
];

export const SELECTED_STYLE = [
	{ label: __( 'All', 'cloudinary' ), value: 'all' },
	{ label: __( 'Border', 'cloudinary' ), value: 'border' },
	{ label: __( 'Gradient', 'cloudinary' ), value: 'gradient' },
];

export const SELECTED_BORDER_POSITION = [
	{ label: __( 'All', 'cloudinary' ), value: 'all' },
	{ label: __( 'Top', 'cloudinary' ), value: 'top' },
	{ label: __( 'Top-Bottom', 'cloudinary' ), value: 'top-bottom' },
	{ label: __( 'Left-Right', 'cloudinary' ), value: 'left-right' },
	{ label: __( 'Bottom', 'cloudinary' ), value: 'bottom' },
	{ label: __( 'Left', 'cloudinary' ), value: 'left' },
	{ label: __( 'Right', 'cloudinary' ), value: 'right' },
];

export const MEDIA_ICON_SHAPE = [
	{
		value: 'round',
		icon: ShapeRound,
		label: __( 'Round', 'cloudinary' ),
	},
	{
		value: 'radius',
		icon: ShapeRadius,
		label: __( 'Radius', 'cloudinary' ),
	},
	{
		value: 'none',
		icon: ShapeNone,
		label: __( 'None', 'cloudinary' ),
	},
	{
		value: 'square',
		icon: ShapeSquare,
		label: __( 'Square', 'cloudinary' ),
	},
];

export const RESIZE_CROP = [
	{
		label: __( 'Pad', 'cloudinary' ),
		value: 'pad',
	},
	{
		label: __( 'Fill', 'cloudinary' ),
		value: 'fill',
	},
];

export const PAD_STYLES = [
	{
		label: __( 'White padding', 'cloudinary' ),
		value: 'rgb:FFFFFF',
	},
	{
		label: __( 'Border color padding', 'cloudinary' ),
		value: 'auto',
	},
	{
		label: __( 'Predominant color padding', 'cloudinary' ),
		value: 'auto:predominant',
	},
	{
		label: __( 'Gradient fade padding', 'cloudinary' ),
		value: 'auto:predominant_gradient',
	},
];
