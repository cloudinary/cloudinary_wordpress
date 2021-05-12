const ResponsiveBreakpoints = {
	scale: window.devicePixelRatio,
	_init() {
		[ ...document.images ].forEach( ( image ) => {
			if ( ! image.dataset.src ) {
				return;
			}

			// Initial build.
			this.buildSize( image );

			// Resize handler.
			window.addEventListener( 'resize', ( ev ) => {
				this.buildSize( image );
			} );
		} );
	},
	_shouldRebuild( image ) {
		return ( ( image.naturalWidth && image.width * this.scale > image.naturalWidth + 50 ) || ! image.cld_loaded ); // Make the 50 a confirgurable band.
	},
	buildSize( image ) {
		if ( this._shouldRebuild( image ) ) {
			image.src = this.getSizeURL( image );
		}
	},
	getSizeURL( image ) {
		const newSize = '/w_' + ( image.width * this.scale ) + ',h_' + ( image.height * this.scale ) + '/';
		const regex = /\/v\d+\//i;
		image.cld_loaded = true;
		return image.dataset.src.replace( regex, newSize );
	},
};
// Init.
ResponsiveBreakpoints._init();
