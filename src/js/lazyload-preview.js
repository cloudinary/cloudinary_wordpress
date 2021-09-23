import { __ } from '@wordpress/i18n';

const LazyLoadPreview = {
	cycleTime: 2000,
	animate: document.getElementById( 'lazy_loading.lazy_animate' ),
	image: document.getElementById( 'lazyload-image' ),
	placeHolders: document.querySelectorAll( '[name="lazy_loading[lazy_placeholder]"]' ),
	preloader: document.getElementById( 'preloader-image' ),
	color: document.getElementById( 'lazy_loading.lazy_custom_color' ),
	previewCycle: document.getElementById( 'preview-cycle' ),
	progress: document.getElementById( 'progress-bar' ),
	threshold: document.getElementById( 'lazy_loading.lazy_threshold' ),
	currentPlaceholder: null,
	svg: null,
	running: false,
	init() {
		this.svg = this.image.dataset.svg;
		this.currentPlaceholder = document.getElementById( 'placeholder-' + this.getPlaceholder() );
		[ ...this.placeHolders ].forEach( ( placeholder ) => {
			placeholder.addEventListener( 'change', () => this.changePlaceholder( placeholder.value ) );
		} );
		this.color.addEventListener( 'input', () => this.changePreloader() );
		this.animate.addEventListener( 'change', () => this.changePreloader() );
		this.previewCycle.addEventListener( 'click', () => this.startCycle() );
	},
	getPlaceholder() {
		return document.querySelector( '[name="lazy_loading[lazy_placeholder]"]:checked' ).value;
	},
	changePreloader() {
		this.preloader.src = this.getSVG();
	},
	changePlaceholder( type ) {

		const newImage = document.getElementById( 'placeholder-' + type );
		if ( this.currentPlaceholder ) {
			this.currentPlaceholder.style.display = 'none';
			this.currentPlaceholder.style.width = '85%';
			this.currentPlaceholder.style.boxShadow = '';
			this.currentPlaceholder.style.bottom = '0';
		}
		if ( newImage ) {
			newImage.style.display = '';
		}
		this.currentPlaceholder = newImage;
	},
	getThreshold() {
		return parseInt( this.threshold.value ) + this.image.parentNode.parentNode.offsetHeight;
	},
	startCycle() {
		if ( ! this.running ) {
			this.changePlaceholder( 'none' );
			this.image.parentNode.parentNode.style.overflowY = 'scroll';
			this.image.parentNode.style.visibility = 'hidden';
			this.image.parentNode.style.width = '100%';
			this.image.parentNode.style.boxShadow = 'none';
			this.progress.style.width = '100%';
			this.preloader.parentNode.style.visibility = 'hidden';
			this.running = setTimeout( () => {
				this.progress.style.display = 'none';
				this.progress.style.width = '0%';
				this.preloader.parentNode.style.visibility = '';

				setTimeout( () => {
					const threshold = this.getThreshold();
					this.image.parentNode.style.visibility = '';
					this.preloader.parentNode.style.bottom = '-' + threshold + 'px';
					setTimeout( () => {
						setTimeout( () => {
							this.image.parentNode.parentNode.scrollTo( { top: threshold, behavior: 'smooth' } );
							this.showPlaceholder();
						}, this.cycleTime / 3 );
					}, this.cycleTime );
				}, this.cycleTime );
			}, this.cycleTime / 2 );

		} else {
			this.endCycle();
		}
	},
	showPlaceholder() {
		const placeholder = this.getPlaceholder();
		const threshold = this.getThreshold();

		if ( 'off' !== placeholder ) {
			this.changePlaceholder( placeholder );
			if ( this.currentPlaceholder ) {
				this.currentPlaceholder.style.width = '100%';
				this.currentPlaceholder.style.boxShadow = 'none';
				this.currentPlaceholder.style.bottom = '-' + threshold + 'px';
			}
		}
		setTimeout( () => {
			this.showImage();
		}, this.cycleTime );
	},
	showImage() {
		const threshold = this.getThreshold();
		this.changePlaceholder( 'none' );
		this.image.parentNode.style.bottom = '-' + threshold + 'px';
		this.image.parentNode.style.visibility = '';
		setTimeout( () => {
			this.endCycle();
		}, this.cycleTime );
	},
	endCycle() {
		clearTimeout( this.running );
		this.running = false;
		this.changePlaceholder( this.getPlaceholder() );
		this.image.parentNode.style.visibility = '';
		this.image.parentNode.style.bottom = '0';
		this.image.parentNode.style.width = '65%';
		this.image.parentNode.style.boxShadow = '';
		this.preloader.parentNode.style.bottom = '0';
		this.image.parentNode.parentNode.style.overflowY = '';
		this.progress.style.display = '';
	},
	getSVG() {
		let colors = this.color.value;
		const animation = [
			colors
		];
		if ( this.animate.checked ) {
			const splitColors = [ ...colors.matchAll( new RegExp( /[\d+\.*]+/g ) ) ];
			splitColors[ 3 ] = 0.1;
			animation.push( 'rgba(' + splitColors.join( ',' ) + ')' );
			animation.push( colors );
		}

		return this.svg.replace( '-color-', animation.join( ';' ) );
	},
	showLoader() {
		this.image.parentNode.style.opacity = 1;
		this.image.parentNode.src = this.getSVG();
		setTimeout( () => {
			this.showPlaceholder( this.image.parentNode.dataset.placeholder );
		}, this.cycleTime );
	}
};

window.addEventListener( 'load', () => LazyLoadPreview.init() );

export default LazyLoadPreview;
