const Storage = {
	select: document.getElementById( 'connect.offload' ),
	tooltip: null,
	descriptions: {},
	change() {
		[ ...this.descriptions ].forEach( ( li ) => {
			li.classList.remove( 'selected' );
		} );

		this.tooltip
			.querySelector( '.' + this.select.value )
			.classList.add( 'selected' );
	},
	addEventListener() {
		this.select.addEventListener( 'change', this.change.bind( this ) );
	},
	_init() {
		if ( this.select ) {
			this.addEventListener();
			this.tooltip = this.select.parentNode.querySelector(
				'.cld-tooltip'
			);
			this.descriptions = this.tooltip.querySelectorAll( 'li' );
			this.change();
		}
	},
};
window.addEventListener( 'load', () => Storage._init() );

export default Storage;
