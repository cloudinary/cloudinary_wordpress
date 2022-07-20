import Dot from 'dot-object';
import cloneDeep from 'lodash/cloneDeep';
import { dispatch } from '@wordpress/data';

export const showNotice = ( { status, message, options = {} } ) => {
	dispatch( 'core/notices' ).createNotice(
		status, // Can be one of: success, info, warning, error.
		message,
		{ isDismissible: true, ...options }
	);
};

const dec2hex = ( dec ) => {
	return dec < 10 ? '0' + String( dec ) : dec.toString( 16 );
};

export const generateId = ( len ) => {
	const arr = new Uint8Array( ( len || 40 ) / 2 );
	window.crypto.getRandomValues( arr );
	return Array.from( arr, dec2hex ).join( '' );
};

export const sortObject = ( object ) => {
	const sortedObj = {};
	const keys = Object.keys( object );

	keys.sort();

	for ( const index in keys ) {
		const key = keys[ index ];
		if (
			typeof object[ key ] === 'object' &&
			! ( object[ key ] instanceof Array )
		) {
			sortedObj[ key ] = sortObject( object[ key ] );
		} else {
			sortedObj[ key ] = object[ key ];
		}
	}

	return sortedObj;
};

export const toBlockAttributes = ( object ) => {
	const blockAttributes = {};

	Object.keys( object ).forEach( ( key ) => {
		blockAttributes[ key ] = {
			type: typeof object[ key ],
			default: object[ key ],
		};
	} );

	return blockAttributes;
};

export const convertColors = ( color ) => {
	if ( color ) {
		if ( color.startsWith( 'var(' ) ) {
			color = color.replace( 'var(', '' ).replace( ')', '' );
		}

		if ( color.startsWith( '--' ) ) {
			color = getComputedStyle(
				document.documentElement
			).getPropertyValue( color );
		}
	}

	return color;
};

export const setupAttributesForRendering = ( attributes ) => {
	const dot = new Dot( '_' );

	const attributesClone = cloneDeep( attributes );
	let { selectedImages: mediaAssets, ...config } = dot.object(
		attributesClone,
		{}
	);

	config.mediaAssets = mediaAssets;

	if ( config?.displayProps?.mode !== 'classic' ) {
		delete config.transition;
	} else {
		delete config.displayProps.columns;
	}

	if ( 'pad' !== config?.transformation_crop ) {
		delete config.transformation_background;
	}

	if ( 'pad' !== config?.transformation?.crop ) {
		delete config.transformation.background;
	}

	if ( config?.themeProps?.primary ) {
		config.themeProps.primary = convertColors(
			config?.themeProps?.primary
		);
	}

	if ( config?.themeProps?.onPrimary ) {
		config.themeProps.onPrimary = convertColors(
			config?.themeProps?.onPrimary
		);
	}

	if ( config?.themeProps?.active ) {
		config.themeProps.active = convertColors( config?.themeProps?.active );
	}

	if ( config.customSettings ) {
		try {
			const customSettings = JSON.parse( config.customSettings );
			config = { ...config, customSettings };
		} catch ( err ) {}
	}

	return config;
};
