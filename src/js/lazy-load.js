const LazyLoad = {
	deviceDensity: window.devicePixelRatio ? window.devicePixelRatio : 'auto',
	density: null,
	images: [],
	debounce: null,
	config: CLDLB ? CLDLB : {},
	lazyThreshold: 0,
	_init() {
		this._calcThreshold();

		const lazysNoScripts = document.querySelectorAll(
			'noscript[data-image]' );

		[ ...lazysNoScripts ].forEach( ( noscript ) => {
			const attributes = JSON.parse( noscript.dataset.image );
			const image = document.createElement( 'img' );
			for ( let att in attributes ) {
				image.setAttribute( att, attributes[ att ] );
			}
			image.originalWidth = attributes[ 'data-size' ][ 0 ];
			image.originalHeight = attributes[ 'data-size' ][ 1 ];

			this.images.push( image );
			noscript.parentNode.replaceChild( image, noscript );
		} );
		[ ...document.images ].forEach( ( image ) => {
			if ( ! image.dataset.publicId ) {
				return;
			}

			const size = image.dataset.size.split( ' ' );
			image.originalWidth = size[ 0 ];
			image.originalHeight = size[ 1 ];

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
		setTimeout( () => this._build(), 0 );
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
		if ( this.density ) {
			return this.density;
		}
		let maxDensity = this.config.dpr ? this.config.dpr.replace(
			'X', '' ) : 'off';
		if ( 'off' === maxDensity ) {
			this.density = 1;
			return 1;
		}
		let deviceDensity = this.deviceDensity;
		if (
			'max' !== maxDensity &&
			'auto' !== deviceDensity
		) {
			maxDensity = parseFloat( maxDensity );
			deviceDensity =
				deviceDensity > Math.ceil( maxDensity )
					? maxDensity
					: deviceDensity;
		}

		this.density = deviceDensity;

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
			this.config.placeholder &&
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
	scaleSize( original, newSize, responsiveStep ) {
		const diff = Math.floor( ( original - newSize ) / responsiveStep );
		let scaledSize = original - responsiveStep * diff;
		if ( scaledSize > original ) {
			scaledSize = original;
		} else if ( this.config.max_width < scaledSize ) {
			scaledSize = this.config.max_width;
		} else if ( this.config.min_width > scaledSize ) {
			scaledSize = this.config.min_width;
		}

		return scaledSize;
	},
	buildSize( image ) {
		if ( this._shouldRebuild( image ) ) {

			if ( image.dataset.srcset ) {
				image.cld_loaded = true;
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
		const ratio = image.originalWidth / image.originalHeight;
		const height = Math.round( width / ratio );
		const density = this._getDensity();
		let name = image.dataset.publicId.split( '/' ).pop();
		let newSize = [];

		if ( width ) {
			newSize.push( 'w_' + width );

			if ( height ) {
				newSize.push( 'h_' + height );
				name += `-${ width }x${ height }`;
			}
			if ( 1 !== density ) {
				newSize.push( 'dpr_' + density );
			}
		}

		const parts = [
			this.config.base_url,
			'images',
			newSize.join( ',' ),
			image.dataset.transformations,
			image.dataset.publicId,
			name
		];

		const url = parts.filter( this.empty ).join( '/' );

		return url;
	},
	getPlaceholderURL( image ) {
		image.cld_placehold = true;
		const width = this.scaleSize(
			image.originalWidth,
			image.width,
			this.config.pixel_step
		);
		let newSize = [];
		if ( width ) {
			newSize.push( 'w_' + width );
		}

		const parts = [
			this.config.base_url,
			'images',
			newSize.join( ',' ),
			this.config.placeholder,
			image.dataset.publicId,
			'responsive'
		];

		return parts.filter( this.empty ).join( '/' );
	},
	empty( thing ) {
		return 0 !== thing.length;
	}
};
// Init.
window.addEventListener( 'load', () => {
	LazyLoad._init();
} );
