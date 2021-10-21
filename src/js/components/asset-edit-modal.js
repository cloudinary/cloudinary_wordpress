import { __ } from '@wordpress/i18n';
import AssetEditor from './asset-editor';
import AssetPreview from './asset-preview';
import CloudinaryModal from './modal';

const AssetEditModal = {
	id: null,
	modal: null,
	body: null,
	callback: null,
	preview: null,
	editor: null,
	base: null,
	publicId: null,
	size: null,
	transformationsInput: null,
	currentURL: null,
	init( id, previewSize = 400 ) {
		this.preview = AssetPreview.init();
		this.editor = AssetEditor.init();
		this.modal = CloudinaryModal.init( id );
		this.modal.appendContent( this.createCloser() );
		this.modal.appendContent( this.preview.createPreview( previewSize ) );
		this.modal.appendContent( this.createTransformationsInput() );
		this.modal.onClose( () => {
			this.preview.reset();
		} );
		this.transformationsInput.addEventListener( 'input', ( ev ) => {
			this.preview.setSrc( this.base + ev.target.value + this.publicId );
		} );

		this.editor.onBefore( () => this.preview.reset() );
		this.editor.onComplete( ( result ) => {
			this.transformationsInput.value = result.transformations;
			this.preview.setSrc(
				this.base + result.transformations + this.publicId,
				true
			);
			this.callback( result.transformations );
			if ( result.note ) {
				alert( result.note );
			}
		} );
		return this;
	},
	edit( item, callback ) {
		this.id = item.ID;
		this.base = item.base + item.size + '/';
		this.publicId = item.file;
		this.callback = callback;
		this.transformationsInput.value = item.transformations
			? item.transformations
			: '';
		this.modal.openModal();
		this.preview.setSrc(
			this.base + this.transformationsInput.value + this.publicId,
			true
		);
	},
	createCloser() {
		const closer = document.createElement( 'span' );
		closer.classList.add( 'dashicons', 'dashicons-no' );
		closer.style.position = 'absolute';
		closer.style.right = '-22px';
		closer.style.top = '-21px';
		closer.style.cursor = 'pointer';
		closer.addEventListener( 'click', () => {
			this.modal.closeModal();
		} );
		return closer;
	},
	createTransformationsInput() {
		const wrap = document.createElement( 'div' );
		const save = document.createElement( 'button' );
		const label = document.createElement( 'label' );
		label.innerText = __( 'Transformations', 'cloudinary' );
		label.style.width = '100%';
		this.transformationsInput = document.createElement( 'input' );
		this.transformationsInput.type = 'text';
		this.transformationsInput.placeholder = __(
			'transformations',
			'cloudinary'
		);
		this.transformationsInput.style.width = '100%';
		this.transformationsInput.classList.add(
			'regular-text',
			'cld-editor-transformations'
		);
		label.appendChild( this.transformationsInput );
		wrap.appendChild( label );
		save.innerText = __( 'Save', 'cloudinary' );
		save.classList.add( 'button', 'button-primary' );
		wrap.style.display = 'flex';
		wrap.style.alignItems = 'flex-end';
		wrap.appendChild( save );

		save.addEventListener( 'click', () => {
			this.editor.save( {
				ID: this.id,
				transformations: this.transformationsInput.value,
			} );
		} );

		return wrap;
	},
	addTransformation( transformation ) {
		this.preview.lastSrc = this.preview.src;
		this.preview.src =
			this.base +
			this.size +
			'/' +
			transformation.replace( /^\/+|\/+$/gm, '' ) +
			'/' +
			this.file;
	},
};

export default AssetEditModal;
