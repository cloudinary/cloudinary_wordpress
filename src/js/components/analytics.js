import apiFetch from '@wordpress/api-fetch';

/**
 * Client-side analytics bridge.
 *
 * Posts custom events to a plugin-internal REST route, which enriches them
 * with the server-side parameter envelope and forwards them to the Cloudinary
 * custom-events collector. Fail-silent by design: nothing here may disrupt
 * wp-admin. Event call-sites are wired in a later PR.
 */
const Analytics = {
	config: null,

	init() {
		if ( this.config ) {
			return;
		}
		if (
			typeof cldData === 'undefined' ||
			! cldData.analytics ||
			! cldData.analytics.enabled
		) {
			return;
		}

		this.config = cldData.analytics;
		apiFetch.use( apiFetch.createNonceMiddleware( this.config.nonce ) );
	},

	/**
	 * Tracks a custom event.
	 *
	 * @param {string}      eventName  The snake_case event identifier.
	 * @param {Object}      params     Event-specific params.
	 * @param {string}      category   The event category.
	 * @param {number|null} funnelStep Ordinal step within a funnel, or null.
	 */
	track(
		eventName,
		params = {},
		category = 'activation_funnel',
		funnelStep = null
	) {
		// Lazy-init so call-sites work regardless of listener order.
		if ( ! this.config ) {
			this.init();
		}
		if ( ! this.config || ! this.config.enabled || ! eventName ) {
			return;
		}

		try {
			apiFetch( {
				path: this.config.endpoint,
				method: 'POST',
				data: {
					event_name: eventName,
					event_category: category,
					funnel_step: funnelStep,
					params,
				},
			} ).catch( () => {} );
		} catch ( e ) {
			// Fail silent: analytics must never disrupt the admin experience.
		}
	},

	/**
	 * Tracks an event that must survive an immediate page unload/navigation
	 * (e.g. a "skip" action that navigates away right after). `track()`'s
	 * plain `fetch()` call can be aborted mid-flight by the navigation,
	 * silently losing the event; `navigator.sendBeacon()` is guaranteed by
	 * the browser to be dispatched even across one.
	 *
	 * @param {string} eventName The snake_case event identifier.
	 * @param {Object} params    Event-specific params.
	 * @param {string} category  The event category.
	 */
	trackReliable( eventName, params = {}, category = 'activation_funnel' ) {
		if ( ! this.config ) {
			this.init();
		}
		if ( ! this.config || ! this.config.enabled || ! eventName ) {
			return;
		}

		if ( ! navigator.sendBeacon ) {
			this.track( eventName, params, category );
			return;
		}

		try {
			// sendBeacon can't set custom headers, so the REST nonce travels
			// as the `_wpnonce` query arg instead of the `X-WP-Nonce` header
			// — both are accepted by WP's REST cookie authentication.
			const separator = this.config.endpoint.includes( '?' ) ? '&' : '?';
			const url =
				this.config.endpoint +
				separator +
				'_wpnonce=' +
				encodeURIComponent( this.config.nonce );
			const blob = new Blob(
				[
					JSON.stringify( {
						event_name: eventName,
						event_category: category,
						funnel_step: null,
						params,
					} ),
				],
				{ type: 'application/json' }
			);
			navigator.sendBeacon( url, blob );
		} catch ( e ) {
			// Fail silent: analytics must never disrupt the admin experience.
		}
	},
};

window.addEventListener( 'load', () => Analytics.init() );

export default Analytics;
