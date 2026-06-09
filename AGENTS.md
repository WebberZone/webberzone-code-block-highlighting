# AGENTS.md

**WebberZone Code Block Highlighting** v1.1.0. WordPress plugin extending the native Gutenberg `core/code` block with syntax highlighting via JS block filters and a `render_block_core/code` PHP filter. Does not replace the block — existing posts stay valid. Namespace: `WebberZone\Code_Block_Highlighting`. Requires WordPress 6.6+, PHP 7.4+. No Freemius.

Two highlighting modes:

- **Client-side** (default): Prism.js runs in the browser. Loads the Prism JS bundle + theme CSS.
- **Server-side**: highlight.php pre-renders token spans on the server. No JS loaded. Uses the same Prism theme CSS — token class remapping (`remap_token_classes()` in `class-blocks.php`) converts hljs-* span classes to Prism `token *` classes via `strtr`, giving exact visual parity across all 21 themes.

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

## Architecture

**Namespace:** `WebberZone\Code_Block_Highlighting`

Bootstrap: `wzcbh()` singleton on `plugins_loaded` → instantiates `Frontend\Blocks`, `Frontend\Styles_Handler` → `Admin\Admin` on `init` (admin only).

Key files:

- `includes/class-main.php` — bootstrap and object wiring
- `includes/frontend/class-blocks.php` — editor assets, REST route, `render_block_core/code` filter; `render_code_block_server()` for server mode; `remap_token_classes()` for hljs→Prism class mapping
- `includes/frontend/class-styles-handler.php` — conditional asset loading for both modes: client (Prism JS + theme CSS) and server (theme CSS + `hljs-server-mode.css`, no JS)
- `includes/admin/class-settings.php` — settings registration; `get_color_scheme_css()` always returns Prism CSS URL (no per-mode branch)
- `includes/blocks/src/js/index.js` — block filter, Inspector Controls
- `includes/blocks/src/js/frontend.js` — Prism grammars + plugins

Always `require` the generated `.asset.php` manifest before enqueueing block scripts.

## Non-obvious implementation details

**`_legacyTitle` attribute** — read-only migration attribute; copies `title` from old `code-syntax-block` format on first load, then clears itself.

**`maxHeight`** — CSS-only: serialized as inline `style` by the block save function, not touched by the PHP render filter.

**`wzcbh_languages` filter** — controls the editor UI dropdown only. Does not affect which Prism grammars are bundled. Adding a slug without a matching grammar import in `frontend.js` results in plain-text output.

**Editor canvas styling** — `enqueue_editor_canvas_styles()` extracts only `background` and `color` from the active Prism theme CSS and re-injects them with `.block-editor-block-list__layout` prepended to win the specificity race against the editor's own `pre` styles.

**Server-mode token remapping** — `remap_token_classes()` in `class-blocks.php` uses `strtr()` to convert every `class="hljs-*"` span emitted by highlight.php into the equivalent Prism `class="token *"` span. Keys are ordered longest-first to prevent prefix collisions.

**highlight.php autoloader** — `\Highlight\Autoloader::register()` does not exist. Use `spl_autoload_register(static function(string $class_name): void { \Highlight\Autoloader::load($class_name); })`.

**`hljs-server-mode.css`** — only handles `.wzcbh-highlighted-line` line highlighting. Font-size, line-numbers gutter, and word-wrap are all in `frontend.css` (webpack build), which loads in both modes.

**Both modes use the same Prism theme CSS** — `Settings::get_color_scheme_css()` always returns the Prism CSS URL. There is no per-mode branch.

**Default color scheme:** `prism-onedark`

## Asset loading

Assets load only on pages containing at least one `core/code` block (`Styles_Handler::enqueue_assets()`). Use `wzcbh_force_load_assets` to override.

- **Client mode**: `frontend.css` + Prism theme CSS + `wzcbh-prism-js` script bundle
- **Server mode**: `frontend.css` + Prism theme CSS + `hljs-server-mode.css` (no JS; syntax already pre-rendered in HTML)

## Filters and routes

- `wzcbh_languages` — language picker UI list (`slug => label`); UI only, not grammar loader
- `wzcbh_color_scheme_css_url` — override the Prism theme CSS URL
- `wzcbh_force_load_assets` — force Prism assets to load on every page
- REST route: `wzcbh/v1/default-settings`
- Settings key: `wzcbh_settings`

## Adding a Prism theme

1. Add the theme mapping in `build-prism.js`
2. Copy the CSS to `includes/assets/`
3. Register the slug in `includes/admin/class-settings.php`
4. Run `npm run build:prism`

## Adding a language

1. Add `import 'prismjs/components/prism-{slug}'` to `frontend.js` (respect dependency order)
2. Add `'slug' => 'Label'` to `get_languages()` in `class-blocks.php`
3. Run `npm run build`

## Accessibility

Toolbar language labels are `aria-hidden`. Expand/collapse button uses `aria-expanded`. When extending toolbar behaviour, preserve keyboard access and screen reader support.

## Before adding new per-block controls

Verify: JS attribute schema → save output → `render_code_block()` in PHP → frontend Prism plugin support.
