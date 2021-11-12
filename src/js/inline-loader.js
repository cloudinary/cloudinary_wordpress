window.Cloudinary_Inline_Loader = {
	deviceDensity: window.devicePixelRatio ? window.devicePixelRatio : 'auto',
	density: null,
	images: [],
	config: CLDLB ? CLDLB : {},
	lazyThreshold: 0,
	enabled: false,
	sizeBands: [],
	iObserver: null,
	pObserver: null,
	rObserver: null,
	bind( image ) {
		if ( image.originalWidth ) {
			return;
		}
		if ( ! this.enabled ) {
			this._init();
		}
		const size = image.dataset.size.split( ' ' );
		image.originalWidth = size[ 0 ];
		image.originalHeight = size[ 1 ];
		if ( this.pObserver ) {
			this.pObserver.observe( image );
			this.iObserver.observe( image );
			image.addEventListener( 'error', ( ev ) => {
				// If load error, set a broken image and remove from images list to prevent infinite load loop.
				image.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>';
				this.rObserver.unobserve( image );
			} );
		} else {
			this.setupFallback( image );
		}
	},
	setupFallback( image ) {
		const srcSet = [];
		this.sizeBands.forEach( ( size ) => {
			if( size <= image.originalWidth ) {
				let newURL = this.getSizeURL( image, size, true ) + ` ${ size }w`;
				if ( -1 === srcSet.indexOf( newURL ) ) {
					srcSet.push( newURL );
				}
			}
		} );
		image.srcset = srcSet.join(',' );
		image.sizes = `(max-width: ${image.originalWidth}px) 100vw, ${image.originalWidth}px`;
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
		const pOptions = {
			rootMargin: this.lazyThreshold * 2 + 'px 0px ' + this.lazyThreshold * 2 + 'px 0px',
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
					if ( entry.target.dataset.srcset ) {
						entry.target.cld_loaded = true;
						entry.target.srcset = entry.target.dataset.srcset;
					} else {
						entry.target.src = this.getSizeURL( entry.target );
						this.rObserver.observe( entry.target );
					}
					observer.unobserve( entry.target );
				}
			} );
		}, iOptions );

		this.pObserver = new IntersectionObserver( ( entries, observer ) => {
			entries.forEach( entry => {
				if ( entry.isIntersecting ) {
					if ( entry.intersectionRatio < 0.5 ) {
						// Low so that it doesn't show partly.
						entry.target.src = this.getPlaceholderURL( entry.target );
					}
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
	},
	scaleWidth( image, width ) {
		const maxSize = parseInt( this.config.max_width );
		if ( ! width ) {
			width = image.width;
			while ( -1 === this.sizeBands.indexOf( width ) && width < maxSize ) {
				width++;
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
		const ratio = ( image.originalWidth / image.originalHeight ).toFixed( 3 );
		const renderedRatio = ( image.width / image.height ).toFixed( 3 );
		const scaledWidth = this.scaleWidth( image, width );
		const newSize = [];
		if ( image.width !== image.originalWidth ) {
			// We know it's a different size.
			newSize.push( ratio === renderedRatio ? 'c_scale' : 'c_fill,g_auto' );
		}
		const scaledHeight = Math.round( scaledWidth / renderedRatio );

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
	getSizeURL( image, width ) {
		const newSize = this.scaleSize( image, width, true );
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
		const newSize = this.scaleSize( image, null, false );

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
