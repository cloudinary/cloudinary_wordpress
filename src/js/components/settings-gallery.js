/*global CLD_THEME_COLORS, CLD_GALLERY_CONFIG */

import React from 'react';
import Dot from 'dot-object';
import { render, useEffect, useState } from '@wordpress/element';
import GalleryControls from '../gallery-block/controls';

import {
	setupAttributesForRendering,
	toBlockAttributes,
} from '../gallery-block/utils';

const { cloudName, mediaAssets, ...attributes } = toBlockAttributes(
	new Dot( '_' ).dot( CLD_GALLERY_CONFIG )
);

const parsedAttrs = {};
Object.keys( attributes ).forEach( ( attr ) => {
	parsedAttrs[ attr ] = attributes[ attr ]?.default;
} );

const galleryWidgetConfig = ( config ) => ( {
	cloudName: 'demo',
	...config,
	mediaAssets: [
		{
			tag: 'shoes_product_gallery_demo',
			mediaType: 'image',
		},
	],
	container: '.gallery-preview',
} );

const app = document.querySelector( '#cloudinary-settings-page form' );

if ( 'undefined' !== typeof app ) {
	app.addEventListener( 'submit', function ( event ) {
		if (
			! event.submitter.name ||
			( event.submitter.name &&
				'cld_submission' !== event.submitter.name )
		) {
			event.preventDefault();
		}
	} );
}

const StatefulGalleryControls = () => {
	const [ statefulAttrs, setStatefulAttrs ] = useState( parsedAttrs );

	const setAttributes = ( attrs ) => {
		setStatefulAttrs( {
			...statefulAttrs,
			...attrs,
		} );
	};

	useEffect( () => {
		let gallery;

		const config = setupAttributesForRendering( statefulAttrs );
		const { customSettings, ...mainConfig } = config;

		try {
			gallery = cloudinary.galleryWidget(
				galleryWidgetConfig( { ...mainConfig, ...customSettings } )
			);
		} catch {
			gallery = cloudinary.galleryWidget(
				galleryWidgetConfig( mainConfig )
			);
		}

		gallery.render();

		const hiddenField = document.getElementById( 'gallery_settings_input' );

		if ( hiddenField ) {
			hiddenField.value = JSON.stringify( config );
		}

		return () => gallery.destroy();
	} );

	return (
		<div className="cld-gallery-settings-container">
			<div className="cld-gallery-settings">
				<div className="interface-interface-skeleton__sidebar cld-gallery-settings__column">
					<div className="interface-complementary-area edit-post-sidebar">
						<div className="components-panel">
							<div className="block-editor-block-inspector">
								<GalleryControls
									attributes={ statefulAttrs }
									setAttributes={ setAttributes }
									colors={ CLD_THEME_COLORS }
								/>
							</div>
						</div>
					</div>
				</div>
				<div className="gallery-preview cld-gallery-settings__column"></div>
			</div>
		</div>
	);
};

render(
	<StatefulGalleryControls />,
	document.getElementById( 'app_gallery_gallery_config' )
);
