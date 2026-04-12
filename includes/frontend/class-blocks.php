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
		$asset_file = WZ_CBH_PLUGIN_DIR . 'includes/blocks/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wz-cbh-editor',
			WZ_CBH_PLUGIN_URL . 'includes/blocks/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$languages    = self::get_languages();
		$default_lang = wz_cbh_get_option( 'default-lang', '' );

		$default_settings = array(
			'language'         => $default_lang,
			'lineNumbers'      => (bool) wz_cbh_get_option( 'default-line-numbers', false ),
			'lineNumbersStart' => (int) wz_cbh_get_option( 'default-line-numbers-start', 1 ),
			'wordWrap'         => (bool) wz_cbh_get_option( 'default-word-wrap', false ),
			'maxHeight'        => (int) wz_cbh_get_option( 'default-max-height', 0 ),
		);

		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		wp_add_inline_script(
			'wz-cbh-editor',
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
	 * `enqueue_block_editor_assets` only loads styles into the outer editor shell.
	 * Since WordPress 6.0 the editing canvas runs in an iframe, styles must be
	 * registered via `enqueue_block_assets` to appear inside it.
	 *
	 * The block editor's `.block-editor-block-list__layout pre` rule (specificity
	 * 0,1,1) overrides Prism's `pre[class*="language-"]` (also 0,1,1) by source
	 * order. To beat it, only the `background` and `color` declarations are
	 * extracted from the active Prism theme file and re-injected inline with
	 * `.block-editor-block-list__layout` prepended, raising specificity to 0,2,1.
	 * Layout properties (margin, padding, position, overflow) are intentionally
	 * excluded to avoid disrupting the editor's block positioning.
	 *
	 * Only runs in the admin context to avoid duplicating the frontend enqueue
	 * that `Styles_Handler` already handles via `wp_enqueue_scripts`.
	 *
	 * @since 1.2.0
	 */
	public function enqueue_editor_canvas_styles(): void {
		if ( ! is_admin() ) {
			return;
		}

		$asset_file = WZ_CBH_PLUGIN_DIR . 'includes/blocks/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		$editor_css = WZ_CBH_PLUGIN_DIR . 'includes/blocks/build/index.css';
		if ( file_exists( $editor_css ) ) {
			wp_enqueue_style(
				'wz-cbh-editor-canvas-style',
				WZ_CBH_PLUGIN_URL . 'includes/blocks/build/index.css',
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
		if ( file_exists( $theme_path ) && wp_style_is( 'wz-cbh-editor-canvas-style', 'enqueued' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$theme_css   = file_get_contents( $theme_path );
			$bg_value    = '';
			$color_value = '';

			if ( preg_match_all( '/([^{}]+)\{([^}]+)\}/', $theme_css, $all_rules, PREG_SET_ORDER ) ) {
				foreach ( $all_rules as $rule ) {
					$selector     = $rule[1];
					$declarations = $rule[2];

					if ( ! preg_match( '/(?:code|pre)\[class\*=["\']language-["\']\]/i', $selector ) ) {
						continue;
					}

					// Skip pseudo-element (::selection) and :not(pre) rules.
					if ( preg_match( '/::/i', $selector ) || preg_match( '/:not\(/i', $selector ) ) {
						continue;
					}

					foreach ( preg_split( '/[\n;]/', $declarations ) as $decl ) {
						$decl = trim( $decl );
						if ( preg_match( '/^background(?:-color)?:\s*.+$/i', $decl ) ) {
							$bg_value = $decl . ';';
						} elseif ( preg_match( '/^color:\s*.+$/i', $decl ) ) {
							$color_value = $decl . ';';
						}
					}
				}
			}

			$props = array_filter( array( $bg_value, $color_value ) );
			if ( $props ) {
				$selectors = '.block-editor-block-list__layout pre[class*="language-"],' .
							'.block-editor-block-list__layout code[class*="language-"]';
				wp_add_inline_style(
					'wz-cbh-editor-canvas-style',
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
			'wz-cbh/v1',
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
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function save_default_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$updated = array();

		if ( $request->has_param( 'language' ) ) {
			wz_cbh_update_option( 'default-lang', $request->get_param( 'language' ) );
			$updated[] = 'language';
		}

		if ( $request->has_param( 'lineNumbers' ) ) {
			wz_cbh_update_option( 'default-line-numbers', (bool) $request->get_param( 'lineNumbers' ) );
			$updated[] = 'lineNumbers';
		}

		if ( $request->has_param( 'lineNumbersStart' ) ) {
			wz_cbh_update_option( 'default-line-numbers-start', (int) $request->get_param( 'lineNumbersStart' ) );
			$updated[] = 'lineNumbersStart';
		}

		if ( $request->has_param( 'wordWrap' ) ) {
			wz_cbh_update_option( 'default-word-wrap', (bool) $request->get_param( 'wordWrap' ) );
			$updated[] = 'wordWrap';
		}

		if ( $request->has_param( 'maxHeight' ) ) {
			wz_cbh_update_option( 'default-max-height', (int) $request->get_param( 'maxHeight' ) );
			$updated[] = 'maxHeight';
		}

		return new \WP_REST_Response( array( 'updated' => $updated ), 200 );
	}

	/**
	 * Filter the rendered HTML of core/code blocks to inject Prism attributes.
	 *
	 * - Adds `language-{lang}` class on `<code>`.
	 * - Adds `line-numbers` class and `data-start` on `<pre>` when enabled.
	 * - Adds `word-wrap` class on `<pre>` when enabled.
	 * - Adds `data-title` attribute on `<pre>` for toolbar pickup.
	 * - Adds `data-line` attribute on `<pre>` for line highlight plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $block_content The rendered block HTML.
	 * @param array<string, mixed> $block         The block data array.
	 * @return string
	 */
	public function render_code_block( string $block_content, array $block ): string {
		$attrs = $block['attrs'] ?? array();

		$language           = sanitize_key( $attrs['language'] ?? '' );
		$line_numbers       = ! empty( $attrs['lineNumbers'] );
		$line_numbers_start = isset( $attrs['lineNumbersStart'] ) ? (int) $attrs['lineNumbersStart'] : 1;
		$word_wrap          = ! empty( $attrs['wordWrap'] );
		$title              = isset( $attrs['title'] ) ? sanitize_text_field( $attrs['title'] ) : '';
		$highlight_lines    = isset( $attrs['highlightLines'] ) ? sanitize_text_field( $attrs['highlightLines'] ) : '';

		// ── Apply language class to <code> ────────────────────────────────────
		if ( $language ) {
			$lang_class = 'language-' . $language;

			if ( preg_match( '/<code[^>]+class="[^"]*"/', $block_content ) ) {
				$block_content = preg_replace(
					'/(<code[^>]+class=")([^"]*)(")/i',
					'$1$2 ' . $lang_class . '$3',
					$block_content,
					1
				);
			} else {
				$block_content = preg_replace(
					'/<code(?=[^>]*>)/i',
					'<code class="' . $lang_class . '"',
					$block_content,
					1
				);
			}
		}

		// ── Apply classes and data attributes to <pre> ────────────────────────
		$pre_classes = array();
		$pre_attrs   = array();

		if ( $line_numbers ) {
			$pre_classes[] = 'line-numbers';
			if ( 1 !== $line_numbers_start ) {
				$pre_attrs[] = 'data-start="' . esc_attr( (string) $line_numbers_start ) . '"';
			}
		}

		if ( $word_wrap ) {
			$pre_classes[] = 'word-wrap';
		}

		if ( $title ) {
			$pre_attrs[] = 'data-title="' . esc_attr( $title ) . '"';
		}

		if ( $highlight_lines ) {
			$pre_attrs[] = 'data-line="' . esc_attr( $highlight_lines ) . '"';
		}

		if ( $pre_classes ) {
			if ( preg_match( '/<pre[^>]+class="[^"]*"/', $block_content ) ) {
				$classes_str   = implode( ' ', $pre_classes );
				$block_content = preg_replace(
					'/(<pre[^>]+class=")([^"]*)(")/i',
					'$1$2 ' . $classes_str . '$3',
					$block_content,
					1
				);
			} else {
				$classes_str   = implode( ' ', $pre_classes );
				$block_content = preg_replace(
					'/<pre(?=[^>]*>)/i',
					'<pre class="' . $classes_str . '"',
					$block_content,
					1
				);
			}
		}

		if ( $pre_attrs ) {
			$attrs_str     = ' ' . implode( ' ', $pre_attrs );
			$block_content = preg_replace(
				'/(<pre\b)/i',
				'$1' . $attrs_str,
				$block_content,
				1
			);
		}

		return $block_content;
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
		return apply_filters( 'wz_cbh_languages', $languages ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}
}
