import { __ } from '@wordpress/i18n';
import AssetPreview from './components/asset-preview';
import AssetEditor from './components/asset-editor';

const AssetEdit = {
	wrap: document.getElementById( 'cld-asset-edit' ),
	preview: null,
	id: null,
	editor: null,
	base: null,
	publicId: null,
	size: null,
	transformationsInput: document.getElementById( 'cld-asset-edit-transformations' ),
	textOverlayTransformations: '',
	saveButton: document.getElementById( 'cld-asset-edit-save' ),
	currentURL: null,
	init() {
		const item = JSON.parse( this.wrap.dataset.item );
		this.id = item.ID;
		this.base = item.base + item.size + '/';
		this.publicId = item.file;
		this.transformationsInput.value = item.transformations ? item.transformations : '';

		// Init components.
		this.initPreview();
		this.initEditor();
		this.initGravityGrid( 'edit-overlay-grid-text' );
		this.initGravityGrid( 'edit-overlay-grid-image' );
		this.initImageSelect();
	},
	initPreview() {
		this.preview = AssetPreview.init();
		this.wrap.appendChild( this.preview.createPreview( 500, 400 ) );
		this.preview.setSrc( this.base + this.transformationsInput.value + this.publicId, true );
		this.transformationsInput.addEventListener( 'input', ( ev ) => {
			this.preview.setSrc( this.base + this.transformationsInput.value + this.publicId );
		} );
		this.transformationsInput.addEventListener( 'keydown', ( ev ) => {
			if ( 'Enter' === ev.code ) {
				ev.preventDefault();
				this.saveButton.dispatchEvent( new Event( 'click' ) );
			}
		} );
	},
	initEditor() {
		this.editor = AssetEditor.init();
		this.editor.onBefore( () => this.preview.reset() );
		this.editor.onComplete( ( result ) => {
			this.transformationsInput.value = result.transformations;
			this.preview.setSrc( this.base + result.transformations + this.publicId, true );
			if ( result.note ) {
				alert( result.note );
			}
		} );

		this.saveButton.addEventListener( 'click', () => {
			this.editor.save( {
				ID: this.id,
				transformations: this.transformationsInput.value,
			} );
		} );
	},
	initGravityGrid( gridId ) {
		const grid = document.getElementById( gridId );
		let gridOptions = [];

		if ( ! grid || ! grid.dataset?.gridOptions ) {
			return;
		}

		try {
			gridOptions = JSON.parse( grid.dataset.gridOptions );

			if( gridOptions.length < 1 ) {
				return;
			}
		} catch ( e ) {
			return;
		}

		gridOptions.forEach( ( option, index ) => {
			const cell = document.createElement( 'div' );
			cell.className = 'edit-overlay-grid__cell';
			cell.dataset.gravity = option;

			cell.addEventListener( 'click', () => {
				grid.querySelectorAll( '.edit-overlay-grid__cell--selected' ).forEach( c => c.classList.remove( 'edit-overlay-grid__cell--selected' ) );
				cell.classList.add( 'edit-overlay-grid__cell--selected' );
			});

			grid.appendChild(cell);
		});
	},
	initImageSelect() {
		const imageSelect = document.getElementById( 'edit-overlay-select-image' );
		const imagePreviewWrapper = document.getElementById( 'edit-overlay-select-image-preview' );

		if ( ! imageSelect ) {
			return;
		}

		imageSelect.addEventListener( 'click', ( ev ) => {
			ev.preventDefault();

			const frame = wp.media({
				title: __( 'Select Image', 'cloudinary' ),
				button: {
					text: __( 'Select Image', 'cloudinary' ),
				},
				library: { type: 'image' },
				multiple: false
			});

			frame.on( 'select', () => {
				const attachment = frame.state().get('selection').first().toJSON();
				// Remove existing preview image if any
				if (imagePreviewWrapper.firstChild) {
					imagePreviewWrapper.removeChild(imagePreviewWrapper.firstChild);
				}

				// Create and insert new preview image
				const img = document.createElement('img');
				img.src = attachment.url;
				img.alt = attachment.alt || '';
				imagePreviewWrapper.appendChild(img);
			});

			frame.open();
		} );
	}
};

window.addEventListener( 'load', () => AssetEdit.init() );

export default AssetEdit;
