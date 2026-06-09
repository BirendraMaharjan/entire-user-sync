const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

/*module.exports = {
	...defaultConfig,
	entry: {
		frontend: path.resolve( __dirname, 'assets/src/js/frontend.js' ),
		backend: path.resolve( __dirname, 'assets/src/js/backend.js' ),
	},
	output: {
		path: path.resolve( __dirname, 'assets/build' ),
		filename: '[name].js',
	}
};*/

// Define modules with JS + SCSS
const modules = {
	/*	'my-account': {
			js: './modules/my-account/assets/src/js/my-account.js',
			scss: './modules/my-account/assets/src/scss/my-account.scss',
		},
		'user-sync': {
			js: './modules/my-account/assets/src/js/user-sync.js',
			scss: './modules/my-account/modules/my-account/assets/src/scss/user-sync.scss',
		},*/
	/*'wishlist': {
		js: './modules/wishlist/assets/src/js/wishlist.js',
		scss: './modules/wishlist/assets/src/scss/wishlist.scss',
	},*/
};

// Base “main” entry
const mainEntry = {
	...defaultConfig,
	entry: {
		frontend: path.resolve( __dirname, 'assets/src/js/frontend.js' ),
		backend: path.resolve( __dirname, 'assets/src/js/backend.js' ),
	},
	output: {
		path: path.resolve( __dirname, 'assets/build' ),
		filename: '[name].js',
	}
};

// Generate configs: main + one per module
const configs = [
	mainEntry,
	// Module-specific builds
	...Object.entries( modules ).map( ( [name, paths] ) => (
		{
			...defaultConfig,
			name,
			entry: {
				[name]: [path.resolve( __dirname, paths.js ), path.resolve( __dirname, paths.scss )],
			},
			output: {
				path: path.resolve( __dirname, `modules/wishlist/assets/build/${name}` ),
				filename: '[name].js',
				clean: true,
			},
		}
	) ),
];

module.exports = configs;