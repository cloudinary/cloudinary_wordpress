import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const ImageEditor = {
	container: document.getElementById( 'cloudinary-editor' ),
	mediaPreview: document.getElementById( 'cloudinary-media-preview' ),
	startControls: document.getElementById( 'edit-start-wrap' ),
	editStart: document.getElementById( 'edit-start' ),
	editClose: document.getElementById( 'edit-close' ),
	editControls: document.getElementById( 'edit-controls-wrap' ),
	saveButton: document.getElementById( 'edit-save' ),
	restoreButton: document.getElementById( 'edit-restore' ),
	config: cldData.editor ? cldData.editor : {},
	editor: null,
	init() {
		if ( ! this.config.publicId ) {
			return;
		}
		this.show( this.editStart );
		this.container.style.transition = 'opacity 0.5s';
		// Set nonce.
		apiFetch.use( apiFetch.createNonceMiddleware( this.config.nonce ) );

		// Setup start.
		this.editStart.addEventListener( 'click', () => {
			this.open();
		} );

		this.editClose.addEventListener( 'click', () => {
			this.close();
		} );
		this.saveButton.addEventListener( 'click', () => {
			this.editor.triggerExport();
		} );
		this.restoreButton.addEventListener( 'click', () => {
			this.restoreButton.disabled = true;
			const data = {
				ID: this.config.assetID,
			};
			this.restore( data );
		} );

		this.restoreMaybe();
		// Setup editor.
		this.editor = cloudinary.mediaEditor( { appendTo: this.container } );
		const events = [
			'interactivecropresize',
			'interactivecropmove',
			'flipvertically',
			'fliphorizontally',
			'rotateclockwise',
			'rotatecounterclockwise',
			'aspectratioclick',
			'cropclick',
		];

		events.forEach( ( event ) => {
			this.editor.on( event, ( ev ) => {
				console.log( ev );
				this.enableSave();
			} );
		} );
	},
	disableSave() {
		if ( 'image' === this.config.resourceType ) {
			this.saveButton.disabled = true;
		}
	},
	enableSave() {
		if ( 'image' === this.config.resourceType ) {
			this.saveButton.disabled = false;
		}
	},
	restoreMaybe() {
		if ( this.config.original ) {
			this.hide( this.restoreButton );
		} else {
			this.restoreButton.disabled = false;
			this.show( this.restoreButton );
		}
	},
	openEditor() {
		// Setup preview.
		// @todo: check video/audio events.
		this.mediaPreview.addEventListener( 'load', () => {
			this.mediaPreview.style.opacity = 1;
		} );

		this.updateEditor();
		// Setup export.
		this.editor.on( 'export', ( ev ) => {
			const data = {
				ID: this.config.assetID,
				transformations: ev.transformation,
				imageUrl: ev.assets[ 0 ].secureUrl,
			};
			this.saveButton.disabled = true;
			this.save( data );
			//this.close();
		} );

		// Show.
		this.editor.show();
	},
	updateEditor() {
		this.editor.update( {
			cloudName: this.config.cloudName,
			publicIds: [
				{
					publicId: this.config.publicId,
					resourceType: this.config.resourceType,
				},
			],
			mode: 'inline',
			image: {
				transformation: this.config.transformation,
				steps: [ 'resizeAndCrop' ],
				resizeAndCrop: {
					toggleAspectRatio: true,
				},
				export: {
					download: false,
					quality: [ 'best' ],
					share: false,
				},
			},
		} );
		this.disableSave();
	},
	hide( element ) {
		element.style.display = 'none';
	},
	show( element, display = 'block' ) {
		element.style.display = display;
	},
	open() {
		this.hide( this.startControls );
		this.show( this.editControls, 'flex' );
		this.hide( this.mediaPreview );
		const height =
			document.body.offsetHeight - this.container.offsetTop - 200;
		this.show( this.container );
		this.container.style.height = height + 'px';
		this.openEditor();
	},
	close() {
		this.show( this.startControls );
		this.hide( this.editControls );
		this.show( this.mediaPreview );
		this.hide( this.container );
		this.editor.hide();
	},
	save( data ) {
		this.disableSave();
		this.container.style.opacity = 0.5;
		apiFetch( {
			url: this.config.saveUrl,
			data,
			method: 'POST',
		} ).then( ( result ) => {
			this.config.publicId = result.publicId;
			this.config.original = result.original;
			//this.editor.hide();
			this.updateEditor();
			this.mediaPreview.src = result.previewUrl;
			this.container.style.opacity = 1;
			this.restoreMaybe();
		} );
	},
	restore( data ) {
		this.container.style.opacity = 0.5;
		apiFetch( {
			url: this.config.restoreUrl,
			data,
			method: 'POST',
		} ).then( ( result ) => {
			this.config.publicId = result.publicId;
			this.config.original = result.original;
			this.updateEditor();
			this.mediaPreview.src = result.previewUrl;
			this.container.style.opacity = 1;
			this.restoreMaybe();
		} );
	},
};

window.addEventListener( 'load', () => ImageEditor.init() );

export default ImageEditor;
