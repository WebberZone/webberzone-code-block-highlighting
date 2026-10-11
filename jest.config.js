const defaultConfig = require('@wordpress/scripts/config/jest-unit.config');

const babelTransform = Object.values(defaultConfig.transform)[0];

module.exports = {
	...defaultConfig,
	testPathIgnorePatterns: ['/node_modules/', '/test/helpers/'],
	// Several @wordpress dependencies ship ESM only (.mjs, uuid, marked).
	transform: { '\\.m?[jt]sx?$': babelTransform },
	transformIgnorePatterns: [],
	setupFiles: [
		...(defaultConfig.setupFiles || []),
		'<rootDir>/includes/blocks/src/js/test/helpers/globals.js',
	],
};
