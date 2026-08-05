---
slug: code-block-highlighting-developer-reference
title: "Code Block Highlighting Developer Reference"
products: [code-block-highlighting]
sections: [03-wzcbh-developer-docs]
tags: [code-block-highlighting, developer, filters, hooks]
status: publish
order: 0
---

Developer reference for [WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/).

[kbtoc]

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

- `$key` *(string)* — The setting key.
- `$default_value` *(mixed, optional)* — Value to return if the key does not exist. Default `null`.

**Returns:** `mixed`

---

### `wzcbh_update_option( $key, $value )`

Updates a single setting key in the database and in the in-memory settings array. Passing an empty, false, or null value removes the key from the settings array.

**Parameters:**

- `$key` *(string)* — The setting key.
- `$value` *(string|bool|int)* — The value to set.

**Returns:** `bool` — `true` on success, `false` on failure.

---

### `wzcbh_delete_option( $key )`

Removes a setting key from the database and from the in-memory settings array.

**Parameters:**

- `$key` *(string)* — The setting key to remove.

**Returns:** `bool`

---

### `wzcbh_settings_defaults()`

Returns the default values for all registered settings.

**Returns:** `array`

---

### `wzcbh_get_default_option( $key )`

Returns the default value for a single setting key.

**Parameters:**

- `$key` *(string)* — The setting key.

**Returns:** `mixed`

---

### `wzcbh_settings_reset()`

Resets all settings to their defaults.

**Returns:** `bool`

---

### `wzcbh_update_settings( $settings, $merge, $autoload )`

Saves a full settings array to the database.

**Parameters:**

- `$settings` *(array)* — Settings array to save.
- `$merge` *(bool, optional)* — Whether to merge with existing settings. Default `true`.
- `$autoload` *(bool, optional)* — Whether to autoload the option. Default `true`.

**Returns:** `bool`

---

## Filter hooks

### [`wzcbh_languages`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_languages/)

Filters the list of languages shown in the block editor language picker. The array maps language slugs to display labels.

```php
add_filter( 'wzcbh_languages', function( array $languages ): array {
    $languages['cobol'] = 'COBOL';
    return $languages;
} );
```

**Parameters:**

- `$languages` *(array)* — Associative array of `slug => label` pairs.

Adding a language to this filter only affects the editor dropdown. The corresponding Prism.js grammar must also be available in the frontend bundle; adding a slug without a matching grammar import in `frontend.js` produces plain-text output.

---

### [`wzcbh_color_scheme_css_url`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_color_scheme_css_url/)

Filters the URL of the Prism theme CSS file enqueued on the frontend.

```php
add_filter( 'wzcbh_color_scheme_css_url', function( string $url ): string {
    return get_stylesheet_directory_uri() . '/prism-custom.css';
} );
```

**Parameters:**

- `$url` *(string)* — The absolute URL to the theme CSS file.

**Returns:** `string`

---

### [`wzcbh_force_load_assets`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_force_load_assets/)

Filters whether Prism assets should load on every page, regardless of whether a code block is present.

```php
add_filter( 'wzcbh_force_load_assets', '__return_true' );
```

**Parameters:**

- `$force` *(bool)* — Whether to force-load assets. Default `false`.

**Returns:** `bool`

---

### [`wzcbh_file_tab_html`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_file_tab_html/)

Filters the file name tab markup rendered above a code block. Applies in both highlighting modes, and only when **File Name Style** is set to *Tab above the code block*.

```php
add_filter( 'wzcbh_file_tab_html', function( string $tab, string $title, string $language ): string {
    return '<div class="wzcbh-file-tab"><span class="wzcbh-file-tab__name">'
        . esc_html( strtoupper( $title ) ) . '</span></div>';
}, 10, 3 );
```

**Parameters:**

- `$tab` *(string)* — The tab HTML.
- `$title` *(string)* — The file name or title set on the block.
- `$language` *(string)* — The block's language slug.

**Returns:** `string`

---

### `wzcbh_download_extensions`

Filters the map of language slugs to the file extension used when a snippet is downloaded. Applies in both highlighting modes, and only when the block has no **File name or title** set — a title always wins over the derived name.

Values starting with a dot are appended to `snippet` (`.py` becomes `snippet.py`); a value without a leading dot is used as the complete file name, which is how `docker` yields `Dockerfile`. A language missing from the map falls back to `snippet.txt`.

```php
add_filter( 'wzcbh_download_extensions', function( array $extensions, string $language ): array {
    $extensions['yaml']   = '.yaml';
    $extensions['nginx']  = 'nginx.conf';
    return $extensions;
}, 10, 2 );
```

**Parameters:**

- `$extensions` *(array)* — Language slug => extension (or complete file name).
- `$language` *(string)* — The language slug being resolved.

**Returns:** `array`

---

## JavaScript objects

The plugin exposes several JavaScript globals via `wp_add_inline_script()`. They are read by the editor bundle and the frontend bundles.

### `cbhLanguages`

Available in the block editor. Maps language slugs to display labels.

```js
cbhLanguages.javascript // "JavaScript"
cbhLanguages.php        // "PHP"
```

The same array is exposed through the `wzcbh_languages` filter.

### `cbhDefaultLang`

Available in the block editor. The slug pre-selected on new code blocks, from the `default-lang` setting. Empty string when no default is configured.

### `cbhDefaultSettings`

Available in the block editor. Object containing the per-block defaults applied to a fresh code block:

```js
cbhDefaultSettings.language         // Default language slug.
cbhDefaultSettings.lineNumbers      // Whether line numbers are on by default.
cbhDefaultSettings.lineNumbersStart // Default starting line number (1).
cbhDefaultSettings.wordWrap         // Whether soft word wrap is on by default.
cbhDefaultSettings.maxHeight        // Default max height in pixels (0 = unlimited).
```

### `cbhSettings`

Available on the frontend in client-side mode (inlined before the `wzcbh-prism-js` script). Toggles the toolbar features that the frontend bundle reads at runtime:

```js
cbhSettings.copyToClipboard   // Show the Copy button.
cbhSettings.showLanguageLabel // Show the language label in the toolbar.
cbhSettings.showFileName      // Show the file-name label in the toolbar.
cbhSettings.fileNameStyle     // "tab" or "toolbar".
```

The download button is not listed here. PHP resolves the global **Download Snippet** setting and the block's own override into a single `data-wzcbh-download` attribute on the `<pre>` element, whose value is the file name to save as. The frontend bundle renders the button when the attribute is present. In server-side mode the same value is carried on the button itself, since PHP emits the toolbar directly.

### `wzcbhI18n`

Available on the frontend in server-side mode (inlined before the `wzcbh-hljs-clipboard` script). Translation strings used by the toolbar:

```js
wzcbhI18n.copy        // "Copy"
wzcbhI18n.copied      // "Copied!"
wzcbhI18n.copySuccess // "Copied code to clipboard."
wzcbhI18n.copyError   // "Unable to copy code to clipboard."
wzcbhI18n.expand      // "Expand"
wzcbhI18n.collapse    // "Collapse"
wzcbhI18n.downloaded  // "Downloaded code as %s." (%s is the file name)
```

---

## Adding a language

To add a custom language to the block editor picker and to the Prism frontend bundle:

1. Add `import 'prismjs/components/prism-{slug}'` to `includes/blocks/src/js/frontend.js` (respect Prism dependency order).
2. Add `'slug' => 'Label'` to `get_languages()` in `includes/frontend/class-blocks.php`.
3. Run `npm run build` to rebuild the frontend bundle.

To add a language only to the editor UI (using an externally loaded grammar, for example), use the `wzcbh_languages` filter instead and load the grammar separately.

---

## Adding a Prism theme

To add a custom Prism theme to the color scheme selector:

1. Add the theme mapping to `build-prism.js`.
2. Copy the theme CSS file to `includes/assets/`.
3. Register the slug and label in `includes/admin/class-settings.php`.
4. Run `npm run build:prism` to register the theme.

To use a custom theme without modifying the plugin, use the `wzcbh_color_scheme_css_url` filter to point to any CSS file.

---

## Script and style handles

### Client-side mode

- `wzcbh-prism-css` — `frontend.css`; toolbar, line-numbers, and layout CSS. Loaded on pages with code blocks.
- `wzcbh-prism-theme` — the active Prism theme CSS. Loaded on pages with code blocks.
- `wzcbh-prism-js` — the Prism JS bundle (grammars + plugins). Loaded on pages with code blocks.

### Server-side mode

- `wzcbh-prism-css` — `frontend.css`; toolbar, line-numbers, and layout CSS. Loaded on pages with code blocks.
- `wzcbh-prism-theme` — the active Prism theme CSS. Loaded on pages with code blocks.
- `wzcbh-hljs-server` — `hljs-server-mode.css`; handles highlighted line styling in server mode.
- `wzcbh-hljs-clipboard` — copy-to-clipboard and expand/collapse script for server mode.

### Editor

- `wzcbh-editor` — block editor JS (Inspector Controls, language picker).

## See also

- [`wzcbh_languages`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_languages/)
- [`wzcbh_color_scheme_css_url`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_color_scheme_css_url/)
- [`wzcbh_force_load_assets`](https://webberzone.dev/webberzone-code-block-highlighting/hooks/wzcbh_force_load_assets/)
