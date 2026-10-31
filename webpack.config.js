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
		'dashboard-colours': path.resolve( __dirname, 'src/index.js' ),
		'placeholder-image': path.resolve(
			__dirname,
			'src/js/admin/placeholder-image.js'
		),
		seo: path.resolve( __dirname, 'src/js/admin/seo.js' ),
	},
};
