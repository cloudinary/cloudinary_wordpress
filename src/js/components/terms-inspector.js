/**
 * WordPress dependencies
 */
import { select, subscribe } from '@wordpress/data';

const Terms = {
	wrapper: null,
	query: {
		per_page: -1,
		orderby: 'name',
		order: 'asc',
		_fields: 'id,name,parent',
		context: 'view',
	},
	available: {},
	_init() {
		this.wrapper = document.getElementById( 'cld-tax-items' );
		setTimeout( () => {
			this._init_listeners();
		}, 3000 );
	},
	_init_listeners() {
		const taxonomies = select( 'core' ).getTaxonomies();
		taxonomies.forEach( ( taxonomy ) => {
			if ( ! taxonomy.rest_base || ! taxonomy.visibility.public ) {
				return;
			}
			subscribe( () => {
				const slug = taxonomy.slug;
				const hierarchical = taxonomy.hierarchical;
				const { isResolving } = select( 'core/data' );
				const args = [ 'taxonomy', slug, this.query ];
				this.available[ slug ] = null;
				if ( hierarchical ) {
					this.available[ slug ] = select( 'core' ).getEntityRecords(
						'taxonomy',
						slug,
						this.query
					);
				}
				if ( ! isResolving( 'core', 'getEntityRecords', args ) ) {
					this.event( taxonomy );
				}
			} );
		} );
	},
	event( taxonomy ) {
		const hasSelection = select( 'core/editor' ).getEditedPostAttribute(
			taxonomy.rest_base
		);
		if ( ! hasSelection ) {
			return;
		}

		const selection = [ ...hasSelection ];
		const selected = Array.from(
			this.wrapper.querySelectorAll( `[data-item*="${ taxonomy.slug }"]` )
		);

		// Go over the selection and add new items it doesn't have.
		[ ...selection ].forEach( ( item ) => {
			const element = this.wrapper.querySelector(
				`[data-item="${ taxonomy.slug }:${ item }"]`
			);
			// Remove the items out of the selected list.
			selected.splice( selected.indexOf( element ), 1 );
			if ( null === element ) {
				// Create one that isin't in the list.
				this.createItem( this.getItem( taxonomy, item ) );
			}
		} );
		//  If there are any items in the selected list, we remove them
		//  since they are not in the selection, as we removed them
		//  previously..
		selected.forEach( ( element ) => {
			element.parentNode.removeChild( element );
		} );
	},
	createItem( item ) {
		if ( ! item || ! item.id ) {
			return;
		}
		const li = document.createElement( 'li' );
		const icon = document.createElement( 'span' );
		const input = document.createElement( 'input' );
		const name = document.createTextNode( item.name );

		li.classList.add( 'cld-tax-order-list-item' );
		li.dataset.item = `${ item.taxonomy }:${ item.id }`;

		input.classList.add( 'cld-tax-order-list-item-input' );
		input.type = 'hidden';
		input.name = 'cld_tax_order[]';
		input.value = `${ item.taxonomy }:${ item.id }`;

		icon.className =
			'dashicons dashicons-menu' + ' cld-tax-order-list-item-handle';

		li.appendChild( icon );
		li.appendChild( input );
		li.appendChild( name );
		this.wrapper.appendChild( li );
	},
	getItem( taxonomy, id ) {
		let term = {};
		if ( null === this.available[ taxonomy.slug ] ) {
			// Get term from data.
			term = select( 'core' ).getEntityRecord(
				'taxonomy',
				taxonomy.slug,
				id
			);
		} else {
			for ( const item of this.available[ taxonomy.slug ] ) {
				if ( item.id === id ) {
					term = item;
					term.taxonomy = taxonomy.slug;
					break;
				}
			}
		}
		return term;
	},
};

window.addEventListener( 'load', () => Terms._init() );

export default Terms;
