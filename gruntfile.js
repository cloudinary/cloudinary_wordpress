module.exports = function ( grunt ) {
	// Load all Grunt plugins.
	require( 'load-grunt-tasks' )( grunt );

	const pluginVersion = grunt.file.read( '.version' );

	const options = {
		plugin_slug:
			'cloudinary-image-management-and-manipulation-in-the-cloud-cdn',
		plugin_main_file: 'cloudinary.php',
		build_dir: '<%= dist_dir %>',
		assets_dir: 'assets',
		svn_user: 'cloudinary',
	};

	grunt.initConfig( {
		dist_dir: 'build',

		clean: {
			build: [ '<%= dist_dir %>' ],
		},

		copy: {
			dist: {
				src: [
					'css/**',
					'js/**',
					'php/**',
					'ui-definitions/**',
					'*.php',
					'readme.txt',
					'!**/src/**',
				],
				dest: '<%= dist_dir %>',
				expand: true,
			},
		},

		replace: {
			version: {
				src: [
					'<%= dist_dir %>/readme.txt',
					'<%= dist_dir %>/cloudinary.php',
				],
				overwrite: true,
				replacements: [
					{
						from: 'STABLETAG',
						to: pluginVersion,
					},
				],
			},
		},

		compress: {
			release: {
				options: {
					archive: 'cloudinary-wordpress-v2.zip',
				},
				cwd: '<%= dist_dir %>',
				expand: true,
				dest:
					'cloudinary-image-management-and-manipulation-in-the-cloud-cdn',
				src: [ '**/*' ],
			},
		},

		wp_deploy: {
			default: {
				// Default deploy to trunk and a tag release.
				options,
			},
			assets: {
				// Deploy only screenshots and icons.
				options: {
					...options,
					deploy_trunk: false,
					deploy_tag: false,
				},
			},
		},

		addtextdomain: {
			options: {
				textdomain: 'cloudinary',
			},
			update_all_domains: {
				options: {
					updateDomains: true,
				},
				src: [
					'*.php',
					'**/*.php',
					'!.git/**/*',
					'!bin/**/*',
					'!node_modules/**/*',
					'!tests/**/*',
					'!build/**/*',
					'!vendor/**/*',
				],
			},
		},

		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					exclude: [
						'.git/*',
						'bin/*',
						'node_modules/*',
						'tests/*',
						'build/*',
						'vendor/*',
					],
					mainFile: 'cloudinary.php',
					potFilename: 'cloudinary.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true,
						'Report-Msgid-Bugs-To':
							'https://github.com/cloudinary/cloudinary_wordpress',
					},
					type: 'wp-plugin',
					updateTimestamp: true,
				},
			},
		},
	} );

	grunt.registerTask( 'i18n', [ 'addtextdomain', 'makepot' ] );

	grunt.registerTask( 'package', [ 'clean', 'copy', 'replace', 'compress' ] );

	grunt.registerTask( 'deploy', [ 'package', 'wp_deploy:default' ] );

	grunt.registerTask( 'deploy-assets', [ 'wp_deploy:assets' ] );
};
