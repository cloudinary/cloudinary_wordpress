const SuffixValue = {
	suffixInputs: null,
	init( context ) {
		this.suffixInputs = context.querySelectorAll( '[data-suffix]' );
		[ ...this.suffixInputs ].forEach( ( input ) =>
			this.bindInput( input )
		);
	},
	bindInput( input ) {
		const suffix = document.getElementById( input.dataset.suffix );
		const template = suffix.dataset.template.split( '@value' );
		this.setSuffix( suffix, template, input.value );
		input.addEventListener( 'change', () =>
			this.setSuffix( suffix, template, input.value )
		);
		input.addEventListener( 'input', () =>
			this.setSuffix( suffix, template, input.value )
		);
	},
	setSuffix( suffix, template, value ) {
		const hidden = [ 'none', 'off', '' ];
		suffix.innerHTML = '';
		suffix.classList.add( 'hidden' );
		if ( -1 === hidden.indexOf( value ) ) {
			suffix.classList.remove( 'hidden' );
		}
		const text = document.createTextNode( template.join( value ) );
		suffix.appendChild( text );
	},
};
export default SuffixValue;
