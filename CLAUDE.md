# CLAUDE.md

WordPress plugin extending the native Gutenberg `core/code` block with Prism.js syntax highlighting via JS block filters and a `render_block_core/code` PHP filter. Does not replace the block — existing posts stay valid.

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

## Source of truth

- Active implementation plan: `PLAN.md`
- Backlog/reference only: `OLD-FEATURE-PLAN.md` — do not assume features exist unless implemented in code.

## Architecture

**Namespace:** `WebberZone\Code_Block_Highlighting`

Bootstrap: `wzcbh()` singleton on `plugins_loaded` → instantiates `Frontend\Blocks`, `Frontend\Styles_Handler` → `Admin\Admin` on `init` (admin only).

Key files:
- `includes/class-main.php` — bootstrap and object wiring
- `includes/frontend/class-blocks.php` — editor assets, REST route, `render_block_core/code`
- `includes/frontend/class-styles-handler.php` — conditional Prism asset loading
- `includes/admin/class-settings.php` — settings registration, theme resolution
- `includes/blocks/src/js/index.js` — block filter, Inspector Controls
- `includes/blocks/src/js/frontend.js` — Prism grammars + plugins

Always `require` the generated `.asset.php` manifest before enqueueing block scripts.

## Non-obvious implementation details

**`_legacyTitle` attribute** — read-only migration attribute; copies `title` from old `code-syntax-block` format on first load, then clears itself.

**`maxHeight`** — CSS-only: serialized as inline `style` by the block save function, not touched by the PHP render filter.

**`wzcbh_languages` filter** — controls the editor UI dropdown only. Does not affect which Prism grammars are bundled. Adding a slug without a matching grammar import in `frontend.js` results in plain-text output.

**Editor canvas styling** — `enqueue_editor_canvas_styles()` extracts only `background` and `color` from the active Prism theme CSS and re-injects them with `.block-editor-block-list__layout` prepended to win the specificity race against the editor's own `pre` styles. Layout properties are intentionally excluded.

**Themes (21):** A11y Dark, Coldark Cold, Coldark Dark, Dracula, Duotone Dark, Duotone Light, GitHub Light, Gruvbox Dark, Gruvbox Light, Lucario, Material Dark, Material Light, Night Owl, Nord, One Dark, One Light, Shades of Purple, Solarized Dark, Synthwave '84, VS Code Dark+, Xonokai (Monokai).

**Default color scheme:** `prism-onedark`

**If you change block attributes in JS**, update `render_code_block()` in `class-blocks.php` and the defaults flow as well.

## Asset loading

Prism assets load only on pages containing at least one `core/code` block (`Styles_Handler::enqueue_assets()`). Use `wzcbh_force_load_assets` to override.

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
