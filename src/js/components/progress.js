import ProgressBar from 'progressbar.js';
import Chart from 'chart.js/auto';
import humanFormat from 'human-format';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const Progress = {
	data: {},
	context: null,
	init( context ) {
		this.context = context;
		const items = context.querySelectorAll( '[data-progress]' );
		const charts = context.querySelectorAll( '[data-chart]' );
		[ ...items ].forEach( ( item ) => {
			if ( item.dataset.url ) {
				if ( ! this.data[ item.dataset.url ] ) {
					this.data[ item.dataset.url ] = {
						items: [],
						poll: null,
					};
				}
				this.data[ item.dataset.url ].items.push( item );
			}
			if ( 'line' === item.dataset.progress ) {
				this.line( item );
			} else if ( 'circle' === item.dataset.progress ) {
				this.circle( item );
			}
		} );

		for ( const url in this.data ) {
			this.getValues( url );
		}

		[ ...charts ].forEach( ( item ) => {
			const data = {
				labels: JSON.parse( item.dataset.dates ),
				datasets: [
					{
						backgroundColor: item.dataset.color,
						borderColor: item.dataset.color,
						data: JSON.parse( item.dataset.data ),
						cubicInterpolationMode: 'monotone',
					},
				],
			};

			new Chart( item, {
				type: 'line',
				data,
				options: {
					responsive: true,
					radius: 0,
					interaction: {
						intersect: false,
					},
					plugins: {
						legend: {
							display: false,
						},
					},
					scales: {
						y: {
							suggestedMin: 0,
							ticks: {
								color: '#999999',
								callback( val, index ) {
									return humanFormat( val, {
										decimals: 2,
										scale: 'SI',
									} );
								},
							},
							grid: {
								color: '#d3dce3',
							},
						},
						x: {
							ticks: {
								color: '#999999',
							},
							grid: {
								color: '#d3dce3',
							},
						},
					},
				},
			} );
		} );
	},
	line( item ) {
		const bar = new ProgressBar.Line( item, {
			strokeWidth: 2,
			easing: 'easeInOut',
			duration: 1400,
			color: item.dataset.color,
			trailColor: '#d3dce3',
			trailWidth: 2,
			svgStyle: { width: '100%', height: '100%', display: 'block' },
		} );

		bar.animate( item.dataset.value / 100 ); // Number from 0.0 to 1.0
	},
	circle( item ) {
		item.dataset.basetext = item.dataset.text;
		item.dataset.text = '';
		const value = item.dataset.value;
		const self = this;
		item.bar = new ProgressBar.Circle( item, {
			strokeWidth: 3,
			easing: 'easeInOut',
			duration: 1400,
			color: item.dataset.color,
			trailColor: '#d3dce3',
			trailWidth: 3,
			svgStyle: null,
			text: {
				autoStyleContainer: false,
				style: {
					color: '#222222',
				},
			},
			step( state, circle ) {
				const newValue = Math.floor( circle.value() * 100 );
				self.setText(
					circle,
					parseFloat( newValue ),
					item.dataset.text
				);
			},
		} );

		if ( ! item.dataset.url ) {
			const val = value / 100;
			item.bar.animate( val );
		}
	},
	getValues( url ) {
		if ( this.data[ url ].poll ) {
			clearTimeout( this.data[ url ].poll );
			this.data[ url ].poll = null;
		}
		apiFetch( {
			path: url,
			method: 'GET',
		} ).then( ( result ) => {
			this.data[ url ].items.forEach( ( item ) => {
				if ( typeof result[ item.dataset.basetext ] !== 'undefined' ) {
					item.dataset.text = result[ item.dataset.basetext ];
				} else {
					item.dataset.text = item.dataset.basetext;
				}
				item.bar.animate( result[ item.dataset.value ] );
				if ( item.dataset.poll && ! this.data[ url ].poll ) {
					this.data[ url ].poll = setTimeout( () => {
						this.getValues( url );
					}, 10000 );
				}
			} );

			for ( const stat in result ) {
				const eventElements = this.context.querySelectorAll(
					`[data-key="${ stat }"]`
				);
				const textElements = this.context.querySelectorAll(
					`[data-text="${ stat }"]`
				);
				eventElements.forEach( ( element ) => {
					element.dataset.value = result[ stat ];
					element.dispatchEvent( new Event( 'focus' ) );
				} );
				textElements.forEach( ( element ) => {
					element.innerText = result[ stat ];

					if ( element.classList.contains( 'cld-toggle' ) ) {
						if ( result[ stat ] ) {
							element.classList.remove( 'hidden' );
						} else {
							element.classList.add( 'hidden' );
						}
					}
				} );
			}
		} );
	},
	setText( bar, value, text ) {
		if ( ! bar ) {
			return;
		}
		const content = document.createElement( 'span' );
		const h2 = document.createElement( 'h2' );
		const desc = document.createTextNode( text );
		h2.innerText = value + '%';
		content.appendChild( h2 );
		content.appendChild( desc );
		bar.setText( content );
	},
};

export default Progress;
