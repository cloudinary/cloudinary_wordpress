import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const Wizard = {
	next: document.querySelector( '[data-navigate="next"]' ),
	back: document.querySelector( '[data-navigate="back"]' ),
	lock: document.getElementById( 'pad-lock' ),
	lockIcon: document.getElementById( 'lock-icon' ),
	options: document.querySelectorAll( '.cld-ui-input[type="checkbox"]' ),
	settings: document.getElementById( 'optimize' ),
	tabs: document.getElementById( 'wizard-tabs' ),
	tracking: document.getElementById( 'tracking' ),
	connection: {
		error: document.getElementById( 'connection-error' ),
		success: document.getElementById( 'connection-success' ),
		working: document.getElementById( 'connection-working' ),
	},
	init() {
		apiFetch.use( apiFetch.createNonceMiddleware( cldData.saveNonce ) );

		const navs = document.querySelectorAll( '[data-navigate]' );
		const connectionInput = document.getElementById(
			'connect.cloudinary_url'
		);
		[ ...navs ].forEach( ( button ) => {
			button.addEventListener( 'click', () =>
				this.navigate( button.dataset.navigate )
			);
		} );
		this.lock.addEventListener( 'click', () => {
			this.lockIcon.classList.toggle( 'dashicons-unlock' );
			this.settings.classList.toggle( 'disabled' );
			this.options.forEach( ( checkbox ) => {
				checkbox.disabled = checkbox.disabled ? '' : 'disabled';
			} );
		} );
		connectionInput.addEventListener( 'input', () => {
			const value = connectionInput.value;
			this.connection.error.classList.remove( 'active' );
			this.connection.success.classList.remove( 'active' );
			this.connection.working.classList.remove( 'active' );
			if ( value.length ) {
				const valid = this.evaluateConnectionString( value );
				if ( valid ) {
					this.connection.working.classList.add( 'active' );
					this.testConnection( value );
				} else {
					this.connection.error.classList.add( 'active' );
				}
			}
		} );
	},
	getCurrent() {
		return document.querySelector( '.active[data-tab]' );
	},
	getTab( tab ) {
		const indicator = document.querySelector( `[data-tab="${ tab }"]` );
		const page = document.getElementById( `tab-${ tab }` );
		this.show( page );
		this.show( this.next );
		indicator.classList.remove( 'complete' );
		indicator.classList.add( 'active' );
		this.hide( this.lock );
		switch ( tab ) {
			case 1:
				this.hide( this.back );
				this.next.disabled = '';
				break;
			case 2:
				this.show( this.back );
				this.next.disabled = 'disabled';
				setTimeout( () => {
					document.getElementById( 'connect.cloudinary_url' ).focus();
				}, 0 );
				break;
			case 3:
				this.show( this.lock );
				break;
			case 4:
				this.hide( this.tabs );
				this.hide( this.next );
				this.hide( this.back );
				this.show( this.tracking );
				break;
		}
		return indicator;
	},
	navigate( direction ) {
		const current = this.getCurrent();
		const tab = parseInt( current.dataset.tab );
		const page = document.getElementById( `tab-${ tab }` );
		this.hide( page );
		current.classList.remove( 'active' );

		if ( 'next' === direction ) {
			this.navigateNext( current );
		} else if ( 'back' === direction ) {
			this.navigateBack( current );
		}
	},
	navigateBack( current ) {
		const tab = parseInt( current.dataset.tab );
		const page = document.getElementById( `tab-${ tab }` );
		const nextTab = tab - 1;
		this.hide( page );
		this.getTab( nextTab );
	},
	navigateNext( current ) {
		const tab = parseInt( current.dataset.tab );
		const nextTab = tab + 1;
		current.classList.add( 'complete' );
		this.getTab( nextTab );
	},
	show( item ) {
		item.classList.remove( 'hidden' );
		item.style.display = '';
	},
	hide( item ) {
		item.classList.add( 'hidden' );
		item.style.display = 'none';
	},
	evaluateConnectionString( value ) {
		const reg = new RegExp(
			/^(?:CLOUDINARY_URL=)?(cloudinary:\/\/){1}(\d*)[:]{1}([^@]*)[@]{1}([^@]*)$/gim
		);
		return reg.test( value );
	},
	testConnection( value ) {
		apiFetch( {
			path: cldData.saveURL,
			data: {
				connect: {
					cloudinary_url: value,
				},
			},
			method: 'POST',
		} ).then( ( result ) => {
			this.connection.working.classList.remove( 'active' );
			if ( result.connection_error ) {
				this.connection.error.classList.add( 'active' );
			} else if ( result.connection_success ) {
				this.connection.success.classList.add( 'active' );
				this.next.disabled = '';
			}
		} );
	},
};

window.addEventListener( 'load', () => Wizard.init() );

export default Wizard;
