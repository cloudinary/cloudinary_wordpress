/**
 * External dependencies
 */
const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const OptimizeCSSAssetsPlugin = require( 'optimize-css-assets-webpack-plugin' );
const RtlCssPlugin = require( 'rtlcss-webpack-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );

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
		minimizer: [
			new TerserPlugin( {
				parallel: true,
				sourceMap: true,
				cache: true,
				terserOptions: {
					output: {
						comments: /translators:/i,
					},
				},
				extractComments: false,
			} ),
			new OptimizeCSSAssetsPlugin( {} ),
		],
	},
	module: {
		...defaultConfig.module,
		rules: [
			// Remove the css/postcss loaders from `@wordpress/scripts` due to version conflicts.
			...defaultConfig.module.rules.filter(
				( rule ) => ! rule.test.toString().match( '.css' )
			),
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
				use: [
					{
						loader: 'file-loader',
						options: {
							name: '[name].[ext]',
							outputPath: '../css/images/',
						},
					},
				],
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf)$/,
				use: [
					{
						loader: 'file-loader',
						options: {
							name: '[name].[contenthash].[ext]',
							outputPath: '../css/fonts/',
						},
					},
				],
			},
			{
				test: /\.(sa|sc|c)ss$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
					},
					'css-loader',
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: '../css/[name].css',
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
	},
};

module.exports = [ cldCore, cldExtras ];
