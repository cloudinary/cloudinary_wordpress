const SizePreview = {
	triggers: null,
	previews: {},
	panels: {},
	allowed: {
		c: [ 'fill', 'scale', 'crop', 'thumb' ],
		g: [ 'auto', 'center', 'face', 'faces', 'bad' ],
	},
	init( context ) {
		this.triggers = context.querySelectorAll(
			'.cld-size-items-item[data-size]'
		);
		const panels = context.querySelectorAll( '.image-item[data-size]' );
		[ ...panels ].forEach( ( panel ) => {
			const size = panel.dataset.size;
			const preview = panel.querySelector( `[data-size="${ size }"]` );
			const input = panel.querySelector( 'input' );
			this.panels[ size ] = panel;
			this.previews[ size ] = preview;
			input.addEventListener( 'input', ( ev ) => {
				input.classList.remove( 'invalid' );
				preview.classList.remove( 'error' );
				if ( input.value.length && ! this.validate( input.value ) ) {
					input.classList.add( 'invalid' );
				} else {
					this.rebuild( preview, input.value.replace( / /g, '' ) );
				}
			} );
		} );
		this.triggers.forEach( ( trigger ) => {
			trigger.addEventListener( 'click', ( ev ) =>
				this.triggerPanel( trigger )
			);
		} );

		if ( this.triggers[ 0 ] ) {
			this.triggerPanel( this.triggers[ 0 ] );
		}
	},
	triggerPanel( trigger ) {
		const size = trigger.dataset.size;
		if ( this.panels[ size ] ) {
			this.show( this.panels[ size ] );
			this.select( trigger );
		}
	},
	show( panel ) {
		this.hideAll();
		panel.classList.add( 'show' );
	},
	hide( panel ) {
		panel.classList.remove( 'show' );
	},
	hideAll() {
		for ( const size in this.panels ) {
			this.hide( this.panels[ size ] );
		}
	},
	unselect( trigger ) {
		trigger.classList.remove( 'selected' );
	},
	select( trigger ) {
		this.triggers.forEach( this.unselect );
		trigger.classList.add( 'selected' );
	},
	validate( input ) {
		const parts = input.replace( / /g, '' ).split( ',' );
		const invalids = [];
		parts.forEach( ( part ) => {
			const sub = part.split( '_' );
			if (
				! this.allowed[ sub[ 0 ] ] ||
				-1 === this.allowed[ sub[ 0 ] ].indexOf( sub[ 1 ] )
			) {
				invalids.push( part );
			}
		} );
		if ( invalids.length ) {
			return false;
		}
		return true;
	},
	rebuild( preview, transformations ) {
		if ( ! transformations.length ) {
			transformations = 'c_fill';
		}
		const base = preview.dataset.base;
		const file = preview.dataset.file;
		const baseTransform = JSON.parse( preview.dataset.transformations );
		baseTransform.crop = transformations;
		const url = `${ base }${ Object.values( baseTransform ).join(
			','
		) }${ file }`;
		const src = `url("${ url }")`;
		if ( preview.style.backgroundImage !== src ) {
			this.testUrl( url, preview );
		}
	},
	testUrl( url, preview ) {
		const testImg = document.createElement( 'img' );
		testImg.addEventListener( 'error', () => {
			preview.classList.add( 'error' );
		} );
		testImg.addEventListener( 'load', () => {
			preview.style.backgroundImage = `url("${ url }")`;
		} );
		testImg.src = url;
	},
};

export default SizePreview;
