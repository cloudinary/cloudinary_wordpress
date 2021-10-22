import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const Wizard = {
	storageKey: '_cld_wizard',
	next: document.querySelector( '[data-navigate="next"]' ),
	back: document.querySelector( '[data-navigate="back"]' ),
	lock: document.getElementById( 'pad-lock' ),
	lockIcon: document.getElementById( 'lock-icon' ),
	options: document.querySelectorAll( '.cld-ui-input[type="checkbox"]' ),
	settings: document.getElementById( 'optimize' ),
	tabBar: document.getElementById( 'wizard-tabs' ),
	tracking: document.getElementById( 'tracking' ),
	complete: document.getElementById( 'complete-wizard' ),
	tabs: {
		'tab-1': document.getElementById( 'tab-icon-1' ),
		'tab-2': document.getElementById( 'tab-icon-2' ),
		'tab-3': document.getElementById( 'tab-icon-3' ),
	},
	content: {
		'tab-1': document.getElementById( 'tab-1' ),
		'tab-2': document.getElementById( 'tab-2' ),
		'tab-3': document.getElementById( 'tab-3' ),
		'tab-4': document.getElementById( 'tab-4' ),
	},
	connection: {
		error: document.getElementById( 'connection-error' ),
		success: document.getElementById( 'connection-success' ),
		working: document.getElementById( 'connection-working' ),
	},
	debounceConnect: null,
	config: {},
	init() {
		if ( ! cldData.wizard ) {
			return;
		}

		this.config = cldData.wizard.config;

		if ( window.localStorage.getItem( this.storageKey ) ) {
			this.config = JSON.parse(
				window.localStorage.getItem( this.storageKey )
			);
		}

		if ( document.location.hash.length ) {
			this.hashChange();
		}

		apiFetch.use(
			apiFetch.createNonceMiddleware( cldData.wizard.saveNonce )
		);
		const navs = document.querySelectorAll( '[data-navigate]' );
		const connectionInput = document.getElementById(
			'connect.cloudinary_url'
		);

		[ ...navs ].forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				this.navigate( button.dataset.navigate );
			} );
		} );
		this.lock.addEventListener( 'click', () => {
			this.lockIcon.classList.toggle( 'dashicons-unlock' );
			this.settings.classList.toggle( 'disabled' );
			this.options.forEach( ( checkbox ) => {
				checkbox.disabled = checkbox.disabled ? '' : 'disabled';
			} );
		} );
		connectionInput.addEventListener( 'input', ( ev ) => {
			const value = connectionInput.value.replace(
				'CLOUDINARY_URL=',
				''
			);
			this.connection.error.classList.remove( 'active' );
			this.connection.success.classList.remove( 'active' );
			this.connection.working.classList.remove( 'active' );
			if ( value.length ) {
				if ( this.debounceConnect ) {
					clearTimeout( this.debounceConnect );
				}

				this.debounceConnect = setTimeout( () => {
					const valid = this.evaluateConnectionString( value );
					if ( valid ) {
						this.connection.working.classList.add( 'active' );
						this.testConnection( value );
					} else {
						this.connection.error.classList.add( 'active' );
					}
				}, 500 );
			}
		} );
		if ( this.config.cldString.length ) {
			connectionInput.value = this.config.cldString;
		}

		this.getTab( this.config.tab );
		this.initFeatures();
		window.addEventListener( 'hashchange', ( ev ) => {
			this.hashChange();
		} );
	},
	hashChange() {
		const tab = parseInt( document.location.hash.replace( '#', '' ) );
		if ( tab && 0 < tab && 5 > tab ) {
			this.getTab( tab );
		}
	},
	initFeatures() {
		const mediaCheck = document.getElementById( 'media_library' );
		mediaCheck.checked = this.config.mediaLibrary;
		mediaCheck.addEventListener( 'change', () => {
			this.setConfig( 'mediaLibrary', mediaCheck.checked );
		} );
		const nonMediaCheck = document.getElementById( 'non_media' );
		nonMediaCheck.checked = this.config.nonMedia;
		nonMediaCheck.addEventListener( 'change', () => {
			this.setConfig( 'nonMedia', nonMediaCheck.checked );
		} );
		const advanced = document.getElementById( 'advanced' );
		advanced.checked = this.config.advanced;
		advanced.addEventListener( 'change', () => {
			this.setConfig( 'advanced', advanced.checked );
		} );
	},
	getCurrent() {
		return this.content[ `tab-${ this.config.tab }` ];
	},
	hideTabs() {
		Object.keys( this.content ).forEach( ( key ) => {
			this.hide( this.content[ key ] );
		} );
	},
	completeTab( tab ) {
		this.incompleteTab();
		Object.keys( this.tabs ).forEach( ( key ) => {
			const thisTab = parseInt( this.tabs[ key ].dataset.tab );
			if ( tab > thisTab ) {
				this.tabs[ key ].classList.add( 'complete' );
			} else if ( tab === thisTab ) {
				this.tabs[ key ].classList.add( 'active' );
			}
		} );
	},
	incompleteTab( tab ) {
		Object.keys( this.tabs ).forEach( ( key ) => {
			this.tabs[ key ].classList.remove( 'complete', 'active' );
		} );
	},
	getCurrentTab() {
		return this.tabs[ `tab-icon-${ this.config.tab }` ];
	},
	getTab( tab ) {
		if ( 4 === tab && window.localStorage.getItem( this.storageKey ) ) {
			// Place a save and wait, before moving.
			this.saveConfig();
			return;
		}

		const current = this.getCurrent();
		const indicator = this.getCurrentTab();
		const page = document.getElementById( `tab-${ tab }` );

		this.hideTabs();
		this.completeTab( tab );
		this.hide( document.getElementById( `tab-${ this.config.tab }` ) );
		current.classList.remove( 'active' );

		this.show( page );
		this.show( this.next );
		this.hide( this.lock );
		switch ( tab ) {
			case 1:
				this.hide( this.back );
				this.next.disabled = '';
				break;
			case 2:
				this.show( this.back );
				if ( ! this.config.cldString.length ) {
					this.next.disabled = 'disabled';
					setTimeout( () => {
						document
							.getElementById( 'connect.cloudinary_url' )
							.focus();
					}, 0 );
				} else {
					this.connection.success.classList.add( 'active' );
				}
				break;
			case 3:
				if ( ! this.config.cldString.length ) {
					document.location.hash = 2;
					return;
				}
				this.show( this.lock );
				this.show( this.back );
				break;
			case 4:
				if ( ! this.config.cldString.length ) {
					document.location.hash = 2;
					return;
				}
				this.hide( this.tabBar );
				this.hide( this.next );
				this.hide( this.back );
				this.saveConfig();
				break;
		}
		this.setConfig( 'tab', tab );
		return indicator;
	},
	navigate( direction ) {
		if ( 'next' === direction ) {
			this.navigateNext();
		} else if ( 'back' === direction ) {
			this.navigateBack();
		}
	},
	navigateBack() {
		document.location.hash = this.config.tab - 1;
	},
	navigateNext() {
		document.location.hash = this.config.tab + 1;
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
			path: cldData.wizard.testURL,
			data: {
				cloudinary_url: value,
			},
			method: 'POST',
		} ).then( ( result ) => {
			this.connection.working.classList.remove( 'active' );
			if ( 'connection_error' === result.type ) {
				this.connection.error.classList.add( 'active' );
			} else if ( 'connection_success' === result.type ) {
				this.connection.success.classList.add( 'active' );
				this.next.disabled = '';
				this.setConfig( 'cldString', value );
			}
		} );
	},
	setConfig( key, value ) {
		this.config[ key ] = value;
		window.localStorage.setItem(
			this.storageKey,
			JSON.stringify( this.config )
		);
	},
	saveConfig() {
		this.next.disabled = true;
		this.next.innerText = __( 'Setting up Cloudinary', 'cloudinary' );
		apiFetch( {
			path: cldData.wizard.saveURL,
			data: this.config,
			method: 'POST',
		} ).then( ( result ) => {
			this.next.innerText = __( 'Next', 'cloudinary' );
			this.next.disabled = null;
			this.getTab( 4 );
			window.localStorage.removeItem( this.storageKey );
		} );
	},
};

window.addEventListener( 'load', () => Wizard.init() );

export default Wizard;
