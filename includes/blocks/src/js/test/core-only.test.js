import { parse } from '@wordpress/blocks';
import { registerCoreCode } from './helpers/setup';
import { current } from './helpers/fixtures';

// Simulates the plugin being deactivated: only core's core/code is registered.
describe('core/code without the plugin', () => {
	beforeAll(registerCoreCode);

	it.each(Object.entries(current))('%s markup is valid', (name, markup) => {
		const [block] = parse(markup);
		expect(block.isValid).toBe(true);
	});
});
