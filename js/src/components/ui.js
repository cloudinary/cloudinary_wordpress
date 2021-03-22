/* global */

import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';

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
		const evaluateStateDebounce = {};
		[ ...triggers ].forEach( ( input ) => self._trigger( input ) );
		[ ...masters ].forEach( ( input ) =>
			self._master( input, evaluateStateDebounce )
		);
		[ ...toggles ].forEach( ( toggle ) => self._toggle( toggle ) );
		[ ...conditions ].forEach( ( condition ) => self._bind( condition ) );
		[ ...aliases ].forEach( ( element ) => self._alias( element ) );
		[ ...files ].forEach( ( file ) =>
			this._files( file, compilerDebounce )
		);
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
		const parentInput = document.getElementById( parent );
		this.check_parents[ parent ] = parentInput;
		if ( ! this.parent_check_data[ parent ] ) {
			this.parent_check_data[ parent ] = [];
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
			}, 2500 );
		} );
	},
	_compileParent( parent ) {
		this.check_parents[ parent ].value = JSON.stringify(
			this.parent_check_data[ parent ]
		);
		this.check_parents[ parent ].dispatchEvent( new Event( 'change' ) );
	},
	_master( input, evaluateStateDebounce ) {
		const masters = JSON.parse( input.dataset.master );
		input.elements = [];
		input.checked_items = [];
		input.partials = [];
		if ( masters.length ) {
			[ ...masters ].forEach( ( masterFor ) => {
				const item = document.getElementById( masterFor );
				if ( ! item || item.disabled ) {
					return; // invalid.
				}
				if ( ! item.masters ) {
					item.masters = [];
					// we only need a single event listener.
					item.addEventListener( 'change', ( ev ) => {
						const itemInput = ev.target;
						const checked = ev.target.checked;
						[ ...itemInput.masters ].forEach( ( master ) => {
							this._checkReport( itemInput, checked, master );
							// tell master to evaluate.
							if ( evaluateStateDebounce[ master.id ] ) {
								clearTimeout(
									evaluateStateDebounce[ master.id ]
								);
							}
							evaluateStateDebounce[ master.id ] = setTimeout(
								() => {
									this._evaluateState( master );
								},
								20
							);
						} );
					} );
				}
				if ( -1 === item.masters.indexOf( input ) ) {
					//console.log( item.masters );
					item.masters.push( input );
					input.elements.push( item );
					if ( item.checked ) {
						input.checked_items.push( item );
					}
				}
			} );
			if ( 0 === input.elements.length ) {
				input.disabled = true;
				return;
			}
			input.addEventListener( 'change', function ( ev ) {
				let checked = ev.target.checked;
				[ ...input.elements ].forEach( ( child ) => {
					if ( child.disabled ) {
						checked = false;
					}
					child.checked = checked;
					child.dispatchEvent( new Event( 'change' ) );
				} );
			} );

			this._evaluateState( input );
		}
	},
	_checkReport( item, checked, master ) {
		const index = master.checked_items.indexOf( item );
		const partialIndex = master.partials.indexOf( item );

		if ( item.disabled ) {
			const elementsIndex = master.elements.indexOf( item );
			master.elements.splice( elementsIndex, 1 );
		}

		if ( item.classList.contains( 'partial' ) && -1 === partialIndex ) {
			// set partial if is partial.
			master.partials.push( item );
		} else if (
			! item.classList.contains( 'partial' ) &&
			-1 < partialIndex
		) {
			// Remove from partials.
			master.partials.splice( partialIndex, 1 );
			//console.log( partialIndex );
		}

		if ( checked && -1 === index ) {
			// add to list.
			master.checked_items.push( item );
		} else if ( ! checked && -1 < index ) {
			// Remove from checked.
			master.checked_items.splice( index, 1 );
		}
	},
	_evaluateState( input ) {
		let checked = input.checked;

		if ( 0 < input.partials.length ) {
			checked = true;
			input.classList.add( 'partial' );
		} else if ( input.checked_items.length === input.elements.length ) {
			checked = true;
			input.classList.remove( 'partial' );
		} else if ( 0 === input.checked_items.length ) {
			checked = false;
			input.classList.remove( 'partial' );
		} else {
			checked = true;
			input.classList.add( 'partial' );
		}
		if ( input.masters ) {
			[ ...input.masters ].forEach( ( master ) => {
				this._checkReport( input, checked, master );
				this._evaluateState( master );
			} );
		}
		if ( input.checked !== checked ) {
			input.checked = checked;
			input.dispatchEvent( new Event( 'input' ) );
		}
	},
	_bind( element ) {
		const self = this;
		element.condition = JSON.parse( element.dataset.condition );
		for ( const event in element.condition ) {
			if ( this.bindings[ event ] ) {
				this.bindings[ event ].elements.push( element );
			}
		}
		window.addEventListener( 'sconditional_event', function ( ev ) {
			const trigger = ev.detail.dataset.bindTrigger;
			if ( element.condition[ trigger ] ) {
				self.toggle( element, ev.detail );
			}
		} );
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
		//	console.log( trigger );
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
