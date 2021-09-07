/* global wpAjax */

const TermsOrder = {
	template: '',
	tags: jQuery( '#cld-tax-items' ),
	tagDelimiter:
		( window.tagsSuggestL10n && window.tagsSuggestL10n.tagDelimiter ) ||
		',',
	startId: null,
	_init() {
		// Check that we found the tax-items.
		if ( ! this.tags.length ) {
			return;
		}

		const self = this;
		this._sortable();

		// Setup ajax overrides.
		if ( typeof wpAjax !== 'undefined' ) {
			wpAjax.procesParseAjaxResponse = wpAjax.parseAjaxResponse;
			wpAjax.parseAjaxResponse = function (
				response,
				settingsResponse,
				element
			) {
				const newResponse = wpAjax.procesParseAjaxResponse(
					response,
					settingsResponse,
					element
				);
				if ( ! newResponse.errors && newResponse.responses[ 0 ] ) {
					if (
						jQuery(
							'[data-taxonomy="' +
								newResponse.responses[ 0 ].what +
								'"]'
						).length
					) {
						const data = jQuery( newResponse.responses[ 0 ].data );
						const text = data.find( 'label' ).last().text().trim();
						self._pushItem( newResponse.responses[ 0 ].what, text );
					}
				}

				return newResponse;
			};
		}

		if ( typeof window.tagBox !== 'undefined' ) {
			window.tagBox.processflushTags = window.tagBox.flushTags;
			window.tagBox.flushTags = function ( el, a, f ) {
				if ( typeof f === 'undefined' ) {
					const taxonomy = el.prop( 'id' );
					const newTag = jQuery( 'input.newtag', el );

					a = a || false;

					const text = a ? jQuery( a ).text() : newTag.val();
					const list = window.tagBox
						.clean( text )
						.split( self.tagDelimiter );

					for ( const i in list ) {
						const tag = taxonomy + ':' + list[ i ];
						if ( ! jQuery( '[data-item="' + tag + '"]' ).length ) {
							self._pushItem( tag, list[ i ] );
						}
					}
				}

				return this.processflushTags( el, a, f );
			};

			window.tagBox.processTags = window.tagBox.parseTags;

			window.tagBox.parseTags = function ( el ) {
				const id = el.id;
				const num = id.split( '-check-num-' )[ 1 ];
				const taxonomy = id.split( '-check-num-' )[ 0 ];
				const taxBox = jQuery( el ).closest( '.tagsdiv' );
				const tagsTextarea = taxBox.find( '.the-tags' );
				const tagToRemove = window.tagBox
					.clean( tagsTextarea.val() )
					.split( self.tagDelimiter )[ num ];

				new wp.api.collections.Tags()
					.fetch( { data: { slug: tagToRemove } } )
					.done( ( tag ) => {
						const tagFromDatabase = tag.length
							? jQuery(
									'[data-item="' +
										taxonomy +
										':' +
										tag[ 0 ].id +
										'"]'
							  )
							: false;

						if ( tagFromDatabase.length ) {
							tagFromDatabase.remove();
						} else {
							jQuery(
								`.cld-tax-order-list-item:contains(${ tagToRemove })`
							).remove();
							--self.startId;
						}
						this.processTags( el );
					} );
			};
		}

		jQuery( 'body' ).on( 'change', '.selectit input', function () {
			const clickedItem = jQuery( this );
			const id = clickedItem.val();
			const checked = clickedItem.is( ':checked' );
			const text = clickedItem.parent().text().trim();

			if ( true === checked ) {
				if (
					! self.tags.find( `[data-item="category:${ id }"]` ).length
				) {
					self._pushItem( `category:${ id }`, text );
				}
			} else {
				self.tags.find( `[data-item="category:${ id }"]` ).remove();
			}
		} );
	},
	_createItem( id, name ) {
		const li = jQuery( '<li/>' );
		const icon = jQuery( '<span/>' );
		const input = jQuery( '<input/>' );

		li.addClass( 'cld-tax-order-list-item' ).attr( 'data-item', id );
		input
			.addClass( 'cld-tax-order-list-item-input' )
			.attr( 'type', 'hidden' )
			.attr( 'name', 'cld_tax_order[]' )
			.val( id );
		icon.addClass(
			'dashicons dashicons-menu cld-tax-order-list-item-handle'
		);

		li.append( icon ).append( name ).append( input ); // phpcs:ignore
		// WordPressVIPMinimum.JS.HTMLExecutingFunctions.append

		return li;
	},
	_pushItem( id, text ) {
		const item = this._createItem( id, text );
		this.tags.append( item ); // phpcs:ignore
		// WordPressVIPMinimum.JS.HTMLExecutingFunctions.append
	},
	_sortable() {
		const items = jQuery( '.cld-tax-order-list' );

		items.sortable( {
			connectWith: '.cld-tax-order',
			axis: 'y',
			handle: '.cld-tax-order-list-item-handle',
			placeholder: 'cld-tax-order-list-item-placeholder',
			forcePlaceholderSize: true,
			helper: 'clone',
		} );
	},
};

if ( typeof window.CLDN !== 'undefined' ) {
	TermsOrder._init();
	// Init checked categories.
	jQuery( '[data-wp-lists] .selectit input[checked]' ).each(
		( ord, check ) => {
			jQuery( check ).trigger( 'change' );
		}
	);
}

export default TermsOrder;
