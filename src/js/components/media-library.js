import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

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

								// Only new assets need downloading, as in the media modal.
								if ( typeof asset.fetch === 'undefined' ) {
									self.removeProgress( download, index );
									return;
								}

								apiFetch( {
									path: cldData.dam.fetch_url,
									data: {
										src: asset.url,
										filename: asset.filename,
										attachment_id: asset.attachment_id,
										transformations: asset.transformations,
									},
									method: 'POST',
								} )
									.then( ( result ) => {
										// A failed download is answered with { success: false, data: error }.
										if (
											result &&
											false === result.success
										) {
											self.showError( result.data );
										}
									} )
									.catch( ( error ) => {
										self.showError(
											error && error.message
										);
									} )
									.finally( () => {
										self.removeProgress( download, index );
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
	/**
	 * Fade out and remove an import progress item, and hide the import status when it's the last one.
	 *
	 * @param {Object} download The progress items being imported, by index.
	 * @param {string} index    The index of the progress item to remove.
	 */
	removeProgress( download, index ) {
		const last = download[ index ];
		delete download[ index ];
		last.removeChild( last.firstChild );
		setTimeout( () => {
			last.style.opacity = 0;
			setTimeout( () => {
				last.parentNode.removeChild( last );
				if ( ! Object.keys( download ).length ) {
					this.libraryWrap.style.marginRight = '0px';
					this.importStatus.style.display = 'none';
				}
			}, 1000 );
		}, 500 );
	},
	/**
	 * Tell the user an asset could not be imported.
	 *
	 * @param {string} message The error message.
	 */
	showError( message ) {
		window.alert(
			message || __( 'Could not import the asset.', 'cloudinary' )
		);
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
