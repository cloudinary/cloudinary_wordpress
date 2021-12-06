const States = {
	key: '_cld_pending_state',
	data: null,
	pending: null,
	changed: false,
	previous: {},
	init() {
		this.data = cldData.stateData ? cldData.stateData : {};
		let prevState = localStorage.getItem( this.key );
		if ( prevState ) {
			prevState = JSON.parse( prevState );
			this.data = { ...this.data, ...prevState };
			this.sendStates();
		}
		this.previous = JSON.stringify( this.data );
	},
	_update() {
		if ( this.pending ) {
			clearTimeout( this.pending );
			localStorage.removeItem( this.key );
		}
		if ( this.previous !== JSON.stringify( this.data ) ) {
			this.pending = setTimeout( () => this.sendStates(), 2000 );
			localStorage.setItem( this.key, JSON.stringify( this.data ) );
		} else {
		}
	},
	set( key, state ) {
		if ( ! this.data[ key ] || this.data[ key ] !== state ) {
			this.data[ key ] = state;
			this._update();
		}
	},
	get( key ) {
		let value = null;
		if ( this.data[ key ] ) {
			value = this.data[ key ];
		}
		return value;
	},
	sendStates() {
		fetch( cldData.stateURL, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cldData.stateNonce,
			},
			body: JSON.stringify( this.data ),
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.success ) {
					this.previous = JSON.stringify( data.state );
					localStorage.removeItem( this.key );
				} else {
				}
			} );
	},
};

export default States;
