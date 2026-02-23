import { __ } from '@wordpress/i18n';
import AssetPreview from './components/asset-preview';
import VideoAssetPreview from './components/video-asset-preview';
import AssetEditor from './components/asset-editor';

const SELECT_IMAGE_LABEL = __('Select Image', 'cloudinary');
const REPLACE_IMAGE_LABEL = __('Replace Image', 'cloudinary');

const AssetEdit = {
	wrap: document.getElementById( 'cld-asset-edit' ),
	isVideo: false,
	preview: null,
	id: null,
	editor: null,
	base: null,
	publicId: null,
	size: null,
	currentURL: null,

	// Transformations Input
	transformationsInput: document.getElementById( 'edit_asset.edit_affects.transformations' ),

	// Text Overlay Inputs
	textOverlayColorInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_color' ),
	textOverlayFontFaceInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_font_face' ),
	textOverlayFontSizeInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_font_size' ),
	textOverlayTextInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_text' ),
	textOverlayPositionInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_position' ),
	textOverlayXOffsetInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_x_offset' ),
	textOverlayYOffsetInput: document.getElementById( 'edit_asset.edit_affects.text_overlay_y_offset' ),

	// Image Overlay Inputs
	imageOverlayImageIdInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_image_id' ),
	imageOverlayPublicIdInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_public_id' ),
	imageOverlaySizeInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_size' ),
	imageOverlayOpacityInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_opacity' ),
	imageOverlayPositionInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_position' ),
	imageOverlayXOffsetInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_x_offset' ),
	imageOverlayYOffsetInput: document.getElementById( 'edit_asset.edit_affects.image_overlay_y_offset' ),

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
	assetPreviewTransformationString: document.getElementById( 'asset-preview-transformation-string' ),
	assetPreviewSuccessMessage: document.getElementById( 'asset-preview-success-message' ),
	imageSelect: document.getElementById( 'edit-overlay-select-image' ),

	// Mapping
	textOverlayMap: null,
	imageOverlayMap: null,

	init() {
		const item = JSON.parse( this.wrap.dataset.item );
		this.id = item.ID;
		this.base = item.base + item.size + '/';
		this.transformationsInput.value = item.transformations ? item.transformations : '';

		if(!item?.file) {
			return;
		}

		this.isVideo = item?.type === 'video';
		this.publicId = item.file;

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
			{ key: 'publicId', input: this.imageOverlayPublicIdInput, defaultValue: '', event: 'input' },
			{ key: 'size', input: this.imageOverlaySizeInput, defaultValue: 100, event: 'input' },
			{ key: 'opacity', input: this.imageOverlayOpacityInput, defaultValue: 20, event: 'input' },
			{ key: 'position', input: this.imageOverlayPositionInput, defaultValue: '', event: 'change' },
			{ key: 'xOffset', input: this.imageOverlayXOffsetInput, defaultValue: 0, event: 'input' },
			{ key: 'yOffset', input: this.imageOverlayYOffsetInput, defaultValue: 0, event: 'input' }
		];

		const textOverlayData = this.parseJsonOverlay( item.text_overlay );
		const imageOverlayData = this.parseJsonOverlay( item.image_overlay );

		// Set overlay input values from item data using unified helper
        this.setOverlayInputs(this.textOverlayMap, textOverlayData);
        this.setOverlayInputs(this.imageOverlayMap, imageOverlayData);
		// Init components.
		this.initPreview(item);
		this.initEditor();
		this.initGravityGrid( 'edit-overlay-grid-text', textOverlayData );
		this.initGravityGrid( 'edit-overlay-grid-image', imageOverlayData );
		this.initImageSelect();
		this.initRemoveOverlayButtons();
	},
	initPreview(item) {
		if ( this.isVideo ) {
			this.preview = VideoAssetPreview.init();
			this.wrap.appendChild( this.preview.createPreview( 480, 360 ) );
			this.preview.setPublicId( item?.data?.public_id );
			this.preview.setSrc( this.buildSrc(), true );
		} else {
			this.preview = AssetPreview.init();
			this.wrap.appendChild( this.preview.createPreview( '100%', 'auto' ) );
			this.preview.setSrc( this.buildSrc(), true );
		}

		this.transformationsInput.addEventListener( 'input', ( ev ) => {
			this.preview.setSrc( this.buildSrc() );
		} );

		this.addOverlayEventListeners();
	},
	addOverlayEventListeners() {
		const updatePreviewForTextOverlay = () => {
			const hasText = this.textOverlayTextInput?.value?.trim();

			if (hasText) {
				this.preview.setSrc( this.buildSrc() );
			}
		};

		const updatePreviewForImageOverlay = () => {
			const hasImageId = this.imageOverlayPublicIdInput?.value?.trim();

			if (hasImageId) {
				this.preview.setSrc( this.buildSrc() );
			}
		};

		if (this.textOverlayTextInput) {
			this.textOverlayTextInput.addEventListener('input', () => {
				this.preview.setSrc( this.buildSrc() );
			});
		}

		if (this.imageOverlayPublicIdInput) {
			this.imageOverlayPublicIdInput.addEventListener('input', () => {
				this.preview.setSrc( this.buildSrc() );
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
			this.preview.setSrc( this.buildSrc(), true );

			if ( result.note ) {
				alert( result.note );
			} else {
				this.assetPreviewSuccessMessage.style.display = 'block';

				setTimeout(() => {
					this.assetPreviewSuccessMessage.style.display = 'none';
				}, 2000);
			}
		} );

		this.saveButton.addEventListener( 'click', (e) => {
			e.preventDefault();

			this.editor.save( {
				ID: this.id,
				transformations: this.transformationsInput.value,
			} );
		} );

		this.saveTextOverlayButton.addEventListener('click', (e) => {
			e.preventDefault();

			const textOverlay = this.getOverlayData(this.textOverlayMap);

			textOverlay.transformation = this.buildTextOverlay();

			this.editor.save({
				ID: this.id,
				textOverlay
			});
		});

		this.saveImageOverlayButton.addEventListener('click', (e) => {
			e.preventDefault();

			const imageOverlay = this.getOverlayData(this.imageOverlayMap);

			imageOverlay.transformation = this.buildImageOverlay();

			this.editor.save({
				ID: this.id,
				imageOverlay
			});
		});
	},
	initGravityGrid( gridId, overlayData ) {
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
				contentInput: this.imageOverlayPublicIdInput
			}
		};

		const config = overlayConfig[gridId];

		gridOptions.forEach( ( option ) => {
			const cell = document.createElement( 'div' );
			cell.className = 'edit-overlay-grid__cell';
			cell.dataset.gravity = option;

			if (overlayData && overlayData.position && overlayData.position === option) {
				cell.classList.add('edit-overlay-grid__cell--selected');
			}

			cell.addEventListener( 'click', () => {
				grid.querySelectorAll( '.edit-overlay-grid__cell--selected' ).forEach( c => c.classList.remove( 'edit-overlay-grid__cell--selected' ) );
				cell.classList.add( 'edit-overlay-grid__cell--selected' );

				if (config) {
					config.positionInput.value = option;

					const hasContent = config.contentInput?.value?.trim();

					if (hasContent) {
						this.preview.setSrc( this.buildSrc() );
					}
				}
			});

			grid.appendChild(cell);
		});
	},
	updateImageSelectLabel(label) {
		if ( this.imageSelect ) {
			this.imageSelect.textContent = label;
		}
	},
	initImageSelect() {

		if ( ! this.imageSelect ) {
			return;
		}

		this.imageSelect.addEventListener('click', (ev) => {
			ev.preventDefault();

			const frame = wp.media({
				title: SELECT_IMAGE_LABEL,
				button: {
					text: SELECT_IMAGE_LABEL,
				},
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', () => {
				const attachment = frame.state().get('selection').first().toJSON();

				if (attachment?.public_id) {
					this.imageOverlayImageIdInput.value = attachment.id;
					this.imageOverlayPublicIdInput.value = attachment.public_id;
					this.updateImageSelectLabel(REPLACE_IMAGE_LABEL);
					this.renderImageOverlay(attachment);
				} else {
					this.imageOverlayImageIdInput.value = '';
					this.imageOverlayPublicIdInput.value = '';
					this.updateImageSelectLabel(SELECT_IMAGE_LABEL);
					this.renderImageOverlay({});
					alert( __('Please select an image that is synced to Cloudinary.', 'cloudinary') );
				}

				this.preview.setSrc(this.buildSrc());
			});

			frame.open();
		});

		// Set initial label if image already selected
		if (this.imageOverlayPublicIdInput?.value) {
			this.updateImageSelectLabel(REPLACE_IMAGE_LABEL);
		} else {
			this.updateImageSelectLabel(SELECT_IMAGE_LABEL);
		}
	},
	renderImageOverlay(attachment) {
		// Remove existing preview image if any
		if (this.imagePreviewWrapper && this.imagePreviewWrapper.firstChild) {
			this.imagePreviewWrapper.removeChild(this.imagePreviewWrapper.firstChild);
		}

		// Create and insert new preview image
		if (this.imagePreviewWrapper && ( attachment?.url || attachment?.source_url )) {
			const img = document.createElement('img');
			img.src = attachment.url || attachment.source_url;
			img.alt = attachment.alt || '';
			this.imagePreviewWrapper.appendChild(img);
		}
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
				input.dispatchEvent(new Event('change'));
			}
		});

		// Clear selected gravity grid for text
		if (this.textGrid) {
			this.textGrid.querySelectorAll('.edit-overlay-grid__cell--selected')
				.forEach(cell => cell.classList.remove('edit-overlay-grid__cell--selected'));
		}

		// Update preview to remove text overlay
		this.preview.setSrc(this.buildSrc());
	},
	clearImageOverlay() {
		// Use imageOverlayMap for image overlay fields and their default values
		this.imageOverlayMap.forEach(({ input, defaultValue }) => {
			if (input) {
				input.value = defaultValue;
				input.dispatchEvent(new Event('change'));
			}
		});

		// Clear image preview
		if (this.imagePreviewWrapper && this.imagePreviewWrapper.firstChild) {
			this.imagePreviewWrapper.removeChild(this.imagePreviewWrapper.firstChild);
			this.updateImageSelectLabel(SELECT_IMAGE_LABEL);
		}

		// Clear selected gravity grid for image
		if (this.imageGrid) {
			this.imageGrid.querySelectorAll('.edit-overlay-grid__cell--selected')
				.forEach(cell => cell.classList.remove('edit-overlay-grid__cell--selected'));
		}

		// Update preview to remove image overlay
		this.preview.setSrc(this.buildSrc());
	},
	getFormattedPercentageValue( value ) {
		const val = value / 100;
		return val % 1 === 0 ? val.toFixed(1) : val;
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
		const imageId = this.imageOverlayPublicIdInput.value.trim().replace(/\//g, ':');

		if ( !imageId ) {
			return '';
		}

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

		return `${imageLayerDefinition}/c_limit,w_1.0,fl_relative/fl_layer_apply${placementString}`;
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

		return `${textLayerDefinition}/c_limit,w_0.9,fl_relative/fl_layer_apply${placementString}`;
	},
	buildSrc() {
		const transformations = this.transformationsInput.value;
		const textOverlay = this.buildTextOverlay();
		const imageOverlay = this.buildImageOverlay();


		// For images, build the full URL
		const urlParts = [this.base];
		const htmlParts = [];

		const addPart = (value, cssClass, displayValue = value, addSlash = true) => {
			if (value) {
				const cleanValue = value.replace(/\/$/, '');
				urlParts.push(cleanValue);
				const suffix = addSlash ? '/' : '';
				htmlParts.push(`<span class="${cssClass} string-preview-base">${suffix}${displayValue}</span>`);
			}
		};

		// Add transformations
		if (transformations) {
			addPart(transformations, 'string-preview-transformations', `.../${transformations}`, false);
		} else {
			htmlParts.push(`<span class="string-preview-transformations string-preview-base">...</span>`);
		}

		// Add overlays
		addPart(textOverlay, 'string-preview-text-overlay');
		addPart(imageOverlay, 'string-preview-image-overlay');

		addPart(this.publicId, 'string-preview-public-id', this.publicId, false);

		const previewUrl = urlParts.join('/').replace(/([^:]\/)\/+/g, '$1');
		this.assetPreviewTransformationString.innerHTML = htmlParts.join('');
		this.assetPreviewTransformationString.href = previewUrl;

		// For videos, return only the transformation string (without base and publicId)
		if ( this.isVideo ) {
			return this.videoTransformations( transformations, imageOverlay, textOverlay );
		}

		return previewUrl;
	},
	videoTransformations(transformations, imageOverlay, textOverlay) {
		const transformationParts = [];

		if (transformations) {
			transformationParts.push(transformations);
		}

		if (textOverlay) {
			transformationParts.push(textOverlay);
		}

		if (imageOverlay) {
			transformationParts.push(imageOverlay);
		}

		return transformationParts.join('/');
	},
	getOverlayData(map) {
		const overlay = {};

		map.forEach(({ key, input }) => {
			overlay[key] = input?.value || '';
		});

		return overlay;
	},
	parseJsonOverlay(data) {
		if (typeof data === 'string') {
			try {
				data = JSON.parse(data);
			} catch (e) {
				data = {};
			}
		}

		return data;
	},
	setOverlayInputs(map, data) {
		map.forEach(({ key, input, defaultValue }) => {
			if (input) {
				input.value = (data && data[key] !== undefined) ? data[key] : defaultValue;
				input.dispatchEvent(new Event('change'));

				// Special handling for color input to initialize color picker
				if (key === 'color' && input.value) {
					jQuery(this.textOverlayColorInput).iris({ color: input.value });
				}

				if (key === 'imageId' && input.value) {
					this.fetchImageById(input.value).then(attachment => {
						AssetEdit.renderImageOverlay(attachment);
					});
				}
			}
		});
	},
	fetchImageById(id) {
		return fetch(`/wp-json/wp/v2/media/${id}`)
			.then(response => {
				if (!response.ok) throw new Error(__('Image not found', 'cloudinary'));
				return response.json();
			});
	},
};

window.addEventListener( 'load', () => AssetEdit.init() );

export default AssetEdit;
