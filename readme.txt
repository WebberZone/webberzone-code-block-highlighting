=== WebberZone Code Block Highlighting ===
Contributors: webberzone, ajay
Donate link: https://wzn.io/donate-wz
Tags: syntax highlighting, code block, prism, gutenberg, code highlighting
Requires at least: 6.6
Tested up to: 7.1
Stable tag: 1.2.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Syntax highlighting for the Gutenberg Code block — Prism.js, 21 themes, 40+ languages, plus an optional server-side highlighting mode.

== Description ==

**WebberZone Code Block Highlighting** is the easiest way to add syntax highlighting to your WordPress site. It extends the native Gutenberg `core/code` block with [Prism.js](https://prismjs.com/) highlighting in the browser, or optional server-side highlighting with [highlight.php](https://github.com/scrivo/highlight.php) — no shortcodes, no block replacement, no risk of breaking existing posts.

Simply activate the plugin and your code blocks will instantly display beautiful, readable syntax highlighting on the frontend. Choose from 40+ programming languages and 21 colour themes, all controlled from the block editor's Inspector Controls sidebar.

### Two highlighting modes

Pick the rendering mode that best fits your site from the settings page:

* **Client-side (default)** — [Prism.js](https://prismjs.com/) highlights your code in the browser. Best for interactive features such as copy-to-clipboard and expand/collapse.
* **Server-side (highlight.php)** — Code is pre-highlighted on the server with [highlight.php](https://github.com/scrivo/highlight.php), so no Prism.js or any other JavaScript is loaded for highlighting. Ideal for performance, Core Web Vitals, AMP-style setups, and strict content-security policies.

Both modes use the same 21 Prism themes and produce visually identical output, so you can switch between client-side and server-side rendering at any time without changing how your code blocks look. Server-side mode leaves code blocks over 128KB unhighlighted.

### Why use this plugin?

* **Safe by design** — Works as a filter on top of `core/code`. Existing posts are never invalidated. Deactivate the plugin and your blocks are still valid standard WordPress code blocks.
* **Zero configuration** — Activate and start writing. No setup wizard, no shortcodes.
* **No highlighting JavaScript in server mode** — Server-side mode pre-renders syntax colours on the server. No Prism.js or any other JavaScript is enqueued for highlighting.
* **Smart asset loading** — Theme CSS (and, in client mode, Prism JS) only load on pages that actually contain code blocks. Pages without code stay fast.
* **Per-block controls** — Set language, theme, line numbers, word wrap, title, highlighted lines, and max height individually for each block.
* **Developer-friendly** — Filters to add languages, override themes, and force asset loading.

### Supported languages

40 languages including: Apache Config, Bash, C, C++, C#, CSS, Dart, Docker, F#, Go, GraphQL, Groovy, Haskell, HTML, Java, JavaScript, JSON, JSX, Kotlin, Lua, Markdown, Nginx, Objective-C, Perl, PHP, PowerShell, Python, R, Ruby, Rust, Sass, Scala, SQL, Swift, TOML, TSX, TypeScript, Vim, XML, YAML. Use the `wzcbh_languages` filter to add or remove entries from the language picker.

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

* Highlighting mode — client-side (Prism.js) or server-side (highlight.php)
* Default colour scheme (theme)
* Default language
* Default line numbers toggle and start value
* Default word wrap
* Copy-to-clipboard button
* Show language label in toolbar
* Font size

### Developer filters

* `wzcbh_languages` — Filter the language list array (`slug => label`)
* `wzcbh_color_scheme_css_url` — Override the Prism theme CSS URL
* `wzcbh_force_load_assets` — Force Prism assets to load on every page
* `wzcbh_server_highlight_max_bytes` — Change the size limit for server-side highlighting

### GDPR

WebberZone Code Block Highlighting does not collect personal data, set cookies, or communicate with any external services.

### Contribute

The plugin is open source and available on [GitHub](https://github.com/WebberZone/webberzone-code-block-highlighting). Pull requests for bug fixes and new features are welcome. Please use the [WordPress.org support forum](https://wordpress.org/support/plugin/webberzone-code-block-highlighting/) for support and [GitHub Issues](https://github.com/WebberZone/webberzone-code-block-highlighting/issues) for confirmed bug reports.

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
* [WebberZone Link Warnings](https://wordpress.org/plugins/webberzone-link-warnings/) — Add accessible warnings for external links and target="_blank" links

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

Use the `wzcbh_languages` filter to add or remove entries from the language picker. Note: adding a language to the filter only affects the UI dropdown — the corresponding Prism.js grammar must also be available on the frontend (either bundled in `frontend.js` or loaded separately).

= How do I add a custom or additional Prism theme? =

1. Add the theme mapping in `build-prism.js`.
2. Copy the generated CSS to `includes/assets/`.
3. Register the slug in `includes/admin/class-settings.php`.
4. Run `npm run build:prism`.

You can also use the `wzcbh_color_scheme_css_url` filter to point to any CSS file without touching the plugin source.

= What is the difference between client-side and server-side highlighting? =

In **client-side** mode (the default), [Prism.js](https://prismjs.com/) runs in the visitor's browser and highlights your code on page load. This mode enables interactive toolbar features such as copy-to-clipboard and expand/collapse.

In **server-side** mode, the plugin uses [highlight.php](https://github.com/scrivo/highlight.php) to pre-render the highlighted markup on the server. No JavaScript is loaded for highlighting, which is great for performance, Core Web Vitals, and sites with strict content-security policies. Both modes share the same 21 Prism themes and produce visually identical results, so you can switch freely from **Settings > Code Block Highlighting**.

= Does server-side highlighting work without JavaScript? =

Yes. When server-side mode is enabled, syntax colours are baked into the HTML on the server — no Prism.js or any other JavaScript is enqueued for highlighting.

= Does Prism.js load on every page? =

No. Theme CSS and (in client-side mode) Prism JS are only enqueued on pages that contain at least one code block. All other pages are unaffected. Use the `wzcbh_force_load_assets` filter to override this behaviour if needed.

= How do I highlight specific lines in a code block? =

Enter a comma-separated list of lines or ranges in the **Highlight Lines** field in the Inspector Controls sidebar (e.g. `1,3-5,8`). This maps to the `data-line` attribute consumed by the Prism line-highlight plugin.

= Can I show a filename or label on the code block? =

Yes. Use the **Title** field in the Inspector Controls sidebar. The label is displayed in the Prism toolbar above the code block.

= Does the plugin support dark mode or multiple themes? =

The plugin ships with 21 Prism themes. The active theme is selected globally on the settings page (**Settings > Code Block Highlighting**). Per-block theme switching is not currently supported.

= Is this plugin compatible with the WordPress block editor (Gutenberg)? =

Yes. The plugin is built specifically for the Gutenberg block editor (WordPress 6.6+) and uses the native block filter APIs. It does not support the Classic Editor.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the WebberZone Code Block Highlighting plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/d9673655-4a25-4c42-8908-9737ab36602a). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Changelog ==

= 1.2.2 =

*Release Date - 5 September 2026*

* Fix: A file name containing `$0`, `$1` or `\0` was written into the code block's markup as the surrounding tag text instead of the name itself. Line ranges and download file names were affected the same way.
* Fix: Copying a code block in server-side mode added a blank line after every highlighted line.
* Fix: Server-side mode repeated the language class the block had already saved, and could add an empty `class` attribute.
* Fix: `data-title`, `data-line` and `data-start` were written twice on every code block.
* Fix: With line highlighting on, server-side mode could render one line more than the line-number gutter showed, and the extra line could pick up the highlight. Line counting now matches the browser in both modes.
* Fix: The "Settings" link in the admin banner, and the redirect at the end of the setup wizard, pointed at an address that returned a permissions error.
* Fix: Code blocks no longer stop the page rendering when a theme or plugin makes the main query return post IDs rather than posts.
* Change: Server-side mode no longer highlights code blocks larger than 128KB; they render as plain text in the active theme. Use the new `wzcbh_server_highlight_max_bytes` filter to change the limit. Client-side mode is unaffected.
* Improvement: Server-side mode is faster on pages with several code blocks.
* Improvement: A "Highlight lines" value spanning a very large range, such as `1-999999999`, no longer exhausts memory while the page is rendering.
* Improvement: The colour scheme setting is now validated everywhere it is used to build a file path.

= 1.2.1 =

*Release Date - 20 August 2026*

* Improvement: Setting defaults are now resolved from a single lightweight list instead of building every settings field, so reading an option early in the page load no longer risks a "translation loading triggered too early" notice.
* Fix: Fixed settings on a multisite network reading another site's values in the same request after a `switch_to_blog()` call.
* Fix: Fixed the settings wizard silently dropping repeater field rows on save.

= 1.2.0 =

*Release Date - 6 August 2026*

Release post: [https://webberzone.com/announcements/code-block-highlighting-v1-2-0/](https://webberzone.com/announcements/code-block-highlighting-v1-2-0/)

* New: File name tab. The file name set on a block now renders as an editor-style tab above the code block, coloured to match the active Prism theme. Works identically in client-side and server-side modes.
* New: "File Name Style" setting to choose between the new tab and the previous toolbar label.
* New: `wzcbh_file_tab_html` filter to customise the file name tab markup.
* New: Download snippet button. A "Download" button now appears next to "Copy" in the code block toolbar, saving the snippet as a file named after the block's file name, or `snippet.{ext}` derived from its language. Enabled by default; it can be turned off globally with the new "Download Snippet" setting, or per block from the "Download button" control in the block sidebar.
* New: `wzcbh_download_extensions` filter to customise the language-to-file-extension map used for downloads.
* Fix: File names saved by the older Code Syntax Block plugin (stored as a `title` attribute on the `<pre>`) are now displayed on the frontend without needing the post to be re-saved.
* Fix: The block editor canvas no longer picked up `background: window` from the forced-colors media query in several themes, which left the editor code block unstyled.

= 1.1.0 =

*Release Date - 10 June 2026*

Release post: [https://webberzone.com/announcements/code-block-highlighting-v1-1-0/](https://webberzone.com/announcements/code-block-highlighting-v1-1-0/)

* New: Server-side highlighting mode powered by highlight.php — syntax is pre-rendered on the server with no JavaScript required for highlighting.
* New: All 21 Prism themes work identically in both client-side and server-side modes. Server mode now outputs Prism-compatible token classes and loads the same theme CSS, so switching modes produces no visual difference.
* New: Added "Plain Text" language option to the language picker. Renders with Prism theme styling but no syntax highlighting.
* Fix: Duplicate `language-*` class on `<code>` elements in client mode when the saved block HTML already carried the class.

= 1.0.0 =

*Release Date - 3 May 2026*

Release post: [https://webberzone.com/announcements/code-block-highlighting-v1-0-0/](https://webberzone.com/announcements/code-block-highlighting-v1-0-0/)

* Initial release.
* Extends `core/code` with Prism.js syntax highlighting — no block replacement, no block validation errors.
* 40 supported languages with per-block language selection.
* 21 built-in Prism themes selectable from the settings page.
* Per-block controls: language, line numbers, start line, word wrap, title, highlight lines, and max height.
* Expand/collapse toolbar button for blocks with a max-height set.
* Smart asset loading — Prism CSS and JS only enqueued on pages containing code blocks.
* Copy-to-clipboard and show-language toolbar buttons (configurable globally).
* Global settings page for colour scheme, default language, and more.
* Developer filters: `wzcbh_languages`, `wzcbh_color_scheme_css_url`, `wzcbh_force_load_assets`.
* GDPR-friendly: no data collection, no external requests.

== Upgrade Notice ==

= 1.2.2 =
Fixes file names containing `$` or `\` corrupting a block's markup, blank lines when copying in server-side mode, duplicated attributes, and a line-count mismatch with line highlighting. Server-side mode is faster, and no longer highlights blocks over 128KB.

= 1.2.1 =
Fixes settings defaults resolution, a multisite settings cache leak, and repeater fields dropping rows on save.

= 1.2.0 =
File names now render as an editor-style tab above the code block, themed to match your chosen colour scheme. Adds a "File Name Style" setting to switch back to the toolbar label.

= 1.1.0 =
Adds server-side highlighting mode (highlight.php) with full theme parity across all 21 Prism themes, plus a "Plain Text" language picker option.

= 1.0.0 =
Initial release.
