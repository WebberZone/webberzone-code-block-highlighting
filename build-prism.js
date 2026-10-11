/**
 * Build script: copies Prism theme CSS files from node_modules into includes/assets/,
 * and writes a copy of each scoped under `.wzcbh-theme-{slug}` into includes/assets/scoped/
 * for per-block colour scheme overrides.
 *
 * Run via: npm run build:prism
 */

const fs = require( 'fs' );
const path = require( 'path' );
const postcss = require( 'postcss' );

const root = __dirname;

const themes = [
	{
		src: 'node_modules/prism-themes/themes/prism-a11y-dark.css',
		dest: 'includes/assets/prism-a11y-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-coldark-cold.css',
		dest: 'includes/assets/prism-coldark-cold.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-coldark-dark.css',
		dest: 'includes/assets/prism-coldark-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-dracula.css',
		dest: 'includes/assets/prism-dracula.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-ghcolors.css',
		dest: 'includes/assets/prism-ghcolors.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-gruvbox-dark.css',
		dest: 'includes/assets/prism-gruvbox-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-gruvbox-light.css',
		dest: 'includes/assets/prism-gruvbox-light.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-material-dark.css',
		dest: 'includes/assets/prism-material-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-lucario.css',
		dest: 'includes/assets/prism-lucario.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-night-owl.css',
		dest: 'includes/assets/prism-night-owl.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-nord.css',
		dest: 'includes/assets/prism-nord.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-one-dark.css',
		dest: 'includes/assets/prism-onedark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-one-light.css',
		dest: 'includes/assets/prism-one-light.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-shades-of-purple.css',
		dest: 'includes/assets/prism-shades-of-purple.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-solarized-dark-atom.css',
		dest: 'includes/assets/prism-solarized-dark-atom.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-synthwave84.css',
		dest: 'includes/assets/prism-synthwave84.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-duotone-dark.css',
		dest: 'includes/assets/prism-duotone-dark.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-duotone-light.css',
		dest: 'includes/assets/prism-duotone-light.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-material-light.css',
		dest: 'includes/assets/prism-material-light.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-xonokai.css',
		dest: 'includes/assets/prism-xonokai.css',
	},
	{
		src: 'node_modules/prism-themes/themes/prism-vsc-dark-plus.css',
		dest: 'includes/assets/prism-vsc-dark-plus.css',
	},
];

const BASE_SELECTOR = /^(?:code|pre)\[class\*=["']?language-["']?\]$/;

/**
 * Scope every rule of a theme under its block class.
 *
 * The leading reset restores frontend.css defaults the global theme would otherwise leak; scoped theme rules tie with it and win on order.
 *
 * @param {string} css   Theme CSS.
 * @param {string} scope Scope selector, e.g. `.wzcbh-theme-prism-nord`.
 * @return {string} Scoped CSS.
 */
function scopeTheme( css, scope ) {
	const ast = postcss.parse( css );

	ast.walkRules( ( rule ) => {
		if ( rule.parent.type === 'atrule' && /keyframes$/i.test( rule.parent.name ) ) {
			return;
		}
		// Beats a global Synthwave '84's `background-color: transparent !important`.
		if ( rule.selectors.some( ( selector ) => BASE_SELECTOR.test( selector.trim() ) ) ) {
			rule.walkDecls( /^background(-color)?$/i, ( decl ) => {
				if ( ! /^(none|transparent)$/i.test( decl.value.trim() ) ) {
					decl.important = true;
				}
			} );
		}
		rule.selectors = rule.selectors.map( ( selector ) => `${ scope } ${ selector }` );
	} );

	const reset = `${ scope } :is(code, pre)[class*="language-"] {
	color: inherit;
	background: none;
	text-shadow: none;
	font-style: normal;
	font-weight: normal;
}

${ scope } .token {
	color: inherit;
	background: none;
	text-shadow: inherit;
	font-style: inherit;
	font-weight: inherit;
}

${ scope } div.code-toolbar > .toolbar.toolbar > .toolbar-item > :is(a, button, span) {
	color: var(--wzcbh-toolbar-color);
	background: var(--wzcbh-toolbar-bg);
}

${ scope } div.code-toolbar > .toolbar.toolbar > .toolbar-item > :is(a, button, span):is(:hover, :focus) {
	color: var(--wzcbh-toolbar-color);
	background: var(--wzcbh-toolbar-bg-hover);
}

${ scope } .line-highlight.line-highlight {
	background: linear-gradient(to right, hsla(24, 85%, 55%, 0.28) 70%, hsla(24, 85%, 55%, 0.04));
	box-shadow: inset 3px 0 0 hsla(24, 90%, 52%, 0.9);
}

${ scope } .line-numbers.line-numbers .line-numbers-rows {
	border-right-color: #999;
}

${ scope } .line-numbers .line-numbers-rows > span:before {
	color: #999;
}
`;

	return `/* Generated by build-prism.js. Do not edit. */\n${ reset }\n${ ast.toString() }`;
}

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

	const slug = path.basename( dest, '.css' );
	const scopedPath = path.resolve( root, 'includes/assets/scoped', `${ slug }.css` );

	fs.mkdirSync( path.dirname( scopedPath ), { recursive: true } );
	fs.writeFileSync(
		scopedPath,
		scopeTheme( fs.readFileSync( srcPath, 'utf8' ), `.wzcbh-theme-${ slug }` )
	);
	console.log( `✔ ${ src } → includes/assets/scoped/${ slug }.css` );
} );

if ( ! success ) {
	process.exit( 1 );
}
