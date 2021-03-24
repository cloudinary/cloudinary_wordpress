/* global */

import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';
import OnOff from './onoff';

const UI = {
	bindings: {},
	parent_check_data: {},
	check_parents: {},
	_init( context ) {
		const conditions = context.querySelectorAll( '[data-condition]' );
		const toggles = context.querySelectorAll( '[data-toggle]' );
		const aliases = context.querySelectorAll( '[data-for]' );
		const tooltips = context.querySelectorAll( '[data-tooltip]' );
		const triggers = context.querySelectorAll( '[data-bind-trigger]' );
		const masters = context.querySelectorAll( '[data-master]' );
		const files = context.querySelectorAll( '[data-file]' );
		const self = this;
		const compilerDebounce = {};

		// Bind on off's.
		OnOff.bind( masters );

		triggers.forEach( ( input ) => this._trigger( input ) );
		toggles.forEach( ( toggle ) => this._toggle( toggle ) );
		conditions.forEach( ( condition ) => this._bind( condition ) );
		aliases.forEach( ( element ) => this._alias( element ) );
		files.forEach( ( file ) => this._files( file, compilerDebounce ) );
		tippy( tooltips, {
			theme: 'cloudinary',
			arrow: false,
			placement: 'bottom-start',
			aria: {
				content: 'auto',
				expanded: 'auto',
			},
			content: ( reference ) =>
				context.getElementById(
					reference.getAttribute( 'data-tooltip' )
				).innerHTML,
		} );
		[ ...triggers ].forEach( ( input ) => {
			input.dispatchEvent( new Event( 'input' ) );
		} );
	},
	_files( file, compilerDebounce ) {
		const parent = file.dataset.parent;
		if ( ! parent ) {
			return;
		}
		this.check_parents[ parent ] = document.getElementById( parent );
		if ( ! this.parent_check_data[ parent ] ) {
			this.parent_check_data[ parent ] = this.check_parents[ parent ]
				.value
				? JSON.parse( this.check_parents[ parent ].value )
				: [];
		}
		file.addEventListener( 'change', () => {
			const index = this.parent_check_data[ parent ].indexOf(
				file.value
			);
			if ( file.checked ) {
				this.parent_check_data[ parent ].push( file.value );
			} else {
				this.parent_check_data[ parent ].splice( index, 1 );
			}
			if ( compilerDebounce[ parent ] ) {
				clearTimeout( compilerDebounce[ parent ] );
			}
			compilerDebounce[ parent ] = setTimeout( () => {
				this._compileParent( parent );
			}, 10 );
		} );
	},
	_compileParent( parent ) {
		this.check_parents[ parent ].value = JSON.stringify(
			this.parent_check_data[ parent ]
		);
		this.check_parents[ parent ].dispatchEvent( new Event( 'change' ) );
	},
	_bind( element ) {
		element.condition = JSON.parse( element.dataset.condition );
		for ( const event in element.condition ) {
			if ( this.bindings[ event ] ) {
				this.bindings[ event ].elements.push( element );
			}
		}
	},
	_trigger( input ) {
		const trigger = input.dataset.bindTrigger;
		const self = this;
		self.bindings[ trigger ] = {
			input,
			value: input.value,
			checked: true,
			elements: [],
		};
		input.addEventListener( 'change', function ( ev ) {
			input.dispatchEvent( new Event( 'input' ) );
		} );
		input.addEventListener( 'input', function () {
			self.bindings[ trigger ].value = input.value;
			if ( 'checkbox' === input.type || 'radio' === input.type ) {
				self.bindings[ trigger ].checked = input.checked;
			}
			for ( const bound in self.bindings[ trigger ].elements ) {
				self.toggle(
					self.bindings[ trigger ].elements[ bound ],
					input
				);
			}
		} );
	},
	_alias( element ) {
		element.addEventListener( 'click', function () {
			const aliasOf = document.getElementById( element.dataset.for );
			aliasOf.dispatchEvent( new Event( 'click' ) );
		} );
	},
	_toggle( element ) {
		const self = this;
		element.addEventListener( 'click', function () {
			const wrap = document.querySelector(
				'[data-wrap="' + element.dataset.toggle + '"]'
			);
			const action = wrap.classList.contains( 'open' )
				? 'closed'
				: 'open';
			self.toggle( wrap, element, action );
		} );
	},
	toggle( element, trigger, action ) {
		if ( ! action ) {
			action = 'open';
			for ( const event in element.condition ) {
				let value = this.bindings[ event ].value;
				const check = element.condition[ event ];
				if ( typeof check === 'boolean' ) {
					value = this.bindings[ event ].checked;
				}
				if ( check !== value ) {
					action = 'closed';
				}
			}
		}
		const inputs = element.getElementsByClassName( 'cld-ui-input' );
		if ( 'closed' === action ) {
			element.classList.remove( 'open' );
			element.classList.add( 'closed' );
			if ( trigger && trigger.classList.contains( 'dashicons' ) ) {
				trigger.classList.remove( 'dashicons-arrow-up-alt2' );
				trigger.classList.add( 'dashicons-arrow-down-alt2' );
			}
			[ ...inputs ].forEach( function ( input ) {
				input.dataset.disabled = true;
			} );
		} else {
			element.classList.remove( 'closed' );
			element.classList.add( 'open' );
			if ( trigger && trigger.classList.contains( 'dashicons' ) ) {
				trigger.classList.remove( 'dashicons-arrow-down-alt2' );
				trigger.classList.add( 'dashicons-arrow-up-alt2' );
			}
			[ ...inputs ].forEach( function ( input ) {
				input.dataset.disabled = false;
			} );
		}
	},
};
// Init.
window.addEventListener( 'load', UI._init( document ) );

export default UI;
