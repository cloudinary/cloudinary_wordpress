const globals = require( 'globals' );
const wpPlugin = require( '@wordpress/eslint-plugin' );

// eslint-import-resolver-typescript v4+ is incompatible with eslint-plugin-import
// (throws "invalid interface loaded as resolver"), and this project has no TypeScript
// anyway, so strip the typescript resolver that @wordpress/eslint-plugin configures.
const recommended = wpPlugin.configs.recommended.map( ( config ) => {
	if ( ! config.settings || ! config.settings[ 'import/resolver' ] ) {
		return config;
	}
	const { typescript, ...resolver } = config.settings[ 'import/resolver' ];
	return {
		...config,
		settings: {
			...config.settings,
			'import/resolver': resolver,
		},
	};
} );

module.exports = [
	{
		ignores: [
			'**/build/**',
			'**/built/**',
			'**/node_modules/**',
			'**/vendor/**',
			'js/**',
			'**/*.min.js',
			// Vendored third-party library (https://github.com/kallookoo/wp-color-picker-alpha).
			'src/js/wp-color-picker-alpha.js',
		],
	},
	...recommended,
	{
		settings: {
			'import/resolver': {
				node: true,
			},
		},
		languageOptions: {
			globals: {
				...globals.browser,
				cloudinary: 'readonly',
				jQuery: 'readonly',
				$: 'readonly',
				CLDN: 'readonly',
				CLDLB: 'readonly',
				CLD_GLOBAL_TRANSFORMATIONS: 'readonly',
				samplePlayer: 'readonly',
				CLDCACHE: 'readonly',
				cldData: 'readonly',
				CLD_METADATA: 'readonly',
				CLDASSETS: 'readonly',
			},
		},
		rules: {
			'no-alert': 'off',
			'no-console': 'off',
			'no-unused-vars': 'off',
			'no-nested-ternary': 'off',
			'jsx-a11y/click-events-have-key-events': 'off',
			'react-hooks/rules-of-hooks': 'error',
			'react-hooks/exhaustive-deps': 'warn',
			'@wordpress/no-global-event-listener': 'off',
			// CLD_Deactivate is a wp_localize_script object name (php/class-deactivation.php);
			// renaming it would require a matching PHP-side change.
			camelcase: [
				'error',
				{ properties: 'never', allow: [ '^CLD_Deactivate$' ] },
			],
		},
	},
];
