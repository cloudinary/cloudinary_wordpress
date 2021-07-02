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
						// Give the event a slight delay to allow the height to
						// be set for the animation to trigger.
						setTimeout( function () {
							self._dismiss( notice );
						}, 5 );
					} );
				}
			} );
		}
	},
	_dismiss( notice ) {
		const token = notice.dataset.dismiss;
		const duration = parseInt( notice.dataset.duration );
		notice.classList.add( 'dismissed' );
		notice.style.height = '0px';
		setTimeout( function () {
			notice.remove();
		}, 400 );
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
