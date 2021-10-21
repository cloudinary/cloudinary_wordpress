import { __ } from '@wordpress/i18n';
import AssetEditModal from './components/asset-edit-modal';

const MediaTransformations = {
	triggers: document.querySelectorAll( '[data-transformation-item]' ),
	editModal: AssetEditModal.init( 'cldAsset', 600 ),
	init() {
		this.triggers.forEach( ( trigger ) => {
			this._bind( trigger );
		} );
	},
	_bind( trigger ) {
		const item = JSON.parse( trigger.dataset.transformationItem );
		trigger.addEventListener( 'click', ( ev ) => {
			ev.preventDefault();
			this.editModal.edit( item, ( transformations ) => {
				item.transformations = transformations;
				if ( ! transformations.length ) {
					trigger.innerText = __( 'Add transformations', 'cloudinary' );
				} else {
					trigger.innerText = item.transformations;
				}
			} );
		} );
	}
};

window.addEventListener( 'load', () => MediaTransformations.init() );
