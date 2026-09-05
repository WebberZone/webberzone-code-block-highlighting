<?php
/**
 * Blocks class.
 *
 * Registers editor assets and filters the rendered core/code block on the frontend.
 *
 * @package WebberZone\Code_Block_Highlighting\Frontend
 */

namespace WebberZone\Code_Block_Highlighting\Frontend;

use WebberZone\Code_Block_Highlighting\Admin\Settings;
use WebberZone\Code_Block_Highlighting\Util\Hook_Registry;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Blocks class.
 *
 * @since 1.0.0
 */
class Blocks {

	/**
	 * Shared highlight.php highlighter, built on first use.
	 *
	 * Untyped: the class ships in an optional bundled library.
	 *
	 * @since 1.2.2
	 *
	 * @var \Highlight\Highlighter|null
	 */
	protected static $highlighter = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		Hook_Registry::add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		Hook_Registry::add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_canvas_styles' ) );
		Hook_Registry::add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		Hook_Registry::add_filter( 'render_block_core/code', array( $this, 'render_code_block' ), 10, 2 );
	}

	/**
	 * Enqueue editor script and pass language data.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = WZCBH_PLUGIN_DIR . 'includes/blocks/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'wzcbh-editor',
			WZCBH_PLUGIN_URL . 'includes/blocks/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$languages    = self::get_languages();
		$default_lang = wzcbh_get_option( 'default-lang', '' );

		$default_settings = array(
			'language'         => $default_lang,
			'lineNumbers'      => (bool) wzcbh_get_option( 'default-line-numbers', false ),
			'lineNumbersStart' => (int) wzcbh_get_option( 'default-line-numbers-start', 1 ),
			'wordWrap'         => (bool) wzcbh_get_option( 'default-word-wrap', false ),
			'maxHeight'        => (int) wzcbh_get_option( 'default-max-height', 0 ),
		);

		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		wp_add_inline_script(
			'wzcbh-editor',
			implode(
				"\n",
				array(
					'const cbhLanguages = ' . wp_json_encode( $languages, $flags ) . ';',
					'const cbhDefaultLang = ' . wp_json_encode( $default_lang, $flags ) . ';',
					'const cbhDefaultSettings = ' . wp_json_encode( $default_settings, $flags ) . ';',
				)
			),
			'before'
		);
	}

	/**
	 * Enqueue editor layout styles and inject the active Prism theme into the
	 * block editor iframe canvas.
	 *
	 * Only background/color are extracted and re-injected with
	 * `.block-editor-block-list__layout` prepended, to beat the editor's own `pre` rule on
	 * specificity (0,2,1 vs 0,1,1) without disrupting block layout.
	 *
	 * @since 1.1.0
	 */
	public function enqueue_editor_canvas_styles(): void {
		if ( ! is_admin() ) {
			return;
		}

		$asset_file = WZCBH_PLUGIN_DIR . 'includes/blocks/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		$editor_css = WZCBH_PLUGIN_DIR . 'includes/blocks/build/index.css';
		if ( file_exists( $editor_css ) ) {
			wp_enqueue_style(
				'wzcbh-editor-canvas-style',
				WZCBH_PLUGIN_URL . 'includes/blocks/build/index.css',
				array(),
				$asset['version']
			);
		}

		// Extract only background and color from the Prism theme and inject them
		// with a more specific selector so they beat the block editor's generic
		// .block-editor-block-list__layout pre rule (specificity 0,1,1).
		// Boosting layout properties (margin, padding, position, overflow) would
		// break the editor's block positioning, so we target only visual props.
		// Scan all matching rule blocks to handle themes that place `background`
		// in a separate `pre[class*="language-"]` rule rather than the combined
		// `code[class*="language-"], pre[class*="language-"]` block.
		// Later declarations win, mirroring the CSS cascade.
		$theme_path = Settings::get_color_scheme_css( true );
		if ( file_exists( $theme_path ) && wp_style_is( 'wzcbh-editor-canvas-style', 'enqueued' ) ) {
			$colors      = Settings::extract_theme_colors( $theme_path );
			$bg_value    = $colors['background'] ? 'background: ' . $colors['background'] . ';' : '';
			$color_value = $colors['color'] ? 'color: ' . $colors['color'] . ';' : '';

			$props = array_filter( array( $bg_value, $color_value ) );
			if ( $props ) {
				$selectors = '.block-editor-block-list__layout pre[class*="language-"],' .
				'.block-editor-block-list__layout code[class*="language-"]';
				wp_add_inline_style(
					'wzcbh-editor-canvas-style',
					$selectors . '{' . implode( ' ', $props ) . '}'
				);
			}
		}
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'wzcbh/v1',
			'/default-settings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_default_settings' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'language'         => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'lineNumbers'      => array(
						'type' => 'boolean',
					),
					'lineNumbersStart' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'wordWrap'         => array(
						'type' => 'boolean',
					),
					'maxHeight'        => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
			)
		);
	}

	/**
	 * Save default block settings via REST API.
	 *
	 * @since 1.1.0
	 *
	 * @param  \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function save_default_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$updated = array();

		if ( $request->has_param( 'language' ) ) {
			wzcbh_update_option( 'default-lang', $request->get_param( 'language' ) );
			$updated[] = 'language';
		}

		if ( $request->has_param( 'lineNumbers' ) ) {
			wzcbh_update_option( 'default-line-numbers', (bool) $request->get_param( 'lineNumbers' ) );
			$updated[] = 'lineNumbers';
		}

		if ( $request->has_param( 'lineNumbersStart' ) ) {
			wzcbh_update_option( 'default-line-numbers-start', (int) $request->get_param( 'lineNumbersStart' ) );
			$updated[] = 'lineNumbersStart';
		}

		if ( $request->has_param( 'wordWrap' ) ) {
			wzcbh_update_option( 'default-word-wrap', (bool) $request->get_param( 'wordWrap' ) );
			$updated[] = 'wordWrap';
		}

		if ( $request->has_param( 'maxHeight' ) ) {
			wzcbh_update_option( 'default-max-height', (int) $request->get_param( 'maxHeight' ) );
			$updated[] = 'maxHeight';
		}

		return new \WP_REST_Response( array( 'updated' => $updated ), 200 );
	}

	/**
	 * Filter the rendered HTML of core/code blocks to inject highlighting attributes.
	 *
	 * In client mode (Prism.js): injects language classes and data-attributes so the
	 * Prism JS bundle can highlight in the browser.
	 * In server mode (highlight.php): runs the highlighter server-side and replaces the
	 * <code> innerHTML before the page is sent; no Prism JS is loaded.
	 *
	 * @since 1.0.0
	 *
	 * @param  string               $block_content The rendered block HTML.
	 * @param  array<string, mixed> $block         The block data array.
	 * @return string
	 */
	public function render_code_block( string $block_content, array $block ): string {
		$attrs = $block['attrs'] ?? array();

		// `language` and `title` are sourced from HTML attributes in the save
		// function (lang= on <code>, data-title= on <pre>) and are not serialized
		// to the block JSON comment. Extract both here before dispatching.
		if ( empty( $attrs['language'] ) && preg_match( '/<code[^>]+\blang="([^"]+)"/', $block_content, $lm ) ) {
			$attrs['language'] = sanitize_key( $lm[1] );
		}
		if ( empty( $attrs['title'] ) && preg_match( '/<pre[^>]+\bdata-title="([^"]*)"/', $block_content, $tm ) ) {
			$attrs['title'] = html_entity_decode( $tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		// Fallback for posts saved by the old code-syntax-block plugin, which
		// stored the file name in pre[title] and was never re-saved. Mirrors the
		// `data-title || title` lookup in the client-side toolbar button.
		if ( empty( $attrs['title'] ) && preg_match( '/<pre[^>]*\stitle="([^"]*)"/', $block_content, $ltm ) ) {
			$attrs['title'] = html_entity_decode( $ltm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		$params = array(
			'language'           => sanitize_key( $attrs['language'] ?? '' ),
			'line_numbers'       => ! empty( $attrs['lineNumbers'] ),
			'line_numbers_start' => max( 1, (int) ( $attrs['lineNumbersStart'] ?? 1 ) ),
			'word_wrap'          => ! empty( $attrs['wordWrap'] ),
			'title'              => sanitize_text_field( $attrs['title'] ?? '' ),
			'highlight_lines'    => sanitize_text_field( $attrs['highlightLines'] ?? '' ),
			'max_height'         => max( 0, (int) ( $attrs['maxHeight'] ?? 0 ) ),
		);

		// Resolved before either render path runs: both mutate `language` ('' in
		// client mode, 'none' in server mode) for plain-text blocks, which would
		// lose the .txt extension if the filename were derived afterwards.
		$params['download']          = self::is_download_enabled( (string) ( $attrs['downloadButton'] ?? '' ) );
		$params['download_filename'] = self::get_download_filename( $params['title'], $params['language'] );

		if ( 'server' === wzcbh_get_option( 'highlighting-mode', 'client' ) ) {
			$block_content = $this->render_code_block_server( $block_content, $params );
		} else {
			$block_content = $this->render_code_block_client( $block_content, $params );
		}

		return $this->maybe_add_file_tab( $block_content, $params );
	}

	/**
	 * Whether the file name should render as a tab above the code block.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	protected static function is_file_tab_style(): bool {
		return (bool) wzcbh_get_option( 'show-file-name', true )
			&& 'tab' === wzcbh_get_option( 'file-name-style', 'tab' );
	}

	/**
	 * Whether the download button should render for a block.
	 *
	 * The per-block attribute is a tri-state string: an empty value inherits the
	 * global setting, `show` and `hide` override it.
	 *
	 * @since 1.2.0
	 *
	 * @param  string $override Per-block `downloadButton` attribute value.
	 * @return bool
	 */
	protected static function is_download_enabled( string $override ): bool {
		if ( 'show' === $override ) {
			return true;
		}
		if ( 'hide' === $override ) {
			return false;
		}

		return (bool) wzcbh_get_option( 'download-button', true );
	}

	/**
	 * Build the file name offered when a snippet is downloaded.
	 *
	 * Uses the block's file name / title when one is set, otherwise falls back to
	 * `snippet.{ext}` derived from the language slug.
	 *
	 * @since 1.2.0
	 *
	 * @param  string $title    The block file name / title.
	 * @param  string $language The block language slug.
	 * @return string
	 */
	protected static function get_download_filename( string $title, string $language ): string {
		if ( '' !== $title ) {
			$filename = sanitize_file_name( $title );

			if ( '' !== $filename ) {
				return $filename;
			}
		}

		$extension = self::get_language_extension( $language );

		if ( '' === $extension ) {
			return 'snippet.txt';
		}

		// A value without a leading dot is already a complete file name (Dockerfile).
		return 0 === strpos( $extension, '.' ) ? 'snippet' . $extension : $extension;
	}

	/**
	 * Map a language slug to the file extension used for downloads.
	 *
	 * Values starting with a dot are appended to `snippet`; a value without a dot
	 * is used as the complete file name (`Dockerfile`).
	 *
	 * @since 1.2.0
	 *
	 * @param  string $language The block language slug.
	 * @return string Extension including the leading dot, a bare file name, or an empty string.
	 */
	protected static function get_language_extension( string $language ): string {
		$extensions = array(
			'apacheconf' => '.conf',
			'bash'       => '.sh',
			'c'          => '.c',
			'cpp'        => '.cpp',
			'csharp'     => '.cs',
			'css'        => '.css',
			'dart'       => '.dart',
			'docker'     => 'Dockerfile',
			'fsharp'     => '.fs',
			'go'         => '.go',
			'graphql'    => '.graphql',
			'groovy'     => '.groovy',
			'haskell'    => '.hs',
			'markup'     => '.html',
			'java'       => '.java',
			'javascript' => '.js',
			'json'       => '.json',
			'jsx'        => '.jsx',
			'kotlin'     => '.kt',
			'lua'        => '.lua',
			'markdown'   => '.md',
			'nginx'      => '.conf',
			'objectivec' => '.m',
			'perl'       => '.pl',
			'php'        => '.php',
			'powershell' => '.ps1',
			'python'     => '.py',
			'r'          => '.r',
			'ruby'       => '.rb',
			'rust'       => '.rs',
			'sass'       => '.scss',
			'scala'      => '.scala',
			'sql'        => '.sql',
			'swift'      => '.swift',
			'text'       => '.txt',
			'toml'       => '.toml',
			'tsx'        => '.tsx',
			'typescript' => '.ts',
			'vim'        => '.vim',
			'xml'        => '.xml',
			'yaml'       => '.yml',
		);

		/**
		 * Filter the language slug to download file extension map.
		 *
		 * Values starting with a dot are appended to `snippet`; a value without a
		 * dot is used as the complete file name.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, string> $extensions Language slug => extension.
		 * @param string                $language   The language slug being resolved.
		 */
		$extensions = (array) apply_filters( 'wzcbh_download_extensions', $extensions, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		return (string) ( $extensions[ $language ] ?? '' );
	}

	/**
	 * Wrap the rendered block in a file-name tab header.
	 *
	 * Emitted by PHP in both highlighting modes so the markup is identical. In
	 * client mode Prism's toolbar plugin wraps the `<pre>` in `.code-toolbar`
	 * at runtime, which lands inside this wrapper; in server mode PHP has
	 * already added that wrapper. Both therefore converge on:
	 * `.wzcbh-code-wrapper > .wzcbh-file-tab + (.code-toolbar >) pre`.
	 *
	 * @since 1.2.0
	 *
	 * @param  string              $block_content The rendered block HTML.
	 * @param  array<string,mixed> $params        Normalised block parameters.
	 * @return string
	 */
	protected function maybe_add_file_tab( string $block_content, array $params ): string {
		$title = (string) $params['title'];

		if ( '' === $title || ! self::is_file_tab_style() ) {
			return $block_content;
		}

		$language = (string) $params['language'];
		$classes  = 'wzcbh-code-wrapper' . ( $language ? ' wzcbh-code-wrapper--' . sanitize_html_class( $language ) : '' );

		$tab = '<div class="wzcbh-file-tab"><span class="wzcbh-file-tab__name">'
			. esc_html( $title ) . '</span></div>';

		/**
		 * Filter the file name tab HTML rendered above a code block.
		 *
		 * @since 1.2.0
		 *
		 * @param string $tab      The tab HTML.
		 * @param string $title    The file name / title.
		 * @param string $language The block language slug.
		 */
		$tab = (string) apply_filters( 'wzcbh_file_tab_html', $tab, $title, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		return '<div class="' . esc_attr( $classes ) . '">' . $tab . $block_content . '</div>';
	}

	/**
	 * Rewrite the attributes of the first `<pre>` (or `<code>`) opening tag.
	 *
	 * Callback rather than a `preg_replace()` replacement string: the injected
	 * values are editor-supplied, and `$1` / `\0` / `\\` in a replacement are
	 * backreferences that `esc_attr()` does not escape.
	 *
	 * @since 1.2.2
	 *
	 * @param  string   $html    The block HTML.
	 * @param  string   $tag     Tag name to rewrite (`pre` or `code`).
	 * @param  callable $rewrite Receives the attribute string, returns its replacement.
	 * @return string
	 */
	protected static function rewrite_tag_attributes( string $html, string $tag, callable $rewrite ): string {
		return (string) preg_replace_callback(
			'/<' . $tag . '\b([^>]*)>/i',
			static function ( array $matches ) use ( $tag, $rewrite ): string {
				return '<' . $tag . $rewrite( $matches[1] ) . '>';
			},
			$html,
			1
		);
	}

	/**
	 * Merge class names into a tag's attribute string.
	 *
	 * Duplicates are dropped, and an empty list leaves the tag alone rather than
	 * adding `class=""`.
	 *
	 * @since 1.2.2
	 *
	 * @param  string   $attributes Raw attribute string of a single tag.
	 * @param  string[] $classes    Class names to add.
	 * @return string
	 */
	protected static function merge_tag_classes( string $attributes, array $classes ): string {
		// Escape only what we add; the existing text is already escaped.
		$classes = array_map( 'esc_attr', array_filter( $classes ) );

		if ( ! $classes ) {
			return $attributes;
		}

		if ( preg_match( '/\sclass="([^"]*)"/i', $attributes, $matches ) ) {
			$existing = preg_split( '/\s+/', trim( $matches[1] ), -1, PREG_SPLIT_NO_EMPTY );
			$existing = is_array( $existing ) ? $existing : array();
			$merged   = array_unique( array_merge( $existing, $classes ) );

			return substr_replace(
				$attributes,
				' class="' . implode( ' ', $merged ) . '"',
				(int) strpos( $attributes, $matches[0] ),
				strlen( $matches[0] )
			);
		}

		return $attributes . ' class="' . implode( ' ', array_unique( $classes ) ) . '"';
	}

	/**
	 * Set attributes on a tag's attribute string, replacing any already present.
	 *
	 * The save function already writes `data-title`, `data-line` and `data-start`,
	 * so these replace rather than duplicate them — the value written here is the
	 * sanitised one.
	 *
	 * @since 1.2.2
	 *
	 * @param  string                $attributes Raw attribute string of a single tag.
	 * @param  array<string, string> $new_attrs  Attribute name => already-escaped value.
	 * @return string
	 */
	protected static function set_tag_attributes( string $attributes, array $new_attrs ): string {
		foreach ( $new_attrs as $name => $value ) {
			$replacement = ' ' . $name . '="' . $value . '"';
			$pattern     = '/\s' . preg_quote( $name, '/' ) . '="[^"]*"/i';

			if ( preg_match( $pattern, $attributes, $matches ) ) {
				$attributes = substr_replace(
					$attributes,
					$replacement,
					(int) strpos( $attributes, $matches[0] ),
					strlen( $matches[0] )
				);
				continue;
			}

			$attributes .= $replacement;
		}

		return $attributes;
	}

	/**
	 * Append declarations to a tag's inline `style` attribute.
	 *
	 * @since 1.2.2
	 *
	 * @param  string $attributes   Raw attribute string of a single tag.
	 * @param  string $declarations CSS declarations to append, semicolon terminated.
	 * @return string
	 */
	protected static function merge_tag_style( string $attributes, string $declarations ): string {
		if ( '' === $declarations ) {
			return $attributes;
		}

		$declarations = esc_attr( $declarations );

		if ( preg_match( '/\sstyle="([^"]*)"/i', $attributes, $matches ) ) {
			return substr_replace(
				$attributes,
				' style="' . trim( $matches[1] . ' ' . $declarations ) . '"',
				(int) strpos( $attributes, $matches[0] ),
				strlen( $matches[0] )
			);
		}

		return $attributes . ' style="' . $declarations . '"';
	}

	/**
	 * Render a code block in client mode (Prism.js).
	 *
	 * Injects language classes and data-attributes into the existing saved HTML
	 * so the Prism JS bundle can highlight in the browser.
	 *
	 * @since 1.1.0
	 *
	 * @param  string              $block_content The rendered block HTML.
	 * @param  array<string,mixed> $params Normalised block parameters.
	 * @return string
	 */
	protected function render_code_block_client( string $block_content, array $params ): string {
		$language           = $params['language'];
		$line_numbers       = $params['line_numbers'];
		$line_numbers_start = $params['line_numbers_start'];
		$word_wrap          = $params['word_wrap'];
		$title              = $params['title'];
		$highlight_lines    = $params['highlight_lines'];

		// 'text' is stored as the block attribute value but Prism has no 'text'
		// grammar. Map it to 'none' so Prism applies theme styling without
		// highlighting. Replace in the saved HTML (which already carries
		// language-text on both <pre> and <code>) rather than appending.
		if ( 'text' === $language ) {
			$block_content = str_replace( 'language-text', 'language-none', $block_content );
			$language      = '';
		}

		// ── Apply language class to <code> (duplicates are dropped) ──────────
		if ( $language ) {
			$lang_class    = 'language-' . $language;
			$block_content = self::rewrite_tag_attributes(
				$block_content,
				'code',
				static function ( string $attributes ) use ( $lang_class ): string {
					return self::merge_tag_classes( $attributes, array( $lang_class ) );
				}
			);
		}

		// ── Apply classes and data attributes to <pre> ────────────────────────
		$pre_classes = array();
		$pre_attrs   = array();

		if ( $line_numbers ) {
			$pre_classes[] = 'line-numbers';
			if ( 1 !== $line_numbers_start ) {
				$pre_attrs['data-start'] = esc_attr( (string) $line_numbers_start );
			}
		}

		if ( $word_wrap ) {
			$pre_classes[] = 'word-wrap';
		}

		if ( $title ) {
			$pre_attrs['data-title'] = esc_attr( $title );
		}

		if ( $highlight_lines ) {
			$pre_attrs['data-line'] = esc_attr( $highlight_lines );
		}

		// The global setting and the per-block override are already resolved, so
		// the toolbar button only has to check for the attribute's presence.
		if ( ! empty( $params['download'] ) ) {
			$pre_attrs['data-wzcbh-download'] = esc_attr( (string) $params['download_filename'] );
		}

		if ( $pre_classes || $pre_attrs ) {
			$block_content = self::rewrite_tag_attributes(
				$block_content,
				'pre',
				static function ( string $attributes ) use ( $pre_classes, $pre_attrs ): string {
					return self::set_tag_attributes(
						self::merge_tag_classes( $attributes, $pre_classes ),
						$pre_attrs
					);
				}
			);
		}

		return $block_content;
	}

	/**
	 * Render a code block using server-side highlight.php highlighting.
	 *
	 * Replaces the <code> element innerHTML with pre-highlighted HTML spans,
	 * and injects toolbar overlays (language label, file name, copy button)
	 * as PHP-rendered HTML so no Prism JS is required.
	 *
	 * @since 1.1.0
	 *
	 * @param  string              $block_content The rendered block HTML.
	 * @param  array<string,mixed> $params        Normalised block parameters.
	 * @return string
	 */
	protected function render_code_block_server( string $block_content, array $params ): string {
		$language           = $params['language'];
		$line_numbers       = $params['line_numbers'];
		$line_numbers_start = $params['line_numbers_start'];
		$word_wrap          = $params['word_wrap'];
		$title              = $params['title'];
		$highlight_lines    = $params['highlight_lines'];
		$max_height         = $params['max_height'];

		// 'text' is stored as the block attribute value but there is no 'text'
		// grammar. Map it to 'none' so plain text blocks get consistent class
		// names with the client-side mode. Replace in the saved HTML (which
		// already carries language-text on both <pre> and <code>) rather than
		// appending.
		if ( 'text' === $language ) {
			$block_content = str_replace( 'language-text', 'language-none', $block_content );
			$language      = 'none';
		}

		// ── Extract raw code from saved HTML ──────────────────────────────────
		if ( ! preg_match( '/<code[^>]*>([\s\S]*?)<\/code>/i', $block_content, $m ) ) {
			return $block_content;
		}
		$inner_html = $m[1];
		$plain_code = html_entity_decode( $inner_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// ── Map Prism slug → hljs slug ────────────────────────────────────────
		$hljs_lang = self::get_hljs_language( $language );

		// ── Run highlight.php ──────────────────────────────────────────────────
		$highlighted = '';
		if ( $hljs_lang && strlen( $plain_code ) <= self::get_highlight_size_limit( $language ) ) {
			try {
				$result      = self::get_highlighter()->highlight( $hljs_lang, $plain_code );
				$highlighted = self::remap_token_classes( $result->value );
			} catch ( \Throwable $e ) {
				$highlighted = esc_html( $plain_code );
			}
		} else {
			$highlighted = esc_html( $plain_code );
		}

		// ── Line count (for line-numbers-rows) ────────────────────────────────
		// One line per newline, plus a final line only when the code does not end
		// on one. Matches what Prism's line-numbers plugin counts in the browser,
		// and what wrap_lines() emits below.
		$line_count = max(
			1,
			substr_count( $plain_code, "\n" ) + ( "\n" === substr( $plain_code, -1 ) ? 0 : 1 )
		);

		// ── Wrap lines for line highlighting (and line numbers) ───────────────
		// Run wrap_lines() whenever line numbers OR line highlighting is active
		// so that line highlighting works independently of line numbers. Ranges
		// are clamped to the lines this block actually has.
		$target_lines = array();
		if ( $highlight_lines ) {
			$target_lines = self::parse_line_ranges(
				$highlight_lines,
				$line_numbers_start,
				$line_numbers_start + $line_count - 1
			);
		}

		if ( $line_numbers || $target_lines ) {
			$highlighted = self::wrap_lines( $highlighted, $line_numbers_start, $target_lines );
		}

		// ── Line-numbers gutter ───────────────────────────────────────────────
		// Generate the same DOM structure as Prism's line-numbers plugin so the
		// bundled frontend.css renders the gutter identically. Prism appends the
		// .line-numbers-rows span inside the <code> element (env.element), where
		// it anchors to `pre.line-numbers > code { position: relative }`. Placing
		// it inside <pre> instead would anchor it to the <pre> padding box, so the
		// gutter's `left: -3.8em` would fall outside the content area and be
		// clipped by `pre { overflow: auto }` — making line numbers invisible.
		$line_numbers_html = '';
		if ( $line_numbers ) {
			$rows_inner        = str_repeat( '<span></span>', $line_count );
			$line_numbers_html = '<span aria-hidden="true" class="line-numbers-rows">'
				. $rows_inner . '</span>';
		}

		// ── Rebuild <code> element ─────────────────────────────────────────────
		$code_classes = $language ? 'language-' . esc_attr( $language ) : '';
		$new_code     = '<code' . ( $code_classes ? ' class="' . $code_classes . '"' : '' ) . '>'
			. $highlighted . $line_numbers_html . '</code>';

		$block_content = preg_replace_callback(
			'/<code[^>]*>[\s\S]*?<\/code>/i',
			static function () use ( $new_code ): string {
				return $new_code;
			},
			$block_content,
			1
		);

		// ── Apply classes and the line-number counter offset to <pre> ─────────
		// The counter-reset mirrors what Prism JS does at runtime by reading
		// data-start.
		$pre_classes = array();
		if ( $language ) {
			$pre_classes[] = 'language-' . $language;
		}
		if ( $line_numbers ) {
			$pre_classes[] = 'line-numbers';
		}
		if ( $word_wrap ) {
			$pre_classes[] = 'word-wrap';
		}

		$counter_decl = ( $line_numbers && $line_numbers_start > 1 )
			? 'counter-reset: linenumber ' . ( $line_numbers_start - 1 ) . ';'
			: '';

		if ( $pre_classes || '' !== $counter_decl ) {
			$block_content = self::rewrite_tag_attributes(
				$block_content,
				'pre',
				static function ( string $attributes ) use ( $pre_classes, $counter_decl ): string {
					return self::merge_tag_style(
						self::merge_tag_classes( $attributes, $pre_classes ),
						$counter_decl
					);
				}
			);
		}

		// ── Toolbar: same HTML structure as Prism's toolbar plugin ────────────
		$show_copy  = (bool) wzcbh_get_option( 'copy-to-clipboard', true );
		$show_label = (bool) wzcbh_get_option( 'show-language-label', true );
		// Suppressed when the file name renders as a tab, so it is not shown twice.
		$show_title = (bool) wzcbh_get_option( 'show-file-name', true ) && ! self::is_file_tab_style();

		$toolbar_items = '';

		// Expand/collapse button (shown when maxHeight inline style is set).
		if ( $max_height > 0 ) {
			$toolbar_items .= '<div class="toolbar-item">'
				. '<button type="button" class="wzcbh-expand-button" aria-expanded="false">'
				. esc_html__( 'Expand', 'webberzone-code-block-highlighting' )
				. '</button></div>';
		}

		// Language label (aria-hidden decorative span, same as Prism show-language).
		if ( $show_label && $hljs_lang ) {
			$languages      = self::get_languages();
			$label          = $languages[ $language ] ?? strtoupper( $hljs_lang );
			$toolbar_items .= '<div class="toolbar-item">'
				. '<span aria-hidden="true">' . esc_html( $label ) . '</span></div>';
		}

		// File name / title (wzcbh-toolbar-title span, same as Prism wzcbh-title button).
		if ( $show_title && $title ) {
			$toolbar_items .= '<div class="toolbar-item">'
				. '<span class="wzcbh-toolbar-title">' . esc_html( $title ) . '</span></div>';
		}

		// Copy button (same class/structure as Prism copy-to-clipboard plugin).
		if ( $show_copy ) {
			$toolbar_items .= '<div class="toolbar-item">'
				. '<button class="copy-to-clipboard-button" type="button" data-copy-state="copy">'
				. '<span>' . esc_html__( 'Copy', 'webberzone-code-block-highlighting' ) . '</span>'
				. '</button></div>';
		}

		// Download button, rendered next to Copy in both modes.
		if ( ! empty( $params['download'] ) ) {
			$filename       = (string) $params['download_filename'];
			$toolbar_items .= '<div class="toolbar-item">'
				. '<button class="wzcbh-download-button" type="button"'
				. ' data-wzcbh-download="' . esc_attr( $filename ) . '"'
				. ' aria-label="' . esc_attr(
					sprintf(
						/* translators: %s: file name of the downloaded snippet */
						__( 'Download code as %s', 'webberzone-code-block-highlighting' ),
						$filename
					)
				) . '">'
				. '<span>' . esc_html__( 'Download', 'webberzone-code-block-highlighting' ) . '</span>'
				. '</button></div>';
		}

		if ( $toolbar_items ) {
			$block_content = '<div class="code-toolbar">'
				. $block_content
				. '<div class="toolbar">' . $toolbar_items . '</div>'
				. '</div>';
		}

		return $block_content;
	}

	/**
	 * Remap highlight.php's hljs-* token class names to Prism's token class names.
	 *
	 * Highlight.php emits single-class spans (e.g. <span class="hljs-keyword">).
	 * Remapping to Prism's token classes (e.g. <span class="token keyword">) lets
	 * server mode reuse the Prism theme CSS directly, giving exact theme parity
	 * across all 21 themes with no per-theme hljs CSS files needed.
	 *
	 * @since 1.1.0
	 *
	 * @param string $html Highlighted HTML from highlight.php.
	 * @return string HTML with hljs class names replaced by Prism token class names.
	 */
	protected static function remap_token_classes( string $html ): string {
		static $map = null;

		if ( null === $map ) {
			$map = array(
				'class="hljs-keyword"'           => 'class="token keyword"',
				'class="hljs-string"'            => 'class="token string"',
				'class="hljs-comment"'           => 'class="token comment"',
				'class="hljs-number"'            => 'class="token number"',
				'class="hljs-operator"'          => 'class="token operator"',
				'class="hljs-punctuation"'       => 'class="token punctuation"',
				'class="hljs-variable"'          => 'class="token variable"',
				'class="hljs-template-variable"' => 'class="token variable"',
				'class="hljs-attr"'              => 'class="token attr-name"',
				'class="hljs-attribute"'         => 'class="token attr-name"',
				'class="hljs-symbol"'            => 'class="token symbol"',
				'class="hljs-built_in"'          => 'class="token builtin"',
				'class="hljs-type"'              => 'class="token class-name"',
				'class="hljs-class"'             => 'class="token class-name"',
				'class="hljs-title"'             => 'class="token function"',
				'class="hljs-section"'           => 'class="token function"',
				'class="hljs-params"'            => 'class="token parameter"',
				'class="hljs-literal"'           => 'class="token boolean"',
				'class="hljs-regexp"'            => 'class="token regex"',
				'class="hljs-meta"'              => 'class="token decorator"',
				'class="hljs-tag"'               => 'class="token tag"',
				'class="hljs-name"'              => 'class="token tag"',
				'class="hljs-selector-tag"'      => 'class="token selector"',
				'class="hljs-selector-id"'       => 'class="token selector"',
				'class="hljs-selector-class"'    => 'class="token selector"',
				'class="hljs-selector-attr"'     => 'class="token attr-name"',
				'class="hljs-selector-pseudo"'   => 'class="token selector"',
				'class="hljs-addition"'          => 'class="token inserted"',
				'class="hljs-deletion"'          => 'class="token deleted"',
				'class="hljs-emphasis"'          => 'class="token italic"',
				'class="hljs-strong"'            => 'class="token bold"',
				'class="hljs-link"'              => 'class="token url"',
				'class="hljs-quote"'             => 'class="token comment"',
				'class="hljs-doctag"'            => 'class="token doctype"',
				'class="hljs-formula"'           => 'class="token keyword"',
				'class="hljs-bullet"'            => 'class="token punctuation"',
				'class="hljs-subst"'             => 'class="token interpolation"',
				'class="hljs-function"'          => 'class="token"',
				'class="hljs-meta-keyword"'      => 'class="token keyword"',
				'class="hljs-meta-string"'       => 'class="token string"',
			);
		}

		return strtr( $html, $map );
	}

	/**
	 * Get the shared highlight.php highlighter, constructing it on first use.
	 *
	 * The instance carries no per-call state, and constructing it dominates the
	 * cost of pages with many small blocks, so one is shared per request.
	 *
	 * @since 1.2.2
	 *
	 * @return \Highlight\Highlighter
	 */
	protected static function get_highlighter() {
		$highlighter = self::$highlighter;

		if ( null === $highlighter ) {
			self::load_highlighter();
			$highlighter       = new \Highlight\Highlighter();
			self::$highlighter = $highlighter;
		}

		return $highlighter;
	}

	/**
	 * Largest code block, in bytes, that is highlighted server-side.
	 *
	 * highlight.php's cost is quadratic in input size and runs inline in the page
	 * render, so oversized blocks fall back to escaped plain text in the same
	 * themed markup. Client mode is unaffected.
	 *
	 * @since 1.2.2
	 *
	 * @param  string $language The block language slug.
	 * @return int Size limit in bytes. Zero or less disables server highlighting.
	 */
	protected static function get_highlight_size_limit( string $language ): int {
		/**
		 * Filter the largest code block that is highlighted server-side.
		 *
		 * Blocks above this size render as escaped plain text. Set to 0 to disable
		 * server-side highlighting entirely.
		 *
		 * @since 1.2.2
		 *
		 * @param int    $limit    Size limit in bytes. Default 131072 (128KB).
		 * @param string $language The block language slug.
		 */
		return (int) apply_filters( 'wzcbh_server_highlight_max_bytes', 131072, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Load the highlight.php library.
	 *
	 * The highlight.php library is a multi-file PSR-0 library bundled under
	 * vendor/, so it cannot be pulled in with a single require_once. It ships its
	 * own autoloader for projects that don't boot Composer's autoloader; we
	 * register that rather than the global vendor/autoload.php to avoid leaking
	 * unrelated dependencies into the WordPress runtime. Runs once per request.
	 *
	 * @since 1.1.0
	 */
	protected static function load_highlighter(): void {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}
		$loaded = true;

		$autoloader = WZCBH_PLUGIN_DIR . 'vendor/scrivo/highlight.php/Highlight/Autoloader.php';
		if ( is_file( $autoloader ) ) {
			require_once $autoloader;
			spl_autoload_register(
				static function ( string $class_name ): void {
					\Highlight\Autoloader::load( $class_name );
				}
			);
		}
	}

	/**
	 * Map a Prism language slug to the equivalent highlight.php slug.
	 *
	 * Languages not in the map are passed through unchanged; an empty string
	 * means "no highlighting" (plain text).
	 *
	 * @since 1.1.0
	 *
	 * @param  string $prism_slug Prism language slug from the block attribute.
	 * @return string hljs language slug, or empty string for plain text.
	 */
	protected static function get_hljs_language( string $prism_slug ): string {
		$map = array(
			'markup'     => 'xml',
			'text'       => '',
			'none'       => '',
			'jsx'        => 'javascript',
			'tsx'        => 'typescript',
			'sass'       => 'scss',
			'docker'     => 'dockerfile',
			'apacheconf' => 'apache',
			'toml'       => 'ini',
			'fsharp'     => 'fsharp',
			'objectivec' => 'objectivec',
			'powershell' => 'powershell',
		);

		if ( array_key_exists( $prism_slug, $map ) ) {
			return $map[ $prism_slug ];
		}

		return $prism_slug;
	}

	/**
	 * Wrap each line of highlighted HTML in a <span> for line numbers and
	 * line highlighting. Properly closes and reopens inline <span> elements
	 * at line boundaries so the output remains valid HTML.
	 *
	 * @since 1.1.0
	 *
	 * @param  string           $html         Highlighted HTML from highlight.php.
	 * @param  int              $start        First line number.
	 * @param  array<int, true> $target_lines Set of line numbers that should be highlighted.
	 * @return string
	 */
	protected static function wrap_lines( string $html, int $start, array $target_lines ): string {
		$result    = '';
		$open_tags = array(); // Stack of opening tag strings.
		$line_buf  = '';
		$line_num  = $start;
		$i         = 0;
		$len       = strlen( $html );

		// Text only — tags do not count. $line_buf is seeded with the reopened
		// tags after a newline, and highlight.php closes its outermost span after
		// the trailing one, so testing $line_buf emitted a phantom final line.
		$line_has_text = false;

		while ( $i < $len ) {
			$ch = $html[ $i ];

			if ( '<' === $ch ) {
				$j = strpos( $html, '>', $i );
				if ( false === $j ) {
					$line_buf     .= substr( $html, $i );
					$line_has_text = true;
					break;
				}
				$tag = substr( $html, $i, $j - $i + 1 );

				if ( 0 === strncmp( $tag, '</', 2 ) ) {
					array_pop( $open_tags );
				} elseif ( '/' !== $tag[ strlen( $tag ) - 2 ] ) {
					$open_tags[] = $tag;
				}

				$line_buf .= $tag;
				$i         = $j + 1;
			} elseif ( "\n" === $ch ) {
				$close_str  = '';
				$reopen_str = '';
				foreach ( array_reverse( $open_tags ) as $open_tag ) {
					preg_match( '/<([a-zA-Z][a-zA-Z0-9]*)/', $open_tag, $tm );
					$close_str .= '</' . ( $tm[1] ?? 'span' ) . '>';
				}
				foreach ( $open_tags as $open_tag ) {
					$reopen_str .= $open_tag;
				}

				$result       .= self::make_line_span( $line_num, $target_lines, $line_buf . $close_str . "\n" );
				$line_buf      = $reopen_str;
				$line_has_text = false;
				++$line_num;
				++$i;
			} else {
				$line_buf     .= $ch;
				$line_has_text = true;
				++$i;
			}
		}

		// Emit the final line (common when code has no trailing newline).
		if ( $line_has_text ) {
			$close_str = '';
			foreach ( array_reverse( $open_tags ) as $open_tag ) {
				preg_match( '/<([a-zA-Z][a-zA-Z0-9]*)/', $open_tag, $tm );
				$close_str .= '</' . ( $tm[1] ?? 'span' ) . '>';
			}
			$result .= self::make_line_span( $line_num, $target_lines, $line_buf . $close_str );
		}

		return $result;
	}

	/**
	 * Build a single <span class="wzcbh-line"> wrapper for one line.
	 *
	 * @since 1.1.0
	 *
	 * @param int              $line_num     Current line number.
	 * @param array<int, true> $target_lines Set of line numbers that should be highlighted.
	 * @param string           $content      Inner HTML content for this line.
	 * @return string
	 */
	protected static function make_line_span( int $line_num, array $target_lines, string $content ): string {
		$classes = 'wzcbh-line';
		if ( isset( $target_lines[ $line_num ] ) ) {
			$classes .= ' wzcbh-highlighted-line';
		}
		return '<span class="' . $classes . '" data-line-number="' . $line_num . '">'
			. $content . '</span>';
	}

	/**
	 * Parse a Prism-style line range string into a set of line numbers.
	 *
	 * Accepts e.g. "1,3-5,7" → [1 => true, 3 => true, 4 => true, 5 => true, 7 => true].
	 *
	 * Ranges are clamped before expanding, so a spec of `1-999999999` cannot
	 * allocate an entry per value; those lines never matched anything anyway.
	 * Keyed by line number so membership is isset() rather than in_array().
	 *
	 * @since 1.1.0
	 * @since 1.2.2 Added the $min_line and $max_line clamps, and the keyed return shape.
	 *
	 * @param  string $spec     The line range specification.
	 * @param  int    $min_line Lowest line number the block renders.
	 * @param  int    $max_line Highest line number the block renders.
	 * @return array<int, true> Set of line numbers, keyed by line number.
	 */
	protected static function parse_line_ranges( string $spec, int $min_line = 1, int $max_line = PHP_INT_MAX ): array {
		$lines = array();

		foreach ( explode( ',', $spec ) as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}

			if ( strpos( $part, '-' ) !== false ) {
				list( $from, $to ) = explode( '-', $part, 2 );
				$from              = (int) trim( $from );
				$to                = (int) trim( $to );
			} else {
				$from = (int) $part;
				$to   = $from;
			}

			$from = max( $min_line, $from );
			$to   = min( $max_line, $to );

			for ( $n = $from; $n <= $to; $n++ ) {
				$lines[ $n ] = true;
			}
		}

		return $lines;
	}

	/**
	 * Get the list of supported Prism.js languages.
	 *
	 * The array key must match a valid Prism language alias.
	 * See https://prismjs.com/#supported-languages
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function get_languages(): array {
		$languages = array(
			'apacheconf' => 'Apache Config',
			'bash'       => 'Bash/Shell',
			'c'          => 'C',
			'cpp'        => 'C++',
			'csharp'     => 'C#',
			'css'        => 'CSS',
			'dart'       => 'Dart',
			'docker'     => 'Docker',
			'fsharp'     => 'F#',
			'go'         => 'Go',
			'graphql'    => 'GraphQL',
			'groovy'     => 'Groovy',
			'haskell'    => 'Haskell',
			'markup'     => 'HTML',
			'java'       => 'Java',
			'javascript' => 'JavaScript',
			'json'       => 'JSON',
			'jsx'        => 'JSX',
			'kotlin'     => 'Kotlin',
			'lua'        => 'Lua',
			'markdown'   => 'Markdown',
			'nginx'      => 'Nginx',
			'objectivec' => 'Objective-C',
			'perl'       => 'Perl',
			'php'        => 'PHP',
			'powershell' => 'PowerShell',
			'python'     => 'Python',
			'r'          => 'R',
			'ruby'       => 'Ruby',
			'rust'       => 'Rust',
			'sass'       => 'Sass',
			'scala'      => 'Scala',
			'sql'        => 'SQL',
			'swift'      => 'Swift',
			'text'       => 'Plain Text',
			'toml'       => 'TOML',
			'tsx'        => 'TSX',
			'typescript' => 'TypeScript',
			'vim'        => 'Vim',
			'xml'        => 'XML',
			'yaml'       => 'YAML',
		);

		/**
		 * Filter the list of supported languages available in the editor language picker.
		 *
		 * The array key must match a valid Prism language alias:
		 * https://prismjs.com/#supported-languages
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $languages Language slug => display label.
		 */
		return apply_filters( 'wzcbh_languages', $languages ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}
}
