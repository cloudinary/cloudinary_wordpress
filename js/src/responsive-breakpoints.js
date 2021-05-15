const ResponsiveBreakpoints = {
	scale: window.devicePixelRatio,
	images: [],
	_init() {
		[ ...document.images ].forEach( ( image ) => {
			if ( ! image.dataset.src ) {
				return;
			}
			image.originalWidth = image.dataset.width;
			console.log( navigator );
			this.images.push( image );

		} );
		let debounce = null;
		// Resize handler.
		window.addEventListener( 'resize', ( ev ) => {
			if ( debounce ) {
				clearTimeout( debounce );
			}
			debounce = setTimeout( () => {
				this._build();
			}, 500 );
		} );
		window.addEventListener( 'scroll', ( ev ) => {
			if ( debounce ) {
				clearTimeout( debounce );
			}
			debounce = setTimeout( () => {
				this._build();
			}, 500 );
		})
		// Build images.
		this._build();
	},
	_build() {
		this.images.forEach( ( image ) => {
			this.buildSize( image );
		} );
	},
	_shouldRebuild( image ) {
		const width = this.scaleSize( image.originalWidth, image.width );
		let rect = image.getBoundingClientRect();

		return rect.top < window.innerHeight + 500 && ( width > image.naturalWidth || ! image.cld_loaded );
	},
	getQuality() {
		let quality = 'q_auto';
		switch ( ( navigator && navigator.connection ) ? navigator.connection.effectiveType : 'none' ) {
			case 'none':
				break;
			case '4g':
				quality = 'q_auto:good';
				break;
			case '3g':
				quality = 'q_auto:eco';
				break;
			case'2g':
			case 'slow-2g':
				quality = 'q_auto:low';
				break;
			default:
				quality = 'q_auto:best';
				break;
		}
		return quality;
	},
	scaleSize( original, newSize ) {
		const diff = Math.floor( ( original - ( newSize + 10 ) * this.scale ) / 100 );
		let scaledSize = original - 100 * diff;
		if ( scaledSize > original ) {
			scaledSize = original;
		}
		return scaledSize;
	},
	buildSize( image ) {
		if ( this._shouldRebuild( image ) ) {
			image.src = this.getSizeURL( image );
		}
	},
	getSizeURL( image ) {
		const width = this.scaleSize( image.originalWidth, image.width );
		const newSize = '/w_' + width + '/';
		const regex = /\/v\d+\//i;
		image.cld_loaded = true;
		return image.dataset.src.replace( regex, newSize ).replace( 'q_auto', this.getQuality() );
	},
};
// Init.
ResponsiveBreakpoints._init();
