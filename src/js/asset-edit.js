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
	currentURL: null,

	// Transformations Input
	transformationsInput: document.getElementById( 'edit_asset.transformations' ),

	// Text Overlay Inputs
	textOverlayColorInput: document.getElementById( 'edit_asset.text_overlay_color' ),
	textOverlayFontFaceInput: document.getElementById( 'edit_asset.text_overlay_font_face' ),
	textOverlayFontSizeInput: document.getElementById( 'edit_asset.text_overlay_font_size' ),
	textOverlayTextInput: document.getElementById( 'edit_asset.text_overlay_text' ),
	textOverlayPositionInput: document.getElementById( 'edit_asset.text_overlay_position' ),
	textOverlayXOffsetInput: document.getElementById( 'edit_asset.text_overlay_x_offset' ),
	textOverlayYOffsetInput: document.getElementById( 'edit_asset.text_overlay_y_offset' ),

	// Image Overlay Inputs
	imageOverlayImageIdInput: document.getElementById( 'edit_asset.image_overlay_image_id' ),
	imageOverlaySizeInput: document.getElementById( 'edit_asset.image_overlay_size' ),
	imageOverlayOpacityInput: document.getElementById( 'edit_asset.image_overlay_opacity' ),
	imageOverlayPositionInput: document.getElementById( 'edit_asset.image_overlay_position' ),
	imageOverlayXOffsetInput: document.getElementById( 'edit_asset.image_overlay_x_offset' ),
	imageOverlayYOffsetInput: document.getElementById( 'edit_asset.image_overlay_y_offset' ),

	// Buttons
	saveButton: document.getElementById( 'cld-asset-edit-save' ),
	removeTextOverlayButton: document.getElementById( 'cld-asset-remove-text-overlay' ),
	removeImageOverlayButton: document.getElementById( 'cld-asset-remove-image-overlay' ),

	// Grid elements
	textGrid: document.getElementById( 'edit-overlay-grid-text' ),
	imageGrid: document.getElementById( 'edit-overlay-grid-image' ),
	imagePreviewWrapper: document.getElementById( 'edit-overlay-select-image-preview' ),

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
		this.initRemoveOverlayButtons();
	},
	initPreview() {
		this.preview = AssetPreview.init();
		this.wrap.appendChild( this.preview.createPreview( 500, 400 ) );
		this.preview.setSrc( this.base + this.transformationsInput.value + this.publicId, true );
		this.transformationsInput.addEventListener( 'input', ( ev ) => {
			this.preview.setSrc( this.getSrc() );
		} );

		// Add overlay preview updates
		this.addOverlayEventListeners();

		this.transformationsInput.addEventListener( 'keydown', ( ev ) => {
			if ( 'Enter' === ev.code ) {
				ev.preventDefault();
				this.saveButton.dispatchEvent( new Event( 'click' ) );
			}
		} );
	},
	addOverlayEventListeners() {
		const updatePreviewForTextOverlay = () => {
			// Only update preview if we have text content
			const hasText = this.textOverlayTextInput?.value?.trim();

			if (hasText) {
				this.preview.setSrc( this.getSrc() );
			}
		};

		const updatePreviewForImageOverlay = () => {
			// Only update preview if we have image ID
			const hasImageId = this.imageOverlayImageIdInput?.value?.trim();

			if (hasImageId) {
				this.preview.setSrc( this.getSrc() );
			}
		};

		// Primary content inputs (always update preview to handle empty states)
		if (this.textOverlayTextInput) {
			this.textOverlayTextInput.addEventListener('input', () => {
				this.preview.setSrc( this.getSrc() );
			});
		}

		if (this.imageOverlayImageIdInput) {
			this.imageOverlayImageIdInput.addEventListener('input', () => {
				this.preview.setSrc( this.getSrc() );
			});
		}

		// Secondary text overlay inputs (only update if text exists)
		const secondaryTextInputs = [
			{ input: this.textOverlayColorInput, event: 'input' },
			{ input: this.textOverlayFontFaceInput, event: 'input' },
			{ input: this.textOverlayFontSizeInput, event: 'input' },
			{ input: this.textOverlayPositionInput, event: 'change' },
			{ input: this.textOverlayXOffsetInput, event: 'input' },
			{ input: this.textOverlayYOffsetInput, event: 'input' }
		];

		// Secondary image overlay inputs (only update if image exists)
		const secondaryImageInputs = [
			{ input: this.imageOverlaySizeInput, event: 'input' },
			{ input: this.imageOverlayOpacityInput, event: 'input' },
			{ input: this.imageOverlayPositionInput, event: 'change' },
			{ input: this.imageOverlayXOffsetInput, event: 'input' },
			{ input: this.imageOverlayYOffsetInput, event: 'input' }
		];

		// Add event listeners with appropriate logic for each overlay type
		secondaryTextInputs.forEach(({ input, event }) => {
			if (input) {
				input.addEventListener(event, updatePreviewForTextOverlay);
			}
		});

		secondaryImageInputs.forEach(({ input, event }) => {
			if (input) {
				input.addEventListener(event, updatePreviewForImageOverlay);
			}
		});
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

				if (gridId === 'edit-overlay-grid-text') {
					this.textOverlayPositionInput.value = option;

					// Update preview only if we have text content
					const hasText = this.textOverlayTextInput?.value?.trim();
					if (hasText) {
						this.preview.setSrc( this.getSrc() );
					}
				} else if (gridId === 'edit-overlay-grid-image') {
					this.imageOverlayPositionInput.value = option;

					// Update preview only if we have image ID
					const hasImageId = this.imageOverlayImageIdInput?.value?.trim();
					if (hasImageId) {
						this.preview.setSrc( this.getSrc() );
					}
				}
			});

			grid.appendChild(cell);
		});
	},
	initImageSelect() {
		const imageSelect = document.getElementById( 'edit-overlay-select-image' );

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
				if (this.imagePreviewWrapper && this.imagePreviewWrapper.firstChild) {
					this.imagePreviewWrapper.removeChild(this.imagePreviewWrapper.firstChild);
				}

				// Create and insert new preview image
				if (this.imagePreviewWrapper) {
					const img = document.createElement('img');
					img.src = attachment.url;
					img.alt = attachment.alt || '';
					this.imagePreviewWrapper.appendChild(img);
				}

				if ( attachment?.public_id ) {
					this.imageOverlayImageIdInput.value = attachment.public_id;
				} else {
					this.imageOverlayImageIdInput.value = '';
				}

				// Update preview with new image overlay
				this.preview.setSrc( this.getSrc() );
			});

			frame.open();
		} );
	},
	initRemoveOverlayButtons() {
		if (this.removeTextOverlayButton) {
			this.removeTextOverlayButton.addEventListener('click', (ev) => {
				ev.preventDefault();
				this.clearTextOverlay();
			});
		}

		if (this.removeImageOverlayButton) {
			this.removeImageOverlayButton.addEventListener('click', (ev) => {
				ev.preventDefault();
				this.clearImageOverlay();
			});
		}
	},
	clearTextOverlay() {
		// Define text overlay fields with their default values
		const textFields = [
			{ input: this.textOverlayTextInput, value: '' },
			{ input: this.textOverlayColorInput, value: '' },
			{ input: this.textOverlayFontFaceInput, value: 'Arial' },
			{ input: this.textOverlayFontSizeInput, value: 20 },
			{ input: this.textOverlayPositionInput, value: '' },
			{ input: this.textOverlayXOffsetInput, value: 0 },
			{ input: this.textOverlayYOffsetInput, value: 0 }
		];

		// Reset all text overlay fields
		textFields.forEach(({ input, value }) => {
			if (input) {
				input.value = value;
			}
		});

		// Clear selected gravity grid for text
		if (this.textGrid) {
			this.textGrid.querySelectorAll('.edit-overlay-grid__cell--selected')
				.forEach(cell => cell.classList.remove('edit-overlay-grid__cell--selected'));
		}

		// Update preview to remove text overlay
		this.preview.setSrc(this.getSrc());
	},
	clearImageOverlay() {
		// Define image overlay fields with their default values
		const imageFields = [
			{ input: this.imageOverlayImageIdInput, value: '' },
			{ input: this.imageOverlaySizeInput, value: 100 },
			{ input: this.imageOverlayOpacityInput, value: 20 },
			{ input: this.imageOverlayPositionInput, value: '' },
			{ input: this.imageOverlayXOffsetInput, value: 0 },
			{ input: this.imageOverlayYOffsetInput, value: 0 }
		];

		// Reset all image overlay fields
		imageFields.forEach(({ input, value }) => {
			if (input) {
				input.value = value;
			}
		});

		// Clear image preview
		if (this.imagePreviewWrapper && this.imagePreviewWrapper.firstChild) {
			this.imagePreviewWrapper.removeChild(this.imagePreviewWrapper.firstChild);
		}

		// Clear selected gravity grid for image
		if (this.imageGrid) {
			this.imageGrid.querySelectorAll('.edit-overlay-grid__cell--selected')
				.forEach(cell => cell.classList.remove('edit-overlay-grid__cell--selected'));
		}

		// Update preview to remove image overlay
		this.preview.setSrc(this.getSrc());
	},
	buildPlacementQualifiers(positionInput, xOffsetInput, yOffsetInput) {
		let placementQualifiers = [];

		if (positionInput?.value) {
			placementQualifiers.push(`g_${positionInput.value}`);
		}

		if (xOffsetInput?.value) {
			placementQualifiers.push(`x_${xOffsetInput.value}`);
		}

		if (yOffsetInput?.value) {
			placementQualifiers.push(`y_${yOffsetInput.value}`);
		}

		return placementQualifiers.length > 0 ? ',' + placementQualifiers.join(',') : '';
	},
	buildImageOverlay() {
		if (!this.imageOverlayImageIdInput || !this.imageOverlayImageIdInput.value.trim()) {
			return '';
		}

		const imageId = this.imageOverlayImageIdInput.value.trim().replace(/\//g, ':');
		let imageLayerDefinition = `l_${imageId}`;

		let transformations = [];

		if (this.imageOverlaySizeInput?.value) {
			transformations.push(`c_scale,w_${this.imageOverlaySizeInput.value}`);
		}

		if (this.imageOverlayOpacityInput?.value) {
			transformations.push(`o_${this.imageOverlayOpacityInput.value}`);
		}

		if (transformations.length > 0) {
			imageLayerDefinition += '/' + transformations.join('/');
		}

		const placementString = this.buildPlacementQualifiers(
			this.imageOverlayPositionInput,
			this.imageOverlayXOffsetInput,
			this.imageOverlayYOffsetInput
		);

		return `${imageLayerDefinition}/fl_layer_apply${placementString}`;
	},
	buildTextOverlay() {
		if (!this.textOverlayTextInput || !this.textOverlayTextInput.value.trim()) {
			return '';
		}

		const text = this.textOverlayTextInput.value.trim();
		const fontFace = this.textOverlayFontFaceInput?.value || 'Arial';
		const fontSize = this.textOverlayFontSizeInput?.value || '20';
		const encodedText = encodeURIComponent(text);
		let textLayerDefinition = `l_text:${fontFace}_${fontSize}:${encodedText}`;

		if (this.textOverlayColorInput?.value) {
			let color = this.textOverlayColorInput.value;

			// Handle different color formats
			if (color.startsWith('rgb')) {
				// Extract RGB/RGBA values from rgb(r,g,b) or rgba(r,g,b,a) format
				const colorMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9]*\.?[0-9]+))?\)/);
				if (colorMatch) {
					const r = parseInt(colorMatch[1]).toString(16).padStart(2, '0');
					const g = parseInt(colorMatch[2]).toString(16).padStart(2, '0');
					const b = parseInt(colorMatch[3]).toString(16).padStart(2, '0');

					// Handle alpha if present (RGBA)
					if (colorMatch[4] !== undefined) {
						const alpha = parseFloat(colorMatch[4]);
						const alphaHex = Math.round(alpha * 255).toString(16).padStart(2, '0');
						color = r + g + b + alphaHex;
					} else {
						// RGB format
						color = r + g + b;
					}
				}
			} else {
				color = color.replace('#', '');
			}

			textLayerDefinition = `co_rgb:${color},${textLayerDefinition}`;
		}

		const placementString = this.buildPlacementQualifiers(
			this.textOverlayPositionInput,
			this.textOverlayXOffsetInput,
			this.textOverlayYOffsetInput
		);

		return `${textLayerDefinition}/fl_layer_apply${placementString}`;
	},
	getSrc() {
		const imageOverlay = this.buildImageOverlay();
		const textOverlay = this.buildTextOverlay();
		let urlParts = [this.base];

		if (this.transformationsInput.value) {
			urlParts.push(this.transformationsInput.value);
		}

		if (imageOverlay) {
			urlParts.push(imageOverlay.replace(/\/$/, ''));
		}

		if (textOverlay) {
			urlParts.push(textOverlay.replace(/\/$/, ''));
		}

		urlParts.push(this.publicId);

		return urlParts.join('/').replace(/\/+/g, '/');
	}
};

window.addEventListener( 'load', () => AssetEdit.init() );

export default AssetEdit;
