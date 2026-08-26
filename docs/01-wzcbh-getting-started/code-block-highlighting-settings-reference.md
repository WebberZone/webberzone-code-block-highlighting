---
slug: code-block-highlighting-settings-reference
title: "Code Block Highlighting Settings Reference"
products: [code-block-highlighting]
sections: ["01-wzcbh-getting-started"]
tags: [code-block-highlighting, settings]
status: publish
toc: true
---

[toc]

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

## Download Snippet

When enabled, a **Download** button appears next to **Copy** in the code block toolbar. Clicking it saves the snippet as a file. Setting key: `download-button`. Default: enabled.

The file is named after the block's **File name or title** when one is set; otherwise it falls back to `snippet.{ext}`, where the extension comes from the block's language (`snippet.js`, `snippet.py`, `snippet.txt` for plain text). Docker blocks download as `Dockerfile`.

Individual blocks can override this setting from the **Download button** control in the block sidebar.

## Show Language Label

When enabled, the programming language name is displayed in the toolbar above each code block. Setting key: `show-language-label`. Default: enabled.

## Show File Name

When enabled, the file name or title is displayed above each code block, provided a title has been set on the block. Setting key: `show-file-name`. Default: enabled.

## File Name Style

Controls how the file name is displayed when **Show File Name** is enabled. Setting key: `file-name-style`. Default: `tab`.

- **Tab above the code block** (`tab`) — an editor-style tab sits flush on top of the block. Its background and text color are taken from the active Prism theme, so the tab matches the block below it across all 21 themes. The markup is rendered by PHP in both highlighting modes.
- **Toolbar label** (`toolbar`) — the file name appears as a label in the hover toolbar, next to the language label and copy button.

## Default Language

The language pre-selected when a new code block is inserted in the editor. Leave this field blank to insert new code blocks with no language pre-selected. Setting key: `default-lang`.

## Font Size (px)

Font size in pixels for code blocks. Set to `0` to inherit the font size from the active theme. Setting key: `font-size`.
