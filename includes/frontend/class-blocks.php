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
		add_filter( 'render_block_core/code', array( $this, 'render_code_block' ), 10, 2 );
	}

	/**
	 * Enqueue editor script and pass language data.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = WZ_CBH_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wz-cbh-editor',
			WZ_CBH_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$editor_css = WZ_CBH_PLUGIN_DIR . 'build/index.css';
		if ( file_exists( $editor_css ) ) {
			wp_enqueue_style(
				'wz-cbh-editor-style',
				WZ_CBH_PLUGIN_URL . 'build/index.css',
				array(),
				$asset['version']
			);
		}

		$languages    = self::get_languages();
		$default_lang = wz_cbh_get_option( 'default-lang', '' );

		wp_add_inline_script(
			'wz-cbh-editor',
			implode(
				"\n",
				array(
					'const cbhLanguages = ' . wp_json_encode( $languages ) . ';',
					'const cbhDefaultLang = ' . wp_json_encode( $default_lang ) . ';',
				)
			),
			'before'
		);
	}

	/**
	 * Filter the rendered HTML of core/code blocks to inject the language class on the <code> element.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $block_content The rendered block HTML.
	 * @param array<string, mixed> $block         The block data array.
	 * @return string
	 */
	public function render_code_block( string $block_content, array $block ): string {
		$language = sanitize_key( $block['attrs']['language'] ?? '' );

		if ( ! $language ) {
			return $block_content;
		}

		$class = 'language-' . $language;

		// Append to an existing class attribute on <code>.
		if ( preg_match( '/<code[^>]+class="[^"]*"/', $block_content ) ) {
			$block_content = preg_replace(
				'/(<code[^>]+class=")([^"]*)(")/i',
				'$1$2 ' . $class . '$3',
				$block_content,
				1
			);
		} else {
			// No existing class attribute — add one.
			$block_content = preg_replace(
				'/<code(?=[^>]*>)/i',
				'<code class="' . $class . '"',
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
