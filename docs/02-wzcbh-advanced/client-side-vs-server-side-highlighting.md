---
slug: client-side-vs-server-side-highlighting
title: "Client-Side vs Server-Side Highlighting"
products: [code-block-highlighting]
sections: [02-wzcbh-advanced]
tags: [code-block-highlighting, server-side, prism, highlight.php]
status: publish
order: 0
---

[WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/) supports two rendering modes: client-side (Prism.js in the browser) and server-side (highlight.php on the server). The mode is set globally and applies to all code blocks on the site.

[kbtoc]

## Client-side mode (default)

<a href="https://prismjs.com/" target="_blank" rel="noopener">Prism.js</a> loads in the visitor's browser and highlights the code on page load. The Prism JS bundle (containing all bundled grammars and plugins) and the active theme CSS are enqueued on pages that contain at least one code block. Interactive toolbar features — copy-to-clipboard, expand/collapse, and the language label — are all available in this mode.

## Server-side mode

<a href="https://github.com/scrivo/highlight.php" target="_blank" rel="noopener">highlight.php</a> runs during WordPress page rendering and pre-bakes the highlighted token spans directly into the HTML. No Prism.js is loaded — syntax highlighting runs entirely on the server. The active Prism theme CSS, `frontend.css`, `hljs-server-mode.css`, and a lightweight script (`hljs-clipboard.js`) for copy-to-clipboard and expand/collapse are enqueued.

This mode is suitable for:

- Performance-focused setups that want to avoid loading Prism.js
- Sites with strict content-security policies that restrict inline scripts
- AMP-compatible pages

The **Highlight Lines** per-block control has no effect in server-side mode.

## Visual output

Both modes load the same 21 Prism themes and produce visually identical output. The plugin remaps the token class names emitted by highlight.php to match Prism's class conventions, so the same theme CSS applies correctly in both modes. You can switch modes at any time from **Settings > Code Block Highlighting** without any visible change to your code blocks.

## Asset loading

In both modes, assets are only enqueued on pages that contain at least one `core/code` block. Pages without code blocks are not affected. Use the `wzcbh_force_load_assets` filter to override this behavior if needed.

- **Client mode**: `frontend.css` + Prism theme CSS + `wzcbh-prism-js` (Prism bundle with all grammars and plugins)
- **Server mode**: `frontend.css` + Prism theme CSS + `hljs-server-mode.css` + `hljs-clipboard.js` (copy-to-clipboard and expand/collapse)

## Switching modes

Go to **Settings > Code Block Highlighting** and change **Highlighting Mode** to either **Client-side (Prism.js)** or **Server-side (highlight.php)**. No other configuration is required.
