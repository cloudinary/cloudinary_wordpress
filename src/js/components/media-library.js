import apiFetch from '@wordpress/api-fetch';

const MediaLibrary = {
	wpWrap: document.getElementById( 'wpwrap' ),
	adminbar: document.getElementById( 'wpadminbar' ),
	wpContent: document.getElementById( 'wpbody-content' ),
	libraryWrap: document.getElementById( 'cloudinary-dam' ),
	cloudinaryHeader: document.getElementById( 'cloudinary-header' ),
	wpFooter: document.getElementById( 'wpfooter' ),
	importStatus: document.getElementById( 'import-status' ),
	downloading: {},
	_init() {
		const self = this;
		const library = this.libraryWrap;
		const status = this.importStatus;
		if (
			typeof CLDN !== 'undefined' &&
			document.querySelector( CLDN.mloptions.inline_container )
		) {
			apiFetch.use( apiFetch.createNonceMiddleware( CLDN.nonce ) );
			cloudinary.openMediaLibrary( CLDN.mloptions, {
				insertHandler( data ) {
					const download = [];
					for ( let i = 0; i < data.assets.length; i++ ) {
						const temp = data.assets[ i ];
						wp.ajax
							.post( 'cloudinary-down-sync', {
								nonce: CLDN.nonce,
								asset: temp,
							} )
							.done( function ( asset ) {
								library.style.marginRight = '220px';
								status.style.display = 'block';
								const preview = self.makeProgress( asset );
								const index = 'download-' + asset.public_id;
								download[ index ] = preview;
								status.appendChild( preview );
								setTimeout( () => {
									preview.style.opacity = 1;
								}, 250 );
								apiFetch( {
									path: cldData.dam.fetch_url,
									data: {
										src: asset.url,
										filename: asset.filename,
										attachment_id: asset.attachment_id,
										transformations: asset.transformations,
									},
									method: 'POST',
								} ).then( ( result ) => {
									const last = download[ index ];
									delete download[ index ];
									last.removeChild( last.firstChild );
									setTimeout( () => {
										last.style.opacity = 0;
										setTimeout( () => {
											last.parentNode.removeChild( last );
											if (
												! Object.keys( download ).length
											) {
												library.style.marginRight =
													'0px';
												status.style.display = 'none';
											}
										}, 1000 );
									}, 500 );
								} );
							} );
					}
				},
			} );

			window.addEventListener( 'resize', function () {
				self._resize();
			} );

			self._resize();
		}
	},
	_resize() {
		this.libraryWrap.style.height =
			this.wpFooter.offsetTop -
			this.libraryWrap.offsetTop -
			this.adminbar.offsetHeight +
			'px';
	},
	makeProgress( asset ) {
		const wrap = document.createElement( 'div' );
		const process = document.createElement( 'span' );
		const id = document.createElement( 'span' );
		wrap.classList.add( 'cld-import-item' );
		process.classList.add( 'spinner' );
		id.classList.add( 'cld-import-item-id' );
		id.innerText = asset.public_id;
		wrap.appendChild( process );
		wrap.appendChild( id );
		return wrap;
	},
};

export default MediaLibrary;

// Init.
window.addEventListener( 'load', () => MediaLibrary._init() );
