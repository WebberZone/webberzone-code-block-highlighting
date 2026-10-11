import {
	parse,
	serialize,
	getBlockContent,
	getBlockType,
} from '@wordpress/blocks';
import { registerCoreCode } from './helpers/setup';
import { legacy, current } from './helpers/fixtures';
import '../index';

describe('core/code with the plugin', () => {
	beforeAll(registerCoreCode);

	it('keeps core save()', () => {
		expect(getBlockType('core/code').attributes.language).toEqual({
			type: 'string',
		});
	});

	it.each(Object.entries(current))('%s markup is valid', (name, markup) => {
		const [block] = parse(markup);
		expect(block.isValid).toBe(true);
		expect(getBlockContent(block)).toBe(
			markup.replace(/<!--[^>]*-->\n?/g, '').trim()
		);
	});

	it('keeps colorScheme in the block comment, not the markup', () => {
		const [block] = parse(current.colorScheme);
		expect(block.attributes.colorScheme).toBe('prism-one-light');
		const html = serialize(block);
		expect(html).toContain('"colorScheme":"prism-one-light"');
		expect(getBlockContent(block)).not.toMatch(/wzcbh-theme|prism-one-light/);
	});

	it.each(Object.entries(legacy))(
		'%s markup from 1.2.3 migrates',
		(name, [markup, expected]) => {
			const [block] = parse(markup, {
				__unstableSkipMigrationLogs: true,
			});
			expect(block.isValid).toBe(true);
			expect(block.attributes).toMatchObject(expected);
			if (!('className' in expected)) {
				return;
			}
			expect(block.attributes.className).toBe(expected.className);
		}
	);

	it.each(Object.entries(legacy))(
		'%s re-saves as core markup',
		(name, [markup]) => {
			const [block] = parse(markup, {
				__unstableSkipMigrationLogs: true,
			});
			const html = serialize(block);
			expect(html).not.toMatch(/ lang=| data-title=| data-line=| data-start=| title=|max-height|line-numbers|word-wrap|language-|wzcbh-theme/);
		}
	);
});
