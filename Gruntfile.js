/* eslint-env node */
module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {

		banana: Object.assign(
			conf.MessagesDirs,
			{
				options: {
					requireLowerCase: 'initial'
				}
			}
		),
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.js{,on}',
				'!libs/**',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		stylelint: {
			options: {
				cache: true
			},
			all: [
				'**/*.css',
				'!libs/**',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
