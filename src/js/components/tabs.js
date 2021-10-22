const Tabs = {
	/**
	 * Holds the tabs buttons.
	 *
	 * @type {NodeList}
	 */
	tabButtonSelectors: null,

	/**
	 * Holds the current selected tab ID.
	 *
	 * @type {string}
	 */
	selectedTabID: '',

	/**
	 * Deselect previous tab.
	 */
	deselectOldTab() {
		document
			.getElementById( this.selectedTabID )
			.classList.remove( 'is-active' );
		this.filterActive( [ ...this.tabButtonSelectors ] ).classList.remove(
			'is-active'
		);
	},

	/**
	 * Selects the new tab.
	 *
	 * @param {HTMLButtonElement} button DOM object.
	 */
	selectCurrentTab( button ) {
		this.selectedTabID = button.dataset.tab;
		button.classList.add( 'is-active' );
		document
			.getElementById( this.selectedTabID )
			.classList.add( 'is-active' );
	},

	/**
	 * Select tab event.
	 *
	 * @param {PointerEvent} ev The click event.
	 */
	selectTab( ev ) {
		ev.preventDefault();

		if ( ev.target.classList.contains( 'is-active' ) ) {
			return;
		}

		this.deselectOldTab();
		this.selectCurrentTab( ev.target );
	},

	/**
	 * Filters out tab buttons without tabbed content.
	 */
	filterTabs() {
		[ ...this.tabButtonSelectors ].forEach( ( tab ) => {
			const tabContent = tab.dataset.tab;

			if ( tabContent ) {
				tab.addEventListener( 'click', this.selectTab.bind( this ) );
			}
		} );
	},

	/**
	 * Filter the active button.
	 *
	 * @param  {Array} buttons All the buttons.
	 * @return {*}             The active button.
	 */
	filterActive( buttons ) {
		return buttons
			.filter( ( button ) => button.classList.contains( 'is-active' ) )
			.pop();
	},

	/**
	 * Init the tabs.
	 */
	init() {
		this.tabButtonSelectors = document.querySelectorAll(
			'.cld-page-tabs-tab button'
		);

		if ( 0 === this.tabButtonSelectors.length ) {
			return;
		}

		this.selectCurrentTab(
			this.filterActive( [ ...this.tabButtonSelectors ] )
		);
		this.filterTabs();
	},
};

window.addEventListener( 'load', () => Tabs.init() );

export default Tabs;
