import { __ } from '@wordpress/i18n';

const CropSizes = {
	wrappers: null,
	frame: null,
	error: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>',
	init( context ) {
		this.wrappers = context.querySelectorAll( '.cld-size-items' );
		this.wrappers.forEach( ( wrapper ) => {
			// Handle size selector tabs
			const sizeTabs = wrapper.querySelectorAll(
				'.cld-size-selector-item'
			);
			sizeTabs.forEach( ( tab ) => {
				tab.addEventListener( 'click', () => {
					// Remove selected state from all tabs
					sizeTabs.forEach( ( subtab ) => {
						delete subtab.dataset.selected;
					} );

					// Set selected state on clicked tab
					tab.dataset.selected = true;

					// Show/hide corresponding content
					this.switchSizeContent( wrapper, tab.dataset.size );
				} );
			} );

			// Initialize: show first size content and build images
			const firstTab = wrapper.querySelector(
				'.cld-size-selector-item[data-selected]'
			);
			if ( firstTab ) {
				this.switchSizeContent( wrapper, firstTab.dataset.size );
			}
		} );
	},
	switchSizeContent( wrapper, selectedSize ) {
		// Hide all size contents
		const allContents = wrapper.querySelectorAll( '.cld-size-content' );
		allContents.forEach( ( content ) => {
			content.style.display = 'none';
		} );

		// Show selected size content
		const selectedContent = wrapper.querySelector(
			`.cld-size-content[data-size="${ selectedSize }"]`
		);
		if ( selectedContent ) {
			selectedContent.style.display = 'block';
			this.buildImages( wrapper, selectedContent );
		}
	},
	buildImages( wrapper, sizeContent ) {
		const baseURL = wrapper.dataset.base;
		const input = sizeContent.querySelector( '.regular-text' );
		const disable = sizeContent.querySelector( '.disable-toggle' );

		if ( ! input || ! disable ) {
			return;
		}

		const images = sizeContent.querySelectorAll( 'img' );
		const crop = input.value.length
			? input.value.replace( ' ', '' )
			: input.placeholder;

		images.forEach( ( image ) => {
			const size = image.dataset.size;
			const file = image.dataset.file;

			if ( ! disable.checked ) {
				input.disabled = false;
				image.src = `${ baseURL }/${ size },${ crop }/${ file }`;
			} else {
				input.disabled = true;
				image.src = `${ baseURL }/${ size }/${ file }`;
			}

			if ( ! image.bound ) {
				image.addEventListener( 'error', () => {
					image.src = this.error;
				} );
				image.bound = true;
			}
		} );

		// Bind input events once
		if ( ! input.bound ) {
			let timout = null;
			input.addEventListener( 'input', () => {
				if ( timout ) {
					clearTimeout( timout );
				}
				timout = setTimeout( () => {
					this.buildImages( wrapper, sizeContent );
				}, 1000 );
			} );
			input.bound = true;
		}

		if ( ! disable.bound ) {
			disable.addEventListener( 'change', () => {
				this.buildImages( wrapper, sizeContent );
			} );
			disable.bound = true;
		}
	},
};

export default CropSizes;
