/* global CLDIS */
const Notices = {
	_init() {
		const self = this;
		if ( typeof CLDIS !== 'undefined' ) {
			const notices = document.getElementsByClassName( 'cld-notice-box' );
			[ ...notices ].forEach( ( notice ) => {
				const dismiss = notice.getElementsByClassName(
					'notice-dismiss'
				);
				if ( dismiss.length ) {
					dismiss[ 0 ].addEventListener( 'click', ( ev ) => {
						notice.style.height = notice.offsetHeight + 'px';
						ev.preventDefault();
						self._dismiss( notice );
					} );
				}
			} );
		}
	},
	_dismiss( notice ) {
		const token = notice.dataset.dismiss;
		const duration = parseInt( notice.dataset.duration );

		notice.classList.add( 'dismissed' );
		notice.remove();

		if ( 0 < duration ) {
			wp.ajax.send( {
				url: CLDIS.url,
				data: {
					token,
					duration,
					_wpnonce: CLDIS.nonce,
				},
			} );
		}
	},
};

// Init.
window.addEventListener( 'load', Notices._init() );

export default Notices;
