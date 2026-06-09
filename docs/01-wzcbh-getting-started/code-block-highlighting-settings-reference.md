---
slug: code-block-highlighting-settings-reference
title: "Code Block Highlighting Settings Reference"
products: [code-block-highlighting]
sections: [01-wzcbh-getting-started]
tags: [code-block-highlighting, settings]
status: publish
order: 0
---

[kbtoc]

All [WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/) settings are available at **Settings > Code Block Highlighting**. Settings are stored in a single WordPress option: `wzcbh_settings`.

## Highlighting Mode

Controls whether syntax highlighting runs in the browser or on the server. Setting key: `highlighting-mode`.

- **Client-side (Prism.js)** *(default)* — Prism.js runs in the browser. This mode supports interactive features such as copy-to-clipboard and expand/collapse.
- **Server-side (highlight.php)** — highlight.php pre-renders syntax token spans on the server before the page is sent to the browser. No JavaScript is loaded for highlighting in this mode.

Both modes use the same 21 Prism themes.

## Color Scheme

Selects the syntax highlighting theme applied to all code blocks. The same Prism theme is used in both client-side and server-side modes. Setting key: `color-scheme`. Default: **One Dark**.

## Copy to Clipboard

When enabled, a **Copy** button appears in the code block toolbar. Visitors can copy the entire code snippet with one click. Setting key: `copy-to-clipboard`. Default: enabled.

## Show Language Label

When enabled, the programming language name is displayed in the toolbar above each code block. Setting key: `show-language-label`. Default: enabled.

## Show File Name

When enabled, the file name or title is displayed in the toolbar above each code block, provided a title has been set on the block. Setting key: `show-file-name`. Default: enabled.

## Default Language

The language pre-selected when a new code block is inserted in the editor. Leave this field blank to insert new code blocks with no language pre-selected. Setting key: `default-lang`.

## Font Size (px)

Font size in pixels for code blocks. Set to `0` to inherit the font size from the active theme. Setting key: `font-size`.
