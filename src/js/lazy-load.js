const LazyLoad = {
	deviceDensity: window.devicePixelRatio ? window.devicePixelRatio : 'auto',
	density: null,
	images: [],
	throttle: false,
	config: CLDLB ? CLDLB : {},
	lazyThreshold: 0,
	_init() {
		this._calcThreshold();
		[ ...document.images ].forEach( ( image ) => {
			if ( ! image.dataset.publicId ) {
				return;
			}

			const size = image.dataset.size.split( ' ' );
			image.originalWidth = size[ 0 ];
			image.originalHeight = size[ 1 ];
			if ( size[ 2 ] ) {
				image.crop = size[ 2 ];
			}
			this.images.push( image );
			image.addEventListener( 'error', ( ev ) => {
				// If load error, set a broken image and remove from images list to prevent infinite load loop.
				image.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>';
				const index = this.images.indexOf( image );
				this.images.splice( index, 1 );
			} );
		} );
		// Resize handler.
		window.addEventListener( 'resize', () => {
			this._throttle( this._build.bind( this ), 100, true );
		} );
		window.addEventListener( 'scroll', () => {
			this._throttle( this._build.bind( this ), 100, false );
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

		// Round to nearest 0.5 to reduce URL variations
		if ( 'auto' !== deviceDensity ) {
			deviceDensity = Math.round( deviceDensity * 2 ) / 2;
		}

		if (
			'max' !== maxDensity &&
			'auto' !== deviceDensity
		) {
			maxDensity = parseFloat( maxDensity );
			// Round maxDensity to nearest 0.5 to maintain consistency
			maxDensity = Math.round( maxDensity * 2 ) / 2;
			deviceDensity =
				deviceDensity > Math.ceil( maxDensity )
					? maxDensity
					: deviceDensity;
		}

		this.density = deviceDensity;

		return deviceDensity;
	},
	_throttle( callback, time, force ) {
		if ( this.throttle ) {
			return;
		}

		setTimeout( () => {
			callback( force );
			this.throttle = false;
		}, time );
	},
	_build( force ) {
		this.images.forEach( ( image ) => {
			if ( ! force && image.cld_loaded ) {
				return;
			}
			this.buildSize( image );
		} );
	},
	_shouldRebuild( image ) {
		const width = this.scaleWidth( image );
		const rect = image.getBoundingClientRect();
		const density = 'auto' !== this.density ? this._getDensity() : 1;
		return (
			rect.top < this.lazyThreshold &&
			( width > image.naturalWidth / density || ! image.cld_loaded )
		);
	},
	_shouldPlacehold( image ) {
		const width = this.scaleWidth( image );
		const rect = image.getBoundingClientRect();
		const density = 'auto' !== this.density ? this._getDensity() : 1;
		return (
			this.config.placeholder &&
			! image.cld_loaded &&
			rect.top < this.lazyThreshold * 2 &&
			( width > image.naturalWidth / density || ! image.cld_placehold )
		);
	},
	scaleWidth( image ) {
		const responsiveStep = this.config.pixel_step;
		const diff = Math.floor( ( image.originalWidth - image.width ) / responsiveStep );
		let scaledWidth = image.originalWidth - responsiveStep * diff;
		if ( scaledWidth > image.originalWidth ) {
			scaledWidth = image.originalWidth;
		} else if ( this.config.max_width < scaledWidth ) {
			scaledWidth = this.config.max_width;
		} else if ( this.config.min_width > scaledWidth ) {
			scaledWidth = this.config.min_width;
		}
		return scaledWidth;
	},
	scaleSize( image, dpr ) {
		const ratio = ( image.originalWidth / image.originalHeight ).toFixed( 3 );
		const renderedRatio = ( image.width / image.height ).toFixed( 3 );
		const scaledWidth = this.scaleWidth( image );
		const newSize = [];
		if ( image.width !== image.originalWidth ) {
			// We know it's a different size.
			newSize.push( ratio === renderedRatio ? 'c_scale' : 'c_fill,g_auto' );
		}
		const scaledHeight = Math.round( scaledWidth / renderedRatio );

		newSize.push( 'w_' + scaledWidth );
		newSize.push( 'h_' + scaledHeight );

		if ( dpr ) {
			const density = this._getDensity();
			if ( 1 !== density ) {
				newSize.push( 'dpr_' + density );
			}
		}

		return {
			transformation: newSize.join( ',' ),
			nameExtension: scaledWidth + 'x' + scaledHeight,
		};
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
		const newSize = this.scaleSize( image, true );

		const format = 'auto' !== this.config[ 'image_format' ] && 'none' !== this.config[ 'image_format' ] ? this.config[ 'image_format' ] : image.dataset.format;
		const name = image.dataset.publicId.split( '/' ).pop();

		const parts = [
			this.config.base_url,
			'images',
			newSize.transformation,
			image.dataset.transformations,
			image.dataset.publicId,
			name + '-' + newSize.nameExtension + '.' + format + '?_i=AA'
		];

		return parts.filter( this.empty ).join( '/' );
	},
	getPlaceholderURL( image ) {
		image.cld_placehold = true;
		const newSize = this.scaleSize( image, false );

		const parts = [
			this.config.base_url,
			'images',
			newSize.transformation,
			this.config.placeholder,
			image.dataset.publicId,
			'placeholder'
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
