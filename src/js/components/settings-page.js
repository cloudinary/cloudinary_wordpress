( function () {
	const __ =
		window.wp && window.wp.i18n && window.wp.i18n.__
			? window.wp.i18n.__
			: ( text ) => text;
	const UNSAVED_CHANGES_MESSAGE = __(
		'You have unsaved changes. If you leave this page, your changes will be lost.',
		'cloudinary'
	);

	const isTrackableField = function ( field ) {
		if ( ! field || ! field.name || field.disabled ) {
			return false;
		}

		if (
			'_wp_http_referer' === field.name ||
			'_wpnonce' === field.name ||
			'option_page' === field.name ||
			'action' === field.name ||
			'tab' === field.name
		) {
			return false;
		}

		return ! [ 'button', 'submit', 'reset', 'image', 'file' ].includes(
			field.type
		);
	};

	const serializeFormState = function ( form ) {
		const state = {};
		const fields = Array.from(
			form.querySelectorAll( 'input, select, textarea' )
		).filter( isTrackableField );

		fields.forEach( ( field ) => {
			const key = field.name;
			let value = field.value;

			if ( 'checkbox' === field.type ) {
				value = field.checked ? field.value : '__unchecked__';
			} else if ( 'radio' === field.type && ! field.checked ) {
				value = '__unchecked__';
			}

			if ( ! Object.prototype.hasOwnProperty.call( state, key ) ) {
				state[ key ] = [];
			}

			state[ key ].push( String( value ) );
		} );

		Object.keys( state ).forEach( ( key ) => {
			state[ key ] = state[ key ].sort();
		} );

		return JSON.stringify( state );
	};

	const getSaveButtons = function ( form ) {
		// Only select the actual settings save buttons by their name attribute.
		// Deliberately excludes other button-primary buttons (e.g. Disconnect)
		// that share the same form but are not save actions.
		const named = Array.from(
			form.querySelectorAll( 'button[name="cld_submission"]' )
		);
		if ( named.length ) {
			return named;
		}
		// Fallback for options.php–style forms that use a standard submit input.
		const legacy = form.querySelector( '#submit, input[type="submit"]' );
		return legacy ? [ legacy ] : [];
	};

	const getButtonLabel = function ( button ) {
		return 'input' === button.tagName.toLowerCase()
			? button.value
			: button.textContent.trim();
	};

	const setButtonLabel = function ( button, label ) {
		if ( 'input' === button.tagName.toLowerCase() ) {
			button.value = label;
		} else {
			button.textContent = label;
		}
	};

	const createDirtyTracker = function ( form ) {
		const saveButtons = getSaveButtons( form );
		const originalLabels = new Map(
			saveButtons.map( ( btn ) => [ btn, getButtonLabel( btn ) ] )
		);
		const tracker = {
			form,
			saveButtons,
			initialState: serializeFormState( form ),
			isDirty: false,
			allowNavigation: false,
		};

		const syncButtonState = function () {
			tracker.saveButtons.forEach( function ( btn ) {
				if ( tracker.isDirty ) {
					btn.classList.add( 'cld-settings-submit-dirty' );
					setButtonLabel( btn, originalLabels.get( btn ) + ' *' );
				} else {
					btn.classList.remove( 'cld-settings-submit-dirty' );
					setButtonLabel( btn, originalLabels.get( btn ) );
				}
			} );
		};

		const updateDirtyState = function () {
			if ( tracker.allowNavigation ) {
				return;
			}

			tracker.isDirty =
				serializeFormState( tracker.form ) !== tracker.initialState;
			tracker.form.classList.toggle(
				'cld-settings-form-has-unsaved',
				tracker.isDirty
			);
			syncButtonState();
		};

		tracker.form.addEventListener( 'input', function ( event ) {
			if ( ! isTrackableField( event.target ) ) {
				return;
			}

			updateDirtyState();
		} );
		tracker.form.addEventListener( 'change', function ( event ) {
			if ( ! isTrackableField( event.target ) ) {
				return;
			}

			updateDirtyState();
		} );
		tracker.form.addEventListener( 'submit', function () {
			tracker.allowNavigation = true;
			tracker.isDirty = false;
			tracker.form.classList.remove( 'cld-settings-form-has-unsaved' );
			tracker.saveButtons.forEach( function ( btn ) {
				btn.classList.remove( 'cld-settings-submit-dirty' );
				setButtonLabel( btn, originalLabels.get( btn ) );
			} );
		} );

		return tracker;
	};

	const initUnsavedChangesGuards = function () {
		const forms = Array.from(
			document.querySelectorAll(
				'form[data-cld-settings-form="true"], #cloudinary-settings-page form.render-trigger, #cloudinary-settings-page form[action*="options.php"], #cloudinary-settings-page form'
			)
		)
			.filter(
				( form, index, list ) =>
					list.findIndex( ( candidate ) => candidate === form ) ===
					index
			)
			.filter( ( form ) =>
				Boolean(
					form.querySelector(
						'input[name="cloudinary-active-slug"], input[name="option_page"]'
					)
				)
			);
		if ( ! forms.length ) {
			return;
		}

		const trackers = forms.map( createDirtyTracker );
		const hasUnsavedChanges = function () {
			return trackers.some(
				( tracker ) => tracker.isDirty && ! tracker.allowNavigation
			);
		};
		const unlockNavigation = function () {
			trackers.forEach( ( tracker ) => {
				tracker.allowNavigation = true;
			} );
		};

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! hasUnsavedChanges() ) {
				return;
			}

			event.preventDefault();
			event.returnValue = UNSAVED_CHANGES_MESSAGE;
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! hasUnsavedChanges() ) {
				return;
			}

			const link = event.target.closest( 'a[href]' );
			if ( ! link || ! link.href ) {
				return;
			}

			if (
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}

			const href = link.getAttribute( 'href' );
			if (
				'_blank' === link.target ||
				link.hasAttribute( 'download' ) ||
				! href ||
				href.startsWith( '#' ) ||
				href.startsWith( 'javascript:' )
			) {
				return;
			}

			if ( ! window.confirm( UNSAVED_CHANGES_MESSAGE ) ) {
				event.preventDefault();
				event.stopPropagation();
				return;
			}

			unlockNavigation();
		} );
	};

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

						target.on(
							'change init',
							function ( _, isInit = false ) {
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
							}
						);

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

		window.setTimeout( initUnsavedChangesGuards, 0 );
	} );
} )( window, jQuery );
