import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Analytics from './analytics';

const Wizard = {
	storageKey: '_cld_wizard',
	testing: null,
	connectAttempts: 0,
	startedEntry: false,
	startedTracked: false,
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
	updateConnection: document.getElementById( 'update-connection' ),
	cancelUpdateConnection: document.getElementById(
		'cancel-update-connection'
	),
	config: {},
	didSave: false,
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

		this.updateConnection.addEventListener( 'click', () => {
			this.lockNext();
			connectionInput.parentNode.classList.remove( 'hidden' );
			this.cancelUpdateConnection.classList.remove( 'hidden' );
			this.updateConnection.classList.add( 'hidden' );
		} );

		this.cancelUpdateConnection.addEventListener( 'click', () => {
			this.unlockNext();
			connectionInput.parentNode.classList.add( 'hidden' );
			this.cancelUpdateConnection.classList.add( 'hidden' );
			this.updateConnection.classList.remove( 'hidden' );
			this.config.cldString = true;
			connectionInput.value = '';
			this.connection.error.classList.remove( 'active' );
			this.connection.success.classList.add( 'active' );
		} );

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
			this.lockNext();
			if ( ! this.startedEntry ) {
				this.startedEntry = true;
				Analytics.track(
					'credentials_entry_started',
					{},
					'activation_funnel',
					3
				);
			}
			const value = connectionInput.value.replace(
				'CLOUDINARY_URL=',
				''
			);
			this.connection.error.classList.remove( 'active' );
			this.connection.success.classList.remove( 'active' );
			this.connection.working.classList.remove( 'active' );
			if ( value.length ) {
				this.testing = value;
				if ( this.debounceConnect ) {
					clearTimeout( this.debounceConnect );
				}

				this.debounceConnect = setTimeout( () => {
					const valid = this.evaluateConnectionString( value );
					Analytics.track(
						'credentials_format_validated',
						{
							format_valid: valid,
							invalid_reason: valid
								? ''
								: this.invalidReason( value ),
						},
						'activation_funnel',
						3
					);
					if ( valid ) {
						this.connection.working.classList.add( 'active' );
						this.testConnection( value );
					} else {
						this.connection.error.classList.add( 'active' );
					}
				}, 500 );
			}
		} );

		if ( this.config.cldString ) {
			connectionInput.parentNode.classList.add( 'hidden' );
			this.updateConnection.classList.remove( 'hidden' );
		}

		const signup = document.querySelector(
			'a[href="https://cloudinary.com/signup"]'
		);
		if ( signup ) {
			signup.addEventListener( 'click', () => {
				Analytics.track(
					'wizard_signup_clicked',
					{},
					'activation_funnel',
					2
				);
			} );
		}

		if ( this.complete ) {
			this.complete.addEventListener( 'click', () => {
				Analytics.track(
					'wizard_dashboard_clicked',
					{},
					'activation_funnel',
					7
				);
			} );
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
		const toggled = ( settingKey, enabled ) => {
			Analytics.track(
				'wizard_setting_toggled',
				{ setting_key: settingKey, enabled },
				'activation_funnel',
				4
			);
		};
		const mediaCheck = document.getElementById( 'media_library' );
		mediaCheck.checked = this.config.mediaLibrary;
		mediaCheck.addEventListener( 'change', () => {
			this.setConfig( 'mediaLibrary', mediaCheck.checked );
			toggled( 'media_library', mediaCheck.checked );
		} );
		const nonMediaCheck = document.getElementById( 'non_media' );
		nonMediaCheck.checked = this.config.nonMedia;
		nonMediaCheck.addEventListener( 'change', () => {
			this.setConfig( 'nonMedia', nonMediaCheck.checked );
			toggled( 'non_media', nonMediaCheck.checked );
		} );
		const advanced = document.getElementById( 'advanced' );
		advanced.checked = this.config.advanced;
		advanced.addEventListener( 'change', () => {
			this.setConfig( 'advanced', advanced.checked );
			toggled( 'advanced', advanced.checked );
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
		if (
			4 === tab &&
			window.localStorage.getItem( this.storageKey ) &&
			! this.didSave
		) {
			// Place a save and wait, before moving.
			this.saveConfig();
			return;
		}

		const current = this.getCurrent();
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
				this.unlockNext();
				if ( ! this.startedTracked ) {
					this.startedTracked = true;
					if ( ! this.config.wizardStartedAt ) {
						this.setConfig( 'wizardStartedAt', Date.now() );
					}
					Analytics.track(
						'wizard_started',
						{ entry_point: this.getEntryPoint() },
						'activation_funnel',
						2
					);
				}
				break;
			case 2:
				Analytics.track(
					'wizard_connect_viewed',
					{},
					'activation_funnel',
					3
				);
				this.show( this.back );
				if ( ! this.config.cldString ) {
					this.lockNext();
					setTimeout( () => {
						document
							.getElementById( 'connect.cloudinary_url' )
							.focus();
					}, 0 );
				} else {
					this.showSuccess();
				}
				if ( this.updateConnection.classList.contains( 'hidden' ) ) {
					this.lockNext();
				}
				break;
			case 3:
				if ( ! this.config.cldString ) {
					document.location.hash = '1';
					return;
				}
				Analytics.track(
					'wizard_settings_viewed',
					{},
					'activation_funnel',
					4
				);
				this.show( this.lock );
				this.show( this.back );
				break;
			case 4:
				if ( ! this.config.cldString ) {
					document.location.hash = '1';
					return;
				}
				Analytics.track(
					'wizard_completed',
					{ time_to_complete_sec: this.timeToCompleteSec() },
					'activation_funnel',
					6
				);
				this.hide( this.tabBar );
				this.hide( this.next );
				this.hide( this.back );
				break;
		}
		this.setConfig( 'tab', tab );
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
	showError() {
		this.connection.error.classList.add( 'active' );
		this.connection.success.classList.remove( 'active' );
	},
	showSuccess() {
		this.connection.error.classList.remove( 'active' );
		this.connection.success.classList.add( 'active' );
	},
	show( item ) {
		item.classList.remove( 'hidden' );
		item.style.display = '';
	},
	hide( item ) {
		item.classList.add( 'hidden' );
		item.style.display = 'none';
	},
	lockNext() {
		this.next.disabled = 'disabled';
	},
	unlockNext() {
		this.next.disabled = '';
	},
	evaluateConnectionString( value ) {
		const reg = new RegExp(
			/^(?:CLOUDINARY_URL=)?(cloudinary:\/\/){1}(\d*)[:]{1}([^@]*)[@]{1}([^@]*)$/gim
		);
		return reg.test( value );
	},
	// Best-effort reason a connection string failed local format validation
	// (the only local check is the regex above). For credential-friction analysis.
	invalidReason( value ) {
		const str = value.replace( 'CLOUDINARY_URL=', '' );
		if ( 0 !== str.indexOf( 'cloudinary://' ) ) {
			return 'missing_scheme';
		}
		if ( -1 === str.indexOf( '@' ) ) {
			return 'missing_cloud_name';
		}
		const creds = str.replace( 'cloudinary://', '' ).split( '@' )[ 0 ];
		if ( -1 === creds.indexOf( ':' ) ) {
			return 'missing_secret';
		}
		if ( ! /^\d+$/.test( creds.split( ':' )[ 0 ] ) ) {
			return 'invalid_api_key';
		}
		return 'invalid_format';
	},
	// auto_redirect when WordPress bounced the admin here post-activation,
	// otherwise menu navigation. Heuristic, based on the referrer.
	getEntryPoint() {
		return -1 !== document.referrer.indexOf( 'plugins.php' )
			? 'auto_redirect'
			: 'menu';
	},
	// Seconds from wizard_started (persisted in config) to completion.
	timeToCompleteSec() {
		const start = this.config.wizardStartedAt;
		if ( ! start ) {
			return null;
		}
		return Math.max( 0, Math.round( ( Date.now() - start ) / 1000 ) );
	},
	testConnection( value ) {
		this.connectAttempts += 1;
		Analytics.track(
			'connection_test_started',
			{ attempt_number: this.connectAttempts },
			'activation_funnel',
			3
		);
		apiFetch( {
			path: cldData.wizard.testURL,
			data: {
				cloudinary_url: value,
				attempt_number: this.connectAttempts,
			},
			method: 'POST',
		} ).then( ( result ) => {
			if ( result.url === this.testing ) {
				// Only handle the one that started the request.

				this.connection.working.classList.remove( 'active' );
				if ( 'connection_error' === result.type ) {
					this.showError();
				} else if ( 'connection_success' === result.type ) {
					this.showSuccess();
					this.unlockNext();
					this.setConfig( 'cldString', value );
				}
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
		this.lockNext();
		this.next.innerText = __( 'Setting up Cloudinary', 'cloudinary' );
		this.didSave = true;

		apiFetch( {
			path: cldData.wizard.saveURL,
			data: this.config,
			method: 'POST',
		} )
			.then( ( result ) => {
				this.next.innerText = __( 'Next', 'cloudinary' );
				this.unlockNext();
				this.getTab( 4 );
				window.localStorage.removeItem( this.storageKey );
			} )
			.fail( ( error ) => {
				this.didSave = false;
			} );
	},
};

window.addEventListener( 'load', () => Wizard.init() );

export default Wizard;
