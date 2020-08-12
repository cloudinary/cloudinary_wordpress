(() => {
  if ( ! wp.compose ) return;

  const withState = wp.compose.withState;	
  const withSelect = wp.data.withSelect;	
  const withDispatch = wp.data.withDispatch;
  const { ToggleControl } = wp.components;

  const StatefulToggle = ( { meta, updateOverrideFeaturedImage } ) => {
    return (
      <ToggleControl 
        label="Overwrite Transformations" 
        checked={ meta.overwrite_transformations_featured_image } 
        onChange={ ( enabled ) =>  updateOverrideFeaturedImage( enabled, meta ) } 
      />
    );
  };

  const ComposedToggle = wp.compose.compose( [
    withState( ( value ) => ({ isChecked: value }) ),

    withSelect( ( select ) => {
        const currentMeta = select( 'core/editor' ).getCurrentPostAttribute( 'meta' );
        const editedMeta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );

        return { meta: { ...currentMeta, ...editedMeta } };
    } ),

    withDispatch( ( dispatch ) => ( {
        updateOverrideFeaturedImage( value, meta ) {
            meta = {
                ...meta,
                overwrite_transformations_featured_image: value,
            };

            dispatch( 'core/editor' ).editPost( { meta } );
        },
    } ) ),
  ] )( StatefulToggle );

  const wrapPostFeaturedImage = ( OriginalComponent ) => ( props ) => (
    <>
      <ComposedToggle />
      <OriginalComponent {...props} />
    </>
  );

  wp.hooks.addFilter( 
    'editor.PostFeaturedImage', 
    'cloudinary/overwrite-transformations-featured-image', 
    wrapPostFeaturedImage
  );
})()