( function () {
	// Disable the "off" dropdown option for Autoplay if
	// the player isn't set to Cloudinary or if Show Controls if unchecked.
	const disableAutoplayOff = function () {
		const player = jQuery( '#field-video_player' ).val();
		const showControls = jQuery( '#field-video_controls' ).prop(
			'checked'
		);
		const offSelection = jQuery(
			'#field-video_autoplay_mode option[value="off"]'
		);

		if ( player === 'cld' && ! showControls ) {
			offSelection.prop( 'disabled', true );
			if ( offSelection.prop( 'selected' ) ) {
				offSelection.next().prop( 'selected', true );
			}
		} else {
			offSelection.prop( 'disabled', false );
		}
	};

	disableAutoplayOff();
	jQuery( document ).on(
		'change',
		'#field-video_player',
		disableAutoplayOff
	);
	jQuery( document ).on(
		'change',
		'#field-video_controls',
		disableAutoplayOff
	);

	jQuery( document ).ready( function ( $ ) {
		if ( $.isFunction( $.fn.wpColorPicker ) ) {
			$( '.regular-color' ).wpColorPicker();
		}

		// Initilize instance events
		$( document ).on( 'tabs.init', function () {
			const tabs = $( '.settings-tab-trigger' ),
				sections = $( '.settings-tab-section' );

			// Create instance bindings
			$( this ).on( 'click', '.settings-tab-trigger', function ( e ) {
				const clicked = $( this ),
					target = $( clicked.attr( 'href' ) );

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
			$( '.cld-field' )
				.not( '[data-condition="false"]' )
				.each( function () {
					const field = $( this );
					const condition = field.data( 'condition' );

					for ( const f in condition ) {
						let target = $( '#field-' + f );
						const value = condition[ f ];
						const wrapper = field.closest( 'tr' );

						if ( ! target.length ) {
							target = $( `[id^=field-${ f }-]` );
						}

						let fieldIsSet = false;

						target.on( 'change init', function (
							_,
							isInit = false
						) {
							if ( fieldIsSet && isInit ) {
								return;
							}

							let fieldCondition =
								this.value === value || this.checked;

							if (
								Array.isArray( value ) &&
								value.length === 2
							) {
								switch ( value[ 1 ] ) {
									case 'neq':
										fieldCondition =
											this.value !== value[ 0 ];
										break;
									case 'gt':
										fieldCondition =
											this.value > value[ 0 ];
										break;
									case 'lt':
										fieldCondition =
											this.value < value[ 0 ];
								}
							}

							if ( fieldCondition ) {
								wrapper.show();
							} else {
								wrapper.hide();
							}

							fieldIsSet = true;
						} );

						target.trigger( 'init', true );
					}
				} );

			$( '#field-cloudinary_url' )
				.on( 'input change', function () {
					const field = $( this ),
						value = field.val();

					const reg = new RegExp(
						/^(?:CLOUDINARY_URL=)?(cloudinary:\/\/){1}(\d)*[:]{1}[^:@]*[@]{1}[^@]*$/g
					);
					if ( reg.test( value ) ) {
						field.addClass( 'settings-valid-field' );
						field.removeClass( 'settings-invalid-field' );
					} else {
						field.removeClass( 'settings-valid-field' );
						field.addClass( 'settings-invalid-field' );
					}
				} )
				.trigger( 'change' );

			$( '[name="cloudinary_sync_media[auto_sync]"]' ).change(
				function () {
					if ( $( this ).val() === 'on' ) {
						$( '#auto-sync-alert-btn' ).click();
					}
				}
			);
		} );

		// On Ready, find all render trigger elements and fire their events.
		$( '.render-trigger[data-event]' ).each( function () {
			const trigger = $( this ),
				event = trigger.data( 'event' );
			trigger.trigger( event, this );
		} );
	} );
} )( window, jQuery );
