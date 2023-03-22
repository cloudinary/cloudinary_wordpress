import { __ } from '@wordpress/i18n';

const CropSizes = {
	wrappers: null,
	frame: null,
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
			const input = image.nextSibling;
			const crop = input.value.length
				? input.value.replace( ' ', '' )
				: input.placeholder;
			image.src = `${ baseURL }/${ size },${ crop }/${ sampleId }`;

			input.addEventListener( 'input', () => {
				if ( timout ) {
					clearTimeout( timout );
				}
				timout = setTimeout( () => {
					this.buildImages( wrapper );
				}, 1000 );
			} );
		} );
	},
};

export default CropSizes;
