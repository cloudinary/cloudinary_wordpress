/**
 * WordPress dependencies
 */
import { select, subscribe } from '@wordpress/data';
import Sortable from 'sortablejs';

const TermsInspector = {
	initTimeout: 3000,
	wrapper: null,
	/**
	 * Leverage the existing Gutenberg query to get the taxonomies.
	 *
	 * arguments: https://github.com/WordPress/gutenberg/blob/3f9968e2815cfb56684c1acc9a2700d8e4a02726/packages/editor/src/components/post-taxonomies/hierarchical-term-selector.js#L32-L38
	 * query: https://github.com/WordPress/gutenberg/blob/3f9968e2815cfb56684c1acc9a2700d8e4a02726/packages/editor/src/components/post-taxonomies/hierarchical-term-selector.js#L214
	 */
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
		// At the given time, not enough options are available to detect when core requests are ready.
		setTimeout( () => {
			this._init_listeners();
		}, this.initTimeout );

		new Sortable( this.wrapper, {
			handle: '.dashicons-menu', // handle's class
			animation: 150,
		} );
	},
	_init_listeners() {
		const taxonomies = this.getTaxonomies();

		taxonomies.forEach( ( taxonomy ) => {
			if ( ! taxonomy.rest_base || ! taxonomy.visibility.public ) {
				return;
			}
			this.addTaxonomyListener( taxonomy );
		} );
	},
	getTaxonomies() {
		return select( 'core' ).getTaxonomies();
	},
	addTaxonomyListener( taxonomy ) {
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
	},
	getSelection( taxonomy ) {
		return select( 'core/editor' ).getEditedPostAttribute(
			taxonomy.rest_base
		);
	},
	getTerm( taxonomy, id ) {
		return select( 'core' ).getEntityRecord(
			'taxonomy',
			taxonomy.slug,
			id
		);
	},
	event( taxonomy ) {
		const hasSelection = this.getSelection( taxonomy );
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
		let term = {
			id,
			name: id,
			taxonomy: taxonomy.slug,
		};
		if ( null === this.available[ taxonomy.slug ] ) {
			// Get term from data.
			term = this.getTerm( taxonomy, id );
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

export default TermsInspector;
