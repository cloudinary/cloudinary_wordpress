import { __ } from '@wordpress/i18n';

const BreakpointsPreview = {
	template: document.getElementById( 'main-image' ),
	stepper: document.getElementById( 'responsive.pixel_step' ),
	counter: document.getElementById( 'responsive.breakpoints' ),
	max: document.getElementById( 'responsive.max_width' ),
	min: document.getElementById( 'responsive.min_width' ),
	details: document.getElementById( 'preview-details' ),
	preview: null,
	init() {
		this.preview = this.template.parentNode;
		this.stepper.addEventListener( 'change', () => {
			this.counter.value = this.rebuildPreview();
		} );
		this.counter.addEventListener( 'change', () => {
			this.calculateShift();
		} );
		this.max.addEventListener( 'change', () => {
			this.calculateShift();
		} );
		this.min.addEventListener( 'change', () => {
			this.calculateShift();
		} );

		this.stepper.dispatchEvent( new Event('change') );
	},
	calculateShift() {
		const count = this.counter.value;
		const distance = this.max.value - this.min.value;

		const steps = distance / ( count - 1 );

		this.stepper.value = Math.floor( steps );
		this.stepper.dispatchEvent( new Event( 'change' ) );
	},
	rebuildPreview() {
		let maxSize = parseInt( this.max.value );
		let minSize = parseInt( this.min.value );
		let steps = parseInt( this.stepper.value );
		if ( 1 > steps ) {
			this.stepper.value = steps = 50;
		}
		if ( ! maxSize ) {
			maxSize = parseInt( this.max.dataset.default );
			this.max.value = maxSize;
		}
		if ( ! minSize ) {
			minSize = 100;
			this.min.value = minSize;
		}

		let size = maxSize;
		let percent = size / maxSize * 100;
		const mainImage = this.makeSize( size, percent );
		mainImage.classList.add( 'main-image' );
		this.preview.innerHTML = '';
		this.preview.appendChild( mainImage );
		let count = 1;
		while ( size > minSize ) {
			size = size - steps;

			if ( size < minSize ) {
				break;
			}

			percent = size / maxSize * 100;
			this.preview.appendChild( this.makeSize( size, percent ) );
			count++;
		}
		this.details.innerText = __( `With a max width of ${ maxSize }px and a minimum of ${ minSize }px, you get a potential of ${ count } images.`, 'cloudinary' );
		return count;
	},
	makeSize( size, percent ) {
		const box = this.template.cloneNode( true );
		const text = box.lastChild;
		text.innerText = size + 'px';
		box.style.width = percent + '%';
		box.style.height = percent + '%';
		box.id = '';
		box.classList.remove( 'main-image' );
		return box;
	}
};

window.addEventListener( 'load', () => BreakpointsPreview.init() );
