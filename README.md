# WebberZone Code Block Highlighting

[![WordPress Plugin Version](https://github.com/WebberZone/webberzone-code-block-highlighting/blob/main/wporg-assets/banner-1544x500.png)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/webberzone-code-block-highlighting.svg?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)
[![License](https://img.shields.io/badge/license-GPL_v2%2B-orange.svg?style=flat-square)](http://opensource.org/licenses/GPL-2.0)
[![WordPress Tested](https://img.shields.io/wordpress/v/webberzone-code-block-highlighting.svg?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)
[![Required PHP](https://img.shields.io/wordpress/plugin/required-php/webberzone-code-block-highlighting?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/webberzone-code-block-highlighting?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)

__Requires:__ 6.6

__Tested up to:__ 6.9

__Requires PHP:__ 7.4

__License:__ [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.html)

__Plugin page:__ [WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/) | [WordPress.org listing](https://wordpress.org/plugins/webberzone-code-block-highlighting/)

Add beautiful syntax highlighting to the Gutenberg Code block — powered by Prism.js with 21 themes and 40 languages, zero configuration required.

## Description

__WebberZone Code Block Highlighting__ is the easiest way to add syntax highlighting to your WordPress site. It extends the native Gutenberg `core/code` block with [Prism.js](https://prismjs.com/) highlighting — no shortcodes, no block replacement, no risk of breaking existing posts.

Simply activate the plugin and your code blocks will instantly display beautiful, readable syntax highlighting on the frontend. Choose from 35+ programming languages and 21 colour themes, all controlled from the block editor's Inspector Controls sidebar.

### Why use this plugin?

* __Safe by design__ — Works as a filter on top of `core/code`. Existing posts are never invalidated. Deactivate the plugin and your blocks are still valid standard WordPress code blocks.
* __Zero configuration__ — Activate and start writing. No setup wizard, no shortcodes.
* __Smart asset loading__ — Prism CSS and JS only load on pages that actually contain code blocks. Pages without code stay fast.
* __Per-block controls__ — Set language, theme, line numbers, word wrap, title, highlighted lines, and max height individually for each block.
* __Developer-friendly__ — Filters to add languages, override themes, and force asset loading.

### Supported languages

40 languages out of the box: Apache Config, Bash, C, C++, C#, CSS, Dart, Docker, F#, Go, GraphQL, Groovy, Haskell, HTML, Java, JavaScript, JSON, JSX, Kotlin, Lua, Markdown, Nginx, Objective-C, Perl, PHP, PowerShell, Python, R, Ruby, Rust, Sass, Scala, SQL, Swift, TOML, TSX, TypeScript, Vim, XML, and YAML. Use the `wz_cbh_languages` filter to add or remove entries from the language picker.

### Included themes (21)

A11y Dark, Coldark Cold (Light), Coldark Dark, Dracula, Duotone Dark, Duotone Light, GitHub (Light), Gruvbox Dark, Gruvbox Light, Lucario, Material Dark, Material Light, Night Owl, Nord, One Dark, One Light, Shades of Purple, Solarized Dark, Synthwave '84, VS Code Dark+, Xonokai (Monokai).

### Per-block features

* __Language selector__ — Choose the programming language from the sidebar; applies the correct Prism grammar automatically.
* __Line numbers__ — Toggle line numbers per block, with a configurable start line.
* __File title / label__ — Add an optional filename or label displayed in the code block toolbar.
* __Highlight lines__ — Specify lines or ranges (e.g. `1,3-5`) to visually highlight using the Prism line-highlight plugin.
* __Max height with expand/collapse__ — Set a maximum height in pixels; an expand/collapse toolbar button appears automatically.
* __Word wrap__ — Toggle soft word wrapping per block.

### Global settings

* Default colour scheme (theme)
* Default language
* Default line numbers toggle and start value
* Default word wrap
* Copy-to-clipboard button
* Show language label in toolbar
* Font size

### Developer filters

* `wz_cbh_languages` — Filter the language list array (`slug => label`)
* `wz_cbh_color_scheme_css_url` — Override the Prism theme CSS URL
* `wz_cbh_force_load_assets` — Force Prism assets to load on every page

### GDPR

WebberZone Code Block Highlighting does not collect personal data, set cookies, or communicate with any external services.

## Screenshots

![Code block in editor with highlighting options](https://raw.github.com/WebberZone/webberzone-code-block-highlighting/main/wporg-assets/screenshot-1.png)
_Code block in editor with highlighting options_

More screenshots are available on the [WordPress plugin page](https://wordpress.org/plugins/webberzone-code-block-highlighting/screenshots/).

## Installation

### WordPress admin (recommended)

1. Go to __Plugins > Add New__ in your WordPress admin.
2. Search for __WebberZone Code Block Highlighting__.
3. Click __Install Now__, then __Activate__.

### Manual installation

1. Download the plugin zip file.
2. Extract it to your `wp-content/plugins/` directory. You should end up with a `webberzone-code-block-highlighting/` folder.
3. Go to __Plugins__ in your WordPress admin and activate __WebberZone Code Block Highlighting__.

After activation, open any post or page in the block editor, add or select a Code block, and the __Code Highlighting__ panel will appear in the Inspector Controls sidebar.

## Frequently Asked Questions

Full FAQ is available on the [WordPress.org plugin page](https://wordpress.org/plugins/webberzone-code-block-highlighting/faq/). For support, use the [WordPress.org support forum](https://wordpress.org/support/plugin/webberzone-code-block-highlighting).

__Does this plugin replace the core Code block?__
No. The plugin uses JavaScript and PHP filters to extend `core/code`. Deactivating it leaves behind valid, standard WordPress blocks.

__Which languages are supported?__
40 out of the box. Use the `wz_cbh_languages` filter to add or remove entries from the language picker — note the corresponding Prism.js grammar must also be available on the frontend.

__Does Prism.js load on every page?__
No. Assets are only enqueued on pages containing at least one code block. Use `wz_cbh_force_load_assets` to override.

__How do I highlight specific lines?__
Enter a comma-separated list of lines or ranges in the __Highlight Lines__ field in the sidebar (e.g. `1,3-5,8`).

## Development

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

## Contribute

The plugin is open source and available on [GitHub](https://github.com/WebberZone/webberzone-code-block-highlighting). Pull requests for bug fixes and new features are welcome.

Bug reports are [welcomed on GitHub Issues](https://github.com/WebberZone/webberzone-code-block-highlighting/issues). Please note GitHub is not a support forum — issues that are not qualified as bugs will be closed.

## Security

Report security vulnerabilities through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/webberzone-code-block-highlighting). The Patchstack team will validate, triage, and handle any reported vulnerabilities.

## Translations

Help translate the plugin into your language on [WordPress.org](https://translate.wordpress.org/projects/wp-plugins/webberzone-code-block-highlighting). See the [Translator Handbook](https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/) for guidance.

## Other plugins by WebberZone

* [Contextual Related Posts](https://wordpress.org/plugins/contextual-related-posts/) — Display fast, intelligent related posts to keep visitors on your site longer
* [Top 10](https://wordpress.org/plugins/top-10/) — Track daily and total visits and display popular and trending posts
* [WebberZone Snippetz](https://wordpress.org/plugins/add-to-all/) — Manage custom HTML, CSS and JS snippets across your site
* [Knowledge Base](https://wordpress.org/plugins/knowledgebase/) — Create a knowledge base or FAQ section on your WordPress site
* [Better Search](https://wordpress.org/plugins/better-search/) — Contextual search results sorted by relevance
* [Auto-Close](https://wordpress.org/plugins/autoclose/) — Automatically close comments, pingbacks, trackbacks and manage revisions
* [Popular Authors](https://wordpress.org/plugins/popular-authors/) — Display popular authors in a WordPress widget
* [Followed Posts](https://wordpress.org/plugins/where-did-they-go-from-here/) — Show related posts based on what your visitors have already read
