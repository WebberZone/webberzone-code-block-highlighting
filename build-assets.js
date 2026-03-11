/**
 * Build script: copies Prism theme CSS files from node_modules into assets/.
 *
 * Run via: npm run build:assets
 */

const fs = require( 'fs' );
const path = require( 'path' );

const root = __dirname;

const themes = [
	{
		src: 'node_modules/prism-themes/themes/prism-a11y-dark.css',
		dest: 'assets/prism-a11y-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-atom-dark.css',
		dest: 'assets/prism-atom-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-darcula.css',
		dest: 'assets/prism-darcula.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-dracula.css',
		dest: 'assets/prism-dracula.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-ghcolors.css',
		dest: 'assets/prism-ghcolors.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-gruvbox-dark.css',
		dest: 'assets/prism-gruvbox-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-gruvbox-light.css',
		dest: 'assets/prism-gruvbox-light.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-material-dark.css',
		dest: 'assets/prism-material-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-material-oceanic.css',
		dest: 'assets/prism-material-oceanic.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-night-owl.css',
		dest: 'assets/prism-night-owl.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-nord.css',
		dest: 'assets/prism-nord.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-one-dark.css',
		dest: 'assets/prism-onedark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-one-light.css',
		dest: 'assets/prism-one-light.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-shades-of-purple.css',
		dest: 'assets/prism-shades-of-purple.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-solarized-dark-atom.css',
		dest: 'assets/prism-solarized-dark-atom.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-synthwave84.css',
		dest: 'assets/prism-synthwave84.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-vs.css',
		dest: 'assets/prism-vs.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-vsc-dark-plus.css',
		dest: 'assets/prism-vsc-dark-plus.css',
	},
];

let success = true;

themes.forEach( ( { src, dest } ) => {
	const srcPath = path.resolve( root, src );
	const destPath = path.resolve( root, dest );

	if ( ! fs.existsSync( srcPath ) ) {
		console.error( `✖ Source not found: ${ src }` );
		success = false;
		return;
	}

	fs.mkdirSync( path.dirname( destPath ), { recursive: true } );
	fs.copyFileSync( srcPath, destPath );
	console.log( `✔ ${ src } → ${ dest }` );
} );

if ( ! success ) {
	process.exit( 1 );
}
