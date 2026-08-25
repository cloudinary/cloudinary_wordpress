import Analytics from './analytics';

/**
 * Tracks clicks on the special-offer promo link.
 *
 * There is only ever one static offer today, so `offer_id` is a fixed
 * constant rather than something read from the offer's own config.
 */
const SpecialOffer = {
	init() {
		document
			.querySelectorAll( '.cld-special-offer-link' )
			.forEach( ( link ) => {
				link.addEventListener( 'click', () => {
					Analytics.track(
						'special_offer_clicked',
						{ offer_id: 'small_plan_29' },
						'settings'
					);
				} );
			} );
	},
};

window.addEventListener( 'load', () => SpecialOffer.init() );

export default SpecialOffer;
