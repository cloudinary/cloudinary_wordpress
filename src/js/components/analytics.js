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
};

window.addEventListener( 'load', () => Analytics.init() );

export default Analytics;
