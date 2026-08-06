/* global cloudinaryGalleryApi CLD_GALLERY_CONFIG CLD_REST_ENDPOINT */

/**
 * External dependencies
 */
import Dot from 'dot-object';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import '@wordpress/components/build-style/style.css';
import { useEffect, useMemo, useState } from '@wordpress/element';
import {
	InspectorControls,
	MediaPlaceholder,
	useBlockProps,
} from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import '../../css/gallery.scss';
import Controls from './controls';
import { ALLOWED_MEDIA_TYPES } from './options';
import { generateId, setupAttributesForRendering, showNotice } from './utils';

const dot = new Dot( '_' );

const PLACEHOLDER_TEXT = __(
	'Drag images, upload new ones or select files from your library.',
	'cloudinary'
);

const galleryWidgetConfig = ( config, container ) => ( {
	...config,
	container: '.' + container,
	zoom: false,
} );

// Prefix for the generated gallery container class. A literal prefix keeps
// the value a valid, unique CSS selector. Prior to the Block API v2 upgrade
// the block's `className` prop was used, but that prop is no longer passed to
// `Edit` in API v2, which produced containers named "undefined<id>".
const CONTAINER_PREFIX = 'cld-gallery-';

const Edit = ( { setAttributes, attributes, isSelected } ) => {
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	const preparedAttributes = useMemo( () => {
		// Do not override block settings with defaults on existing ones.
		if ( 0 !== attributes.selectedImages.length ) {
			return attributes;
		}

		const defaultAttrs = {};

		const { container, ...flattenedAttrs } = dot.dot( CLD_GALLERY_CONFIG );

		Object.keys( flattenedAttrs ).forEach( ( attr ) => {
			if ( ! attributes[ attr ] ) {
				defaultAttrs[ attr ] = flattenedAttrs[ attr ];
			}
		} );

		return { ...attributes, ...defaultAttrs };
	}, [ attributes ] );

	const getAttachmentIds = useMemo( () => {
		if ( ! attributes.selectedImages.length ) {
			return [];
		}

		return attributes.selectedImages.map( ( { attachmentId } ) => ( {
			id: attachmentId,
		} ) );
	}, [ attributes ] );

	const onSelect = async ( images ) => {
		setLoading( true );

		try {
			const selectedImages = await apiFetch( {
				path: CLD_REST_ENDPOINT + '/image_data',
				method: 'POST',
				data: { images },
			} );

			setAttributes( { selectedImages } );
		} catch {
			setLoading( false );

			setErrorMessage(
				__(
					'Could not load selected images. Please try again.',
					'cloudinary'
				)
			);
		}
	};

	useEffect( () => {
		if ( errorMessage ) {
			showNotice( { status: 'error', message: errorMessage } );
			setErrorMessage( null );
		}

		if ( attributes.selectedImages.length ) {
			let gallery;

			const { customSettings, ...config } =
				setupAttributesForRendering( attributes );

			try {
				gallery = cloudinary.galleryWidget(
					galleryWidgetConfig(
						{ ...config, ...customSettings },
						attributes.container
					)
				);
			} catch {
				gallery = cloudinary.galleryWidget(
					galleryWidgetConfig( config, attributes.container )
				);
			}

			gallery.render();
			setLoading( false );

			return () => gallery.destroy();
		}
	}, [ errorMessage, attributes, setAttributes ] );

	const hasImages = !! attributes.selectedImages.length;

	// Persist the generated container id and prepared defaults after render.
	// Calling setAttributes during render triggers a React "Cannot update a
	// component while rendering a different component" warning under React 19
	// (WordPress 7.1).
	useEffect( () => {
		if ( ! attributes.container ) {
			setAttributes( {
				container: `${ CONTAINER_PREFIX }${ generateId( 15 ) }`,
			} );
		}
	}, [ attributes.container, setAttributes ] );

	useEffect( () => {
		setAttributes( preparedAttributes );
	}, [ preparedAttributes, setAttributes ] );

	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<>
				<div className={ attributes.container }></div>
				<div className="wp-block-cloudinary-gallery">
					<MediaPlaceholder
						labels={ {
							title:
								! hasImages &&
								__( 'Cloudinary Gallery', 'cloudinary' ),
							instructions: ! hasImages && PLACEHOLDER_TEXT,
						} }
						icon="format-gallery"
						disableMediaButtons={ hasImages && ! isSelected }
						allowedTypes={ ALLOWED_MEDIA_TYPES }
						addToGallery={ hasImages }
						isAppender={ hasImages }
						onSelect={ ( images ) => onSelect( images ) }
						value={ getAttachmentIds }
						multiple
					>
						{ loading && (
							<div className="loading-spinner-container">
								<Spinner />
							</div>
						) }
					</MediaPlaceholder>
				</div>
			</>
			<InspectorControls>
				<Controls
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
		</div>
	);
};

export default Edit;
