/**
 * Custom Webpack configuration for Data Signals
 * Extends @wordpress/scripts default configuration
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve(process.cwd(), 'assets/src/js', 'index.jsx'),
	},
	output: {
		filename: '[name].js',
		path: path.resolve(process.cwd(), 'assets/build'),
	},
};
