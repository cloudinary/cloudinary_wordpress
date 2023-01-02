import { __ } from '@wordpress/i18n';

const TagsInput = {
	values: {},
	inputs: {},
	context: null,
	init( context ) {
		this.context = context;
		const tagsInput = context.querySelectorAll( '[data-tags]' );
		tagsInput.forEach( ( input ) => this.bind( input ) );
	},
	bind( input ) {
		input.innerText = input.dataset.placeholder;
		const id = input.dataset.tags;
		const boundInput = document.getElementById( id );
		const currentDelete = this.context.querySelectorAll(
			`[data-tags-delete="${ id }"]`
		);

		this.values[ id ] = JSON.parse( boundInput.value );
		this.inputs[ id ] = boundInput;
		input.boundInput = id;
		input.boundDisplay = this.context.querySelector(
			`[data-tags-display="${ id }"]`
		);
		input.boundDisplay.addEventListener( 'click', ( ev ) => {
			input.focus();
		} );
		input.addEventListener( 'focus', ( ev ) => {
			input.innerText = null;
		} );
		input.addEventListener( 'blur', ( ev ) => {
			if ( 3 < input.innerText.length ) {
				this.captureTag( input, input.innerText );
			}
			input.innerText = input.dataset.placeholder;
		} );
		input.addEventListener( 'keydown', ( ev ) => {
			if ( 'Tab' === ev.key ) {
				if ( 3 < input.innerText.length ) {
					ev.preventDefault();
					this.captureTag( input, input.innerText );
				}
			} else if ( 'Escape' === ev.key ) {
				input.blur();
			} else if (
				'Backspace' === ev.key &&
				0 === input.innerText.length
			) {
				const lastItem = input.boundDisplay.lastChild.previousSibling;
				if ( lastItem ) {
					lastItem.parentNode.removeChild( lastItem );
					this.values[ id ].pop();
					this.updateInput( id );
				}
			}
		} );
		input.addEventListener( 'keypress', ( ev ) => {
			if (
				'Comma' === ev.code ||
				'Enter' === ev.code ||
				'Tab' === ev.code ||
				'Space' === ev.code
			) {
				ev.preventDefault();
				if ( 3 < input.innerText.length ) {
					this.captureTag( input, input.innerText );
				}
			}
		} );

		currentDelete.forEach( ( control ) => {
			control.parentNode.control = control;
			control.parentNode.style.width = getComputedStyle(
				control.parentNode
			).width;
			control.addEventListener( 'click', ( ev ) => {
				ev.stopPropagation();
				this.deleteTag( control );
			} );
		} );
	},
	deleteTag( control ) {
		const tag = control.parentNode;
		const id = tag.dataset.inputId;
		const index = this.values[ id ].indexOf( tag.dataset.value );
		if ( 0 <= index ) {
			this.values[ id ].splice( index, 1 );
		}
		tag.style.width = 0;
		tag.style.opacity = 0;
		tag.style.padding = 0;
		tag.style.margin = 0;
		setTimeout( () => {
			tag.parentNode.removeChild( tag );
		}, 500 );

		this.updateInput( id );
	},
	captureTag( input, value ) {
		if ( this[ input.dataset.format ] ) {
			value = this[ input.dataset.format ]( value );
			if ( typeof value !== 'string' ) {
				input.classList.add( 'pulse' );
				setTimeout( () => {
					input.classList.remove( 'pulse' );
				}, 1000 );
				return;
			}
		}
		// Check if it exists.
		if ( ! this.validateUnique( input.boundDisplay, value ) ) {
			//Only add if it's new.
			const item = this.createTag( value );
			item.dataset.inputId = input.boundInput;
			this.values[ input.boundInput ].push( value );
			input.innerText = null;
			input.boundDisplay.insertBefore( item, input ); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.insertBefore
			item.style.width = getComputedStyle( item ).width;
			item.style.opacity = 1;
			this.updateInput( input.boundInput );
		}
	},
	createTag( value ) {
		const wrap = document.createElement( 'span' );
		const text = document.createElement( 'span' );
		const control = document.createElement( 'span' );

		wrap.classList.add( 'cld-input-tags-item' );
		text.classList.add( 'cld-input-tags-item-text' );
		control.className =
			'cld-input-tags-item-delete dashicons dashicons-no-alt';
		control.addEventListener( 'click', () => this.deleteTag( control ) );
		text.innerText = value;
		wrap.appendChild( text );
		wrap.appendChild( control );

		wrap.dataset.value = value;
		wrap.style.opacity = 0;

		wrap.control = control;
		return wrap;
	},
	validateUnique( display, value ) {
		const tag = display.querySelector( `[data-value="${ value }"]` );
		let exists = false;
		if ( tag ) {
			tag.classList.remove( 'pulse' );
			tag.classList.add( 'pulse' );
			setTimeout( () => {
				tag.classList.remove( 'pulse' );
			}, 500 );
			exists = true;
		}
		return exists;
	},
	updateInput( id ) {
		this.inputs[ id ].value = JSON.stringify( this.values[ id ] );
	},
	host( value ) {
		const isUrl =
			/^(?:http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)/.test(
				value
			);
		if ( false === isUrl ) {
			value = 'https://' + value;
		}
		let url = '';
		try {
			url = new URL( value );
		} catch ( e ) {
			return e;
		}

		return decodeURIComponent( url.host );
	},
};

export default TagsInput;
