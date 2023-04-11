import { __ } from '@wordpress/i18n';

const CropSizes = {
	wrappers: null,
	frame: null,
	error: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>',
	init( context ) {
		this.wrappers = context.querySelectorAll( '.cld-size-items' );
		this.wrappers.forEach( ( wrapper ) => {
			const demos = wrapper.querySelectorAll(
				'.cld-image-selector-item'
			);
			demos.forEach( ( demo ) => {
				demo.addEventListener( 'click', () => {
					demos.forEach( ( subdemo ) => {
						delete subdemo.dataset.selected;
					} );

					demo.dataset.selected = true;
					this.buildImages( wrapper );
				} );
			} );
			this.buildImages( wrapper );
		} );
	},
	buildImages( wrapper ) {
		const baseURL = wrapper.dataset.base;
		const images = wrapper.querySelectorAll( 'img' );
		const selectedPreview = wrapper.querySelector(
			'.cld-image-selector-item[data-selected]'
		);
		const sampleId = selectedPreview.dataset.image;
		let timout = null;
		images.forEach( ( image ) => {
			const size = image.dataset.size;
			const input = image.parentNode.querySelector( '.regular-text' );
			const disable = image.parentNode.querySelector( '.disable-toggle' );
			const crop = input.value.length
				? input.value.replace( ' ', '' )
				: input.placeholder;
			if ( ! disable.checked ) {
				input.disabled = false;
				image.src = `${ baseURL }/${ size },${ crop }/${ sampleId }`;
			} else {
				input.disabled = true;
				image.src = `${ baseURL }/${ size }/${ sampleId }`;
			}
			if ( ! image.bound ) {
				input.addEventListener( 'input', () => {
					if ( timout ) {
						clearTimeout( timout );
					}
					timout = setTimeout( () => {
						this.buildImages( wrapper );
					}, 1000 );
				} );

				disable.addEventListener( 'change', () => {
					this.buildImages( wrapper );
				} );

				image.addEventListener( 'error', () => {
					image.src = this.error;
				} );
				image.bound = true;
			}
		} );
	},
};

export default CropSizes;
