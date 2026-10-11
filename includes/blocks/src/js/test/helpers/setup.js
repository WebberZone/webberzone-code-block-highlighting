import '@wordpress/block-editor';
import { registerBlockType, unregisterBlockType, getBlockType } from '@wordpress/blocks';
import { __experimentalGetCoreBlocks } from '@wordpress/block-library';

global.cbhLanguages = { php: 'PHP', javascript: 'JavaScript' };
global.cbhDefaultLang = '';
global.cbhDefaultSettings = {};

export function registerCoreCode() {
	if (getBlockType('core/code')) {
		unregisterBlockType('core/code');
	}
	__experimentalGetCoreBlocks()
		.find((block) => block.name === 'core/code')
		.init();
}
