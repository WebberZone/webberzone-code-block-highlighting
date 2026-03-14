# CLAUDE.md

WordPress plugin that extends the native Gutenberg `core/code` block with Prism.js syntax highlighting. The editor integration is implemented by replacing the `core/code` block edit/save behaviour via a JS block filter, while frontend output is normalized and enhanced through the `render_block_core/code` PHP filter.

## Commands

```bash
# PHP
composer install
composer test
composer phpcs
composer phpstan

# JS
npm install
npm run build          # Production build -> includes/blocks/build/
npm run build:prism    # Copy Prism themes -> includes/assets/
npm run build:assets   # Minify generated CSS/JS assets
npm run start          # Watch mode for block/editor/frontend bundles
npm run zip            # Plugin zip
```

## Current feature scope

The current implementation follows the active plan in `PLAN.md`, not the broader experimental ideas in `OLD-FEATURE-PLAN.md`.

- Extends `core/code` with per-block attributes:
  - `language`
  - `lineNumbers`
  - `lineNumbersStart`
  - `wordWrap`
  - `title`
  - `_legacyTitle` — read-only migration attribute; copies `title` from old `code-syntax-block` format on first load, then clears itself
  - `highlightLines` — maps to `data-line` on `<pre>`, consumed by Prism line-highlight plugin
  - `maxHeight` — inline `style="max-height:{n}px;overflow-y:auto"` on `<pre>` (CSS only, no Prism)
- Adds Inspector Controls for:
  - language selection
  - file name/title
  - line numbers toggle + start line
  - word wrap
  - highlight lines (e.g. `1,3-5`)
  - max height in px
  - save current settings as defaults
- Saves defaults through the REST route `wz-cbh/v1/default-settings`
- Frontend integrates Prism plugins for:
  - line numbers
  - line highlight
  - toolbar
  - show language
  - copy to clipboard
- Registers a custom Prism toolbar label that reads from `data-title`
- Registers a `wz-cbh-expand` Prism toolbar button that appears when `max-height` is set; toggles the block between collapsed (original inline style) and expanded (inline style cleared), updating `aria-expanded` on each toggle
- Supports global settings for:
  - color scheme
  - copy to clipboard
  - show language label
  - show file name
  - default language
  - default line numbers toggle
  - default line numbers start
  - default word wrap
  - font size
- Ships with `One Dark` as the default theme slug: `prism-onedark`

Do not assume features from `OLD-FEATURE-PLAN.md` exist unless they are implemented in code.

## Architecture

**Namespace:** `WebberZone\Code_Block_Highlighting`

**Autoloader:** `includes/autoloader.php`

**Bootstrap flow:**

- Main plugin file loads the autoloader.
- `wz_cbh()` resolves the `Main` singleton on `plugins_loaded`.
- `Main` loads `includes/options-api.php`.
- `Main` instantiates:
  - `Frontend\Blocks`
  - `Frontend\Styles_Handler`
- `Admin\Admin` is created on `init` only when `is_admin()`.

**Key PHP classes:**

- `includes/class-main.php` — plugin bootstrap and object wiring
- `includes/frontend/class-blocks.php` — editor asset registration, REST route, `render_block_core/code`
- `includes/frontend/class-styles-handler.php` — conditional frontend Prism asset loading
- `includes/admin/class-settings.php` — settings registration, theme resolution, language autocomplete wiring

**JS entry points:**

- `includes/blocks/src/js/index.js` — replaces `core/code` edit/save and adds Inspector Controls
- `includes/blocks/src/js/frontend.js` — Prism core, supported grammars, toolbar/copy/show-language plugins, frontend toolbar behaviour

**Build output:**

- `includes/blocks/build/index.js`
- `includes/blocks/build/frontend.js`
- corresponding `.asset.php` manifests and extracted CSS files

Always `require` the generated `.asset.php` file before enqueueing block scripts.

## Data flow

- Language list is provided by `Frontend\Blocks::get_languages()`
- Editor globals are injected with `wp_add_inline_script()`:
  - `cbhLanguages`
  - `cbhDefaultLang`
  - `cbhDefaultSettings`
- Frontend globals are injected with `wp_add_inline_script()`:
  - `cbhSettings`
- Block attributes are saved in block markup and normalized again in `render_code_block()`
- Default settings are stored in plugin options through the REST endpoint

## Asset loading

`Frontend\Styles_Handler::enqueue_assets()` only loads Prism on the frontend when at least one `core/code` block is present in the current queried posts.

Use `wz_cbh_force_load_assets` to bypass conditional loading.

The editor canvas styling is handled separately in `Frontend\Blocks::enqueue_editor_canvas_styles()`, which:

- enqueues editor CSS into the block editor iframe
- extracts only `background` and `color` declarations from the active Prism theme
- re-injects them with stronger selectors so the editor canvas matches the chosen frontend theme without breaking editor layout

## Key filters, options, and routes

- `wz_cbh_languages` — filter supported Prism languages (`slug => label`)
- `wz_cbh_color_scheme_css_url` — filter the resolved Prism theme CSS URL
- `wz_cbh_force_load_assets` — force frontend Prism assets to load
- REST route: `wz-cbh/v1/default-settings`
- Settings option key: `wz_cbh_settings`
- Settings prefix: `wz_cbh`
- Settings page slug: `wz_cbh_settings`

## Current option IDs

These option IDs are registered in `includes/admin/class-settings.php`:

- `color-scheme`
- `copy-to-clipboard`
- `show-language-label`
- `show-file-name`
- `default-lang`
- `default-line-numbers`
- `default-line-numbers-start`
- `default-word-wrap`
- `font-size`

The default color scheme is `prism-onedark`.

## Frontend rendering rules

`Frontend\Blocks::render_code_block()` currently:

- adds `language-{slug}` to `<code>`
- adds `line-numbers` to `<pre>` when enabled
- adds `data-start` to `<pre>` when line numbering starts from a value other than `1`
- adds `word-wrap` to `<pre>` when enabled
- adds `data-title` to `<pre>` for the custom toolbar label
- adds `data-line` to `<pre>` from `highlightLines` attribute (consumed by Prism line-highlight plugin)
- `maxHeight` is CSS-only: serialized as inline `style` by the block save function, not touched by PHP

If you change block attributes in JS, update the PHP rendering logic and defaults flow as well.

## Accessibility notes

The active plan targets strong accessibility support. Current frontend code already includes:

- decorative toolbar language labels marked `aria-hidden`
- a custom title label in the toolbar
- Prism copy-to-clipboard integration controlled by plugin settings
- expand/collapse button with `aria-expanded` state management

If you extend toolbar behaviour, preserve keyboard access and screen reader behaviour.

## Adding a Prism theme

1. Add the theme mapping in `build-prism.js`
2. Ensure the generated CSS file is copied to `includes/assets/`
3. Register the theme slug in `includes/admin/class-settings.php`
4. Run `npm run build:prism`

## Notes for future work

- Prefer `PLAN.md` as the source of truth for current implementation direction
- Treat `OLD-FEATURE-PLAN.md` as backlog/reference only
- Before adding new per-block controls, verify:
  - the JS attribute schema
  - the save output
  - the PHP render filter
  - frontend Prism plugin support
