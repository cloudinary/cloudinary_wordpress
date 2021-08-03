import ProgressBar from 'progressbar.js';
import Chart from 'chart.js/auto';
import humanFormat from 'human-format';

const Progress = {
	init( context ) {
		const items = context.querySelectorAll( '[data-progress]' );
		const charts = context.querySelectorAll( '[data-chart]' );
		[ ...items ].forEach( ( item ) => {
			if ( 'line' === item.dataset.progress ) {
				this.line( item );
			} else if ( 'circle' === item.dataset.progress ) {
				this.circle( item );
			}
		} );

		[ ...charts ].forEach( ( item ) => {
			const data = {
				labels: JSON.parse( item.dataset.dates ),
				datasets: [
					{
						backgroundColor: '#304ec4',
						borderColor: '#304ec4',
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
							min: -10,
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
			color: '#304ec4',
			trailColor: '#d3dce3',
			trailWidth: 2,
			svgStyle: { width: '100%', height: '100%', display: 'block' },
		} );

		bar.animate( item.dataset.value / 100 ); // Number from 0.0 to 1.0
	},
	circle( item ) {
		const bar = new ProgressBar.Circle( item, {
			strokeWidth: 3,
			easing: 'easeInOut',
			duration: 1400,
			color: '#304ec4',
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
				const value = Math.round( circle.value() * 100 );
				if ( value === 0 ) {
					circle.setText( '' );
				} else {
					circle.setText(
						'<h2>' + value + '%</h2>' + item.dataset.text
					);
				}
			},
		} );
		const val = item.dataset.value / 100;
		bar.animate( val ); // Number from 0.0 to 1.0

		/*setInterval( () => {
		 if ( val < 1 ) {
		 val = val + 0.1;
		 if ( val > 1 ) {
		 val = 1;
		 }

		 bar.animate( val ); // Number from 0.0 to 1.0
		 }
		 }, 5000 );*/
	},
};

export default Progress;
