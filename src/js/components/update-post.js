import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';

const DisableUpdatePostButtonIfNoChanges = () => {
	const hasChanges = useSelect(
		( select ) => select( 'core/editor' ).hasChangedContent(),
		[]
	);
	const updateButton = document.querySelector(
		'.editor-post-publish-button'
	);
	const isSaving = useSelect( ( select ) =>
		select( 'core/editor' ).isSavingPost()
	);
	const hasUpdateButton = updateButton !== null;

	useEffect( () => {
		if ( updateButton ) {
			updateButton.setAttribute( 'aria-disabled', ! hasChanges );
			updateButton.disabled = ! hasChanges && ! isSaving;
			updateButton.classList.toggle(
				'is-disabled',
				! hasChanges && ! isSaving
			);
		}
	}, [ hasChanges, hasUpdateButton ] );
};

registerPlugin( 'disable-update-button', {
	render: DisableUpdatePostButtonIfNoChanges,
} );

export default DisableUpdatePostButtonIfNoChanges;
