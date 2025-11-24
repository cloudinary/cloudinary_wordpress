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
	saveTextOverlayButton: document.getElementById( 'cld-asset-save-text-overlay' ),
	saveImageOverlayButton: document.getElementById( 'cld-asset-save-image-overlay' ),
	removeTextOverlayButton: document.getElementById( 'cld-asset-remove-text-overlay' ),
	removeImageOverlayButton: document.getElementById( 'cld-asset-remove-image-overlay' ),

	// Grid elements
	textGrid: document.getElementById( 'edit-overlay-grid-text' ),
	imageGrid: document.getElementById( 'edit-overlay-grid-image' ),
	imagePreviewWrapper: document.getElementById( 'edit-overlay-select-image-preview' ),

	// Mapping
	textOverlayMap: null,
	imageOverlayMap: null,

	init() {
		const item = JSON.parse( this.wrap.dataset.item );
		this.id = item.ID;
		this.base = item.base + item.size + '/';
		this.publicId = item.file;
		this.transformationsInput.value = item.transformations ? item.transformations : '';

		// Set up centralized text overlay mapping as a property
		this.textOverlayMap = [
			{ key: 'text', input: this.textOverlayTextInput, defaultValue: '', event: 'input' },
			{ key: 'color', input: this.textOverlayColorInput, defaultValue: '', event: 'input' },
			{ key: 'fontFace', input: this.textOverlayFontFaceInput, defaultValue: 'Arial', event: 'input' },
			{ key: 'fontSize', input: this.textOverlayFontSizeInput, defaultValue: 20, event: 'input' },
			{ key: 'position', input: this.textOverlayPositionInput, defaultValue: '', event: 'change' },
			{ key: 'xOffset', input: this.textOverlayXOffsetInput, defaultValue: 0, event: 'input' },
			{ key: 'yOffset', input: this.textOverlayYOffsetInput, defaultValue: 0, event: 'input' }
		];

		// Set up centralized image overlay mapping as a property
		this.imageOverlayMap = [
			{ key: 'imageId', input: this.imageOverlayImageIdInput, defaultValue: '', event: 'input' },
			{ key: 'size', input: this.imageOverlaySizeInput, defaultValue: 100, event: 'input' },
			{ key: 'opacity', input: this.imageOverlayOpacityInput, defaultValue: 20, event: 'input' },
			{ key: 'position', input: this.imageOverlayPositionInput, defaultValue: '', event: 'change' },
			{ key: 'xOffset', input: this.imageOverlayXOffsetInput, defaultValue: 0, event: 'input' },
			{ key: 'yOffset', input: this.imageOverlayYOffsetInput, defaultValue: 0, event: 'input' }
		];

		// Set overlay input values from item data using unified helper
        this.setOverlayInputs(this.textOverlayMap, item.text_overlay);
        this.setOverlayInputs(this.imageOverlayMap, item.image_overlay);

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
		this.addOverlayEventListeners();
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

		// Use textOverlayMap for secondary text overlay inputs (exclude text input)
		const secondaryTextInputs = this.textOverlayMap.filter(({ key }) => key !== 'text');

		// Use imageOverlayMap for secondary image overlay inputs (exclude imageId)
		const secondaryImageInputs = this.imageOverlayMap.filter(({ key }) => key !== 'imageId');

		// Add event listeners with appropriate logic for each overlay type
		secondaryTextInputs.forEach(({ input, event }) => {
			if (input) {
				// Special handling for color input to ensure value is updated
				if (input === this.textOverlayColorInput) {
					input.addEventListener(event, () => {
						setTimeout(updatePreviewForTextOverlay, 0);
					});
				} else {
					input.addEventListener(event, updatePreviewForTextOverlay);
				}
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
			this.preview.setSrc( this.getSrc(), true );

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

		this.saveTextOverlayButton.addEventListener('click', () => {
			const textOverlay = this.getOverlayData(this.textOverlayMap);
			textOverlay.transformation = this.buildTextOverlay();
			this.editor.save({
				ID: this.id,
				textOverlay
			});
		});

		this.saveImageOverlayButton.addEventListener('click', () => {
			const imageOverlay = this.getOverlayData(this.imageOverlayMap);
			imageOverlay.transformation = this.buildImageOverlay();
			this.editor.save({
				ID: this.id,
				imageOverlay
			});
		});
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

		const overlayConfig = {
			'edit-overlay-grid-text': {
				positionInput: this.textOverlayPositionInput,
				contentInput: this.textOverlayTextInput
			},
			'edit-overlay-grid-image': {
				positionInput: this.imageOverlayPositionInput,
				contentInput: this.imageOverlayImageIdInput
			}
		};

		const config = overlayConfig[gridId];

		gridOptions.forEach( ( option ) => {
			const cell = document.createElement( 'div' );
			cell.className = 'edit-overlay-grid__cell';
			cell.dataset.gravity = option;

			cell.addEventListener( 'click', () => {
				grid.querySelectorAll( '.edit-overlay-grid__cell--selected' ).forEach( c => c.classList.remove( 'edit-overlay-grid__cell--selected' ) );
				cell.classList.add( 'edit-overlay-grid__cell--selected' );

				if (config) {
					config.positionInput.value = option;

					const hasContent = config.contentInput?.value?.trim();

					if (hasContent) {
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
		// Use textOverlayMap for text overlay fields and their default values
		this.textOverlayMap.forEach(({ input, defaultValue }) => {
			if (input) {
				input.value = defaultValue;
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
		// Use imageOverlayMap for image overlay fields and their default values
		this.imageOverlayMap.forEach(({ input, defaultValue }) => {
			if (input) {
				input.value = defaultValue;
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
	},
	getOverlayData(map) {
		const overlay = {};

		map.forEach(({ key, input }) => {
			overlay[key] = input?.value || '';
		});

		return overlay;
	},

	setOverlayInputs(map, data) {
		map.forEach(({ key, input, defaultValue }) => {
			if (input) {
				input.value = (data && data[key] !== undefined) ? data[key] : defaultValue;
			}
		});
	},
};

window.addEventListener( 'load', () => AssetEdit.init() );

export default AssetEdit;
