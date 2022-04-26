/* global */

import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';
import OnOff from './onoff';
import Progress from './progress';
import States from './states';
import RestrictedTypes from './restricted-types';
import TagsInput from './tags-input';
import SuffixValue from './suffix-value';

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
		const mains = context.querySelectorAll( '[data-main]' );
		const files = context.querySelectorAll( '[data-file]' );
		const autoSuffix = context.querySelectorAll( '[data-auto-suffix]' );
		const confirms = context.querySelectorAll( '[data-confirm]' );
		const self = this;
		const compilerDebounce = {};
		// Init states.
		States.init();

		// Bind on offs.
		OnOff.bind( mains );
		autoSuffix.forEach( ( input ) => this._autoSuffix( input ) );
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
				document.getElementById( reference.dataset.tooltip ).innerHTML,
		} );
		[ ...triggers ].forEach( ( input ) => {
			input.dispatchEvent( new Event( 'input' ) );
		} );

		confirms.forEach( ( item ) => {
			item.addEventListener( 'click', ( ev ) => {
				if ( ! confirm( item.dataset.confirm ) ) {
					ev.preventDefault();
					ev.stopPropagation();
				}
			} );
		} );
		// Start cache manager.
		Progress.init( context );
		RestrictedTypes.init( context );
		TagsInput.init( context );
		SuffixValue.init( context );
	},
	_autoSuffix( input ) {
		const suffixes = input.dataset.autoSuffix;
		let defaultSuffix = '';
		const valid = [ ...suffixes.split( ';' ) ].map( ( suffix ) => {
			if ( 0 === suffix.indexOf( '*' ) ) {
				defaultSuffix = suffix.replace( '*', '' );
				return defaultSuffix;
			}
			return suffix;
		} );
		input.addEventListener( 'change', () => {
			const value = input.value.replace( ' ', '' );
			const number = value.replace( /[^0-9]/g, '' );
			const type = value.replace( /[0-9]/g, '' ).toLowerCase();
			if ( number ) {
				if ( -1 === valid.indexOf( type ) ) {
					input.value = number + defaultSuffix;
				} else {
					input.value = number + type;
				}
			}
		} );
		input.dispatchEvent( new Event( 'change' ) );
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
			if ( 'checkbox' === input.type ) {
				self.bindings[ trigger ].checked = input.checked;
			}
			if ( 'radio' === input.type && false === input.checked ) {
				return; // Ignore an unchecked radio.
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
		const wrap = document.querySelector(
			'[data-wrap="' + element.dataset.toggle + '"]'
		);
		if ( ! wrap ) {
			return;
		}
		const state = States.get( element.id );
		element.addEventListener( 'click', function ( ev ) {
			ev.stopPropagation();
			const action = wrap.classList.contains( 'open' )
				? 'closed'
				: 'open';
			self.toggle( wrap, element, action );
		} );
		if ( state !== element.dataset.state ) {
			this.toggle( wrap, element, state );
		}
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

		if ( 'closed' === action ) {
			this.close( element, trigger );
		} else {
			this.open( element, trigger );
		}
		States.set( trigger.id, action );
	},
	open( element, trigger ) {
		const inputs = element.getElementsByClassName( 'cld-ui-input' );
		element.classList.remove( 'closed' );
		element.classList.add( 'open' );
		if ( trigger && trigger.classList.contains( 'dashicons' ) ) {
			trigger.classList.remove( 'dashicons-arrow-down-alt2' );
			trigger.classList.add( 'dashicons-arrow-up-alt2' );
		}
		[ ...inputs ].forEach( function ( input ) {
			input.dataset.disabled = false;
		} );
	},
	close( element, trigger ) {
		const inputs = element.getElementsByClassName( 'cld-ui-input' );
		element.classList.remove( 'open' );
		element.classList.add( 'closed' );
		if ( trigger && trigger.classList.contains( 'dashicons' ) ) {
			trigger.classList.remove( 'dashicons-arrow-up-alt2' );
			trigger.classList.add( 'dashicons-arrow-down-alt2' );
		}
		[ ...inputs ].forEach( function ( input ) {
			input.dataset.disabled = true;
		} );
	},
};

const context = document.getElementById( 'cloudinary-settings-page' );

if ( context ) {
	// Init.
	window.addEventListener( 'load', UI._init( context ) );
}

export default UI;
