/* eslint-disable prettier/prettier */
const AColorPicker = require( 'a-color-picker' );

const ColorPicker = {
	pickers: null,
	_init( context ) {
		this.pickers = context.getElementsByClassName( 'cld-input-color-picker' );
		[ ...this.pickers ].forEach( ( picker ) => {
			const input = document.getElementById( picker.dataset.id );
			const preview = document.getElementById( picker.dataset.id + '_preview' );

			const pickerInstance = AColorPicker.createPicker( picker, { attachTo: preview } );
			pickerInstance.on( 'change', ( { color } ) => {
				preview.value = color;
				preview.style.backgroundColor = color;
				input.value = color;
				input.dispatchEvent( new Event( 'input' ) );
			} );
			pickerInstance.hide();

			preview.addEventListener( 'click', () => {
				if ( 'hidden' === input.type ) {
					this.showPicker( pickerInstance, input );
				} else {
					this.hidePicker( pickerInstance, input );
				}
			} );
			input.addEventListener( 'input', ( ev ) => {
				pickerInstance.setColor( ev.target.value, true );
			} );
			document.addEventListener( 'mousedown', ( ev ) => {
				if ( -1 === ev.path.indexOf( picker ) && -1 === ev.path.indexOf( preview ) && ev.path.indexOf( input ) ) {
					this.hidePicker( pickerInstance, input );
				}
			} );
			window.addEventListener( 'keydown', ( ev ) => {
				if ( 'Escape' === ev.key ) {
					this.hidePicker( pickerInstance, input );
				}
			} );
		} );

	},
	showPicker( pickerInstance, input ) {
		if ( 'text' !== input.type ) {
			input.type = 'text';
			pickerInstance.show();
		}
	},
	hidePicker( pickerInstance, input ) {
		if ( 'hidden' !== input.type ) {
			input.type = 'hidden';
			pickerInstance.hide();
		}
	},
	setColor( color ) {
		this.color.value = color;
		this.colorPreview.style.backgroundColor = color;
		this.color.dispatchEvent( new Event( 'input' ) );
	},
};

export default ColorPicker;
