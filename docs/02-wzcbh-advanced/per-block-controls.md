---
slug: per-block-controls
title: "Per-Block Controls"
products: [code-block-highlighting]
sections: [02-wzcbh-advanced]
tags: [code-block-highlighting, block editor, inspector controls]
status: publish
order: 0
---

Each code block has its own highlighting settings, available in the **Syntax Highlighting** panel of the Inspector Controls sidebar. These override the global defaults set on the [Code Block Highlighting settings page](https://webberzone.com/plugins/webberzone-code-block-highlighting/).

[kbtoc]

## Language

A dropdown listing 40+ supported languages. Selecting a language applies the correct Prism grammar (or highlight.php language in server-side mode). Choosing **Plain Text** renders the code with the active theme's styling but no syntax highlighting.

The `wzcbh_languages` filter can be used to add or remove entries from the dropdown. Adding a language to the filter only affects the UI dropdown; the corresponding Prism.js grammar must also be available in `frontend.js` for the language to highlight correctly in client-side mode.

## Line Numbers

Toggle to show or hide line numbers on this block. When enabled, a **Start Line** field appears. Set this to any integer to begin numbering from that value — useful when showing a code excerpt that starts partway through a file.

## Title

A text field for an optional filename or label (e.g. `config.yml`). When **Show File Name** is enabled in global settings, the title appears in the toolbar above the block. If **Show File Name** is disabled globally, the title field is still saved but not displayed.

## Highlight Lines

Enter a comma-separated list of line numbers or ranges (e.g. `1,3-5,8`) to visually highlight those lines. This maps to the Prism `data-line` attribute. Available in client-side mode only; has no effect in server-side mode.

## Max Height

Set a maximum visible height in pixels. When the code block content exceeds this height, an **Expand** button appears in the toolbar; clicking it reveals the full block. Set to `0` (the default) to disable this limit. The height limit is applied as inline CSS and is not affected by the highlighting mode.

## Word Wrap

Toggle soft word wrapping for long lines. When disabled (the default), long lines scroll horizontally.

## Save as Default

After configuring the controls above, click **Save as default** at the bottom of the **Syntax Highlighting** panel to copy the current block's settings (language, line numbers, line numbers start, word wrap, max height) into the global `default-lang` setting and the equivalent global defaults. Future code blocks inserted via the editor will inherit these values.

The button calls the `POST /wzcbh/v1/default-settings` REST endpoint under the `wzcbh/v1` namespace. Only users with the `manage_options` capability can call it. See the [Developer Reference](https://webberzone.com/support/knowledgebase/code-block-highlighting-developer-reference/) for the full route schema.
