=== WebberZone Code Block Highlighting ===
Contributors: webberzone, ajay
Donate link: https://wzn.io/donate-cbh
Tags: syntax highlighting, code block, prism, gutenberg, code, highlight, block editor, syntax highlighter, code highlighting, prism.js, developer, code syntax
Requires at least: 6.6
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add beautiful syntax highlighting to the Gutenberg Code block — powered by Prism.js with 21 themes and 40 languages, zero configuration required.

== Description ==

**WebberZone Code Block Highlighting** is the easiest way to add syntax highlighting to your WordPress site. It extends the native Gutenberg `core/code` block with [Prism.js](https://prismjs.com/) highlighting — no shortcodes, no block replacement, no risk of breaking existing posts.

Simply activate the plugin and your code blocks will instantly display beautiful, readable syntax highlighting on the frontend. Choose from 35+ programming languages and 21 colour themes, all controlled from the block editor's Inspector Controls sidebar.

### Why use this plugin?

* **Safe by design** — Works as a filter on top of `core/code`. Existing posts are never invalidated. Deactivate the plugin and your blocks are still valid standard WordPress code blocks.
* **Zero configuration** — Activate and start writing. No setup wizard, no shortcodes.
* **Smart asset loading** — Prism CSS and JS only load on pages that actually contain code blocks. Pages without code stay fast.
* **Per-block controls** — Set language, theme, line numbers, word wrap, title, highlighted lines, and max height individually for each block.
* **Developer-friendly** — Filters to add languages, override themes, and force asset loading.

### Supported languages

40 languages including: Apache Config, Bash, C, C++, C#, CSS, Dart, Docker, F#, Go, GraphQL, Groovy, Haskell, HTML, Java, JavaScript, JSON, JSX, Kotlin, Lua, Markdown, Nginx, Objective-C, Perl, PHP, PowerShell, Python, R, Ruby, Rust, Sass, Scala, SQL, Swift, TOML, TSX, TypeScript, Vim, XML, YAML. Use the `wz_cbh_languages` filter to add or remove entries from the language picker.

### Included themes (21)

A11y Dark, Coldark Cold (Light), Coldark Dark, Dracula, Duotone Dark, Duotone Light, GitHub (Light), Gruvbox Dark, Gruvbox Light, Lucario, Material Dark, Material Light, Night Owl, Nord, One Dark, One Light, Shades of Purple, Solarized Dark, Synthwave '84, VS Code Dark+, Xonokai (Monokai).

### Per-block features

* **Language selector** — Choose the programming language from the sidebar; applies the correct Prism grammar automatically.
* **Line numbers** — Toggle line numbers per block, with a configurable start line.
* **File title / label** — Add an optional filename or label displayed in the code block toolbar.
* **Highlight lines** — Specify lines or ranges (e.g. `1,3-5`) to visually highlight using Prism's line-highlight plugin.
* **Max height with expand/collapse** — Set a maximum height in pixels; an expand/collapse toolbar button appears automatically.
* **Word wrap** — Toggle soft word wrapping per block.

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

### Contribute

The plugin is open source and available on [GitHub](https://github.com/WebberZone/webberzone-code-block-highlighting). Pull requests for bug fixes and new features are welcome. Please use [GitHub Issues](https://github.com/WebberZone/webberzone-code-block-highlighting/issues) for bug reports — GitHub is not a support forum.

### Translations

Help translate WebberZone Code Block Highlighting into your language on [WordPress.org](https://translate.wordpress.org/projects/wp-plugins/webberzone-code-block-highlighting). See the [Translator Handbook](https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/) for guidance.

### Other plugins by WebberZone

* [Contextual Related Posts](https://wordpress.org/plugins/contextual-related-posts/) — Display fast, intelligent related posts to keep visitors on your site longer
* [Top 10](https://wordpress.org/plugins/top-10/) — Track daily and total visits and display popular and trending posts
* [WebberZone Snippetz](https://wordpress.org/plugins/add-to-all/) — Manage custom HTML, CSS and JS snippets across your site
* [Knowledge Base](https://wordpress.org/plugins/knowledgebase/) — Create a knowledge base or FAQ section on your WordPress site
* [Better Search](https://wordpress.org/plugins/better-search/) — Contextual search results sorted by relevance
* [Auto-Close](https://wordpress.org/plugins/autoclose/) — Automatically close comments, pingbacks, trackbacks and manage revisions
* [Popular Authors](https://wordpress.org/plugins/popular-authors/) — Display popular authors in a WordPress widget
* [Followed Posts](https://wordpress.org/plugins/where-did-they-go-from-here/) — Show related posts based on what your visitors have already read

== Installation ==

= WordPress admin (recommended) =

1. Go to **Plugins > Add New** in your WordPress admin.
2. Search for **WebberZone Code Block Highlighting**.
3. Click **Install Now**, then **Activate**.

= Manual installation =

1. Download the plugin zip file.
2. Extract it to your `wp-content/plugins/` directory. You should end up with a `webberzone-code-block-highlighting/` folder.
3. Go to **Plugins** in your WordPress admin and activate **WebberZone Code Block Highlighting**.

After activation, open any post or page in the block editor, add or select a Code block, and the **Code Highlighting** panel will appear in the Inspector Controls sidebar.

== Screenshots ==

1. Block editor — language picker, line numbers toggle, title field, and more in the Inspector Controls sidebar.
2. Frontend code block with Prism.js syntax highlighting, line numbers, and toolbar.
3. Plugin settings page — choose a global colour scheme, default language, and more.

== Frequently Asked Questions ==

= Does this plugin replace the core Code block? =

No. WebberZone Code Block Highlighting uses JavaScript and PHP filters to extend the native `core/code` block. It never swaps the block for a custom one, so existing posts are never invalidated and the plugin can be deactivated without leaving behind broken blocks.

= Will my code blocks break if I deactivate the plugin? =

No. Because the plugin extends `core/code` rather than replacing it, deactivating simply removes the highlighting. Your code content is stored in standard WordPress block markup and remains valid.

= Which programming languages are supported? =

40 languages out of the box: Apache Config, Bash, C, C++, C#, CSS, Dart, Docker, F#, Go, GraphQL, Groovy, Haskell, HTML, Java, JavaScript, JSON, JSX, Kotlin, Lua, Markdown, Nginx, Objective-C, Perl, PHP, PowerShell, Python, R, Ruby, Rust, Sass, Scala, SQL, Swift, TOML, TSX, TypeScript, Vim, XML, and YAML.

Use the `wz_cbh_languages` filter to add or remove entries from the language picker. Note: adding a language to the filter only affects the UI dropdown — the corresponding Prism.js grammar must also be available on the frontend (either bundled in `frontend.js` or loaded separately).

= How do I add a custom or additional Prism theme? =

1. Add the theme mapping in `build-prism.js`.
2. Copy the generated CSS to `includes/assets/`.
3. Register the slug in `includes/admin/class-settings.php`.
4. Run `npm run build:prism`.

You can also use the `wz_cbh_color_scheme_css_url` filter to point to any CSS file without touching the plugin source.

= Does Prism.js load on every page? =

No. Prism CSS and JS are only enqueued on pages that contain at least one code block. All other pages are unaffected. Use the `wz_cbh_force_load_assets` filter to override this behaviour if needed.

= How do I highlight specific lines in a code block? =

Enter a comma-separated list of lines or ranges in the **Highlight Lines** field in the Inspector Controls sidebar (e.g. `1,3-5,8`). This maps to the `data-line` attribute consumed by the Prism line-highlight plugin.

= Can I show a filename or label on the code block? =

Yes. Use the **Title** field in the Inspector Controls sidebar. The label is displayed in the Prism toolbar above the code block.

= Does the plugin support dark mode or multiple themes? =

The plugin ships with 21 Prism themes. The active theme is selected globally on the settings page (**Settings > Code Block Highlighting**). Per-block theme switching is not currently supported.

= Is this plugin compatible with the WordPress block editor (Gutenberg)? =

Yes. The plugin is built specifically for the Gutenberg block editor (WordPress 6.6+) and uses the native block filter APIs. It does not support the Classic Editor.

= How can I report security vulnerabilities? =

Report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team will validate, triage, and handle any reported vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/webberzone-code-block-highlighting)

== Changelog ==

= 1.0.0 =

* Initial release.
* Extends `core/code` with Prism.js syntax highlighting — no block replacement, no block validation errors.
* 35 supported languages with per-block language selection.
* 18 built-in Prism themes selectable from the settings page.
* Per-block controls: language, line numbers, start line, word wrap, title, highlight lines, and max height.
* Expand/collapse toolbar button for blocks with a max-height set.
* Smart asset loading — Prism CSS and JS only enqueued on pages containing code blocks.
* Copy-to-clipboard and show-language toolbar buttons (configurable globally).
* Global settings page for colour scheme, default language, and more.
* Developer filters: `wz_cbh_languages`, `wz_cbh_color_scheme_css_url`, `wz_cbh_force_load_assets`.
* GDPR-friendly: no data collection, no external requests.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
