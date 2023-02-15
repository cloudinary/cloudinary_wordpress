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
	sizeTransformations: document.querySelectorAll('[id*="asset_size_transformations"]'),
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
	},
	initPreview() {
		this.preview = AssetPreview.init();
		this.wrap.appendChild( this.preview.createPreview( 900, 675 ) );
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
			const sizes = {};
			[ ...this.sizeTransformations ].forEach(
				( size ) => {
					let sizeName = size.id.split( '.' ).pop().replace( 'asset_sized_', '' ).replace( 'asset_disable_size_', '' ).replace( 'size_', '' );

					if ( ! isNaN( sizeName ) ) {
						return;
					}

					const key = size.type === 'checkbox' ? 'toggle' : 'transformations';
					sizes[ sizeName ]        = sizes[ sizeName ] || {};
					sizes[ sizeName ][ key ] = size.type === 'checkbox' ? size.checked : size.value;
				}
			);
			this.editor.save( {
				ID: this.id,
				transformations: this.transformationsInput.value,
				sizes
			} );
		} );
	}
};

window.addEventListener( 'load', () => AssetEdit.init() );

export default AssetEdit;
