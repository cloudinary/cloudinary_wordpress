/* global CLD_Deactivate */

const Deactivate = {
	// The link that triggers the ThickBox
	modal: document.getElementById( 'cloudinary-deactivation' ),
	modalBody: document.getElementById( 'modal-body' ),
	modalFooter: document.getElementById( 'modal-footer' ),
	modalUninstall: document.getElementById( 'modal-uninstall' ),
	modalClose: document.querySelectorAll( 'button[data-action="cancel"], button[data-action="close"]' ),
	// The different links to deactivate the plugin.
	pluginListLinks: document.querySelectorAll(
		'.cld-deactivate-link, .cld-deactivate'
	),
	// The deactivation links when Cloudinary only is set for storage.
	triggers: document.getElementsByClassName( 'cld-deactivate' ),
	// The reasons.
	options: document.querySelectorAll(
		'.cloudinary-deactivation .reasons input[type="radio"]'
	),
	report: document.getElementById( 'cld-report' ),
	contact: document.getElementById( 'cld-contact' ),
	// The feedback submit button.
	submitButton: document.querySelectorAll(
		'.cloudinary-deactivation button[data-action="submit"]'
	),
	// The contact me button.
	contactButton: document.querySelectorAll(
		'.cloudinary-deactivation button[data-action="contact"]'
	),
	// The deactivate button.
	deactivateButton: document.querySelectorAll(
		'.cloudinary-deactivation button[data-action="deactivate"]'
	),
	// The email field.
	emailField: document.getElementById( 'email' ),
	// Selected reason.
	reason: '',
	// The more details .
	more: null,
	// The deactivation link for the plugin.
	deactivationUrl: '',
	// The contact me email.
	email: '',

	addEvents() {
		const context = this;

		[...context.modalClose].forEach( ( button ) => {
			button.addEventListener( 'click', ( ev ) => {
				context.closeModal();
			} );
		} );

		window.addEventListener( 'keyup', ( ev ) => {
			if ( 'visible' === context.modal.style.visibility && 'Escape' === ev.key ) {
				context.modal.style.visibility = 'hidden';
				context.modal.style.opacity = '0';
			}
		} );

		context.modal.addEventListener( 'click', ( ev ) => {
			ev.stopPropagation();
			if ( ev.target === context.modal ) {
				context.closeModal();
			}
		} );

		// Add event listener to deactivation links to add the pop up.
		[ ...context.pluginListLinks ].forEach( ( link ) => {
			link.addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				context.deactivationUrl = ev.target.getAttribute( 'href' );
				context.openModal();
			} );
		} );

		// Add it a trigger watch to stop deactivation.
		[ ...context.triggers ].forEach( ( trigger ) => {
			trigger.addEventListener( 'click', function( ev ) {
				if (
					! confirm(
						wp.i18n.__(
							'Caution: Your storage setting is currently set to "Cloudinary only", disabling the plugin will result in broken links to media assets. Are you sure you want to continue?',
							'cloudinary'
						)
					)
				) {
					ev.preventDefault();
					// Close the feedback form.
					context.closeModal();
				}
			} );
		} );

		[ ...context.contactButton ].forEach( ( button ) => {
			button.addEventListener( 'click', function () {
				if ( context.emailField ) {
					context.email = context.emailField.value;
				}
				context.submit();
			} );
		} );

		[ ...context.deactivateButton ].forEach( ( button ) => {
			button.addEventListener( 'click', function () {
				window.location.href = context.deactivationUrl;
			} );
		} );

		// Add event listener to update reason and more container.
		[ ...context.options ].forEach( ( option ) => {
			option.addEventListener( 'change', function( ev ) {
				context.reason = ev.target.value;
				context.more = ev.target.parentNode.querySelector( 'textarea' );
			} );
		} );

		// Allowing Cloudinary contact should include the System Report.
		if ( context.contact ) {
			context.report.addEventListener( 'change', function () {
				if ( context.report.checked ) {
					context.contact.parentNode.removeAttribute( 'style' );
				} else {
					context.contact.parentNode.style.display = 'none';
				}
			} );
		}

		// Add event listener to submit the feedback.
		[ ...context.submitButton ].forEach( ( button ) => {
			button.addEventListener( 'click', function() {
				const option = document.querySelector(
					'.cloudinary-deactivation .data input[name="option"]:checked' );
				let value = '';

				if ( option ) {
					value = option.value;
				}

				if ( 'uninstall' === value ) {
					context.modalBody.style.display = 'none';
					context.modalFooter.style.display = 'none';
					context.modalUninstall.style.display = 'block';
				}

				context.submit( value );
			} );
		} );
	},
	closeModal() {
		document.body.style.removeProperty('overflow');
		this.modal.style.visibility = 'hidden';
		this.modal.style.opacity = '0';
	},
	openModal() {
		document.body.style.overflow = 'hidden';
		this.modal.style.visibility = 'visible';
		this.modal.style.opacity = '1';
	},
	submit( dataHandling = '' ) {
		wp.ajax
			.send( {
				url: CLD_Deactivate.endpoint,
				data: {
					reason: this.reason,
					more: this.more?.value,
					report: this.report?.checked,
					contact: this.contact?.checked,
					email: this.email,
					dataHandling
				},
				beforeSend( request ) {
					request.setRequestHeader(
						'X-WP-Nonce',
						CLD_Deactivate.nonce
					);
				},
			} )
			.always( function() {
				window.location.reload();
			} );
	},
	/**
	 * Init method.
	 */
	init() {
		this.addEvents();
	},
};

Deactivate.init();

export default Deactivate;
