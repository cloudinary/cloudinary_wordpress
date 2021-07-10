/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

import { ToggleControl } from '@wordpress/components';
import { withDispatch, withSelect } from '@wordpress/data';

// Set our component.
let FeaturedTransformationsToggle = ( props ) => {
	return (
		<>
			{ props.modalClass && (
				<ToggleControl
					label={ __( 'Overwrite Transformations', 'cloudinary' ) }
					checked={ props.overwrite_featured_transformations }
					onChange={ ( value ) => props.setOverwrite( value ) }
				/>
			) }
		</>
	);
};

// Setup our properties.
FeaturedTransformationsToggle = withSelect( ( select ) => ( {
	overwrite_featured_transformations:
		select( 'core/editor' )?.getEditedPostAttribute( 'meta' )
			._cloudinary_featured_overwrite ?? false,
} ) )( FeaturedTransformationsToggle );

// Setup our update method.
FeaturedTransformationsToggle = withDispatch( ( dispatch ) => {
	return {
		setOverwrite: ( value ) => {
			dispatch( 'core/editor' ).editPost( {
				meta: { _cloudinary_featured_overwrite: value },
			} );
		},
	};
} )( FeaturedTransformationsToggle );

// This filter callback must return a class component because the original from core is a class component. See:
// https://github.com/WordPress/gutenberg/blob/95188199c8b8045322d7f75a2666d47ea6504ad2/packages/media-utils/src/components/media-upload/index.js#L227
// If this callback returns a function component and other components use the same filter expecting a class component, the
// resulting error will break the editor.
// @see https://github.com/ampproject/amp-wp/issues/5534
const cldFilterFeatured = ( InitialMediaUpload ) => {
	return class CloudinaryMediaUpload extends InitialMediaUpload {
		render() {
			// We only need this on a MediaUpload component that has a value.
			return (
				<>
					{ super.render() }
					{ !! this.props.value && (
						<FeaturedTransformationsToggle { ...this.props } />
					) }
				</>
			);
		}
	};
};

// Setup an init wrapper.
const Featured = {
	_init() {
		// Add it to Media Upload to allow for deeper connection with getting
		// the media object, to determine if an asset has transformations.
		// Also adds deeper support for other image types within Guttenberg.
		// @todo: find other locations (i.e Video poster).
		wp.hooks.addFilter(
			'editor.MediaUpload',
			'cloudinary/filter-featured-image',
			cldFilterFeatured
		);
	},
};

// Push Init.
Featured._init();

// Export to keep it in scope.
export default Featured;
