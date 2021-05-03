import apiFetch from '@wordpress/api-fetch';
import OnOff from './onoff';

const CacheManage = {
	cachePoints: {},
	spinners: {},
	init( context ) {
		if ( typeof CLDCACHE !== 'undefined' ) {
			apiFetch.use( apiFetch.createNonceMiddleware( CLDCACHE.nonce ) );
			const cachePoints = context.querySelectorAll(
				'[data-cache-point]'
			);
			cachePoints.forEach( ( cachePoint ) => this._bind( cachePoint ) );
		}
	},
	getCachePoint( ID ) {
		return this.cachePoints[ '_' + ID ]
			? this.cachePoints[ '_' + ID ]
			: null;
	},
	setCachePoint( ID, cachePoint ) {
		const paginate = document.createElement( 'div' );
		const loader = this._getRow();
		const loaderTd = document.createElement( 'td' );
		loaderTd.colSpan = 2;
		loaderTd.className = 'cld-loading';
		loader.appendChild( loaderTd );
		const master = document.getElementById( cachePoint.dataset.slug );
		const search = document.getElementById(
			cachePoint.dataset.slug + '_search'
		);
		const reload = document.getElementById(
			cachePoint.dataset.slug + '_reload'
		);
		const controller = document.getElementById(
			cachePoint.dataset.browser
		);
		const apply = document.getElementById( cachePoint.dataset.apply );
		controller.addEventListener( 'change', ( ev ) => {
			this._handleManager( ID );
		} );
		window.addEventListener( 'CacheToggle', ( ev ) => {
			if ( ev.detail.cachePoint === cachePoint ) {
				this._cacheChange( cachePoint, ev.detail );
			}
		} );
		apply.addEventListener( 'click', ( ev ) => {
			this._applyChanges( cachePoint );
		} );
		reload.addEventListener( 'click', ( ev ) => {
			this._load( ID );
		} );
		search.addEventListener( 'keydown', ( ev ) => {
			if ( 13 === ev.which ) {
				ev.preventDefault();
				ev.stopPropagation();
				this._load( ID );
			}
		} );
		paginate.className = 'cld-pagenav';
		apply.cacheChanges = {
			disable: [],
			enable: [],
			delete: [],
		};
		cachePoint.master = master;
		cachePoint.search = search;
		cachePoint.controller = controller;
		cachePoint.viewer = cachePoint.parentNode.parentNode;
		cachePoint.loader = loader;
		cachePoint.table = cachePoint.parentNode;
		cachePoint.apply = apply;
		cachePoint.paginate = paginate;
		cachePoint.currentPage = 1;
		cachePoint.viewer.appendChild( paginate );

		this.cachePoints[ '_' + ID ] = cachePoint;
	},
	close( element ) {
		element.classList.add( 'closed' );
	},
	open( element ) {
		element.classList.remove( 'closed' );
	},
	isOpen( ID ) {
		const cachePoint = this.getCachePoint( ID );
		let open = false;
		if ( cachePoint ) {
			open = cachePoint.controller.checked && cachePoint.master.checked;
		}
		return open;
	},
	_bind( cachePoint ) {
		const cachePointID = cachePoint.dataset.cachePoint;
		this.setCachePoint( cachePointID, cachePoint );

		// initial load.
		this._handleManager( cachePointID );
	},
	_handleManager( ID ) {
		const cachePoint = this.getCachePoint( ID );
		if ( ! cachePoint ) {
			return;
		}
		if ( this.isOpen( ID ) ) {
			this.open( cachePoint.viewer );
			if ( ! cachePoint.loaded ) {
				this._load( ID );
			}
		} else {
			this.close( cachePoint.viewer );
			cachePoint.controller.checked = false;
		}
	},
	_load( ID ) {
		const cachePoint = this.getCachePoint( ID );
		this._clearChildren( cachePoint );
		cachePoint.appendChild( cachePoint.loader );
		this.open( cachePoint.loader );
		apiFetch( {
			path: CLDCACHE.fetch_url,
			data: {
				ID,
				page: cachePoint.currentPage,
				search: cachePoint.search.value,
			},
			method: 'POST',
		} ).then( ( result ) => {
			cachePoint.removeChild( cachePoint.loader );
			this._buildList( cachePoint, result.items );
			this._buildNav( cachePoint, result );
			const masters = cachePoint.querySelectorAll( '[data-master]' );
			OnOff.bind( masters );
			cachePoint.loaded = true;
		} );
	},
	_cacheChange( cachePoint, data ) {
		const stateTo = data.checked ? data.states.on : data.states.off;
		const stateFrom = data.checked ? data.states.off : data.states.on;
		if ( ! this._removeFromList( cachePoint, data.item.ID, stateFrom ) ) {
			this._addToList( cachePoint, data.item.ID, stateTo );
		}
		this._evaluateApply( cachePoint );
	},
	_evaluateApply( cachePoint ) {
		this.close( cachePoint.apply );
		const lists = cachePoint.apply.cacheChanges;
		let show = false;
		for ( const state in lists ) {
			if ( lists[ state ].length ) {
				show = true;
			}
		}
		if ( show ) {
			this.open( cachePoint.apply );
		}
	},
	_applyChanges( cachePoint ) {
		const lists = cachePoint.apply.cacheChanges;
		cachePoint.apply.disabled = 'disabled';
		for ( const state in lists ) {
			if ( lists[ state ].length ) {
				this._set_state( cachePoint, state, lists[ state ] );
			}
		}
	},
	_set_state( cachePoint, state, ids ) {
		this._showSpinners( ids );
		apiFetch( {
			path: CLDCACHE.update_url,
			data: {
				state,
				ids,
			},
			method: 'POST',
		} ).then( ( result ) => {
			this._hideSpinners( result );
			result.forEach( ( id ) => {
				this.close( cachePoint.apply );
				this._removeFromList( cachePoint, id, state );
				this._evaluateApply( cachePoint );
				cachePoint.apply.disabled = '';
			} );
			if ( 'delete' === state ) {
				this._load( cachePoint.dataset.cachePoint );
			}
		} );
	},
	_purgeCache( cachePoint ) {
		apiFetch( {
			path: CLDCACHE.purge_url,
			data: {
				cachePoint: cachePoint.dataset.cachePoint,
			},
			method: 'POST',
		} ).then( () => {
			this._load( cachePoint.dataset.cachePoint );
		} );
	},
	_showSpinners( ids ) {
		ids.forEach( ( id ) => {
			this.spinners[ 'spinner_' + id ].style.visibility = 'visible';
		} );
	},
	_hideSpinners( ids ) {
		ids.forEach( ( id ) => {
			this.spinners[ 'spinner_' + id ].style.visibility = 'hidden';
		} );
	},
	_removeFromList( cachePoint, ID, state ) {
		const index = this._getListIndex( cachePoint, ID, state );
		let removed = false;
		if ( -1 < index ) {
			cachePoint.apply.cacheChanges[ state ].splice( index, 1 );
			removed = true;
		}
		return removed;
	},
	_addToList( cachePoint, ID, state ) {
		const index = this._getListIndex( cachePoint, ID, state );
		if ( -1 === index ) {
			cachePoint.apply.cacheChanges[ state ].push( ID );
		}
	},
	_getListIndex( cachePoint, ID, state ) {
		return cachePoint.apply.cacheChanges[ state ].indexOf( ID );
	},
	_noCache( cachePoint ) {
		const note = this._getNote(
			wp.i18n.__( 'No files cached.', 'cloudinary' )
		);
		cachePoint.viewer.appendChild( note );
		this.close( cachePoint.table );
	},
	_clearChildren( element ) {
		while ( element.children.length ) {
			const el = element.lastChild;
			if ( el.children.length ) {
				this._clearChildren( el );
			}

			element.removeChild( el );
		}
	},
	_buildList( cachePoint, cachedItems ) {
		cachedItems.forEach( ( item ) => {
			if ( item.note ) {
				cachePoint.appendChild( this._getNote( item.note ) );
				return;
			}
			const row = this._getRow( item.ID );
			const statSwitch = this._getStateSwitch( cachePoint, item, {
				on: 'enable',
				off: 'disable',
			} );
			const file = this._getFile( cachePoint, item, row );
			row.appendChild( file );
			row.appendChild( statSwitch );
			cachePoint.appendChild( row );
		} );
	},
	_buildNav( cachePoint, result ) {
		cachePoint.paginate.innerHTML = '';
		const left = document.createElement( 'button' );
		const right = document.createElement( 'button' );

		if ( result.items.length ) {
			const purge = document.createElement( 'button' );
			purge.type = 'button';
			purge.className = 'button';
			purge.innerText = wp.i18n.__( 'Purge cache point', 'cloudinary' );
			purge.style.float = 'left';
			cachePoint.paginate.appendChild( purge );

			purge.addEventListener( 'click', ( ev ) => {
				if (
					confirm(
						wp.i18n.__( 'Purge entire cache point?', 'cloudinary' )
					)
				) {
					this._purgeCache( cachePoint );
				}
			} );
		}

		left.type = 'button';
		left.innerHTML = '&lsaquo;';
		left.className = 'button cld-pagenav-prev';
		if ( 1 === result.current_page ) {
			left.disabled = true;
		} else {
			left.addEventListener( 'click', ( ev ) => {
				cachePoint.currentPage = result.current_page - 1;
				this._load( cachePoint.dataset.cachePoint );
			} );
		}

		right.type = 'button';
		right.innerHTML = '&rsaquo;';
		right.className = 'button cld-pagenav-next';
		if (
			result.current_page === result.total_pages ||
			0 === result.total_pages
		) {
			right.disabled = true;
		} else {
			right.addEventListener( 'click', ( ev ) => {
				cachePoint.currentPage = result.current_page + 1;
				this._load( cachePoint.dataset.cachePoint );
			} );
		}

		const text = document.createElement( 'span' );
		text.innerText = result.nav_text;
		text.className = 'cld-pagenav-text';
		cachePoint.paginate.appendChild( left );
		cachePoint.paginate.appendChild( text );
		cachePoint.paginate.appendChild( right );
	},
	_getNote( message ) {
		const row = this._getRow();
		const td = document.createElement( 'td' );
		td.colSpan = 2;
		td.innerText = message;
		row.appendChild( td );
		return row;
	},
	_getRow( itemID ) {
		const row = document.createElement( 'tr' );
		if ( itemID ) {
			row.id = 'row_' + itemID;
		}
		return row;
	},
	_getFile( cachePoint, item ) {
		const file = document.createElement( 'td' );
		const label = document.createElement( 'label' );
		const deleter = this._getDeleter( cachePoint, file, item );
		label.innerText = item.short_url;
		label.htmlFor = item.key;
		file.appendChild( deleter );
		file.appendChild( label );
		const spinner = document.createElement( 'span' );
		const spinnerId = 'spinner_' + item.ID;
		spinner.className = 'spinner';
		spinner.id = spinnerId;
		file.appendChild( spinner );
		this.spinners[ spinnerId ] = spinner;
		return file;
	},
	_getDeleter( cachePoint, file, item ) {
		const checkbox = document.createElement( 'input' );
		const masters = [ cachePoint.dataset.slug + '_deleter' ];
		const index = this._getListIndex( cachePoint, item.ID, 'delete' );

		checkbox.type = 'checkbox';
		checkbox.value = item.ID;
		checkbox.id = item.key;
		checkbox.dataset.master = JSON.stringify( masters );
		if ( -1 < index ) {
			checkbox.checked = true;
			file.style.textDecoration = 'line-through';
		}

		checkbox.addEventListener( 'change', ( ev ) => {
			file.style.opacity = 1;
			file.style.textDecoration = '';
			if ( checkbox.checked ) {
				file.style.opacity = 0.8;
				file.style.textDecoration = 'line-through';
			}
			const event = new CustomEvent( 'CacheToggle', {
				detail: {
					checked: checkbox.checked,
					states: {
						on: 'delete',
						off: item.active ? 'enable' : 'disable',
					},
					item,
					cachePoint,
				},
			} );
			window.dispatchEvent( event );
		} );
		return checkbox;
	},

	_getStateSwitch( cachePoint, item, states ) {
		const column = document.createElement( 'td' );
		const wrap = document.createElement( 'label' );
		const checkbox = document.createElement( 'input' );
		const slider = document.createElement( 'span' );
		const masters = [ cachePoint.dataset.slug + '_selector' ];
		const index = this._getListIndex( cachePoint, item.ID, 'disable' );
		column.style.textAlign = 'right';
		wrap.className = 'cld-input-on-off-control mini';
		checkbox.type = 'checkbox';
		checkbox.value = item.ID;
		checkbox.checked = -1 < index ? false : item.active;
		checkbox.dataset.master = JSON.stringify( masters );
		slider.className = 'cld-input-on-off-control-slider';

		wrap.appendChild( checkbox );
		wrap.appendChild( slider );

		checkbox.addEventListener( 'change', ( ev ) => {
			const event = new CustomEvent( 'CacheToggle', {
				detail: {
					checked: checkbox.checked,
					states,
					item,
					cachePoint,
				},
			} );
			window.dispatchEvent( event );
		} );
		column.appendChild( wrap );

		return column;
	},
};

export default CacheManage;
