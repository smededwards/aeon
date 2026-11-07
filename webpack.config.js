const defaults = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

module.exports = {
	...defaults,
	plugins: defaults.plugins.map( ( plugin ) =>
		plugin instanceof MiniCssExtractPlugin
			? new MiniCssExtractPlugin( {
					filename: ( { chunk } ) =>
						chunk.name === 'dashboard-colours'
							? 'dashboard-colours.css'
							: '[name].css',
				} )
			: plugin
	),
	entry: {
		'admin-bar-scroll': path.resolve(
			__dirname,
			'src/js/admin-bar-scroll.js'
		),
		'dashboard-colours': path.resolve( __dirname, 'src/index.js' ),
		'dashboard-colours-development': path.resolve(
			__dirname,
			'src/scss/admin/dashboard-colours-development.scss'
		),
		'dashboard-colours-local': path.resolve(
			__dirname,
			'src/scss/admin/dashboard-colours-local.scss'
		),
		'dashboard-colours-production': path.resolve(
			__dirname,
			'src/scss/admin/dashboard-colours-production.scss'
		),
		'placeholder-image': path.resolve(
			__dirname,
			'src/js/admin/placeholder-image.js'
		),
		seo: path.resolve( __dirname, 'src/js/admin/seo.js' ),
	},
};
