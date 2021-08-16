const LazyLoad = {
	density: window.devicePixelRatio ? window.devicePixelRatio : 'auto',
	images: [],
	debounce: null,
	config: CLDLB ? CLDLB : {},
	lazyThreshold: 0,
	_init() {
		this._calcThreshold();
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
	_calcThreshold() {
		const number = this.config.lazy_threshold.replace( /[^0-9]/g, '' );
		const type = this.config.lazy_threshold
			.replace( /[0-9]/g, '' )
			.toLowerCase();
		let unit = 0;
		switch ( type ) {
			case 'em':
				unit =
					parseFloat( getComputedStyle( document.body ).fontSize ) *
					number;
				break;
			case 'rem':
				unit =
					parseFloat(
						getComputedStyle( document.documentElement ).fontSize
					) * number;
				break;
			case 'vh':
				unit = ( window.innerHeight / number ) * 100;
				break;
			default:
				unit = number;
		}
		this.lazyThreshold = window.innerHeight + parseInt( unit, 10 );
	},
	_debounceBuild() {
		if ( this.debounce ) {
			clearTimeout( this.debounce );
		}
		this.debounce = setTimeout( () => {
			this._build();
		}, 100 );
	},
	_getDensity() {
		const maxDensity = CLDLB.dpr ? CLDLB.dpr.replace( 'X', '' ) : 'off';
		if ( 'off' === maxDensity ) {
			return 1;
		}
		let deviceDensity = this.density;
		if (
			! CLDLB.dpr_precise &&
			'auto' !== maxDensity &&
			'auto' !== deviceDensity
		) {
			deviceDensity =
				deviceDensity > Math.ceil( maxDensity )
					? maxDensity
					: deviceDensity;
		} else if ( 'auto' === CLDLB.dpr && 'auto' !== deviceDensity ) {
			deviceDensity = 'auto';
		}

		return deviceDensity;
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
			this.config.pixel_step
		);
		const rect = image.getBoundingClientRect();
		const density = 'auto' !== this.density ? this._getDensity() : 1;
		return (
			rect.top < this.lazyThreshold &&
			( width > image.naturalWidth / density || ! image.cld_loaded )
		);
	},
	_shouldPlacehold( image ) {
		const width = this.scaleSize(
			image.originalWidth,
			image.width,
			this.config.pixel_step
		);
		const rect = image.getBoundingClientRect();
		const density = 'auto' !== this.density ? this._getDensity() : 1;
		return (
			image.dataset.placeholder &&
			! image.cld_loaded &&
			rect.top < this.lazyThreshold * 2 &&
			( width > image.naturalWidth / density || ! image.cld_placehold )
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
	getQuality() {
		let quality = 'q_auto';
		switch (
			navigator && navigator.connection
				? navigator.connection.effectiveType
				: 'none'
		) {
			case 'none':
				break;
			case '4g':
				quality = 'q_auto:good';
				break;
			case '3g':
				quality = 'q_auto:eco';
				break;
			case '2g':
			case 'slow-2g':
				quality = 'q_auto:low';
				break;
			default:
				quality = 'q_auto:best';
				break;
		}
		return quality;
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
			if ( image.dataset.srcset ) {
				image.srcset = image.dataset.srcset;
			} else {
				image.src = this.getSizeURL( image );
			}
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
			this.config.pixel_step
		);
		const density = this._getDensity();
		let newSize = '';
		if ( width ) {
			newSize += 'w_' + width;
			if ( 1 !== density ) {
				newSize += ',dpr_' + density;
			}
		}
		return image.dataset.src
			.replace( '--size--', newSize )
			.replace( /q_auto(?!:)/gi, this.getQuality() );
	},
	getPlaceholderURL( image ) {
		image.cld_placehold = true;
		const width = this.scaleSize(
			image.originalWidth,
			image.width,
			this.config.pixel_step
		);
		const density = this._getDensity();
		let newSize = '';
		if ( width ) {
			newSize += 'w_' + width;
			if ( 1 !== density ) {
				newSize += ',dpr_' + density;
			}
		}
		return image.dataset.placeholder.replace( '--size--', newSize );
	},
};
// Init.
window.addEventListener( 'load', () => {
	LazyLoad._init();
} );
