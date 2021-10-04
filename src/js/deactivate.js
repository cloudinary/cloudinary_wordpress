/* global CLD_Deactivate */

const Deactivate = {
	// The link that triggers the ThickBox
	modal: document.getElementById( 'cloudinary-deactivation' ),
	modalBody: document.getElementById( 'modal-body' ),
	modalFooter: document.getElementById( 'modal-footer' ),
	modalUninstall: document.getElementById( 'modal-uninstall' ),
	modalClose: document.getElementsByClassName( 'cancel-close' ),
	// The different links to deactivate the plugin.
	pluginListLinks: document.querySelectorAll(
		'.cld-deactivate-link, .cld-deactivate'
	),
	// The deactivation links when Cloudinary only is set for storage.
	triggers: document.getElementsByClassName( 'cld-deactivate' ),
	// The reasons.
	options: document.querySelectorAll(
		'.cloudinary-deactivation input[type="radio"]:checked'
	),
	//report: document.getElementById( 'cld-report' ),
	//contact: document.getElementById( 'cld-contact' ).parentNode,
	// The feedback submit button.
	submitButton: document.querySelector(
		'.cloudinary-deactivation .button-primary'
	),
	// The skip button.
	//skipButton: document.querySelector(
	//	'.cloudinary-deactivation .button-link'
	//),
	// Selected reason.
	reason: '',
	// The more details .
	more: null,
	// The deactivation link for the plugin.
	deactivationUrl: '',

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
					document.getElementById( 'TB_closeWindowButton' ).click();
				}
			} );
		} );

		// Add event listener to skip feedback.
		//context.skipButton.addEventListener( 'click', function () {
		//	window.location.href = context.deactivationUrl;
		//} );

		// Add event listener to update reason and more container.
		[ ...context.options ].forEach( ( option ) => {
			option.addEventListener( 'change', function( ev ) {
				context.submitButton.removeAttribute( 'disabled' );
				context.reason = ev.target.value;
				context.more = ev.target.parentNode.querySelector( 'textarea' );
			} );
		} );

		// Allowing Cloudinary contact should include the System Report.
		/*context.report.addEventListener( 'change', function () {
		 if ( context.report.checked ) {
		 context.contact.removeAttribute( 'style' );
		 } else {
		 context.contact.style.display = 'none';
		 }
		 } );*/

		// Add event listener to submit the feedback.
		context.submitButton.addEventListener( 'click', function() {
			const option = document.querySelector(
				'.cloudinary-deactivation input[name="option"]:checked' );

			if ( 'uninstall' === option.value ) {
				context.modalBody.style.display = 'none';
				context.modalFooter.style.display = 'none';
				context.modalUninstall.style.display = 'block';

				context.uninstall();
			} else{
				window.location.href = context.deactivationUrl;
			}
		} );
	},
	closeModal() {
		this.modal.style.visibility = 'hidden';
		this.modal.style.opacity = '0';
	},
	openModal() {
		this.modal.style.visibility = 'visible';
		this.modal.style.opacity = '1';
	},
	uninstall() {

	},
	submit() {
		wp.ajax
			.send( {
				url: CLD_Deactivate.endpoint,
				data: {
					reason: context.reason,
					more: context.more?.value,
					//report: context.report.checked,
					//contact: context.contact.checked,
				},
				beforeSend( request ) {
					request.setRequestHeader(
						'X-WP-Nonce',
						CLD_Deactivate.nonce
					);
				},
			} )
			.always( function() {
				window.location.href = context.deactivationUrl;
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
