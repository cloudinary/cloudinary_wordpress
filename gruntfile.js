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
		pluginVersion,

		plugin_slug: options.plugin_slug,

		dist_dir: 'build',

		package_dir: 'package/dist',

		tester_dir: '<%= package_dir %>/cloudinary-update-tester',

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
			package: {
				src: '<%= tester_dir %>/cloudinary-wordpress-<%= pluginVersion %>.zip',
				dest: '<%= package_dir %>/cloudinary-wordpress-<%= pluginVersion %>.zip',
			},
		},

		replace: {
			version: {
				src: [
					'<%= dist_dir %>/readme.txt',
					'<%= dist_dir %>/cloudinary.php',
					'<%= tester_dir %>/cloudinary-update-tester.php',
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
			dist: {
				options: {
					archive:
						'<%= tester_dir %>/cloudinary-wordpress-<%= pluginVersion %>.zip',
				},
				cwd: '<%= dist_dir %>',
				expand: true,
				dest: '<%= plugin_slug %>',
				src: [ '**/*' ],
			},
			package: {
				options: {
					archive:
						'<%= package_dir %>/cloudinary-update-tester-<%= pluginVersion %>.zip',
				},
				cwd: '<%= tester_dir %>',
				expand: true,
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
					'!package/**/*',
					'!php/media/class-filter.php',
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
						'package/*',
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
					updateTimestamp: false,
				},
			},
		},
	} );

	grunt.registerTask( 'i18n', [ 'addtextdomain', 'makepot' ] );

	grunt.registerTask( 'prepare', [ 'clean', 'copy:dist', 'replace' ] );

	grunt.registerTask( 'package', [
		'i18n',
		'prepare',
		'compress:dist',
		'copy:package',
		'compress:package',
	] );

	grunt.registerTask( 'deploy', [ 'prepare', 'wp_deploy:default' ] );

	grunt.registerTask( 'deploy-assets', [ 'wp_deploy:assets' ] );
};
