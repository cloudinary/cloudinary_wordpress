import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

const MaybeReloadAfterSave = () => {
	const isSaving = useSelect( ( select ) =>
		select( 'core/editor' ).isSavingPost()
	);
	const isDirty = useSelect( ( select ) =>
		select( 'core/editor' ).isEditedPostDirty()
	);
	const { storage } = window?.CLDN || null;

	useEffect( () => {
		if ( isSaving && ! isDirty && 'cld' === storage ) {
			window.location.reload();
		}
	}, [ isSaving ] );
};

registerPlugin( 'cloudinary-maybe-reload-after-save', {
	render: MaybeReloadAfterSave,
} );

export default MaybeReloadAfterSave;
