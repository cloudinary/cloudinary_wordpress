const togglers = document.querySelectorAll( '.cloudinary-collapsible__toggle' );

togglers.forEach( function ( toggler ) {
	toggler.addEventListener( 'click', function () {
		const targetId = toggler.dataset.collapsibleTarget;
		if ( ! targetId ) {
			return;
		}

		const content = document.getElementById( targetId );
		if ( ! content ) {
			return;
		}

		// Toggle the content visibility.
		content.hidden = ! content.hidden;

		const button = toggler.querySelector( 'button' );
		const arrowIcon = toggler.querySelector( 'button i' );

		if ( button ) {
			button.setAttribute( 'aria-expanded', String( ! content.hidden ) );
		}

		if ( arrowIcon ) {
			arrowIcon.classList.toggle(
				'dashicons-arrow-down-alt2',
				content.hidden
			);
			arrowIcon.classList.toggle(
				'dashicons-arrow-up-alt2',
				! content.hidden
			);
		}
	} );
} );
