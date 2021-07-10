/* global CLD_VIDEO_PLAYER */

import { __ } from '@wordpress/i18n';
import { withSelect } from '@wordpress/data';
import { PanelBody, ToggleControl } from '@wordpress/components';

const Video = {
	_init() {
		if ( typeof CLD_VIDEO_PLAYER === 'undefined' ) {
			return;
		}

		// Gutenberg Video Settings
		wp.hooks.addFilter(
			'blocks.registerBlockType',
			'Cloudinary/Media/Video',
			function ( settings, name ) {
				if ( name === 'core/video' ) {
					if ( 'off' !== CLD_VIDEO_PLAYER.video_autoplay_mode ) {
						settings.attributes.autoplay.default = true;
					}

					if ( 'on' === CLD_VIDEO_PLAYER.video_loop ) {
						settings.attributes.loop.default = true;
					}

					if ( 'off' === CLD_VIDEO_PLAYER.video_controls ) {
						settings.attributes.controls.default = false;
					}
				}
				return settings;
			}
		);
	},
};

export default Video;

// Init.
Video._init();

const cldAddToggle = function ( settings, name ) {
	if ( 'core/image' === name || 'core/video' === name ) {
		if ( ! settings.attributes ) {
			settings.attributes = {};
		}

		settings.attributes.overwrite_transformations = {
			type: 'boolean',
		};

		settings.attributes.transformations = {
			type: 'boolean',
		};
	}

	return settings;
};

wp.hooks.addFilter(
	'blocks.registerBlockType',
	'cloudinary/addAttributes',
	cldAddToggle
);

/* eslint-disable camelcase */
const TransformationsToggle = ( props ) => {
	const {
		attributes: { overwrite_transformations },
		setAttributes,
	} = props;

	return (
		<PanelBody title={ __( 'Transformations', 'cloudinary' ) }>
			<ToggleControl
				label={ __( 'Overwrite Global Transformations', 'cloudinary' ) }
				checked={ overwrite_transformations }
				onChange={ ( value ) => {
					setAttributes( { overwrite_transformations: value } );
				} }
			/>
		</PanelBody>
	);
};
/* eslint-enable camelcase */

let ImageInspectorControls = ( props ) => {
	const { setAttributes, media } = props;
	const { InspectorControls } = wp.editor;

	if ( media && media.transformations ) {
		setAttributes( { transformations: true } );
	}

	return (
		<InspectorControls>
			<TransformationsToggle { ...props } />
		</InspectorControls>
	);
};

ImageInspectorControls = withSelect( ( select, ownProps ) => ( {
	...ownProps,
	media: ownProps.attributes.id
		? select( 'core' ).getMedia( ownProps.attributes.id )
		: null,
} ) )( ImageInspectorControls );

const cldFilterBlocksEdit = ( BlockEdit ) => {
	return ( props ) => {
		const { name } = props;
		const shouldDisplayInspector =
			'core/image' === name || 'core/video' === name;

		return (
			<>
				{ shouldDisplayInspector ? (
					<ImageInspectorControls { ...props } />
				) : null }
				<BlockEdit { ...props } />
			</>
		);
	};
};

wp.hooks.addFilter(
	'editor.BlockEdit',
	'cloudinary/filterEdit',
	cldFilterBlocksEdit,
	20
);
