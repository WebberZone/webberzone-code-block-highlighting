# WebberZone Code Block Highlighting

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/webberzone-code-block-highlighting.svg?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)
[![License](https://img.shields.io/badge/license-GPL_v2%2B-orange.svg?style=flat-square)](http://opensource.org/licenses/GPL-2.0)
[![WordPress Tested](https://img.shields.io/wordpress/v/webberzone-code-block-highlighting.svg?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)
[![Required PHP](https://img.shields.io/wordpress/plugin/required-php/webberzone-code-block-highlighting?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/webberzone-code-block-highlighting?style=flat-square)](https://wordpress.org/plugins/webberzone-code-block-highlighting/)

__Requires:__ 6.6

__Tested up to:__ 6.7

__Requires PHP:__ 7.4

__License:__ [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.html)

__Plugin page:__ [WebberZone Code Block Highlighting home page](https://webberzone.com/plugins/webberzone-code-block-highlighting/) | [WordPress.org listing](https://wordpress.org/plugins/webberzone-code-block-highlighting/)

Add beautiful syntax highlighting to the Gutenberg Code block — powered by Prism.js with 18 themes and 35+ languages, zero configuration required.

## Description

WebberZone Code Block Highlighting extends the native WordPress Gutenberg `core/code` block with [Prism.js](https://prismjs.com/) syntax highlighting. It does not replace the block — existing posts remain fully compatible with zero risk of block validation errors.

### Key features

* __Zero configuration__: Activate and start writing. Prism.js highlighting is automatically applied to all code blocks on the frontend.
* __35+ languages__: Pick the language for each code block from the Inspector Controls panel in the block editor. Includes Bash, C, C++, C#, CSS, Dart, Docker, F#, Go, GraphQL, Haskell, HTML, Java, JavaScript, JSON, JSX, Kotlin, Markdown, Nginx, Objective-C, PHP, PowerShell, Python, Ruby, Rust, Sass, SQL, Swift, TOML, TSX, TypeScript, Vim, XML, YAML and more.
* __18 beautiful themes__: Choose from A11y Dark, Atom Dark, Darcula, Dracula, GitHub (Light), Gruvbox Dark, Gruvbox Light, Material Dark, Material Oceanic, Night Owl, Nord, One Dark, One Light, Shades of Purple, Solarized Dark, Synthwave '84, VS (Light), and VS Code Dark+.
* __Line numbers__: Toggle line numbers per code block from the editor sidebar.
* __Block title / label__: Add an optional title or filename label to each code block.
* __Smart asset loading__: Prism CSS and JS are only enqueued on pages that actually contain code blocks, keeping other pages fast.
* __Extendable__: Developer-friendly filters to customize the language list, override the theme CSS URL, or force assets to load on every page.

### How it works

The plugin uses three layers of integration with the block editor:

1. **Editor attributes** — A `blocks.registerBlockType` JS filter adds `language`, `lineNumbers`, and `title` attributes to `core/code` without replacing it.
2. **InspectorControls** — A `editor.BlockEdit` HOC adds a sidebar panel with a language picker, line number toggle, and title field.
3. **Frontend rendering** — A `render_block_core/code` PHP filter injects the `language-*` class onto the `<code>` element so Prism.js picks it up automatically.

### Developer filters

* `wz_cbh_languages` — filter the language list array (`slug => label`)
* `wz_cbh_color_scheme_css_url` — override the Prism theme CSS URL
* `wz_cbh_force_load_assets` — force Prism assets to load on every page

### GDPR

WebberZone Code Block Highlighting does not collect personal data or communicate with external services.

### Contribute

WebberZone Code Block Highlighting is available on [Github](https://github.com/WebberZone/webberzone-code-block-highlighting). If you have a feature you'd like to implement or a bug you've fixed, consider forking the project and sending a pull request.

Bug reports are [welcomed on Github](https://github.com/WebberZone/webberzone-code-block-highlighting/issues). Please note Github is _not_ a support forum, and issues that aren't suitably qualified as bugs will be closed.

### Translations

WebberZone Code Block Highlighting is available for [translation directly on WordPress.org](https://translate.wordpress.org/projects/wp-plugins/webberzone-code-block-highlighting). Check out the official [Translator Handbook](https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/) to contribute.

## Screenshots

More screenshots are available on the [WordPress plugin page](https://wordpress.org/plugins/webberzone-code-block-highlighting/screenshots/).

## Other plugins by WebberZone

WebberZone Code Block Highlighting is one of the many plugins developed by WebberZone. Check out our other plugins:

* [Contextual Related Posts](https://wordpress.org/plugins/contextual-related-posts/) - Display fast, intelligent related posts to keep visitors on your site longer
* [Top 10](https://wordpress.org/plugins/top-10/) - Track daily and total visits to your blog posts and display the popular and trending posts
* [WebberZone Snippetz](https://wordpress.org/plugins/add-to-all/) - The ultimate snippet manager for WordPress to create and manage custom HTML, CSS or JS code snippets
* [Knowledge Base](https://wordpress.org/plugins/knowledgebase/) - Create a knowledge base or FAQ section on your WordPress site
* [Better Search](https://wordpress.org/plugins/better-search/) - Enhance the default WordPress search with contextual results sorted by relevance
* [Auto-Close](https://wordpress.org/plugins/autoclose/) - Automatically close comments, pingbacks and trackbacks and manage revisions
* [Popular Authors](https://wordpress.org/plugins/popular-authors/) - Display popular authors in your WordPress widget
* [Followed Posts](https://wordpress.org/plugins/where-did-they-go-from-here/) - Show a list of related posts based on what your users have read

## Installation

### WordPress install (the easy way)

1. Navigate to Plugins within your WordPress Admin Area
2. Click "Add new" and in the search box enter "WebberZone Code Block Highlighting"
3. Find the plugin in the list (usually the first result) and click "Install Now"

### Manual install

1. Download the plugin
2. Extract the contents of webberzone-code-block-highlighting.zip to wp-content/plugins/ folder. You should get a folder called webberzone-code-block-highlighting.
3. Activate the Plugin in WP-Admin under the Plugins screen

## Frequently Asked Questions

Check out the [FAQ on the plugin page](https://wordpress.org/plugins/webberzone-code-block-highlighting/faq/).

If your question isn't listed there, please create a new post at the [WordPress.org support forum](https://wordpress.org/support/plugin/webberzone-code-block-highlighting). It is the fastest way to get support as I monitor the forums regularly.

## How can I report security bugs?

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/webberzone-code-block-highlighting)
