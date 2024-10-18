import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Enforce content change on save to prevent breaking the image block.
 *
 * @return {null} There is nothing to render.
 */
const EnforceContentChangeOnSave = () => {
	const { editPost } = useDispatch( 'core/editor' );
	const postContent = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'content' ),
		[]
	);
	const hasChanges = useSelect(
		( select ) => select( 'core/editor' ).hasChangedContent(),
		[]
	);

	const isSaving = useSelect(
		( select ) => select( 'core/editor' ).isSavingPost(),
		[]
	);

	useEffect( () => {
		if ( ! hasChanges && isSaving ) {
			editPost( { content: postContent + ' ' } );
		}
	}, [ hasChanges, isSaving ] );

	return null;
};

registerPlugin( 'enforce-content-change-on-save', {
	render: EnforceContentChangeOnSave,
} );

export default EnforceContentChangeOnSave;
