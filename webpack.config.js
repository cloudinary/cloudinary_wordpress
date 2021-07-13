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
		cloudinary: './js/src/main.js',
		video: './css/src/video.scss',
	},
	output: {
		path: path.resolve( process.cwd(), 'js' ),
		filename: '[name].js',
	},
	module: {
		rules: [
			{
				test: /\.(png|svg|jpg|gif)$/,
				use: [
					{
						loader: 'file-loader',
						options: {
							name: '[name].[ext]',
							outputPath: '../css/',
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

const cldBlockEditor = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		'block-editor': './js/src/blocks.js',
	},
	module: {
		...sharedConfig.module,
		rules: [
			...sharedConfig.module.rules,
			{
				test: /\.(sa|sc|c)ss$/,
				use: 'null-loader',
			},
		],
	},
};

const cldGalleryBlock = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		'gallery-block': './js/src/gallery-block/index.js',
	},
	output: {
		path: path.resolve( process.cwd(), 'js' ),
		filename: '[name].js',
	},
};

const cldGalleryInit = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		'gallery-init': './js/src/components/gallery-init.js',
	},
};

const cldSettingsGallery = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		gallery: './js/src/components/settings-gallery.js',
	},
};

const cldGalleryUI = {
	...cldCore,
	entry: {
		'gallery-ui': './css/src/gallery-ui.scss',
	},
};

const cldDeactivate = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		deactivate: './js/src/deactivate.js',
	},
};

const cldVideoInit = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		'video-init': './js/src/video-init.js',
	},
};

const cldLazyLoad = {
	...defaultConfig,
	...sharedConfig,
	entry: {
		'lazy-load': './js/src/lazy-load.js',
	},
};

module.exports = [
	cldBlockEditor,
	cldCore,
	cldGalleryBlock,
	cldGalleryInit,
	cldDeactivate,
	cldVideoInit,
	cldSettingsGallery,
	cldGalleryUI,
	cldLazyLoad,
];
