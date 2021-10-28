import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import OnOff from './components/onoff';
import States from './components/states';

const AssetManager = {
	cachePoints: {},
	spinners: {},
	states: null,
	init( context, States ) {
		this.states = States;

		if ( typeof CLDASSETS !== 'undefined' ) {
			apiFetch.use( apiFetch.createNonceMiddleware( CLDASSETS.nonce ) );
			const cachePoints = context.querySelectorAll(
				'[data-cache-point]'
			);

			cachePoints.forEach( ( cachePoint ) => this._bind( cachePoint ) );
			const purgeAll = document.getElementById(
				'connect.cache.cld_purge_all'
			);

			if ( purgeAll ) {
				purgeAll.disabled = 'disabled';
				purgeAll.style.width = '100px';
				purgeAll.style.transition = 'width 0.5s';
				purgeAll.addEventListener( 'click', () => {
					if ( ! purgeAll.dataset.purging ) {
						if (
							confirm(
								wp.i18n.__(
									'Purge entire cache?',
									'cloudinary'
								)
							)
						) {
							this._purgeAll( purgeAll, false );
						}
					}
				} );
				this._watchPurge( purgeAll );
				setInterval( () => {
					this._watchPurge( purgeAll );
				}, 5000 );
			}
		}
	},
	getCachePoint( ID ) {
		return this.cachePoints[ '_' + ID ]
			? this.cachePoints[ '_' + ID ]
			: null;
	},
	setCachePoint( ID, cachePoint ) {
		const main = document.getElementById( cachePoint.dataset.slug );

		const paginate = document.createElement( 'div' );
		const loader = this._getRow();
		const loaderTd = document.createElement( 'td' );
		loaderTd.colSpan = 2;
		loaderTd.className = 'cld-loading';
		loader.appendChild( loaderTd );

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
		apply.style.float = 'right';
		apply.style.marginLeft = '6px';

		controller.addEventListener( 'change', ( ev ) => {
			this._handleManager( ID );
		} );
		main.addEventListener( 'change', ( ev ) => {
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
		cachePoint.main = main;
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
			open = cachePoint.controller.checked && cachePoint.main.checked;
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
			this.states.set( cachePoint.viewer.id, 'open' );
			if ( ! cachePoint.loaded ) {
				this._load( ID );
			}
		} else {
			this.close( cachePoint.viewer );
			cachePoint.controller.checked = false;
			this.states.set( cachePoint.viewer.id, 'close' );
		}
	},
	_load( ID ) {
		const cachePoint = this.getCachePoint( ID );
		let height = '100px';
		if ( cachePoint.clientHeight ) {
			height = cachePoint.clientHeight - 16 + 'px'; // Subtract padding.
		}
		this._clearChildren( cachePoint );
		cachePoint.appendChild( cachePoint.loader );
		this.open( cachePoint.loader );
		cachePoint.loader.firstChild.style.height = height;
		apiFetch( {
			path: CLDASSETS.fetch_url,
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
			const mains = cachePoint.querySelectorAll( '[data-main]' );
			OnOff.bind( mains );
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
		cachePoint.apply.disabled = 'disabled';
		const lists = cachePoint.apply.cacheChanges;
		let show = false;
		for ( const state in lists ) {
			if ( lists[ state ].length ) {
				show = true;
			}
		}
		if ( show ) {
			cachePoint.apply.disabled = '';
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
	_watchPurge( button ) {
		if ( ! button.dataset.purging && ! button.dataset.updating ) {
			button.dataset.updating = true;
			apiFetch( {
				path: CLDASSETS.purge_all,
				data: {
					count: true,
				},
				method: 'POST',
			} ).then( ( result ) => {
				button.dataset.updating = '';
				if ( 0 < result.percent && 100 > result.percent ) {
					button.disabled = '';
					this._purgeAll( button, true );
				} else if ( 0 < result.pending ) {
					button.disabled = '';
				} else {
					button.disabled = 'disabled';
				}
			} );
		}
	},
	_purgeAll( button, count, callback ) {
		button.blur();
		const percent = 0;
		button.dataset.purging = true;
		button.style.width = '200px';
		button.style.border = '0';
		button.dataset.title = button.innerText;
		button.innerText = __( 'Purging cache 0%', 'cloudinary' );
		button.style.backgroundImage =
			'linear-gradient(90deg,' +
			' #2a0 ' +
			percent +
			'%,' +
			' #787878 ' +
			percent +
			'%)';
		this._purgeAction( button, count, callback );
	},
	_purgeAction( button, count, callback ) {
		const parent = button.dataset.parent;
		apiFetch( {
			path: CLDASSETS.purge_all,
			data: {
				count,
				parent,
			},
			method: 'POST',
		} ).then( ( result ) => {
			button.innerText =
				__( 'Purging cache', 'cloudinary' ) +
				' ' +
				Math.round( result.percent, 2 ) +
				'%';
			button.style.backgroundImage =
				'linear-gradient(90deg,' +
				' #2a0 ' +
				result.percent +
				'%,' +
				' #787878 ' +
				result.percent +
				'%)';
			if ( 100 > result.percent ) {
				this._purgeAction( button, true, callback );
			} else if ( callback ) {
				callback();
			} else {
				button.innerText = wp.i18n.__(
					'Purge complete.',
					'cloudinary'
				);
				setTimeout( () => {
					button.dataset.purging = '';
					button.style.backgroundImage = '';
					button.style.minHeight = '';
					button.style.border = '';
					button.style.width = '100px';
					button.disabled = 'disabled';
					button.innerText = button.dataset.title;
				}, 2000 );
			}
		} );
	},
	_set_state( cachePoint, state, ids ) {
		this._showSpinners( ids );
		apiFetch( {
			path: CLDASSETS.update_url,
			data: {
				state,
				ids,
			},
			method: 'POST',
		} ).then( ( result ) => {
			this._hideSpinners( result );
			result.forEach( ( id ) => {
				this._removeFromList( cachePoint, id, state );
				this._evaluateApply( cachePoint );
				cachePoint.apply.disabled = 'disabled';
			} );
			if ( 'delete' === state ) {
				this._load( cachePoint.dataset.cachePoint );
			}
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
			const edit = this._getEdit( item, cachePoint );
			row.appendChild( file );
			row.appendChild( edit );
			row.appendChild( statSwitch );
			cachePoint.appendChild( row );
		} );
	},
	_buildNav( cachePoint, result ) {
		cachePoint.paginate.innerHTML = '';
		const left = document.createElement( 'button' );
		const right = document.createElement( 'button' );

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
		cachePoint.paginate.appendChild( cachePoint.apply );
		cachePoint.apply.classList.remove( 'closed' );
		cachePoint.apply.disabled = 'disabled';
		// Add purge
		if ( result.items.length ) {
			const purge = document.createElement( 'button' );
			purge.type = 'button';
			purge.className = 'button';
			purge.innerText = wp.i18n.__( 'Purge cache point', 'cloudinary' );
			purge.style.float = 'right';
			cachePoint.paginate.appendChild( purge );

			purge.addEventListener( 'click', ( ev ) => {
				if (
					confirm(
						wp.i18n.__( 'Purge entire cache point?', 'cloudinary' )
					)
				) {
					purge.dataset.parent = cachePoint.dataset.cachePoint;
					const self = this;
					purge.classList.add( 'button-primary' );
					this._purgeAll( purge, false, function() {
						self._load( cachePoint.dataset.cachePoint );
					} );
				}
			} );
		}
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
	_getEdit( item ) {
		const td = document.createElement( 'td' );
		const editor = document.createElement( 'a' );

		editor.href = '#';
		if ( ! item.data.transformations ) {
			editor.innerText = __( 'Add transformations', 'cloudinary' );
		} else {
			editor.innerText = item.data.transformations;
		}

		editor.addEventListener( 'click', ( ev ) => {
			ev.preventDefault();
			this.editModal.edit( item, ( transformations ) => {
				item.data.transformations = transformations;
				if ( ! transformations.length ) {
					editor.innerText = __( 'Add transformations', 'cloudinary' );
				} else {
					editor.innerText = item.data.transformations;
				}
			} );
		} );
		td.appendChild( editor );
		return td;
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
		const mains = [ cachePoint.dataset.slug + '_deleter' ];
		const index = this._getListIndex( cachePoint, item.ID, 'delete' );

		checkbox.type = 'checkbox';
		checkbox.value = item.ID;
		checkbox.id = item.key;
		checkbox.dataset.main = JSON.stringify( mains );
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
		const mains = [ cachePoint.dataset.slug + '_selector' ];
		const index = this._getListIndex( cachePoint, item.ID, 'disable' );
		column.style.textAlign = 'right';
		wrap.className = 'cld-input-on-off-control mini';
		checkbox.type = 'checkbox';
		checkbox.value = item.ID;
		checkbox.checked = -1 < index ? false : item.active;
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

const context = document.getElementById( 'cloudinary-settings-page' );

if ( context ) {
	// Init states.
	States.init();
	// Init.
	window.addEventListener( 'load', () => AssetManager.init( context, States ) );
}
