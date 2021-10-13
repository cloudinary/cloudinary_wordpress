const ResponsiveBreakpoints = {
	density: window.devicePixelRatio ? window.devicePixelRatio : 'auto',
	images: [],
	debounce: null,
	config: CLDLB ? CLDLB : {},
	_init() {
		[ ...document.images ].forEach( ( image ) => {
			if ( ! image.dataset.src ) {
				return;
			}
			image.originalWidth = image.dataset.width;
			this.images.push( image );
		} );

		// Resize handler.
		window.addEventListener( 'resize', ( ev ) => {
			this._debounceBuild();
		} );
		window.addEventListener( 'scroll', ( ev ) => {
			this._debounceBuild();
		} );
		// Build images.
		this._build();
	},
	_debounceBuild() {
		if ( this.debounce ) {
			clearTimeout( this.debounce );
		}
		this.debounce = setTimeout( () => {
			this._build();
		}, 100 );
	},
	_build() {
		this.images.forEach( ( image ) => {
			this.buildSize( image );
		} );
	},
	_shouldRebuild( image ) {
		const width = this.scaleSize(
			image.originalWidth,
			image.width,
			this.config.bytes_step
		);
		const rect = image.getBoundingClientRect();
		const diff =
			window.innerHeight + parseInt( this.config.lazy_threshold, 10 );
		return (
			rect.top < diff &&
			( width > image.naturalWidth / this.density || ! image.cld_loaded )
		);
	},
	_shouldPlacehold( image ) {
		const width = this.scaleSize(
			image.originalWidth,
			image.width,
			this.config.bytes_step
		);
		const rect = image.getBoundingClientRect();
		const diff =
			window.innerHeight + parseInt( this.config.lazy_threshold, 10 );
		return (
			! image.cld_loaded &&
			rect.top < diff * 2 &&
			( width > image.naturalWidth / this.density ||
				! image.cld_placehold )
		);
	},
	getResponsiveSteps( image ) {
		const steps = Math.ceil(
			image.dataset.breakpoints
				? image.originalWidth / image.dataset.breakpoints
				: this.responsiveStep
		);
		return steps;
	},
	scaleSize( original, newSize, responsiveStep ) {
		const diff = Math.floor( ( original - newSize ) / responsiveStep );
		let scaledSize = original - responsiveStep * diff;
		if ( scaledSize > original ) {
			scaledSize = original;
		} else if ( this.config.max_width < scaledSize ) {
			scaledSize = original;
		}
		return scaledSize;
	},
	buildSize( image ) {
		if ( this._shouldRebuild( image ) ) {
			image.src = this.getSizeURL( image );
		} else if ( this._shouldPlacehold( image ) ) {
			image.src = this.getPlaceholderURL( image );
		}
	},
	getSizeURL( image ) {
		image.cld_loaded = true;
		if ( image.dataset.srcset ) {
			image.srcset = image.dataset.srcset;
			delete image.dataset.srcset;
			return '';
		}
		const width = this.scaleSize(
			image.originalWidth,
			image.width,
			this.config.bytes_step
		);
		const newSize = 'w_' + width + ',dpr_' + this.density;
		return image.dataset.src
			.replace( '--size--', newSize )
			.replace( '/--placehold--', '' );
	},
	getPlaceholderURL( image ) {
		image.cld_placehold = true;
		return image.dataset.placeholder;
	},
};
// Init.
window.addEventListener( 'load', () => {
	ResponsiveBreakpoints._init();
} );
