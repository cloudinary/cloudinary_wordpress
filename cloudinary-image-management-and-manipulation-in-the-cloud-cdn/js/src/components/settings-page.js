(function() {

	// Disable the "off" dropdown option for Autoplay if
	// the player isn't set to Cloudinary or if Show Controls if unchecked.
	const disableAutoplayOff = function() {
		const player = jQuery( '#field-video_player' ).val();
		const showControls = jQuery( '#field-video_controls' ).prop( 'checked' );
		const offSelection = jQuery( '#field-video_autoplay_mode option[value="off"]' );

		if ( player === 'cld' && ! showControls ) {
			offSelection.prop( 'disabled', true );
			if ( offSelection.prop( 'selected' ) ) {
				offSelection.next().prop( 'selected', true );
			}
		} else {
			offSelection.prop( 'disabled', false );
		}
	}

	disableAutoplayOff();
	jQuery( document ).on( 'change', '#field-video_player', disableAutoplayOff );
	jQuery( document ).on( 'change', '#field-video_controls', disableAutoplayOff );

	jQuery( document ).ready( function( $ ) {

		// Initilize instance events
		$( document ).on( 'tabs.init', function() {

			var tabs     = $( '.settings-tab-trigger' ),
			    sections = $( '.settings-tab-section' );

			// Create instance bindings
			$( this ).on( 'click', '.settings-tab-trigger', function( e ) {
				var clicked = $( this ),
				    target  = $( clicked.attr( 'href' ) );

				// Trigger an instance action.
				e.preventDefault();

				tabs.removeClass( 'active' );
				sections.removeClass( 'active' );

				clicked.addClass( 'active' );
				target.addClass( 'active' );

				// Trigger the tabbed event.
				$( document ).trigger( 'settings.tabbed', clicked );

			} );

			// Bind conditions.
			$( '.cld-field' ).not( '[data-condition="false"]' ).each( function() {
				const field     = $(this);
				const condition = field.data('condition');
				for (let f in condition) {
					const value = condition[ f ];
					const target = $( '#field-' + f );
					const wrapper = field.closest('tr');
					target.on('change init', function(){
						if( this.value === value || this.checked ){
							wrapper.show();
						}else{
							wrapper.hide();
						}
					});
					target.trigger('init');
				}
			} );

			$('#field-cloudinary_url').on('input change', function(){
				let field = $(this),
					value = field.val();

				let reg = new RegExp(/^(?:CLOUDINARY_URL=)?(cloudinary:\/\/){1}(\d)*[:]{1}[^:@]*[@]{1}[^@]*$/g );
				if( reg.test( value ) ){
					field.addClass( 'settings-valid-field' );
					field.removeClass( 'settings-invalid-field' );
				}else{
					field.removeClass( 'settings-valid-field' );
					field.addClass( 'settings-invalid-field' );
				}
			}).trigger('change');

			$('[name="cloudinary_sync_media[auto_sync]"]').change(function () {
				if ($(this).val() === 'on') $('#auto-sync-alert-btn').click();
			});


		} );

		// On Ready, find all render trigger elements and fire their events.
		$( '.render-trigger[data-event]' ).each( function() {
			var trigger = $( this ),
			    event   = trigger.data( 'event' );
			trigger.trigger( event, this );
		} );
	} );

})( window, jQuery );
