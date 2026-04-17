/**
 * External dependencies
 */
const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );
const RtlCssPlugin = require( 'rtlcss-webpack-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const webpack = require( 'webpack' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const sharedConfig = {
	output: {
		path: path.resolve( process.cwd(), 'js' ),
		filename: '[name].js',
		chunkFilename: '[name].js',
	},
	optimization: {
		minimize: true,
		minimizer: [
			new TerserPlugin( {
				parallel: true,
				terserOptions: {
					output: {
						comments: /translators:/i,
					},
				},
				extractComments: false,
			} ),
			new CssMinimizerPlugin(),
		],
	},
	module: {
		...defaultConfig.module,
		rules: [
			// Remove the css/postcss loaders from `@wordpress/scripts` due to version conflicts.
			// Also patch the babel-loader rule to use the classic JSX transform so the build does
			// not depend on the `react-jsx-runtime` WP script handle (only available in WP 6.6+).
			...defaultConfig.module.rules
				.filter( ( rule ) => ! rule.test.toString().match( '.css' ) )
				.map( ( rule ) => {
					const uses = Array.isArray( rule.use )
						? rule.use
						: [ rule.use ];
					const hasBabelLoader = uses.some( ( use ) =>
						use?.loader?.includes( 'babel-loader' )
					);
					if ( ! hasBabelLoader ) {
						return rule;
					}
					return {
						...rule,
						use: uses.map( ( use ) => {
							if ( ! use?.loader?.includes( 'babel-loader' ) ) {
								return use;
							}
							return {
								...use,
								options: {
									...use.options,
									plugins: [
										...( use.options?.plugins ?? [] ),
										[
											require.resolve(
												'@babel/plugin-transform-react-jsx'
											),
											{ runtime: 'classic' },
										],
									],
								},
							};
						} ),
					};
				} ),
			{
				test: /\.css$/,
				use: [
					// prettier-ignore
					MiniCssExtractPlugin.loader,
					'css-loader',
					'postcss-loader',
				],
			},
		],
	},
	plugins: [
		// Remove the CleanWebpackPlugin and  FixStyleWebpackPlugin plugins from `@wordpress/scripts` due to version conflicts.
		...defaultConfig.plugins.filter(
			( plugin ) =>
				! [ 'CleanWebpackPlugin', 'FixStyleWebpackPlugin' ].includes(
					plugin.constructor.name
				)
		),
		new MiniCssExtractPlugin( {
			filename: '../css/[name].css',
		} ),
		new RtlCssPlugin( {
			filename: '../css/[name]-rtl.css',
		} ),
	],
	cache: {
		type: 'filesystem',
	},
	devtool: 'source-map',
};

const cldCore = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		cloudinary: './src/js/main.js',
		video: './src/css/video.scss',
		'wp-color-picker-alpha': './src/js/wp-color-picker-alpha.js',
		'front-overlay': './src/js/front-overlay.js',
		'breakpoints-preview': './src/js/breakpoints-preview.js',
		'lazyload-preview': './src/js/lazyload-preview.js',
		'asset-manager': './src/js/asset-manager.js',
		'asset-edit': './src/js/asset-edit.js',
		'syntax-highlight': './src/js/syntax-highlight.js',
		'gallery-ui': './src/css/gallery-ui.scss',
	},
	module: {
		rules: [
			{
				test: /\.(png|svg|jpg|gif|webp)$/,
				type: 'asset/resource',
				generator: {
					filename: '../css/images/[name][ext]',
				},
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf)$/,
				type: 'asset/resource',
				generator: {
					filename: '../css/fonts/[name].[contenthash][ext]',
				},
			},
			{
				test: /\.(sa|sc|c)ss$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
					},
					'css-loader',
					'css-unicode-loader',
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: '../css/[name].css',
		} ),
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve( process.cwd(), 'src/css/images' ),
					to: path.resolve( process.cwd(), 'css/images' ),
				},
			],
		} ),
	],
	optimization: {
		...sharedConfig.optimization,
	},
};

const cldExtras = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		'block-editor': './src/js/blocks.js',
		'gallery-block': './src/js/gallery-block/index.js',
		'gallery-init': './src/js/components/gallery-init.js',
		gallery: './src/js/components/settings-gallery.js',
		deactivate: './src/js/deactivate.js',
		'video-init': './src/js/video-init.js',
		'lazy-load': './src/js/lazy-load.js',
		'inline-loader': './src/js/inline-loader.js',
		'media-modal': './src/js/components/media-modal.js',
		'terms-order': './src/js/terms-order.js',
	},
	plugins: [
		...sharedConfig.plugins,
		// Inject React from wp-element into every module using JSX, so the
		// classic transform (React.createElement) works without explicit imports
		// on all WP versions (wp-element has been available since WP 5.0).
		new webpack.ProvidePlugin( {
			React: '@wordpress/element',
		} ),
	],
};

module.exports = [ cldCore, cldExtras ];
