/**
 * WebberZone Code Block Highlighting
 *
 * Extends the Gutenberg core/code block with Prism.js syntax highlighting
 * via block filters — no block replacement, no validation errors.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/* global cbhLanguages, cbhDefaultLang */

/**
 * 1. Add language, lineNumbers, and title attributes to core/code.
 */
addFilter(
	'blocks.registerBlockType',
	'wz-cbh/add-attributes',
	( settings, name ) => {
		if ( name !== 'core/code' ) {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				language: {
					type: 'string',
					default: '',
				},
				lineNumbers: {
					type: 'boolean',
					default: false,
				},
				title: {
					type: 'string',
					default: '',
				},
			},
		};
	}
);

/**
 * 2. Wrap the core/code edit component with an InspectorControls panel.
 */
const withHighlightingControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( props.name !== 'core/code' ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const { language, lineNumbers, title } = attributes;

		// Apply the default language from plugin settings on first insert.
		useEffect( () => {
			if ( ! language && cbhDefaultLang ) {
				setAttributes( { language: cbhDefaultLang } );
			}
		}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

		const languageOptions = [
			{
				label: __( 'Select a language', 'webberzone-code-block-highlighting' ),
				value: '',
			},
			...Object.entries( cbhLanguages ).map( ( [ value, label ] ) => ( {
				value,
				label,
			} ) ),
		];

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'Syntax Highlighting',
							'webberzone-code-block-highlighting'
						) }
						initialOpen={ true }
					>
						<PanelRow>
							<SelectControl
								label={ __(
									'Language',
									'webberzone-code-block-highlighting'
								) }
								value={ language }
								options={ languageOptions }
								onChange={ ( value ) =>
									setAttributes( { language: value } )
								}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={ __(
									'Show line numbers',
									'webberzone-code-block-highlighting'
								) }
								checked={ lineNumbers }
								onChange={ ( value ) =>
									setAttributes( { lineNumbers: value } )
								}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={ __(
									'File name or title',
									'webberzone-code-block-highlighting'
								) }
								value={ title }
								onChange={ ( value ) =>
									setAttributes( { title: value } )
								}
								placeholder={ __(
									'e.g. index.js (optional)',
									'webberzone-code-block-highlighting'
								) }
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withHighlightingControls'
);

addFilter(
	'editor.BlockEdit',
	'wz-cbh/with-highlighting-controls',
	withHighlightingControls
);

/**
 * 3. Add language and line-numbers classes to the saved <pre> element.
 *    The PHP render_block filter also adds language-* to <code> for Prism compatibility.
 */
addFilter(
	'blocks.getSaveContent.extraProps',
	'wz-cbh/add-save-props',
	( extraProps, blockType, attributes ) => {
		if ( blockType.name !== 'core/code' ) {
			return extraProps;
		}

		const classes = [ extraProps.className ];

		if ( attributes.language ) {
			classes.push( `language-${ attributes.language }` );
		}

		if ( attributes.lineNumbers ) {
			classes.push( 'line-numbers' );
		}

		const newProps = {
			...extraProps,
			className: classes.filter( Boolean ).join( ' ' ) || undefined,
		};

		if ( attributes.title ) {
			newProps[ 'data-label' ] = attributes.title;
		}

		return newProps;
	}
);
