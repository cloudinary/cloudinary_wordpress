/* global window wp */

import { __ } from '@wordpress/i18n';

import { ToggleControl, PanelBody } from '@wordpress/components';
import { withSelect, withDispatch } from '@wordpress/data';

// Set our component.
let FeaturedTransformationsToggle = ( props ) => {

    // We only show the toggle if the item has transformations.
    return (
        <>
            { props.media && props.media.transformations &&
                <ToggleControl
                    label={ __( 'Overwrite Transformations', 'cloudinary' ) }
                    checked={ props.overwrite_featured_transformations }
                    onChange={ ( value ) => props.setOverwrite( value ) }
                />
            }
        </>
    );
};

// Setup our properties.
FeaturedTransformationsToggle = withSelect( ( select, ownProps ) => ( {
    // We get the media item here since we add in the property indicating if it has transformations.
    media: ownProps.value ? select( 'core' ).getMedia( ownProps.value ) : {},
    overwrite_featured_transformations: select( 'core/editor' ).getEditedPostAttribute( 'meta' )[ '_overwrite_featured_transformations' ] ?? false,
} ) )( FeaturedTransformationsToggle );

// Setup our update method.
FeaturedTransformationsToggle = withDispatch(
    ( dispatch ) => {
        return {
            setOverwrite: ( value ) => {
                dispatch( 'core/editor' ).editPost( { meta: { _overwrite_featured_transformations: value } } );
            }
        };
    }
)( FeaturedTransformationsToggle );

// Hook in and add our component.
const cldFilterFeatured = ( BlockEdit ) => {
    return ( props ) => {
        // We only need this on a MediaUpload component that has a value.
        return (
            <>
                <BlockEdit { ...props }  />
                { props.value &&
                    <FeaturedTransformationsToggle { ...props } />
                }
            </>
        );
    };
};

// Setup an init wrapper.
const Featured = {
    _init: function() {
        // Add it to Media Upload to allow for deeper connection with getting
        // the media object, to determine if an asset has transformations.
        // Also adds deeper support for other image types within Guttenberg.
        // @todo: find other locations (i.e Video poster).
        wp.hooks.addFilter( 'editor.MediaUpload', 'cloudinary/filter-featured-image', cldFilterFeatured );
    },
};

// Push Init.
Featured._init();

// Export to keep it in scope.
export default Featured;
