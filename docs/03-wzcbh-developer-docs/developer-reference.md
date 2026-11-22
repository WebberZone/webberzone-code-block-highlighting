---
slug: code-block-highlighting-developer-reference
title: "Code Block Highlighting Developer Reference"
products: [code-block-highlighting]
sections: ["03-wzcbh-developer-docs"]
tags: [code-block-highlighting, developer, filters, hooks]
status: publish
order: 0
toc: true
---

Developer reference for [WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/).

[toc]

## PHP wrapper functions

### `wzcbh_get_settings()`

Returns all plugin settings merged with defaults.

**Returns:** `array`

---

### `wzcbh_get_option( $key, $default_value )`

Returns the value of a single setting key, or the default value if the key does not exist.

```php
$mode = wzcbh_get_option( 'highlighting-mode', 'client' );
```

**Parameters:**

- `$key` *(string)*: The setting key.
- `$default_value` *(mixed, optional)*: Value to return if the key does not exist. Default `null`.

**Returns:** `mixed`

---

### `wzcbh_update_option( $key, $value )`

Updates a single setting key in the database and in the in-memory settings array. Passing an empty, false, or null value removes the key from the settings array.

**Parameters:**

- `$key` *(string)*: The setting key.
- `$value` *(string|bool|int)*: The value to set.

**Returns:** `bool`: `true` on success, `false` on failure.

---

### `wzcbh_delete_option( $key )`

Removes a setting key from the database and from the in-memory settings array.

**Parameters:**

- `$key` *(string)*: The setting key to remove.

## Filter hooks

Each filter has its own page on [webberzone.dev](https://webberzone.dev/webberzone-code-block-highlighting/#hooks) with the full signature and examples. Hooks fired by the shared settings framework, such as `wzcbh_settings_*` and `wzcbh_wizard_*`, are not listed.

### Languages and downloads

- [`wzcbh_languages`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_languages/): add or remove entries in the block's language dropdown. The Prism.js grammar must also be available for client-side mode.
- [`wzcbh_download_extensions`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_download_extensions/): change the language-to-extension map used for downloaded snippets. A value without a leading dot is used as the whole file name.
- [`wzcbh_download_blocked_extensions`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_download_blocked_extensions/): change the executable extensions a download file name may not keep.

### Appearance and assets

- [`wzcbh_color_scheme_css_url`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_color_scheme_css_url/): replace the URL of the Prism theme stylesheet.
- [`wzcbh_dark_mode_selector`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_dark_mode_selector/): change the CSS selector that marks your site as being in dark mode. A selector with unbalanced brackets or comment markers is ignored.
- [`wzcbh_file_tab_html`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_file_tab_html/): change the HTML of the file name tab shown above a block.
- [`wzcbh_force_load_assets`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_force_load_assets/): load the plugin's styles and scripts on pages without a code block.
- [`wzcbh_wizard_steps`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_wizard_steps/): change the steps of the setup wizard.

### Server-side highlighting limits

These apply in server-side mode. See [Client-Side vs Server-Side Highlighting](https://webberzone.com/support/knowledgebase/client-side-vs-server-side-highlighting/) for how the limits work together.

| Filter | Default | Purpose |
|---|---|---|
| [`wzcbh_server_highlight_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_highlight_max_bytes/) | 128KB | Largest block highlighted on the server. On front-end pages, 0 sends every block with a language to the browser fallback. |
| [`wzcbh_server_highlight_request_budget`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_highlight_request_budget/) | 256KB | Total code highlighted on the server per page. 0 removes the limit. |
| [`wzcbh_server_prism_fallback`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_server_prism_fallback/) | `true` | Whether blocks over a server limit are highlighted in the browser. Return `false` for plain text. |
| [`wzcbh_prism_fallback_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_prism_fallback_max_bytes/) | 512KB | Largest block sent to the browser fallback. |
| [`wzcbh_prism_fallback_page_budget`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_prism_fallback_page_budget/) | 1MB | Total code sent to the browser fallback per page. 0 removes the limit. |

### Language detection

- [`wzcbh_auto_detect_languages`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_languages/): the languages detection chooses between, most likely first.
- [`wzcbh_auto_detect_min_relevance`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_min_relevance/): how strongly a language must match before it is used. Default 6.
- [`wzcbh_auto_detect_max_bytes`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_max_bytes/): the largest block detected. Default 16KB.
- [`wzcbh_auto_detect_request_limit`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_auto_detect_request_limit/): uncached detections per page. Default 25. 0 removes the limit.

## REST API

### `POST /wzcbh/v1/default-settings`

Saves a block's settings as the defaults for new code blocks. The **Save as default** button in the block sidebar calls it. Only users with the `manage_options` capability can use it.

Send only the parameters you want to change. Parameters you leave out keep their current value.

| Parameter | Type | Allowed values | Setting updated |
|---|---|---|---|
| `language` | string | A language slug | `default-lang` |
| `lineNumbers` | boolean | `true`, `false` | `default-line-numbers` |
| `lineNumbersStart` | integer | 1 to 1,000,000 | `default-line-numbers-start` |
| `wordWrap` | boolean | `true`, `false` | `default-word-wrap` |
| `maxHeight` | integer | 0 or more | `default-max-height` |
| `downloadButton` | string | `''` (use global), `show`, `hide` | `default-download-button` |
| `colorScheme` | string | `''` (use global) or a color scheme slug | `default-color-scheme` |

**Returns:** `{ "updated": [ ... ] }`, listing the parameters that were saved.
