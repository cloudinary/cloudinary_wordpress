import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import OnOff from './onoff';

const Extensions = {
	pageReloader: document.getElementById( 'page-reloader' ),
	init() {
		apiFetch.use(
			apiFetch.createNonceMiddleware( cldData.extensions.nonce )
		);

		const toggles = document.querySelectorAll( '[data-extension]' );
		[ ...toggles ].forEach( ( toggle ) => {
			toggle.addEventListener( 'change', ( ev ) => {
				if ( ! toggle.spinner ) {
					toggle.spinner = this.createSpinner();
					toggle.parentNode.appendChild( toggle.spinner );
				}
				if ( toggle.debounce ) {
					clearTimeout( toggle.debounce );
				}
				toggle.debounce = setTimeout( () => {
					this.toggleExtension( toggle );
					toggle.debounce = null;
				}, 1000 );
			} );
		} );
	},
	toggleExtension( toggle ) {
		const extension = toggle.dataset.extension;
		const enabled = toggle.checked;
		apiFetch( {
			path: cldData.extensions.url,
			data: {
				extension,
				enabled,
			},
			method: 'POST',
		} ).then( ( result ) => {
			if ( toggle.spinner ) {
				toggle.parentNode.removeChild( toggle.spinner );
				delete toggle.spinner;
			}
			Object.keys( result ).forEach( ( key ) => {
				document
					.querySelectorAll( `[data-text="${ key }"]` )
					.forEach( ( item ) => {
						item.innerText = result[ key ];
					} );
			} );
			this.pageReloader.style.display = 'block';
		} );
	},
	createSpinner() {
		const spinner = document.createElement( 'span' );
		spinner.classList.add( 'spinner' );
		spinner.classList.add( 'cld-extension-spinner' );
		return spinner;
	},
};

window.addEventListener( 'load', () => Extensions.init() );

export default Extensions;
