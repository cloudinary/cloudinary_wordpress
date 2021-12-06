import { __ } from '@wordpress/i18n';

const CloudinaryModal = {
	modal: null,
	body: null,
	preview: null,
	editor: null,
	closeCallbacks: [],
	init( id ) {
		this._createModal( id );
		return this;
	},
	_createModal( id ) {
		// Make modal.
		this.modal = document.createElement( 'div' );
		this.modal.id = id;
		this.modal.classList.add( 'cld-modal', 'modal-body' );
		// Add modal to document body.
		document.body.appendChild( this.modal );

		// Make Body.
		this.body = document.createElement( 'div' );
		this.body.style.transition = 'opacity 0.5s';
		this.body.style.opacity = 1;
		this.body.style.position = 'relative';

		// Make internal box.
		const box = document.createElement( 'div' );
		box.classList.add( 'cld-modal-box' );
		box.appendChild( this.body );
		// Add box to modal.
		this.modal.appendChild( box );

		// Bind events.
		this._bindEvents();
	},
	_bindEvents( input ) {
		// Add escape button closing.
		document.addEventListener( 'keydown', ( ev ) => {
			if ( 'Escape' === ev.key ) {
				this.closeModal();
			}
		} );
		// Add backdrop click close.
		this.modal.addEventListener( 'click', ( e ) => {
			if ( e.target === this.modal ) {
				// Check direct target.
				this.closeModal();
			}
		} );
	},
	appendContent( content ) {
		this.body.appendChild( content );
	},
	openModal() {
		this.modal.style.visibility = 'visible';
		this.modal.style.opacity = 1;
		document.body.style.overflow = 'hidden';
	},
	closeModal() {
		this.modal.style.visibility = 'hidden';
		this.modal.style.opacity = 0;
		document.body.style.overflow = 'initial';
		this.closeCallbacks.forEach( ( callback ) => callback( this ) );
	},
	onClose( callback ) {
		this.closeCallbacks.push( callback );
	},
};

export default CloudinaryModal;
