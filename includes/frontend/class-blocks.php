<?php
/**
 * Blocks class.
 *
 * Registers editor assets and filters the rendered core/code block on the frontend.
 *
 * @package WebberZone\Code_Block_Highlighting\Frontend
 */

namespace WebberZone\Code_Block_Highlighting\Frontend;

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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'render_block_core/code', array( $this, 'render_code_block' ), 10, 2 );
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

		$color_scheme_path    = \WebberZone\Code_Block_Highlighting\Admin\Settings::get_color_scheme_css( true );
		$color_scheme_version = file_exists( $color_scheme_path ) ? (string) filemtime( $color_scheme_path ) : WZ_CBH_VERSION;

		wp_enqueue_style(
			'wz-cbh-editor-theme',
			\WebberZone\Code_Block_Highlighting\Admin\Settings::get_color_scheme_css(),
			array(),
			$color_scheme_version
		);

		$editor_css = WZ_CBH_PLUGIN_DIR . 'includes/blocks/build/index.css';
		if ( file_exists( $editor_css ) ) {
			wp_enqueue_style(
				'wz-cbh-editor-style',
				WZ_CBH_PLUGIN_URL . 'includes/blocks/build/index.css',
				array( 'wz-cbh-editor-theme' ),
				$asset['version']
			);
		}

		$languages    = self::get_languages();
		$default_lang = wz_cbh_get_option( 'default-lang', '' );

		$default_settings = array(
			'language'         => $default_lang,
			'lineNumbers'      => (bool) wz_cbh_get_option( 'default-line-numbers', false ),
			'lineNumbersStart' => (int) wz_cbh_get_option( 'default-line-numbers-start', 1 ),
			'wordWrap'         => (bool) wz_cbh_get_option( 'default-word-wrap', false ),
		);

		wp_add_inline_script(
			'wz-cbh-editor',
			implode(
				"\n",
				array(
					'const cbhLanguages = ' . wp_json_encode( $languages ) . ';',
					'const cbhDefaultLang = ' . wp_json_encode( $default_lang ) . ';',
					'const cbhDefaultSettings = ' . wp_json_encode( $default_settings ) . ';',
				)
			),
			'before'
		);
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

		return new \WP_REST_Response( array( 'updated' => $updated ), 200 );
	}

	/**
	 * Filter the rendered HTML of core/code blocks to inject Prism attributes.
	 *
	 * - Adds `language-{lang}` class on `<code>`.
	 * - Adds `line-numbers` class and `data-start` on `<pre>` when enabled.
	 * - Adds `word-wrap` class on `<pre>` when enabled.
	 * - Adds `data-title` attribute on `<pre>` for toolbar pickup.
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
			'haskell'    => 'Haskell',
			'markup'     => 'HTML',
			'java'       => 'Java',
			'javascript' => 'JavaScript',
			'json'       => 'JSON',
			'jsx'        => 'JSX',
			'kotlin'     => 'Kotlin',
			'markdown'   => 'Markdown',
			'nginx'      => 'Nginx',
			'objectivec' => 'Objective-C',
			'php'        => 'PHP',
			'powershell' => 'PowerShell',
			'python'     => 'Python',
			'ruby'       => 'Ruby',
			'rust'       => 'Rust',
			'sass'       => 'Sass',
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
		return apply_filters( 'wz_cbh_languages', $languages );
	}
}
