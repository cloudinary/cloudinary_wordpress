const CloudinaryLoader = {
	deviceDensity: window.devicePixelRatio ? window.devicePixelRatio : 'auto',
	density: null,
	config: CLDLB ? CLDLB : {},
	lazyThreshold: 0,
	enabled: false,
	sizeBands: [],
	iObserver: null,
	pObserver: null,
	rObserver: null,
	aboveFold: true,
	minPlaceholderThreshold: 500,
	bind( image ) {
		image.CLDbound = true;
		if ( ! this.enabled ) {
			this._init();
		}
		const size = image.dataset.size.split( ' ' );
		image.originalWidth = size[ 0 ];
		image.originalHeight = size[ 1 ];
		if ( this.pObserver ) {
			if ( this.aboveFold && this.inInitialView( image ) ) {
				this.buildImage( image );
			} else {
				this.pObserver.observe( image );
				this.iObserver.observe( image );
			}
			image.addEventListener( 'error', ( ev ) => {
				// If load error, set a broken image and remove from images list to prevent infinite load loop.
				image.srcset = ''; // Remove srcset to stop browser from trying again.
				image.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>';
				this.rObserver.unobserve( image );
			} );
		} else {
			this.setupFallback( image );
		}
	},
	buildImage( image ) {
		if ( image.dataset.srcset ) {
			image.cld_loaded = true;
			image.srcset = image.dataset.srcset;
		} else {
			image.src = this.getSizeURL( image );
			if ( image.dataset.responsive ) {
				this.rObserver.observe( image );
			}
		}
	},
	inInitialView( image ) {
		const rect = image.getBoundingClientRect();
		this.aboveFold = rect.top < window.innerHeight + this.lazyThreshold;
		return this.aboveFold;
	},
	setupFallback( image ) {
		const srcSet = [];
		this.sizeBands.forEach( ( size ) => {
			if ( size <= image.originalWidth ) {
				let newURL = this.getSizeURL( image, size, true ) + ` ${ size }w`;
				if ( -1 === srcSet.indexOf( newURL ) ) {
					srcSet.push( newURL );
				}
			}
		} );
		image.srcset = srcSet.join( ',' );
		image.sizes = `(max-width: ${ image.originalWidth }px) 100vw, ${ image.originalWidth }px`;
	},
	_init() {
		this.enabled = true;
		this._calcThreshold();
		this._getDensity();
		let maxWidth = parseInt( this.config.max_width );
		const minWidth = parseInt( this.config.min_width );
		const pixelStep = parseInt( this.config.pixel_step );
		while ( maxWidth - pixelStep >= minWidth ) {
			maxWidth = maxWidth - pixelStep;
			this.sizeBands.push( maxWidth );
		}

		if ( typeof IntersectionObserver !== 'undefined' ) {
			this._setupObservers();
		}

		this.enabled = true;
	},
	_setupObservers() {
		const iOptions = {
			rootMargin: this.lazyThreshold + 'px 0px ' + this.lazyThreshold + 'px 0px',
		};

		const placeholderThreshold = this.minPlaceholderThreshold < this.lazyThreshold * 2 ? this.lazyThreshold * 2 : this.minPlaceholderThreshold;
		const pOptions = {
			rootMargin: placeholderThreshold + 'px 0px ' + placeholderThreshold + 'px 0px',
		};
		this.rObserver = new ResizeObserver( ( entries, observer ) => {
			entries.forEach( entry => {
				if ( entry.target.cld_loaded && entry.contentRect.width >= entry.target.cld_loaded ) {
					entry.target.src = this.getSizeURL( entry.target );
				}
			} );
		} );
		this.iObserver = new IntersectionObserver( ( entries, observer ) => {
			entries.forEach( entry => {
				if ( entry.isIntersecting ) {
					this.buildImage( entry.target );
					observer.unobserve( entry.target );
					this.pObserver.unobserve( entry.target );
				}
			} );
		}, iOptions );
		this.pObserver = new IntersectionObserver( ( entries, observer ) => {
			entries.forEach( entry => {

				if ( entry.isIntersecting ) {
					entry.target.src = this.getPlaceholderURL( entry.target );
					observer.unobserve( entry.target );
				}
			} );
		}, pOptions );
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
		this.lazyThreshold = parseInt( unit, 10 );
	},
	_getDensity() {
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
	},
	scaleWidth( image, width, ratio ) {
		const maxSize = parseInt( this.config.max_width );
		const maxHeight = Math.round( maxSize / ratio );
		if ( ! width ) {
			width = image.width;
			let height = Math.round( width / ratio );

			while ( -1 === this.sizeBands.indexOf( width ) && height < maxHeight && width < maxSize ) {
				width++;
				height = Math.round( width / ratio );
			}
		}
		if ( width > maxSize ) {
			width = maxSize;
		}
		if ( image.originalWidth < width ) {
			width = image.originalWidth;
		}
		return width;
	},
	scaleSize( image, width, dpr ) {

		const ratio = image.dataset.crop ? parseFloat( image.dataset.crop ) : ( image.originalWidth / image.originalHeight ).toFixed( 2 );
		const scaledWidth = this.scaleWidth( image, width, ratio );
		const scaledHeight = Math.round( scaledWidth / ratio );
		const newSize = [];

		// Set crop if needed, else just scale it.
		if ( image.dataset.transformationCrop ) {
			newSize.push( image.dataset.transformationCrop );
		} else if ( ! image.dataset.crop ) {
			newSize.push( image.dataset.crop ? 'c_fill' : 'c_scale' );
			if ( image.dataset.crop ) {
				newSize.push( 'g_auto' );
			}
		}

		newSize.push( 'w_' + scaledWidth );
		newSize.push( 'h_' + scaledHeight );

		if ( dpr ) {
			if ( 1 !== this.density ) {
				newSize.push( 'dpr_' + this.density );
			}
		}
		image.cld_loaded = scaledWidth;
		return {
			transformation: newSize.join( ',' ),
			nameExtension: scaledWidth + 'x' + scaledHeight,
		};
	},
	getDeliveryMethod( image ) {
		return image.dataset.seo && 'upload' === image.dataset.delivery ? 'images' : 'image/' + image.dataset.delivery;
	},
	getSizeURL( image, width ) {
		const newSize = this.scaleSize( image, width, true );

		const parts = [
			this.config.base_url,
			this.getDeliveryMethod( image ),
			'upload' === image.dataset.delivery ? newSize.transformation : '',
			image.dataset.transformations,
			'v' + image.dataset.version,
			image.dataset.publicId + '?_i=AA'
		];

		return parts.filter( this.empty ).join( '/' );
	},
	getPlaceholderURL( image ) {
		image.cld_placehold = true;
		const newSize = this.scaleSize( image, null, false );

		const parts = [
			this.config.base_url,
			this.getDeliveryMethod( image ),
			newSize.transformation,
			this.config.placeholder,
			image.dataset.publicId
		];

		return parts.filter( this.empty ).join( '/' );
	},
	empty( thing ) {
		return 'undefined' !== typeof thing && 0 !== thing.length;
	}
};
window.CLDBind = ( image ) => {
	if ( ! image.CLDbound ) {
		CloudinaryLoader.bind( image );
	}
};
window.initFallback = () => {
	[...document.querySelectorAll('img[data-cloudinary="lazy"]')].forEach( ( image )=>{
		CLDBind( image );
	})
}
// Window load Fallback.
window.addEventListener( 'load', () => {
	initFallback();
} );
// Dynamic loaded.
if( document.querySelector('script[src*="?cloudinary_lazy_load_loader"]') ) {
	initFallback();
}
