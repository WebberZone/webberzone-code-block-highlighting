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
	 * Bytes sent to highlight.php during this request.
	 *
	 * @since 1.4.0
	 *
	 * @var int
	 */
	protected static $highlight_budget_used = 0;

	/**
	 * Filtered per-request highlight budget, read on first use.
	 *
	 * @since 1.4.0
	 *
	 * @var int|null
	 */
	protected static $highlight_budget = null;

	/**
	 * Largest block that falls back to Prism.js, -1 when the fallback is off, read on first use.
	 *
	 * @since 1.4.0
	 *
	 * @var int|null
	 */
	protected static $prism_fallback_max_bytes = null;

	/**
	 * Bytes sent to the Prism.js fallback during this request.
	 *
	 * @since 1.4.0
	 *
	 * @var int
	 */
	protected static $prism_fallback_used = 0;

	/**
	 * Filtered per-request Prism.js fallback budget, read on first use.
	 *
	 * @since 1.4.0
	 *
	 * @var int|null
	 */
	protected static $prism_fallback_budget = null;

	/**
	 * Whether this request renders a front-end page that can run Prism.js, read on first use.
	 *
	 * @since 1.4.0
	 *
	 * @var bool|null
	 */
	protected static $is_page_request = null;

	/**
	 * Uncached language detections run during this request.
	 *
	 * @since 1.4.0
	 *
	 * @var int
	 */
	protected static $auto_detect_used = 0;

	/**
	 * Filtered per-request language detection limit, read on first use.
	 *
	 * @since 1.4.0
	 *
	 * @var int|null
	 */
	protected static $auto_detect_limit = null;

	/**
	 * Filtered executable download extensions, built on first use when the filter is hooked.
	 *
	 * @since 1.4.0
	 *
	 * @var array<string, true>|null
	 */
	protected static $blocked_download_extensions = null;

	/**
	 * Executable file extensions a download title may not keep.
	 *
	 * @since 1.4.0
	 *
	 * @var array<string, true>
	 */
	private const BLOCKED_DOWNLOAD_EXTENSIONS = array(
		'exe' => true,
		'bat' => true,
		'cmd' => true,
		'com' => true,
		'scr' => true,
		'msi' => true,
		'dll' => true,
		'vbs' => true,
		'vbe' => true,
		'wsf' => true,
		'hta' => true,
		'cpl' => true,
		'pif' => true,
		'lnk' => true,
		'reg' => true,
		'jar' => true,
		'apk' => true,
		'app' => true,
		'dmg' => true,
		'pkg' => true,
		'deb' => true,
		'rpm' => true,
		'bin' => true,
		'run' => true,
		'iso' => true,
	);

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
			'downloadButton'   => (string) wzcbh_get_option( 'default-download-button', '' ),
			'colorScheme'      => Settings::sanitize_block_color_scheme( (string) wzcbh_get_option( 'default-color-scheme', '' ) ),
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
					'const cbhColorSchemes = ' . wp_json_encode( Settings::$color_schemes, $flags ) . ';',
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

		if ( wp_style_is( 'wzcbh-editor-canvas-style', 'enqueued' ) ) {
			wp_add_inline_style( 'wzcbh-editor-canvas-style', self::get_editor_dark_mode_css() . self::get_editor_color_scheme_css() );
		}
	}

	/**
	 * Build the editor canvas rules for the dark mode color scheme.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	protected static function get_editor_dark_mode_css(): string {
		$slug = Settings::get_dark_color_scheme_slug();

		if ( '' === $slug ) {
			return '';
		}

		$colors = Settings::extract_theme_colors( WZCBH_PLUGIN_DIR . "includes/assets/{$slug}.css" );
		$props  = array( 'background: ' . ( '' !== $colors['background'] ? $colors['background'] : Settings::FALLBACK_TAB_BACKGROUND ) . ';' );

		if ( '' !== $colors['color'] ) {
			$props[] = 'color: ' . $colors['color'] . ';';
		}

		$prefix = '' === Settings::get_dark_mode_selector() ? '' : Settings::get_dark_mode_scope() . ' ';

		return Settings::wrap_dark_mode_css(
			$prefix . '.block-editor-block-list__layout pre[class*="language-"],' .
			$prefix . '.block-editor-block-list__layout code[class*="language-"]{' . implode( ' ', $props ) . '}'
		);
	}

	/**
	 * Build the editor canvas rules for every per-block color scheme, since a block can switch at any time.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	protected static function get_editor_color_scheme_css(): string {
		$css = '';

		foreach ( array_keys( Settings::$color_schemes ) as $slug ) {
			$colors = Settings::extract_theme_colors( WZCBH_PLUGIN_DIR . "includes/assets/{$slug}.css" );
			// A transparent theme background (Synthwave '84) would show the global scheme through.
			$props = array( 'background: ' . ( '' !== $colors['background'] ? $colors['background'] : Settings::FALLBACK_TAB_BACKGROUND ) . ';' );

			if ( '' !== $colors['color'] ) {
				$props[] = 'color: ' . $colors['color'] . ';';
			}

			$css .= '.block-editor-block-list__layout pre.wp-block-code.' . self::get_color_scheme_class( $slug ) . '{' . implode( ' ', $props ) . '}';
		}

		return $css;
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
						'maximum' => 1000000,
					),
					'wordWrap'         => array(
						'type' => 'boolean',
					),
					'maxHeight'        => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'downloadButton'   => array(
						'type' => 'string',
						'enum' => array( '', 'show', 'hide' ),
					),
					'colorScheme'      => array(
						'type' => 'string',
						'enum' => array_merge( array( '' ), array_keys( Settings::$color_schemes ) ),
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

		if ( $request->has_param( 'downloadButton' ) ) {
			wzcbh_update_option( 'default-download-button', (string) $request->get_param( 'downloadButton' ) );
			$updated[] = 'downloadButton';
		}

		if ( $request->has_param( 'colorScheme' ) ) {
			wzcbh_update_option( 'default-color-scheme', Settings::sanitize_block_color_scheme( (string) $request->get_param( 'colorScheme' ) ) );
			$updated[] = 'colorScheme';
		}

		return new \WP_REST_Response( array( 'updated' => $updated ), 200 );
	}

	/**
	 * Filter the rendered HTML of core/code blocks to inject highlighting attributes.
	 *
	 * Client mode adds what Prism.js reads; server mode replaces the `<code>` content with
	 * highlight.php output, falling back to client markup above the server limits.
	 *
	 * @since 1.0.0
	 *
	 * @param  string               $block_content The rendered block HTML.
	 * @param  array<string, mixed> $block         The block data array.
	 * @return string
	 */
	public function render_code_block( string $block_content, array $block ): string {
		$attrs = $block['attrs'] ?? array();

		// Markup saved by 1.2.3 and earlier, or by code-syntax-block, keeps the language and title in the HTML.
		$need_language = empty( $attrs['language'] ) && false !== stripos( $block_content, 'lang' );
		$need_title    = empty( $attrs['title'] ) && false !== stripos( $block_content, 'title' );
		if ( $need_language || $need_title ) {
			$attrs = array_merge( $attrs, self::get_legacy_attributes( $block_content, $need_language, $need_title ) );
		}

		$line_start = (int) ( $attrs['lineNumbersStart'] ?? 1 );
		$max_height = (int) ( $attrs['maxHeight'] ?? 0 );

		$params = array(
			'language'           => sanitize_key( $attrs['language'] ?? '' ),
			'line_numbers'       => ! empty( $attrs['lineNumbers'] ),
			'line_numbers_start' => $line_start < 1 ? 1 : ( $line_start > 1000000 ? 1000000 : $line_start ),
			'word_wrap'          => ! empty( $attrs['wordWrap'] ),
			'diff'               => ! empty( $attrs['diff'] ),
			'title'              => sanitize_text_field( $attrs['title'] ?? '' ),
			'highlight_lines'    => sanitize_text_field( $attrs['highlightLines'] ?? '' ),
			'max_height'         => $max_height < 0 ? 0 : ( $max_height > 100000 ? 100000 : $max_height ),
			'color_scheme'       => Settings::sanitize_block_color_scheme( (string) ( $attrs['colorScheme'] ?? '' ) ),
		);

		// Without a base language there is nothing to combine with, so the block is a plain diff.
		if ( $params['diff'] && in_array( $params['language'], array( '', 'text', 'none', 'diff' ), true ) ) {
			$params['language'] = 'diff';
			$params['diff']     = false;
		}

		$server_mode = 'server' === wzcbh_get_option( 'highlighting-mode', 'client' );

		// Before the download filename, so a detected language also sets the extension.
		if ( $server_mode && '' === $params['language'] && wzcbh_get_option( 'auto-detect-language', false ) ) {
			$params['language'] = self::detect_language( $block_content );
		}

		// Resolved before either render path runs: both render paths map '' and
		// 'text' to 'none' for plain-text blocks, which would lose the .txt
		// extension if the filename were derived afterwards.
		$params['download']          = self::is_download_enabled( (string) ( $attrs['downloadButton'] ?? '' ) );
		$params['download_filename'] = self::get_download_filename( $params['title'], $params['diff'] ? 'diff' : $params['language'] );

		if ( $server_mode ) {
			$block_content = $this->render_code_block_server( $block_content, $params );
		} else {
			$block_content = $this->render_code_block_client( $block_content, $params );
		}

		$has_tab       = self::has_file_tab( $params );
		$block_content = $this->maybe_add_file_tab( $block_content, $params );

		// An ancestor, so the scope also reaches the toolbar Prism wraps around the <pre>.
		if ( $params['color_scheme'] && ! $has_tab ) {
			$block_content = '<div class="' . esc_attr( self::get_color_scheme_class( $params['color_scheme'] ) ) . '">' . $block_content . '</div>';
		}

		return $block_content;
	}

	/**
	 * Read the language and title that markup saved by 1.2.3 and earlier keeps in the HTML.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $block_content The rendered block HTML.
	 * @param  bool   $language      Whether to read `lang` from the `<code>`.
	 * @param  bool   $title         Whether to read `data-title`, then `title`, from the `<pre>`.
	 * @return array<string, string> Found `language` and `title` attributes.
	 */
	protected static function get_legacy_attributes( string $block_content, bool $language, bool $title ): array {
		$found = array();
		$tags  = self::parse_simple_tags( $block_content );

		if ( null !== $tags ) {
			if ( $title ) {
				$found['title'] = $tags['pre'][2]['data-title'] ?? '';
				if ( '' === $found['title'] ) {
					$found['title'] = $tags['pre'][2]['title'] ?? '';
				}
			}
			if ( $language ) {
				$found['language'] = sanitize_key( $tags['code'][2]['lang'] ?? '' );
			}

			return array_filter( $found );
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );

		if ( $processor->next_tag( array( 'tag_name' => 'PRE' ) ) ) {
			if ( $title ) {
				foreach ( array( 'data-title', 'title' ) as $name ) {
					$value = $processor->get_attribute( $name );
					if ( is_string( $value ) && '' !== $value ) {
						$found['title'] = $value;
						break;
					}
				}
			}

			if ( $language && $processor->next_tag( array( 'tag_name' => 'CODE' ) ) ) {
				$value = $processor->get_attribute( 'lang' );
				if ( is_string( $value ) ) {
					$found['language'] = sanitize_key( $value );
				}
			}
		}

		return array_filter( $found );
	}

	/**
	 * Get the class that scopes a per-block color scheme.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $slug A registered color scheme slug.
	 * @return string
	 */
	public static function get_color_scheme_class( string $slug ): string {
		return 'wzcbh-theme-' . $slug;
	}

	/**
	 * Whether a block renders its file name as a tab.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string,mixed> $params Normalised block parameters.
	 * @return bool
	 */
	protected static function has_file_tab( array $params ): bool {
		return '' !== (string) $params['title'] && self::is_file_tab_style();
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
	 * Uses the block's file name / title when one is set, otherwise `snippet.{ext}` from the
	 * language slug. An executable title extension is replaced with the language's.
	 *
	 * @since 1.2.0
	 * @since 1.4.0 Executable title extensions are replaced.
	 *
	 * @param  string $title    The block file name / title.
	 * @param  string $language The block language slug.
	 * @return string
	 */
	protected static function get_download_filename( string $title, string $language ): string {
		if ( '' !== $title ) {
			$filename = sanitize_file_name( $title );

			if ( '' !== $filename ) {
				$dot = strrpos( $filename, '.' );

				if ( false === $dot || ! self::is_blocked_download_extension( strtolower( substr( $filename, $dot + 1 ) ) ) ) {
					return $filename;
				}

				$extension = self::get_language_extension( $language );

				return substr( $filename, 0, $dot ) . ( 0 === strpos( $extension, '.' ) ? $extension : '.txt' );
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
	 * Whether a download title may not keep this extension.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $extension Lowercase extension without the leading dot.
	 * @return bool
	 */
	protected static function is_blocked_download_extension( string $extension ): bool {
		if ( ! has_filter( 'wzcbh_download_blocked_extensions' ) ) {
			return isset( self::BLOCKED_DOWNLOAD_EXTENSIONS[ $extension ] );
		}

		if ( null === self::$blocked_download_extensions ) {
			/**
			 * Filter the executable file extensions a download file name may not keep.
			 *
			 * A title ending in one of these gets the block language's extension, or `.txt`.
			 *
			 * @since 1.4.0
			 *
			 * @param string[] $extensions Lowercase extensions without the leading dot.
			 */
			$extensions = (array) apply_filters( 'wzcbh_download_blocked_extensions', array_keys( self::BLOCKED_DOWNLOAD_EXTENSIONS ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			self::$blocked_download_extensions = array();
			foreach ( $extensions as $blocked ) {
				$blocked = strtolower( (string) $blocked );
				if ( preg_match( '/^[a-z][a-z0-9]{0,9}$/', $blocked ) ) {
					self::$blocked_download_extensions[ $blocked ] = true;
				}
			}
		}

		return isset( self::$blocked_download_extensions[ $extension ] );
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
			'diff'       => '.diff',
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
			'kujo'       => '.kujo',
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
		if ( ! self::has_file_tab( $params ) ) {
			return $block_content;
		}

		$title    = (string) $params['title'];
		$language = (string) $params['language'];
		$classes  = 'wzcbh-code-wrapper' . ( $language ? ' wzcbh-code-wrapper--' . sanitize_html_class( $language ) : '' );

		if ( ! empty( $params['color_scheme'] ) ) {
			$classes .= ' ' . self::get_color_scheme_class( (string) $params['color_scheme'] );
		}

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
	 * Rewrite the attributes of the block's `<pre>` and `<code>` opening tags.
	 *
	 * Core-form tags are rebuilt from their parsed attributes and anything else goes through
	 * the HTML API, so a value can never become another attribute; event handlers are removed.
	 *
	 * @since 1.2.2
	 * @since 1.4.0 Takes a rewrite spec per tag instead of a callback.
	 *
	 * @param  string                              $html  The block HTML.
	 * @param  array<string, array<string, mixed>> $specs `pre` and/or `code` => spec for apply_tag_spec().
	 * @return string
	 */
	protected static function rewrite_tag_attributes( string $html, array $specs ): string {
		$tags = self::parse_simple_tags( $html );

		if ( null !== $tags ) {
			// The <code> first, so the <pre> offsets stay valid.
			foreach ( array( 'code', 'pre' ) as $tag ) {
				if ( ! isset( $specs[ $tag ] ) ) {
					continue;
				}

				list( $start, $end, $attrs ) = $tags[ $tag ];
				$updated                     = self::apply_tag_spec( $attrs, $specs[ $tag ], 'esc_attr' );

				if ( $updated !== $attrs ) {
					$html = substr_replace( $html, self::build_simple_tag( $tag, $updated ), $start, $end - $start );
				}
			}

			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );

		foreach ( array( 'pre', 'code' ) as $tag ) {
			if ( ! isset( $specs[ $tag ] ) ) {
				continue;
			}

			if ( ! $processor->next_tag( array( 'tag_name' => strtoupper( $tag ) ) ) ) {
				if ( 'code' === $tag ) {
					break;
				}
				// No <pre>: start over so a bare <code> is still rewritten.
				$processor = new \WP_HTML_Tag_Processor( $html );
				continue;
			}

			$attrs = array();
			foreach ( (array) $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				$attrs[ $name ] = $processor->get_attribute( $name );
			}

			$updated = self::apply_tag_spec( $attrs, $specs[ $tag ] );

			foreach ( $attrs as $name => $value ) {
				if ( ! array_key_exists( $name, $updated ) ) {
					$processor->remove_attribute( $name );
				}
			}
			foreach ( $updated as $name => $value ) {
				if ( ( $attrs[ $name ] ?? null ) !== $value ) {
					// WordPress 6.6 escapes with esc_attr(), which empties invalid UTF-8, so scrub it rather than lose the saved text.
					if ( is_string( $value ) && ! preg_match( '//u', $value ) ) {
						$value = htmlspecialchars_decode( htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ), ENT_QUOTES );
					}
					$processor->set_attribute( $name, $value );
				}
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Parse the block's leading `<pre>` and `<code>` opening tags when they are in the plain form core saves.
	 *
	 * That form is lowercase names with double-quoted values containing no `&`,
	 * so the raw values are the decoded ones. Anything else returns null.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $html The block HTML.
	 * @return array<string, array{0: int, 1: int, 2: array<string, string>}>|null Tag => start offset, end offset and attributes.
	 */
	protected static function parse_simple_tags( string $html ): ?array {
		$offset = strspn( $html, " \t\n\r\f" );

		if ( ! preg_match( '/\G<pre((?: [a-z][a-z0-9-]*+="[^"&]*+")*+)><code((?: [a-z][a-z0-9-]*+="[^"&]*+")*+)>/', $html, $matches, 0, $offset ) ) {
			return null;
		}

		$tags  = array();
		$start = $offset;
		foreach ( array(
			'pre'  => 1,
			'code' => 2,
		) as $tag => $group ) {
			$attrs = array();
			if ( '' !== $matches[ $group ] && preg_match_all( '/ ([a-z][a-z0-9-]*+)="([^"]*+)"/', $matches[ $group ], $pairs, PREG_SET_ORDER ) ) {
				foreach ( $pairs as $pair ) {
					// A repeated attribute is ignored by browsers, so it is dropped.
					if ( ! isset( $attrs[ $pair[1] ] ) ) {
						$attrs[ $pair[1] ] = $pair[2];
					}
				}
			}

			$end          = $start + strlen( $tag ) + strlen( $matches[ $group ] ) + 2;
			$tags[ $tag ] = array( $start, $end, $attrs );
			$start        = $end;
		}

		return $tags;
	}

	/**
	 * Apply a rewrite spec to a tag's decoded attributes.
	 *
	 * Spec keys: `classes` (merged), `attrs` (replacing saved ones), `max_height`, `style` (appended) and
	 * `rename_text` (`language-text` to `language-none`). Event handlers go; `$escape` escapes added text only.
	 *
	 * @since 1.4.0
	 *
	 * @param  array<string, string|true> $attrs  Attribute name => value, true when valueless.
	 * @param  array<string, mixed>       $spec   Rewrite spec.
	 * @param  callable|null              $escape Escapes added text, for a tag serialized by build_simple_tag().
	 * @return array<string, string|true>
	 */
	protected static function apply_tag_spec( array $attrs, array $spec, ?callable $escape = null ): array {
		foreach ( $attrs as $name => $value ) {
			if ( 0 === strncmp( (string) $name, 'on', 2 ) ) {
				unset( $attrs[ $name ] );
			}
		}

		$classes = array();
		foreach ( (array) ( $spec['classes'] ?? array() ) as $class_name ) {
			if ( '' !== $class_name ) {
				$classes[] = $escape ? $escape( $class_name ) : $class_name;
			}
		}
		$rename = ! empty( $spec['rename_text'] );

		if ( $classes || $rename ) {
			$existing = isset( $attrs['class'] ) && is_string( $attrs['class'] ) ? preg_split( '/[ \t\n\r\f]+/', $attrs['class'], -1, PREG_SPLIT_NO_EMPTY ) : array();
			$existing = $existing ? $existing : array();

			if ( $rename ) {
				foreach ( array_keys( $existing, 'language-text', true ) as $index ) {
					$existing[ $index ] = 'language-none';
				}
			}

			$merged = array_unique( array_merge( $existing, $classes ) );
			if ( $merged ) {
				$attrs['class'] = implode( ' ', $merged );
			}
		}

		foreach ( (array) ( $spec['attrs'] ?? array() ) as $name => $value ) {
			$attrs[ $name ] = $escape ? $escape( (string) $value ) : (string) $value;
		}

		$style        = $attrs['style'] ?? null;
		$declarations = self::get_max_height_declarations( (int) ( $spec['max_height'] ?? 0 ), $style ) . ( $spec['style'] ?? '' );

		if ( '' !== $declarations ) {
			// Saved inline styles have no trailing semicolon.
			$existing       = is_string( $style ) ? rtrim( $style, " \t\n\r\0\x0B;" ) : '';
			$attrs['style'] = ( '' === $existing ? '' : $existing . ';' ) . ( $escape ? $escape( $declarations ) : $declarations );
		}

		return $attrs;
	}

	/**
	 * Serialize an opening tag parsed by parse_simple_tags().
	 *
	 * Values are written as given: parsed ones contain no `"`, and apply_tag_spec() escaped the rest.
	 *
	 * @since 1.4.0
	 *
	 * @param  string                     $tag   Tag name.
	 * @param  array<string, string|true> $attrs Attribute name => value safe to write between double quotes.
	 * @return string
	 */
	protected static function build_simple_tag( string $tag, array $attrs ): string {
		$html = '<' . $tag;

		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . ( true === $value ? '' : '="' . $value . '"' );
		}

		return $html . '>';
	}

	/**
	 * Build the inline max-height declarations for a block's `<pre>`.
	 *
	 * Markup saved by 1.2.3 and earlier already carries them, so they are not
	 * repeated there. The expand button keys off `pre.style.maxHeight`.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Takes the decoded `style` value instead of the raw attribute string.
	 *
	 * @param  int              $max_height Max height in pixels; zero or less for none.
	 * @param  string|bool|null $style      The `<pre>` tag's `style` value, if any.
	 * @return string
	 */
	protected static function get_max_height_declarations( int $max_height, $style = null ): string {
		if ( $max_height <= 0 || ( is_string( $style ) && preg_match( '/\bmax-height\s*:/i', $style ) ) ) {
			return '';
		}

		return 'max-height:' . $max_height . 'px;overflow-y:auto;';
	}

	/**
	 * Find the first `<code>` element in the block HTML.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $html The block HTML.
	 * @return array{0: int, 1: int, 2: int, 3: int}|null Element start, inner start, inner end and element end offsets.
	 */
	protected static function find_code_element( string $html ): ?array {
		$tags = self::parse_simple_tags( $html );

		if ( null !== $tags ) {
			$close = strpos( $html, '<', $tags['code'][1] );
			// Core escapes `<` in code, so a raw one may hide that `</code>` in a comment, raw text element or attribute.
			if ( false !== $close && 0 === substr_compare( $html, '</code>', $close, 7, true ) ) {
				return array( $tags['code'][0], $tags['code'][1], $close, $close + 7 );
			}
		}

		$processor = new Tag_Processor( $html );

		if ( ! $processor->next_tag( array( 'tag_name' => 'CODE' ) ) ) {
			return null;
		}
		$opener = $processor->get_tag_span();

		while ( $processor->next_tag(
			array(
				'tag_name'    => 'CODE',
				'tag_closers' => 'visit',
			)
		) ) {
			if ( $processor->is_tag_closer() ) {
				$closer = $processor->get_tag_span();
				if ( null === $opener || null === $closer ) {
					return null;
				}

				return array( $opener[0], $opener[0] + $opener[1], $closer[0], $closer[0] + $closer[1] );
			}
		}

		return null;
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
		$max_height         = $params['max_height'];

		// Prism has no 'text' grammar; 'none' gets theme styling without
		// highlighting. Empty language is treated the same so theme CSS applies.
		// Markup saved by 1.2.3 and earlier carries language-text.
		$rename_text = 'text' === $language && false !== strpos( $block_content, 'language-text' );
		if ( in_array( $language, array( '', 'text' ), true ) ) {
			$language = 'none';
		}

		$diff        = ! empty( $params['diff'] );
		$lang_class  = $language ? 'language-' . ( $diff ? 'diff-' : '' ) . $language : '';
		$pre_classes = array();
		$pre_attrs   = array();

		if ( $diff || 'diff' === $language ) {
			$pre_classes[] = 'diff-highlight';
		}

		if ( $diff ) {
			$pre_attrs['data-language'] = self::get_diff_label( $language );
		}

		// Theme CSS selects on the <pre> class before Prism runs.
		if ( $lang_class ) {
			$pre_classes[] = $lang_class;
		}

		if ( $line_numbers ) {
			$pre_classes[] = 'line-numbers';
			if ( 1 !== $line_numbers_start ) {
				$pre_attrs['data-start'] = (string) $line_numbers_start;
			}
		}

		if ( $word_wrap ) {
			$pre_classes[] = 'word-wrap';
		}

		if ( $title ) {
			$pre_attrs['data-title'] = $title;
		}

		if ( $highlight_lines ) {
			$pre_attrs['data-line'] = $highlight_lines;
		}

		// The global setting and the per-block override are already resolved, so
		// the toolbar button only has to check for the attribute's presence.
		if ( ! empty( $params['download'] ) ) {
			$pre_attrs['data-wzcbh-download'] = (string) $params['download_filename'];
		}

		// Tells hljs-clipboard.js to leave this server-mode block to Prism's own toolbar.
		if ( ! empty( $params['client_fallback'] ) ) {
			$pre_attrs['data-wzcbh-client'] = '1';
		}

		// Always rewritten, so event handler attributes never reach the page.
		return self::rewrite_tag_attributes(
			$block_content,
			array(
				'pre'  => array(
					'rename_text' => $rename_text,
					'classes'     => $pre_classes,
					'attrs'       => $pre_attrs,
					'max_height'  => $max_height,
				),
				'code' => array(
					'rename_text' => $rename_text,
					'classes'     => array( $lang_class ),
				),
			)
		);
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
		$diff               = ! empty( $params['diff'] );

		// There is no 'text' grammar; 'none' matches the client-side class names.
		// Empty language is treated the same so theme CSS applies.
		// Markup saved by 1.2.3 and earlier carries language-text.
		$rename_text = 'text' === $language && false !== strpos( $block_content, 'language-text' );
		if ( in_array( $language, array( '', 'text' ), true ) ) {
			$language = 'none';
		}

		// ── Extract raw code from saved HTML ──────────────────────────────────
		$code_element = self::find_code_element( $block_content );
		if ( null === $code_element ) {
			return self::rewrite_tag_attributes(
				$block_content,
				array(
					'pre'  => array( 'rename_text' => $rename_text ),
					'code' => array( 'rename_text' => $rename_text ),
				)
			);
		}
		$inner_html = substr( $block_content, $code_element[1], $code_element[2] - $code_element[1] );
		$plain_code = html_entity_decode( $inner_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// ── Map Prism slug → hljs slug ────────────────────────────────────────
		$hljs_lang = self::get_hljs_language( $language );

		// ── Run highlight.php ──────────────────────────────────────────────────
		$highlighted = null;
		if ( $hljs_lang ) {
			$size     = strlen( $plain_code );
			$fallback = self::is_page_request();

			if ( $size <= self::get_highlight_size_limit( $language ) && ( ! $fallback || self::has_highlight_budget( $size ) ) ) {
				try {
					$highlighted = $diff || 'diff' === $language
						? self::highlight_diff( $plain_code, $diff ? $hljs_lang : '', $diff ? $language : '' )
						: self::remap_token_classes( self::get_highlighter()->highlight( $hljs_lang, $plain_code )->value );
					if ( $fallback ) {
						self::$highlight_budget_used += $size;
					}
				} catch ( \Throwable $e ) {
					$fallback = $fallback && array_key_exists( $language, self::get_languages() );
				}
			}

			if ( null === $highlighted && $fallback && self::is_prism_fallback_allowed( $size ) ) {
				Styles_Handler::enqueue_prism_fallback();

				$params['client_fallback'] = true;
				return $this->render_code_block_client( $block_content, $params );
			}
		}

		if ( null === $highlighted ) {
			$highlighted = self::escape_plain_text( $plain_code );
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
			$highlighted = self::wrap_lines( $highlighted, $line_numbers_start, $target_lines, $diff || 'diff' === $language );
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
		$class_slug   = ( $diff ? 'diff-' : '' ) . $language;
		$code_classes = $language ? 'language-' . esc_attr( $class_slug ) : '';
		$new_code     = '<code' . ( $code_classes ? ' class="' . $code_classes . '"' : '' ) . '>'
			. $highlighted . $line_numbers_html . '</code>';

		$block_content = substr_replace( $block_content, $new_code, $code_element[0], $code_element[3] - $code_element[0] );

		// ── Apply classes and the line-number counter offset to <pre> ─────────
		// The counter-reset mirrors what Prism JS does at runtime by reading
		// data-start.
		$pre_classes = array();
		if ( $language ) {
			$pre_classes[] = 'language-' . $class_slug;
		}
		if ( $diff || 'diff' === $language ) {
			$pre_classes[] = 'diff-highlight';
		}
		if ( $line_numbers ) {
			$pre_classes[] = 'line-numbers';
		}
		if ( $word_wrap ) {
			$pre_classes[] = 'word-wrap';
		}

		$style_decl = ( $line_numbers && $line_numbers_start > 1 )
			? 'counter-reset: linenumber ' . ( $line_numbers_start - 1 ) . ';'
			: '';

		// Not read by server mode, but Prism's bundled line-highlight CSS pads
		// pre[data-line], so they are kept for parity with client mode.
		$pre_attrs = array();
		if ( $title ) {
			$pre_attrs['data-title'] = $title;
		}
		if ( $line_numbers && 1 !== $line_numbers_start ) {
			$pre_attrs['data-start'] = (string) $line_numbers_start;
		}
		if ( $highlight_lines ) {
			$pre_attrs['data-line'] = $highlight_lines;
		}

		// Always rewritten, so event handler attributes never reach the page.
		$block_content = self::rewrite_tag_attributes(
			$block_content,
			array(
				'pre' => array(
					'rename_text' => $rename_text,
					'classes'     => $pre_classes,
					'attrs'       => $pre_attrs,
					'max_height'  => $max_height,
					'style'       => $style_decl,
				),
			)
		);

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
			$label          = $diff ? self::get_diff_label( $language ) : ( $languages[ $language ] ?? strtoupper( $hljs_lang ) );
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
			$highlighter = new \Highlight\Highlighter();
			\Highlight\Highlighter::registerLanguage( 'kujo', WZCBH_PLUGIN_DIR . 'includes/highlight/kujo.json' );
			self::$highlighter = $highlighter;
		}

		return $highlighter;
	}

	/**
	 * Largest code block, in bytes, that is highlighted server-side.
	 *
	 * Highlighting cost is quadratic in input size, so larger blocks fall back to Prism.js
	 * in the browser or to escaped plain text.
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
		 * Larger blocks fall back to Prism.js in the browser, or to escaped plain text when the
		 * Prism fallback is off. Set to 0 to disable server-side highlighting entirely.
		 *
		 * @since 1.2.2
		 *
		 * @param int    $limit    Size limit in bytes. Default 131072 (128KB).
		 * @param string $language The block language slug.
		 */
		return (int) apply_filters( 'wzcbh_server_highlight_max_bytes', 131072, $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Whether a block of this size still fits in the request's highlight budget.
	 *
	 * @since 1.4.0
	 *
	 * @param  int $size Block size in bytes.
	 * @return bool
	 */
	protected static function has_highlight_budget( int $size ): bool {
		if ( null === self::$highlight_budget ) {
			/**
			 * Filter the total bytes highlighted server-side per request.
			 *
			 * Blocks beyond the budget are highlighted by Prism.js in the browser,
			 * or render as escaped plain text when the Prism fallback is off.
			 *
			 * @since 1.4.0
			 *
			 * @param int $budget Budget in bytes. Default 262144 (256KB). Zero or less disables the budget.
			 */
			self::$highlight_budget = (int) apply_filters( 'wzcbh_server_highlight_request_budget', 262144 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return self::$highlight_budget <= 0 || self::$highlight_budget_used + $size <= self::$highlight_budget;
	}

	/**
	 * Reset the per-request server highlight budget and its cached filter values.
	 *
	 * @since 1.4.0
	 */
	public static function reset_server_highlight_budget(): void {
		self::$highlight_budget_used       = 0;
		self::$highlight_budget            = null;
		self::$prism_fallback_max_bytes    = null;
		self::$prism_fallback_used         = 0;
		self::$prism_fallback_budget       = null;
		self::$is_page_request             = null;
		self::$blocked_download_extensions = null;
		self::$auto_detect_used            = 0;
		self::$auto_detect_limit           = null;
	}

	/**
	 * Whether this request renders a front-end page, where Prism.js can run.
	 *
	 * Feeds, REST, Ajax, WP-CLI and admin renders keep plain text for skipped blocks.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	protected static function is_page_request(): bool {
		if ( null === self::$is_page_request ) {
			self::$is_page_request = ! (
				is_admin()
				|| wp_is_json_request()
				|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
				|| wp_doing_ajax()
				|| ( defined( 'WP_CLI' ) && WP_CLI )
				|| is_feed()
			);
		}

		return self::$is_page_request;
	}

	/**
	 * Whether a server-mode block that was not highlighted may fall back to Prism.js.
	 *
	 * An allowed block is counted against the request's fallback budget.
	 *
	 * @since 1.4.0
	 *
	 * @param  int $size Block size in bytes.
	 * @return bool
	 */
	protected static function is_prism_fallback_allowed( int $size ): bool {
		if ( null === self::$prism_fallback_max_bytes ) {
			/**
			 * Filter whether server-mode blocks that are not highlighted fall back to Prism.js.
			 *
			 * @since 1.4.0
			 *
			 * @param bool $enabled Whether the fallback is enabled. Default true.
			 */
			$enabled = (bool) apply_filters( 'wzcbh_server_prism_fallback', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			/**
			 * Filter the largest code block that falls back to Prism.js in server mode.
			 *
			 * Larger blocks render as escaped plain text.
			 *
			 * @since 1.4.0
			 *
			 * @param int $max_bytes Size limit in bytes. Default 524288 (512KB).
			 */
			$max_bytes = (int) apply_filters( 'wzcbh_prism_fallback_max_bytes', 524288 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			self::$prism_fallback_max_bytes = $enabled ? $max_bytes : -1;
		}

		if ( $size > self::$prism_fallback_max_bytes ) {
			return false;
		}

		if ( null === self::$prism_fallback_budget ) {
			/**
			 * Filter the total bytes sent to the Prism.js fallback per request.
			 *
			 * Blocks beyond the budget render as escaped plain text.
			 *
			 * @since 1.4.0
			 *
			 * @param int $budget Budget in bytes. Default 1048576 (1MB). Zero or less disables the budget.
			 */
			self::$prism_fallback_budget = (int) apply_filters( 'wzcbh_prism_fallback_page_budget', 1048576 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		if ( self::$prism_fallback_budget > 0 && self::$prism_fallback_used + $size > self::$prism_fallback_budget ) {
			return false;
		}

		self::$prism_fallback_used += $size;

		return true;
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
	 * @param  bool             $block_runs   Whether the HTML has block-level diff runs; see split_html_lines().
	 * @return string
	 */
	protected static function wrap_lines( string $html, int $start, array $target_lines, bool $block_runs = false ): string {
		$result = '';

		foreach ( self::split_html_lines( $html, $block_runs ) as $offset => $line ) {
			$result .= self::make_line_span( $start + $offset, $target_lines, $line );
		}

		return $result;
	}

	/**
	 * Split highlighted HTML into lines, closing and reopening inline tags at each line boundary.
	 *
	 * Each line keeps its trailing newline. A final line is returned only when it has text,
	 * because highlight.php closes its outermost span after the trailing newline.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $html            Highlighted HTML from highlight.php.
	 * @param  bool   $newline_inside  Keep the newline inside the open tags. A newline after a block-level
	 *                                 diff run would otherwise add an empty line below it.
	 * @return string[] Balanced HTML, one entry per line.
	 */
	protected static function split_html_lines( string $html, bool $newline_inside = false ): array {
		$lines     = array();
		$open_tags = array(); // Stack of opening tag strings.
		$line_buf  = '';
		$i         = 0;
		$len       = strlen( $html );

		// Text only — tags do not count. $line_buf is seeded with the reopened
		// tags after a newline, so testing $line_buf emitted a phantom final line.
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
				$lines[]       = $newline_inside ? $line_buf . "\n" . self::close_tags( $open_tags ) : $line_buf . self::close_tags( $open_tags ) . "\n";
				$line_buf      = implode( '', $open_tags );
				$line_has_text = false;
				++$i;
			} else {
				$run           = strcspn( $html, "<\n", $i );
				$line_buf     .= substr( $html, $i, $run );
				$line_has_text = true;
				$i            += $run;
			}
		}

		if ( $line_has_text ) {
			$lines[] = $line_buf . self::close_tags( $open_tags );
		}

		return $lines;
	}

	/**
	 * Highlight a unified or normal diff with the block's base language, matching Prism's diff-highlight markup.
	 *
	 * Runs of lines sharing a prefix (`+`, `-`, `<`, `>`, space or `!`) have the prefix removed and
	 * the rest highlighted as one piece, then each line gets its prefix back inside a
	 * `token prefix` span, so the same theme and diff-highlight CSS apply in both modes.
	 * Coordinate lines (`@@ … @@`, `---`, `+++`, `***` and `12,14c12`) and lines with no prefix
	 * are left unhighlighted, as Prism does.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $code      The plain code, entities decoded.
	 * @param  string $hljs_lang highlight.php language slug of the base language, or empty for a plain diff.
	 * @param  string $language  Prism language slug of the base language, or empty for a plain diff.
	 * @return string
	 */
	protected static function highlight_diff( string $code, string $hljs_lang, string $language ): string {
		$types = array(
			'-' => array( 'deleted-sign deleted', 'deleted' ),
			'<' => array( 'deleted-arrow deleted', 'deleted' ),
			'+' => array( 'inserted-sign inserted', 'inserted' ),
			'>' => array( 'inserted-arrow inserted', 'inserted' ),
			' ' => array( 'unchanged', 'unchanged' ),
			'!' => array( 'diff bold', 'diff' ),
		);

		$html = '';
		$run  = array();
		$kind = '';

		foreach ( (array) preg_split( '/(?<=\n)/', $code, -1, PREG_SPLIT_NO_EMPTY ) as $line ) {
			$line = (string) $line;
			$text = rtrim( $line, "\r\n" );

			if ( preg_match( '/^(?:\*{3}|-{3}|\+{3}|@@.*@@$|\d)/', $text ) ) {
				$html .= self::render_diff_run( $kind, $run, $types, $hljs_lang, $language ) . '<span class="token coord">' . self::escape_plain_text( $text ) . '</span>' . substr( $line, strlen( $text ) );
				$run   = array();
				$kind  = '';
				continue;
			}

			$first = $line[0];

			if ( ! isset( $types[ $first ] ) ) {
				$html .= self::render_diff_run( $kind, $run, $types, $hljs_lang, $language ) . self::escape_plain_text( $line );
				$run   = array();
				$kind  = '';
				continue;
			}

			if ( $first !== $kind ) {
				$html .= self::render_diff_run( $kind, $run, $types, $hljs_lang, $language );
				$run   = array();
				$kind  = $first;
			}

			$run[] = $line;
		}

		return $html . self::render_diff_run( $kind, $run, $types, $hljs_lang, $language );
	}

	/**
	 * Render one run of diff lines that share a prefix.
	 *
	 * @since 1.4.0
	 *
	 * @param  string                            $kind      The prefix character, or an empty string for no run.
	 * @param  string[]                          $run       The lines of the run, prefix included.
	 * @param  array<string, array<int, string>> $types  Prefix => token classes and prefix alias.
	 * @param  string                            $hljs_lang highlight.php language slug.
	 * @param  string                            $language  Prism language slug.
	 * @return string
	 */
	protected static function render_diff_run( string $kind, array $run, array $types, string $hljs_lang, string $language ): string {
		if ( '' === $kind || ! $run ) {
			return '';
		}

		$body = '';
		foreach ( $run as $line ) {
			$body .= substr( $line, 1 );
		}

		$lines = '' === $hljs_lang ? array() : self::split_html_lines( self::remap_token_classes( self::get_highlighter()->highlight( $hljs_lang, $body )->value ) );

		// A last line that is only its prefix, with no newline, leaves highlight.php nothing to return for it.
		if ( count( $lines ) === count( $run ) - 1 && '' === substr( (string) end( $run ), 1 ) ) {
			$lines[] = '';
		}

		// No base language, or highlight.php returned a different number of lines.
		if ( count( $lines ) !== count( $run ) ) {
			$lines = array_map( array( self::class, 'escape_plain_text' ), array_map( static fn( string $line ): string => substr( $line, 1 ), $run ) );
		}

		$prefix = '<span class="token prefix ' . $types[ $kind ][1] . '">' . esc_html( $kind ) . '</span>';

		return '<span class="token ' . $types[ $kind ][0] . ( '' === $language ? '' : ' language-' . esc_attr( $language ) ) . '">' . $prefix . implode( $prefix, $lines ) . '</span>';
	}

	/**
	 * Escape code text for output, keeping typed entities such as `&lt;` as typed.
	 *
	 * @since 1.4.0
	 *
	 * @param  string $text Decoded code text.
	 * @return string
	 */
	protected static function escape_plain_text( string $text ): string {
		// esc_html() returns the input uncopied; it only differs by not double-encoding `&`.
		return false === strpos( $text, '&' ) ? esc_html( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Toolbar label for a block shown as a diff, such as "PHP diff".
	 *
	 * @since 1.4.0
	 *
	 * @param  string $language The base language slug.
	 * @return string
	 */
	protected static function get_diff_label( string $language ): string {
		$languages = self::get_languages();

		/* translators: %s: language name, such as PHP */
		return sprintf( __( '%s diff', 'webberzone-code-block-highlighting' ), $languages[ $language ] ?? $language );
	}

	/**
	 * Closing tags for a stack of open tags, innermost first.
	 *
	 * @since 1.4.0
	 *
	 * @param  string[] $open_tags Opening tag strings, outermost first.
	 * @return string
	 */
	protected static function close_tags( array $open_tags ): string {
		$html = '';
		for ( $i = count( $open_tags ) - 1; $i >= 0; $i-- ) {
			preg_match( '/<([a-zA-Z][a-zA-Z0-9]*)/', $open_tags[ $i ], $tm );
			$html .= '</' . ( $tm[1] ?? 'span' ) . '>';
		}
		return $html;
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
			$part = trim( $part, " \t\n\r\0\x0B" );
			if ( '' === $part ) {
				continue;
			}

			if ( strpos( $part, '-' ) !== false ) {
				list( $from, $to ) = explode( '-', $part, 2 );
				$from              = (int) trim( $from, " \t\n\r\0\x0B" );
				$to                = (int) trim( $to, " \t\n\r\0\x0B" );
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
			'diff'       => 'Diff',
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
			'kujo'       => 'Kujo',
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

	/**
	 * Detect the language of a code block that has none set, for server mode.
	 *
	 * Detection highlights the code once per candidate language, so the result,
	 * including no match, is cached per code block.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $block_content The rendered block HTML.
	 * @return string Prism language slug, or an empty string when none is detected.
	 */
	protected static function detect_language( string $block_content ): string {
		$code_element = self::find_code_element( $block_content );
		if ( null === $code_element ) {
			return '';
		}
		$code = html_entity_decode( substr( $block_content, $code_element[1], $code_element[2] - $code_element[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		/**
		 * Filter the largest code block whose language is detected server-side.
		 *
		 * @since 1.3.0
		 *
		 * @param int $limit Size limit in bytes. Zero or less disables detection.
		 */
		$limit = (int) apply_filters( 'wzcbh_auto_detect_max_bytes', 16384 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( '' === trim( $code, " \t\n\r\0\x0B" ) || strlen( $code ) > min( $limit, self::get_highlight_size_limit( '' ) ) ) {
			return '';
		}

		$slugs = self::get_auto_detect_languages();
		if ( ! $slugs ) {
			return '';
		}

		/**
		 * Filter the relevance highlight.php must score before a detected language is used.
		 *
		 * Lower values detect more blocks, but label more plain text as code.
		 *
		 * @since 1.3.0
		 *
		 * @param int $min_relevance Minimum relevance. Default 6.
		 */
		$min_relevance = (int) apply_filters( 'wzcbh_auto_detect_min_relevance', 6 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$cache_key = 'wzcbh_lang_' . md5( WZCBH_VERSION . '|' . implode( ',', $slugs ) . '|' . $min_relevance . '|' . $code );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		// Past the cap the block stays plain and uncached, so a later request detects it.
		if ( self::is_page_request() ) {
			if ( ! self::has_auto_detect_budget() ) {
				return '';
			}
			++self::$auto_detect_used;
		}

		$language = '';
		try {
			$candidates = self::get_detection_candidates( $slugs );
			$result     = self::get_highlighter()->highlightAuto( $code, array_keys( $candidates ) );

			if ( $result->relevance >= $min_relevance && isset( $candidates[ $result->language ] ) ) {
				$language = $candidates[ $result->language ];
			}
		} catch ( \Throwable $e ) {
			$language = '';
		}

		set_transient( $cache_key, $language, MONTH_IN_SECONDS );

		return $language;
	}

	/**
	 * Whether this request may run another uncached language detection.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	protected static function has_auto_detect_budget(): bool {
		if ( null === self::$auto_detect_limit ) {
			/**
			 * Filter how many uncached language detections a front-end page request runs.
			 *
			 * Blocks beyond the limit render as plain text and are detected on a later request.
			 *
			 * @since 1.4.0
			 *
			 * @param int $limit Detections per request. Default 25. Zero or less disables the limit.
			 */
			self::$auto_detect_limit = (int) apply_filters( 'wzcbh_auto_detect_request_limit', 25 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return self::$auto_detect_limit <= 0 || self::$auto_detect_used < self::$auto_detect_limit;
	}

	/**
	 * Languages, as Prism slugs, that server-side detection chooses between.
	 *
	 * @since 1.3.0
	 *
	 * @return string[]
	 */
	protected static function get_auto_detect_languages(): array {
		// Earlier wins a tie. Groovy and Vim match prose, TypeScript and Dart outscore JavaScript, and TOML is only INI here.
		$preferred = array( 'php', 'markup', 'css', 'sass', 'javascript', 'json', 'sql', 'bash', 'yaml', 'python', 'ruby', 'java', 'c', 'cpp', 'csharp', 'go', 'rust', 'kotlin', 'swift', 'perl', 'lua', 'r', 'haskell', 'scala', 'fsharp', 'objectivec', 'powershell', 'markdown', 'nginx', 'apacheconf', 'docker' );
		$slugs     = array_values( array_intersect( $preferred, array_keys( self::get_languages() ) ) );

		/**
		 * Filter the languages that server-side detection chooses between.
		 *
		 * On a tie, the language listed first wins.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $slugs Prism language slugs, most likely first.
		 */
		$slugs = (array) apply_filters( 'wzcbh_auto_detect_languages', $slugs ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $slugs ) ) ) ) );
	}

	/**
	 * Map highlight.php's name for each detection language back to its Prism slug.
	 *
	 * @since 1.3.0
	 *
	 * @param  string[] $slugs Prism language slugs.
	 * @return array<string, string> highlight.php language name => Prism slug, in detection order.
	 */
	protected static function get_detection_candidates( array $slugs ): array {
		$highlighter = self::get_highlighter();
		$candidates  = array();

		foreach ( $slugs as $slug ) {
			$hljs = self::get_hljs_language( $slug );
			if ( '' === $hljs ) {
				continue;
			}

			try {
				// Detection reports the canonical name, not the alias (csharp is cs, c is arduino).
				$name = $highlighter->highlight( $hljs, '' )->language;
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( ! isset( $candidates[ $name ] ) ) {
				$candidates[ $name ] = $slug;
			}
		}

		return $candidates;
	}
}
