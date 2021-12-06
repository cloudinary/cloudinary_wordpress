import { __ } from '@wordpress/i18n';

const RestrictedTypes = {
	init( context ) {
		const removers = context.querySelectorAll( '[data-remove]' );

		[ ...removers ].forEach( ( tick ) => {
			tick.addEventListener( 'click', ( ev ) => {
				if (
					tick.dataset.message &&
					! confirm( tick.dataset.message )
				) {
					return;
				}
				const remove = document.getElementById( tick.dataset.remove );
				remove.parentNode.removeChild( remove );
			} );
		} );
	},
};

export default RestrictedTypes;
