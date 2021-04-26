import filesize from 'file-size';

const OnOff = {
	controlled: null,
	bind( inputs ) {
		this.controlled = inputs;
		this.controlled.forEach( ( input ) => {
			this._master( input );
		} );
		this._init();
	},
	_init() {
		this.controlled.forEach( ( input ) => {
			this._checkUp( input );
		} );
	},
	_master( input ) {
		const masters = JSON.parse( input.dataset.master );
		if ( input.dataset.size ) {
			input.filesize = parseInt( input.dataset.size, 10 );
		}
		input.masters = masters.map( ( master ) => {
			const masterElement = document.getElementById( master );
			const sizespan = document.getElementById(
				master + '_size_wrapper'
			);
			if ( sizespan ) {
				masterElement.filesize = 0;
				masterElement.sizespan = sizespan;
			}
			this._addChild( masterElement, input );
			return masterElement;
		} );

		this._bindEvents( input );

		input.masters.forEach( ( master ) => {
			this._bindEvents( master );
		} );
	},
	_bindEvents( input ) {
		if ( ! input.eventBound ) {
			input.addEventListener( 'click', ( ev ) => {
				const target = ev.target;
				if ( target.elements ) {
					this._checkDown( target );
					this._evaluateSize( target );
				}
				if ( target.masters ) {
					this._checkUp( input );
				}
			} );
			input.eventBound = true;
		}
	},
	_addChild( input, child ) {
		const children = input.elements ? input.elements : [];
		if ( -1 === children.indexOf( child ) ) {
			children.push( child );
			input.elements = children;
		}
	},
	_removeChild( input, child ) {
		const index = input.elements.indexOf( child );
		if ( -1 < index ) {
			input.elements.splice( index, 1 );
		}
	},
	_checkDown( input ) {
		if ( input.elements ) {
			input.classList.remove( 'partial' );
			input.elements.forEach( ( child ) => {
				if ( child.checked !== input.checked ) {
					child.checked = input.checked;
					if ( child.disabled ) {
						child.checked = false;
					}
					child.dispatchEvent( new Event( 'change' ) );
				}
			} );
			input.elements.forEach( ( child ) => {
				this._checkDown( child );
				if ( ! child.elements ) {
					// Checkup.
					this._checkUp( child, input );
				}
			} );
		}
	},
	_checkUp( input, exclude ) {
		if ( input.masters ) {
			[ ...input.masters ].forEach( ( master ) => {
				if ( master !== exclude ) {
					this._evaluateCheckStatus( master );
				}
				this._checkUp( master );
				this._evaluateSize( master );
			} );
		}
	},
	_evaluateCheckStatus( input ) {
		let sum = 0;
		let isPartial = input.classList.contains( 'partial' );
		if ( isPartial ) {
			input.classList.remove( 'partial' );
			isPartial = false;
		}
		input.elements.forEach( ( child ) => {
			if ( null === child.parentNode ) {
				this._removeChild( input, child );
				return;
			}
			sum += child.checked;
			if ( child.classList.contains( 'partial' ) ) {
				isPartial = true;
			}
		} );
		let value = 'some';
		if ( sum === input.elements.length ) {
			value = 'on';
		} else if ( 0 === sum ) {
			value = 'off';
		} else {
			isPartial = true;
		}
		if ( isPartial ) {
			input.classList.add( 'partial' );
		}

		const newCheck = 'off' !== value;
		if ( input.checked !== newCheck || input.value !== value ) {
			input.value = value;
			input.checked = newCheck;
			input.dispatchEvent( new Event( 'change' ) );
		}
	},
	_evaluateSize( input ) {
		if ( input.sizespan && input.elements ) {
			input.filesize = 0;
			input.elements.forEach( ( child ) => {
				if ( child.checked ) {
					input.filesize += child.filesize;
				}
			} );
			let size = null;
			if ( 0 < input.filesize ) {
				size = filesize( input.filesize, {
					spacer: ' ',
				} ).human( 'jedec' );
			}
			input.sizespan.innerText = size;
		}
	},
};

export default OnOff;
