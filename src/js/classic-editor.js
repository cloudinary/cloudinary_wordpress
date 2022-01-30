/* global */
import TermsInspector from './components/terms-inspector';

const ClassicEditor = {
	...TermsInspector,
	initTimeout: 10,
	interval: 100,
	selection: {},
	tagDelimiter: wp.i18n._x( ',', 'tag delimiter' ) || ',',
	getTaxonomies() {
		return cldData.taxonomies;
	},
	addTaxonomyListener( taxonomy ) {
		const catBox = document.getElementById( `taxonomy-${ taxonomy.slug }` );
		const tagBox = document.getElementById( `tax-input-${ taxonomy.slug }` );
		this.selection[ taxonomy.slug ] = {
			items: 0,
			selected: 0,
		};

		// Run the watch.
		setInterval( () => {
			if ( catBox ) {
				this.watchCatBox( catBox, taxonomy );
			}
			if ( tagBox ) {
				this.watchTagBox( tagBox, taxonomy );
			}
		}, this.interval );
	},
	watchCatBox( catBox, taxonomy ) {
		const checks = catBox.querySelectorAll( 'input[type="checkbox"]' );
		const checked = Array.from( checks ).filter( check => true === check.checked );
		if ( this.selection[ taxonomy.slug ].items !== checks.length || this.selection[ taxonomy.slug ].selected !== checked.length ) {
			checks.forEach( ( checkbox ) => {
				const hasItem = this.wrapper.querySelector( `[data-item="${ taxonomy.slug }:${ checkbox.value }"]` );
				if ( checkbox.checked && ! hasItem ) {
					const term = {
						id: checkbox.value,
						name: checkbox.parentNode.innerText.trim(),
						taxonomy: taxonomy.slug,
					};
					this.createItem( term );
				} else if ( ! checkbox.checked && hasItem ) {
					hasItem.parentNode.removeChild( hasItem );
				}
			} );
			this.selection[ taxonomy.slug ].items = checks.length;
			this.selection[ taxonomy.slug ].selected = checked.length;
		}
	},
	watchTagBox( tagBox, taxonomy ) {
		const cleaned = window.tagBox.clean( tagBox.value );
		if ( this.selection[ taxonomy.slug ].items !== cleaned ) {
			const newItems = cleaned.split( this.tagDelimiter );
			const oldItems = this.wrapper.querySelectorAll( `[data-item^=${ taxonomy.slug }\\:]` );

			oldItems.forEach( ( item ) => {
				const tag = item.dataset.item.split( ':' )[ 1 ];
				const index = newItems.indexOf( tag );
				if ( -1 === index ) {
					item.parentNode.removeChild( item );
				} else {
					newItems.splice( index, 1 );
				}
			} );
			if ( newItems.length ) {
				newItems.forEach( ( item ) => {
					const term = {
						id: item,
						name: item,
						taxonomy: taxonomy.slug,
					};
					this.createItem( term );
				} );
			}
			this.selection[ taxonomy.slug ].items = cleaned;
		}

	},
};
if ( typeof window.CLDN !== 'undefined' ) {
	ClassicEditor._init();
}

export default ClassicEditor;
