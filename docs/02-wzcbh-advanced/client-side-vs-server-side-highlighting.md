---
slug: client-side-vs-server-side-highlighting
title: "Client-Side vs Server-Side Highlighting"
products: [code-block-highlighting]
sections: ["02-wzcbh-advanced"]
tags: [code-block-highlighting, highlight-php, prism, server-side]
status: publish
order: 0
toc: true
---

[WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/) supports two rendering modes: client-side (Prism.js in the browser) and server-side (highlight.php on the server). The mode is set globally and applies to all code blocks on the site.

[toc]

## Client-side mode (default)

<a href="https://prismjs.com/" target="_blank" rel="noopener">Prism.js</a> loads in the visitor's browser and highlights the code on page load. The Prism JS bundle (containing all bundled grammars and plugins) and the active theme CSS are enqueued on pages that contain at least one code block. Interactive toolbar features (copy-to-clipboard, expand/collapse, and the language label) are all available in this mode.

## Server-side mode

<a href="https://github.com/scrivo/highlight.php" target="_blank" rel="noopener">highlight.php</a> runs during WordPress page rendering and pre-bakes the highlighted token spans directly into the HTML. Blocks highlighted this way need no Prism.js. Pages with a block too large for the server load Prism.js for that block only (see **Large code blocks** below). The active Prism theme CSS, `frontend.css`, `hljs-server-mode.css`, and a lightweight script (`hljs-clipboard.js`) for copy-to-clipboard and expand/collapse are enqueued.

This mode is suitable for:

- Performance-focused setups that want to avoid loading Prism.js on most pages
- Sites with strict content-security policies that restrict inline scripts, with the browser fallback turned off
- AMP-compatible pages, with the browser fallback turned off

### Large code blocks

highlight.php slows sharply as a block grows, and that work runs while the page is built. Two limits keep a page with large code blocks fast:

- **Per-block limit**: blocks larger than 128KB are not highlighted on the server. Change it with the [`wzcbh_server_highlight_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_highlight_max_bytes/) filter.
- **Per-page limit**: a block that would take the code highlighted on the server during one page load past 256KB is not highlighted there. Smaller blocks after it can still fit within the limit. Change it with the [`wzcbh_server_highlight_request_budget`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_highlight_request_budget/) filter, or return 0 to turn it off.

A block over either limit is highlighted in the visitor's browser with Prism.js instead, using the same theme. Prism.js loads only on pages that contain such a block, so other pages stay free of it. The same fallback applies if highlight.php fails on a block. Blocks set to **Plain Text** or with no language are never sent to the fallback. GraphQL has no server-side grammar, so GraphQL blocks always use the fallback.

The per-page limit and the browser fallback apply to front-end pages only. In feeds, the REST API, AJAX requests, WP-CLI and admin screens, nothing runs Prism.js, so a block over the per-block limit is shown as plain text and there is no per-page limit.

The fallback has its own limits, and blocks over them are shown as plain text in the active theme:

- **Per-block limit**: 512KB. Change it with the [`wzcbh_prism_fallback_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_prism_fallback_max_bytes/) filter.
- **Per-page limit**: a block that would take the code sent to the fallback during one page load past 1MB stays plain. Smaller blocks after it can still fit. Change it with the [`wzcbh_prism_fallback_page_budget`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_prism_fallback_page_budget/) filter, or return 0 to turn it off.

To turn the fallback off and show every block over a limit as plain text, return `false` from the [`wzcbh_server_prism_fallback`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_prism_fallback/) filter:

```php
add_filter( 'wzcbh_server_prism_fallback', '__return_false' );
```

On front-end pages, setting `wzcbh_server_highlight_max_bytes` to 0 sends every block with a language to the fallback. Combine it with the filter above to show them all as plain text.

### Page caching

Server-side highlighting runs on every uncached page load. On pages with many or very large code blocks, use server-side mode together with a page caching plugin or your host's page cache, so the highlighted HTML is built once and served from the cache afterwards.

### Language detection

With **Detect Language** turned on, server-side mode highlights code blocks that have no language set, using the language highlight.php detects. Blocks set to **Plain Text** are left alone, and a block stays plain unless one language clearly matches, so command output and prose are not colored as code. Each block is detected once and the result is cached. Client-side mode does not detect languages.

Developers can change the candidate languages with [`wzcbh_auto_detect_languages`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_languages/), how strict the match must be with [`wzcbh_auto_detect_min_relevance`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_min_relevance/), and the largest block detected (16KB by default) with [`wzcbh_auto_detect_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_max_bytes/).

A front-end page detects at most 25 blocks that are not already cached. Blocks beyond that show as plain text and are detected on a later visit. Change the limit with [`wzcbh_auto_detect_request_limit`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_request_limit/) (0 turns it off).

## Visual output

Both modes load the same 21 Prism themes and produce visually identical output. The plugin remaps the token class names emitted by highlight.php to match Prism's class conventions, so the same theme CSS applies correctly in both modes. You can switch modes at any time from **Settings > Code Block Highlighting** without any visible change to your code blocks, apart from blocks over the server-side limits described above, which are highlighted in the browser or shown as plain text.

## Asset loading

In both modes, assets are only enqueued on pages that contain at least one `core/code` block. Pages without code blocks are not affected. Use the [`wzcbh_force_load_assets`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_force_load_assets/) filter to override this behavior if needed.

- **Client mode**: `frontend.css` + Prism theme CSS + `wzcbh-prism-js` (Prism bundle with all grammars and plugins)
- **Server mode**: `frontend.css` + Prism theme CSS + `hljs-server-mode.css` + `hljs-clipboard.js` (copy-to-clipboard and expand/collapse). The Prism bundle is added in the footer only on pages with a block that uses the browser fallback.

## Switching modes

Go to **Settings > Code Block Highlighting** and change **Highlighting Mode** to either **Client-side (Prism.js)** or **Server-side (highlight.php)**. No other configuration is required.

## See also

- [`wzcbh_force_load_assets`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_force_load_assets/)
- [`wzcbh_server_highlight_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_highlight_max_bytes/)
- [`wzcbh_server_highlight_request_budget`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_highlight_request_budget/)
- [`wzcbh_server_prism_fallback`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_prism_fallback/)
- [`wzcbh_prism_fallback_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_prism_fallback_max_bytes/)
- [`wzcbh_prism_fallback_page_budget`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_prism_fallback_page_budget/)
- [`wzcbh_auto_detect_languages`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_languages/)
- [`wzcbh_auto_detect_min_relevance`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_min_relevance/)
- [`wzcbh_auto_detect_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_max_bytes/)
- [`wzcbh_auto_detect_request_limit`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_request_limit/)
