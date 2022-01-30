/* eslint-disable prettier/prettier */
const AColorPicker = require( 'a-color-picker' );

const ColorPicker = {
	pickers: null,
	_init( context ) {
		this.pickers = context.getElementsByClassName( 'cld-input-color-picker' );
		[ ...this.pickers ].forEach( ( picker ) => {
			const input = document.getElementById( picker.dataset.id );
			const container = document.getElementById( picker.dataset.id + '_container' );
			const preview = document.getElementById( picker.dataset.id + '_preview' );
			const defaultButton = document.getElementById( picker.dataset.id + '_default' );
			const options = {
				attachTo: preview,
			};

			const pickerInstance = AColorPicker.createPicker( picker, options );
			pickerInstance.on( 'change', ( { color } ) => {
				const rgba = AColorPicker.parseColor( color, "rgbcss4")
				preview.style.backgroundColor = rgba;
				input.value = rgba;
				input.dispatchEvent( new Event( 'input' ) );
			} );
			pickerInstance.hide();

			container.addEventListener( 'click', () => {
				this.togglePicker( pickerInstance, container );
			} );
			input.addEventListener( 'input', ( ev ) => {
				preview.style.backgroundColor = ev.target.value;
				pickerInstance.setColor( ev.target.value, true );
			} );
			defaultButton.addEventListener( 'click', () => {
				pickerInstance.setColor( defaultButton.dataset.defaultColor );
			} );
			document.addEventListener( 'mousedown', ( ev ) => {
				if ( -1 === ev.path.indexOf( picker ) && -1 === ev.path.indexOf( container ) && -1 === ev.path.indexOf( input ) && -1 === ev.path.indexOf( defaultButton ) ) {
					this.hidePicker( pickerInstance, container );
				}
			} );
			window.addEventListener( 'keydown', ( ev ) => {
				if ( 'Escape' === ev.key ) {
					this.hidePicker( pickerInstance, container );
				}
			} );
		} );
	},
	togglePicker( pickerInstance, container ) {
		if ( ! container.parentNode.classList.contains( 'focus' ) ) {
			this.showPicker( pickerInstance, container );
		} else {
			this.hidePicker( pickerInstance, container );
		}
	},
	showPicker( pickerInstance, container ) {
		container.parentNode.classList.add( 'focus' );
		pickerInstance.show();
	},
	hidePicker( pickerInstance, container ) {
		container.parentNode.classList.remove( 'focus' );
		pickerInstance.hide();
	},
	setColor( color ) {
		this.color.value = color;
		this.colorPreview.style.backgroundColor = color;
		this.color.dispatchEvent( new Event( 'input' ) );
	},
};

export default ColorPicker;
