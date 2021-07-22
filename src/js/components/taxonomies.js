const toggler = document.querySelector( '.cloudinary-collapsible__toggle' );

if ( toggler ) {
	toggler.addEventListener( 'click', function () {
		const content = document.querySelector(
			'.cloudinary-collapsible__content'
		);
		const isHidden =
			window
				.getComputedStyle( content, null )
				.getPropertyValue( 'display' ) === 'none';
		const arrowIcon = document.querySelector(
			'.cloudinary-collapsible__toggle button i'
		);

		content.style.display = isHidden ? 'block' : 'none';

		const arrowDown = 'dashicons-arrow-down-alt2';
		const arrowUp = 'dashicons-arrow-up-alt2';

		if ( arrowIcon.classList.contains( arrowDown ) ) {
			arrowIcon.classList.remove( arrowDown );
			arrowIcon.classList.add( arrowUp );
		} else {
			arrowIcon.classList.remove( arrowUp );
			arrowIcon.classList.add( arrowDown );
		}
	} );
}
