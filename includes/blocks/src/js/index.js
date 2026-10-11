/**
 * WebberZone Code Block Highlighting
 *
 * Extends the Gutenberg core/code block with Prism.js syntax highlighting.
 */

import '../css/editor.css';
import { addFilter } from '@wordpress/hooks';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { select } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/* global cbhLanguages, cbhDefaultLang, cbhDefaultSettings, cbhColorSchemes */

// Blocks already given defaults; moving a block remounts its edit component.
const initialisedBlocks = new Set();

/**
 * Edit function for core/code block.
 */
const edit = ({ attributes, setAttributes, clientId }) => {
	const {
		content,
		language,
		lineNumbers,
		lineNumbersStart,
		wordWrap,
		title,
		_legacyTitle,
		highlightLines,
		maxHeight,
		downloadButton,
		colorScheme,
	} = attributes;
	const [isSaving, setIsSaving] = useState(false);
	const [savedNotice, setSavedNotice] = useState('');

	useEffect(() => {
		const updates = {};

		// Parsed blocks carry originalContent; only newly inserted ones take the saved defaults.
		const isNew =
			!initialisedBlocks.has(clientId) &&
			select('core/block-editor').getBlock(clientId)?.originalContent ===
				undefined;
		initialisedBlocks.add(clientId);

		if (isNew) {
			const defaults =
				typeof cbhDefaultSettings !== 'undefined'
					? cbhDefaultSettings
					: {};
			const defaultLang = defaults.language || cbhDefaultLang;

			if (!language && defaultLang) updates.language = defaultLang;
			if (lineNumbers === undefined && defaults.lineNumbers)
				updates.lineNumbers = true;
			if (lineNumbersStart === undefined && defaults.lineNumbersStart > 1)
				updates.lineNumbersStart = defaults.lineNumbersStart;
			if (wordWrap === undefined && defaults.wordWrap)
				updates.wordWrap = true;
			if (maxHeight === undefined && defaults.maxHeight > 0)
				updates.maxHeight = defaults.maxHeight;
			if (downloadButton === undefined && defaults.downloadButton)
				updates.downloadButton = defaults.downloadButton;
			if (colorScheme === undefined && defaults.colorScheme)
				updates.colorScheme = defaults.colorScheme;
		}

		// Migrate title from old code-syntax-block format (pre[title]) to ours
		// (pre[data-title]). Mirrors the toolbar JS: getAttribute('data-title') ||
		// getAttribute('title'). On first save the block adopts our format.
		if (!title && _legacyTitle) updates.title = _legacyTitle;

		if (Object.keys(updates).length) setAttributes(updates);
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	const saveAsDefault = async () => {
		setIsSaving(true);
		setSavedNotice('');
		try {
			await apiFetch({
				path: '/wzcbh/v1/default-settings',
				method: 'POST',
				data: {
					language: language || '',
					lineNumbers: !!lineNumbers,
					lineNumbersStart: lineNumbersStart || 1,
					wordWrap: !!wordWrap,
					maxHeight: maxHeight || 0,
					downloadButton: downloadButton || '',
					colorScheme: colorScheme || '',
				},
			});
			setSavedNotice(
				__('Saved!', 'webberzone-code-block-highlighting')
			);
			setTimeout(() => setSavedNotice(''), 2000);
		} catch (e) {
			setSavedNotice(
				__('Error saving.', 'webberzone-code-block-highlighting')
			);
		} finally {
			setIsSaving(false);
		}
	};

	const blockProps = useBlockProps({
		className:
			[
				language ? `language-${language}` : '',
				wordWrap ? 'word-wrap' : '',
				colorScheme ? `wzcbh-theme-${colorScheme}` : '',
			]
				.filter(Boolean)
				.join(' ') || undefined,
		style: maxHeight
			? { maxHeight: `${maxHeight}px`, overflowY: 'auto' }
			: undefined,
	});

	return (
		<>
			<InspectorControls key="controls">
				<PanelBody
					title={__(
						'Syntax Highlighting',
						'webberzone-code-block-highlighting'
					)}
					initialOpen={true}
				>
					<PanelRow>
						<SelectControl
							label={__(
								'Language',
								'webberzone-code-block-highlighting'
							)}
							value={language || ''}
							options={[
								{
									label: __(
										'Select a language',
										'webberzone-code-block-highlighting'
									),
									value: '',
								},
							].concat(
								Object.keys(cbhLanguages).map((lang) => ({
									label: cbhLanguages[lang],
									value: lang,
								}))
							)}
							onChange={(lang) =>
								setAttributes({ language: lang })
							}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__(
								'File name or title',
								'webberzone-code-block-highlighting'
							)}
							value={title || ''}
							onChange={(str) => setAttributes({ title: str })}
							placeholder={__(
								'e.g. index.js (optional)',
								'webberzone-code-block-highlighting'
							)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={__(
								'Show line numbers',
								'webberzone-code-block-highlighting'
							)}
							checked={!!lineNumbers}
							onChange={(state) =>
								setAttributes({ lineNumbers: state })
							}
						/>
					</PanelRow>
					{lineNumbers && (
						<PanelRow>
							<TextControl
								type="number"
								label={__(
									'Start line number',
									'webberzone-code-block-highlighting'
								)}
								value={lineNumbersStart || 1}
								min={1}
								onChange={(val) =>
									setAttributes({
										lineNumbersStart:
											parseInt(val, 10) || 1,
									})
								}
							/>
						</PanelRow>
					)}
					<PanelRow>
						<ToggleControl
							label={__(
								'Word wrap',
								'webberzone-code-block-highlighting'
							)}
							checked={!!wordWrap}
							onChange={(state) =>
								setAttributes({ wordWrap: state })
							}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__(
								'Highlight lines',
								'webberzone-code-block-highlighting'
							)}
							value={highlightLines || ''}
							onChange={(val) =>
								setAttributes({
									highlightLines: val || undefined,
								})
							}
							placeholder={__(
								'e.g. 1,3-5 (optional)',
								'webberzone-code-block-highlighting'
							)}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							type="number"
							label={__(
								'Max height (px)',
								'webberzone-code-block-highlighting'
							)}
							value={maxHeight || ''}
							min={0}
							onChange={(val) =>
								setAttributes({
									maxHeight:
										parseInt(val, 10) || undefined,
								})
							}
							placeholder={__(
								'e.g. 400 (optional)',
								'webberzone-code-block-highlighting'
							)}
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label={__(
								'Download button',
								'webberzone-code-block-highlighting'
							)}
							help={__(
								'Override the global "Download Snippet" setting for this block.',
								'webberzone-code-block-highlighting'
							)}
							value={downloadButton || ''}
							options={[
								{
									label: __(
										'Use global setting',
										'webberzone-code-block-highlighting'
									),
									value: '',
								},
								{
									label: __(
										'Show',
										'webberzone-code-block-highlighting'
									),
									value: 'show',
								},
								{
									label: __(
										'Hide',
										'webberzone-code-block-highlighting'
									),
									value: 'hide',
								},
							]}
							onChange={(val) =>
								setAttributes({
									downloadButton: val || undefined,
								})
							}
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label={__(
								'Color scheme',
								'webberzone-code-block-highlighting'
							)}
							help={__(
								'Override the global color scheme for this block.',
								'webberzone-code-block-highlighting'
							)}
							value={colorScheme || ''}
							options={[
								{
									label: __(
										'Use global setting',
										'webberzone-code-block-highlighting'
									),
									value: '',
								},
							].concat(
								Object.keys(cbhColorSchemes).map((slug) => ({
									label: cbhColorSchemes[slug],
									value: slug,
								}))
							)}
							onChange={(val) =>
								setAttributes({
									colorScheme: val || undefined,
								})
							}
						/>
					</PanelRow>
					<PanelRow>
						<div className="wzcbh-save-default">
							<Button
								variant="secondary"
								isBusy={isSaving}
								onClick={saveAsDefault}
							>
								{__(
									'Save as default',
									'webberzone-code-block-highlighting'
								)}
							</Button>
							{savedNotice && (
								<span className="wzcbh-save-default__notice">
									{savedNotice}
								</span>
							)}
						</div>
					</PanelRow>
				</PanelBody>
			</InspectorControls>
			<>
				{(language || title || highlightLines || maxHeight) && (
					<div className="wp-block wzcbh-block__labels">
						{title && (
							<span className="wzcbh-block__label-title">
								{title}
							</span>
						)}
						{language && (
							<span className="wzcbh-block__label-lang">
								&lt;{cbhLanguages[language]}&gt;
							</span>
						)}
						{lineNumbers && (
							<span className="wzcbh-block__label-line-num">
								#
							</span>
						)}
						{wordWrap && (
							<span className="wzcbh-block__label-word-wrap">
								&#8629;
							</span>
						)}
						{highlightLines && (
							<span className="wzcbh-block__label-highlight">
								&#9776;
							</span>
						)}
						{maxHeight && (
							<span className="wzcbh-block__label-max-height">
								&#8597;
							</span>
						)}
					</div>
				)}
				<pre {...blockProps}>
					<RichText
						tagName="code"
						value={content || ''}
						onChange={(text) =>
							setAttributes({ content: text })
						}
						placeholder={__('Write code…')}
						aria-label={__('Code')}
						preserveWhiteSpace={true}
						allowedFormats={[]}
						withoutInteractiveFormatting={true}
						__unstablePastePlainText={true}
					/>
				</pre>
			</>
		</>
	);
};

const MANAGED_CLASS = /^(line-numbers|word-wrap|language-.+)$/;

/**
 * Deprecation for markup saved by this plugin up to 1.2.3, whose save() wrote
 * the highlighting options into the HTML instead of leaving core's markup.
 */
const pluginSaveDeprecation = (blockAttributes, supports) => ({
	// Deprecations don't inherit supports; without them the color, spacing and
	// typography classes and styles are missing from the regenerated markup.
	supports,
	attributes: {
		...blockAttributes,
		language: {
			type: 'string',
			selector: 'code',
			source: 'attribute',
			attribute: 'lang',
		},
		title: {
			type: 'string',
			source: 'attribute',
			selector: 'pre',
			attribute: 'data-title',
		},
	},
	// Blocks with only line numbers or word wrap still validate against core's
	// save once the parser folds those classes into className, so opt them in.
	isEligible: (parsedAttributes, innerBlocks, { block }) => {
		const custom = (parsedAttributes.className || '').split(/\s+/);
		return (block?.attributes?.className || '')
			.split(/\s+/)
			.some((name) => MANAGED_CLASS.test(name) && !custom.includes(name));
	},
	save: ({ attributes }) => {
		const {
			language,
			lineNumbers,
			lineNumbersStart,
			wordWrap,
			title,
			highlightLines,
			maxHeight,
		} = attributes;
		const codeClassName = language ? `language-${language}` : undefined;
		const preClassName = [
			language ? `language-${language}` : '',
			lineNumbers ? 'line-numbers' : '',
			wordWrap ? 'word-wrap' : '',
		]
			.filter(Boolean)
			.join(' ');

		const blockProps = useBlockProps.save({
			className: preClassName || undefined,
			'data-title': title || undefined,
			...(lineNumbers && lineNumbersStart && lineNumbersStart !== 1
				? { 'data-start': lineNumbersStart }
				: {}),
			...(highlightLines ? { 'data-line': highlightLines } : {}),
			...(maxHeight
				? { style: { maxHeight: `${maxHeight}px`, overflowY: 'auto' } }
				: {}),
		});

		return (
			<pre {...blockProps}>
				<RichText.Content
					tagName="code"
					value={
						typeof attributes.content === 'string'
							? attributes.content
							: attributes.content?.toHTMLString?.({
								preserveWhiteSpace: true,
							}) ?? ''
					}
					lang={language || undefined}
					className={codeClassName}
				/>
			</pre>
		);
	},
});

/**
 * Deprecation for markup saved by the old code-syntax-block plugin.
 */
const codeSyntaxBlockDeprecation = (contentAttribute, supports) => ({
	supports,
	attributes: {
		content: contentAttribute,
		language: {
			type: 'string',
			selector: 'code',
			source: 'attribute',
			attribute: 'lang',
		},
		lineNumbers: {
			type: 'boolean',
		},
		title: {
			type: 'string',
			source: 'attribute',
			selector: 'pre',
			attribute: 'title',
		},
	},
	save: ({ attributes }) => {
		const { language, lineNumbers, title } = attributes;
		const codeClassName = [
			language ? `language-${language}` : '',
			lineNumbers ? 'line-numbers' : '',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<pre {...useBlockProps.save()} title={title || undefined}>
				<RichText.Content
					tagName="code"
					value={
						typeof attributes.content === 'string'
							? attributes.content
							: attributes.content?.toHTMLString?.({
								preserveWhiteSpace: true,
							}) ?? ''
					}
					lang={language || undefined}
					className={codeClassName || undefined}
				/>
			</pre>
		);
	},
});

/**
 * Extend core/code with our attributes and editor UI, keeping core's save().
 */
const addSyntaxToCodeBlock = (settings, name, deprecation) => {
	// WordPress also runs this filter on each deprecation; leave their save intact.
	if (name !== 'core/code' || deprecation) {
		return settings;
	}

	const attributes = {
		...settings.attributes,
		language: {
			type: 'string',
		},
		lineNumbers: {
			type: 'boolean',
		},
		lineNumbersStart: {
			type: 'number',
		},
		wordWrap: {
			type: 'boolean',
		},
		highlightLines: {
			type: 'string',
		},
		maxHeight: {
			type: 'number',
		},
		// Tri-state override of the global download setting: undefined
		// inherits it, 'show' and 'hide' force it.
		downloadButton: {
			type: 'string',
		},
		// Registered scheme slug; undefined inherits the global scheme.
		colorScheme: {
			type: 'string',
		},
		title: {
			type: 'string',
		},
		// Read-only fallback: sources title from pre[title] as saved by the
		// old code-syntax-block plugin. Migrated to `title` on first edit.
		_legacyTitle: {
			type: 'string',
			source: 'attribute',
			selector: 'pre',
			attribute: 'title',
		},
	};

	return {
		...settings,
		attributes,
		edit,
		deprecated: [
			pluginSaveDeprecation(attributes, settings.supports),
			codeSyntaxBlockDeprecation(
				settings.attributes.content,
				settings.supports
			),
			...(settings.deprecated || []),
		],
	};
};

addFilter(
	'blocks.registerBlockType',
	'wzcbh/code-syntax-block',
	addSyntaxToCodeBlock
);
