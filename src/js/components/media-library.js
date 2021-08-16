/* global CLD_ML */
const MediaLibrary = {
	wpWrap: document.getElementById( 'wpwrap' ),
	wpContent: document.getElementById( 'wpbody-content' ),
	libraryWrap: document.getElementById( 'cloudinary-embed' ),
	_init() {
		const self = this;
		if ( typeof CLD_ML !== 'undefined' ) {
			cloudinary.openMediaLibrary( CLD_ML.mloptions, {
				insertHandler() {
					// @todo: Determin what to do here.
					alert( 'Import is not yet implemented.' );
				},
			} );

			window.addEventListener( 'resize', function () {
				self._resize();
			} );

			self._resize();
		}
	},
	_resize() {
		const style = getComputedStyle( this.wpContent );
		this.libraryWrap.style.height =
			this.wpWrap.offsetHeight -
			parseInt( style.getPropertyValue( 'padding-bottom' ) ) +
			'px';
	},
};

export default MediaLibrary;

// Init.
MediaLibrary._init();
