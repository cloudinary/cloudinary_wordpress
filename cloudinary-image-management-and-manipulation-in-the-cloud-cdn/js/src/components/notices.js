/* global window wp  */
const Notices = {
    _init: function() {
        let self = this;
        if ( typeof CLDIS !== 'undefined' ) {
            let notices = document.getElementsByClassName( 'cld-notice' );
            [ ...notices ].forEach( ( notice ) => {
                notice.addEventListener( 'click', ( ev ) => {
                    // WordPress has an onClick that cannot be unbound.
                    // So, we have the click on our Notice, and act on the
                    // button as a target.
                    if ( 'notice-dismiss' === ev.target.className ) {
                        self._dismiss( notice );
                    }
                } );
            } );
        }
    },
    _dismiss: function( notice ) {
        let token    = notice.dataset.dismiss;
        let duration = notice.dataset.duration;
        wp.ajax.send( {
            url  : CLDIS.url,
            data : {
                token    : token,
                duration : duration,
                _wpnonce : CLDIS.nonce
            }
        } );
    },
};

// Init.
window.addEventListener( 'load', Notices._init() );

export default Notices;
