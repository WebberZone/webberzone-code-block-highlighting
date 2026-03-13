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
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/* global cbhLanguages, cbhDefaultLang, cbhDefaultSettings */

/**
 * Edit function for core/code block.
 */
const edit = ({ attributes, setAttributes }) => {
	const { content, language, lineNumbers, lineNumbersStart, wordWrap, title } =
		attributes;
	const [isSaving, setIsSaving] = useState(false);
	const [savedNotice, setSavedNotice] = useState('');

	// Apply saved defaults only on initial mount (newly inserted blocks have undefined attrs).
	useEffect(() => {
		const defaults =
			typeof cbhDefaultSettings !== 'undefined' ? cbhDefaultSettings : {};
		const updates = {};

		if (!language)
			updates.language = defaults.language || cbhDefaultLang || '';
		if (lineNumbers === undefined && defaults.lineNumbers)
			updates.lineNumbers = defaults.lineNumbers;
		if (lineNumbersStart === undefined && defaults.lineNumbersStart > 1)
			updates.lineNumbersStart = defaults.lineNumbersStart;
		if (wordWrap === undefined && defaults.wordWrap)
			updates.wordWrap = defaults.wordWrap;

		if (Object.keys(updates).length) setAttributes(updates);
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	const saveAsDefault = async () => {
		setIsSaving(true);
		setSavedNotice('');
		try {
			await apiFetch({
				path: '/wz-cbh/v1/default-settings',
				method: 'POST',
				data: {
					language: language || '',
					lineNumbers: !!lineNumbers,
					lineNumbersStart: lineNumbersStart || 1,
					wordWrap: !!wordWrap,
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
			[language ? `language-${language}` : '', wordWrap ? 'word-wrap' : '']
				.filter(Boolean)
				.join(' ') || undefined,
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
						<div className="wz-cbh-save-default">
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
								<span className="wz-cbh-save-default__notice">
									{savedNotice}
								</span>
							)}
						</div>
					</PanelRow>
				</PanelBody>
			</InspectorControls>
			<>
				{(language || title) && (
					<div className="wp-block wz-cbh-block__labels">
						{title && (
							<span className="wz-cbh-block__label-title">
								{title}
							</span>
						)}
						{language && (
							<span className="wz-cbh-block__label-lang">
								&lt;{cbhLanguages[language]}&gt;
							</span>
						)}
						{lineNumbers && (
							<span className="wz-cbh-block__label-line-num">
								#
							</span>
						)}
						{wordWrap && (
							<span className="wz-cbh-block__label-word-wrap">
								&#8629;
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

/**
 * Save function for core/code block.
 */
const save = ({ attributes }) => {
	const { language, lineNumbers, lineNumbersStart, wordWrap, title } =
		attributes;
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
};

/**
 * Replace core/code block with our enhanced version.
 */
const addSyntaxToCodeBlock = (settings) => {
	if (settings.name !== 'core/code') {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			language: {
				type: 'string',
				selector: 'code',
				source: 'attribute',
				attribute: 'lang',
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
			title: {
				type: 'string',
				source: 'attribute',
				selector: 'pre',
				attribute: 'data-title',
			},
		},
		edit,
		save,
	};
};

addFilter(
	'blocks.registerBlockType',
	'wz-cbh/code-syntax-block',
	addSyntaxToCodeBlock
);
