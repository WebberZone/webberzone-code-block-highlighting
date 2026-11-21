<?php
/**
 * Tests for the core/code render filter in both highlighting modes.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

use WebberZone\Code_Block_Highlighting\Frontend\Blocks;

/**
 * Render filter tests.
 */
class Test_Blocks_Render extends WP_UnitTestCase {

	/**
	 * Blocks instance under test.
	 *
	 * @var Blocks
	 */
	private $blocks;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->blocks = new Blocks();
		Blocks::reset_server_highlight_budget();
		delete_option( 'wzcbh_settings' );
		\WebberZone\Code_Block_Highlighting\Options_API::flush_cache();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		delete_option( 'wzcbh_settings' );
		\WebberZone\Code_Block_Highlighting\Options_API::flush_cache();
		parent::tear_down();
	}

	/**
	 * Set a plugin option and clear the read cache.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Option value.
	 */
	private function set_option( $key, $value ) {
		wzcbh_update_option( $key, $value );
		\WebberZone\Code_Block_Highlighting\Options_API::flush_cache();
	}

	/**
	 * Build saved core/code HTML the way 1.2.3 and earlier saved it.
	 *
	 * @param array $args Block arguments.
	 * @return string
	 */
	private function saved_html( array $args ) {
		$language = $args['language'] ?? '';
		$code     = $args['code'] ?? "one\ntwo";

		$classes = array_filter(
			array(
				'wp-block-code',
				$language ? 'language-' . $language : '',
				! empty( $args['lineNumbers'] ) ? 'line-numbers' : '',
			)
		);

		$attrs = '';
		if ( ! empty( $args['title'] ) ) {
			$attrs .= ' data-title="' . esc_attr( $args['title'] ) . '"';
		}
		if ( ! empty( $args['highlightLines'] ) ) {
			$attrs .= ' data-line="' . esc_attr( $args['highlightLines'] ) . '"';
		}

		return '<pre class="' . implode( ' ', $classes ) . '"' . $attrs . '>'
			. '<code' . ( $language ? ' lang="' . $language . '" class="language-' . $language . '"' : '' ) . '>'
			. htmlspecialchars( $code, ENT_QUOTES, 'UTF-8' )
			. '</code></pre>';
	}

	/**
	 * Render a block through the filter.
	 *
	 * @param array $args Block arguments.
	 * @return string
	 */
	private function render( array $args ) {
		$attrs = array_intersect_key(
			$args,
			array_flip( array( 'language', 'lineNumbers', 'lineNumbersStart', 'wordWrap', 'title', 'highlightLines', 'maxHeight', 'diff' ) )
		);

		return $this->blocks->render_code_block( $this->saved_html( $args ), array( 'attrs' => $attrs ) );
	}

	/**
	 * A file name containing regex backreference syntax must survive verbatim.
	 *
	 * @param string $title Title to round-trip.
	 *
	 * @dataProvider data_backreference_titles
	 */
	public function test_backreference_syntax_in_title_is_not_expanded( $title ) {
		$html = $this->render(
			array(
				'language' => 'javascript',
				'title'    => $title,
			)
		);

		$this->assertStringContainsString( 'data-title="' . esc_attr( $title ) . '"', $html );
		$this->assertStringNotContainsString( 'data-title="a<pre', $html );
	}

	/**
	 * Titles that contain regex replacement syntax.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_backreference_titles() {
		return array(
			'dollar zero'   => array( 'a$0b' ),
			'dollar one'    => array( 'a$1b' ),
			'backslash'     => array( 'a\0b' ),
			'double slash'  => array( 'a\\\\b' ),
			'plain'         => array( 'index.js' ),
		);
	}

	/**
	 * The plugin's sanitised title wins over whatever the saved HTML carries.
	 */
	public function test_pre_title_attribute_is_the_sanitised_value() {
		$html = $this->render(
			array(
				'language' => 'javascript',
				'title'    => '<b>name.js</b>',
			)
		);

		$this->assertStringContainsString( 'data-title="name.js"', $html );
		$this->assertStringNotContainsString( '&lt;b&gt;name.js', $html );
	}

	/**
	 * The line-number gutter must have exactly one row per wrapped line.
	 *
	 * @param string $code     Block source.
	 * @param int    $expected Expected line count.
	 *
	 * @dataProvider data_line_counts
	 */
	public function test_gutter_rows_match_wrapped_lines( $code, $expected ) {
		$this->set_option( 'highlighting-mode', 'server' );

		$html = $this->render(
			array(
				'language'       => 'php',
				'lineNumbers'    => true,
				'highlightLines' => '1-99',
				'code'           => $code,
			)
		);

		preg_match( '/<span aria-hidden="true" class="line-numbers-rows">(.*?)<\/span><\/code>/s', $html, $gutter );

		$this->assertNotEmpty( $gutter, 'no line-number gutter rendered' );
		$this->assertSame( $expected, substr_count( $gutter[1], '<span></span>' ), 'gutter row count' );
		$this->assertSame( $expected, substr_count( $html, 'class="wzcbh-line' ), 'wrapped line count' );
	}

	/**
	 * Code samples and the number of lines they render as.
	 *
	 * @return array<string, array{string, int}>
	 */
	public function data_line_counts() {
		return array(
			'no trailing newline'   => array( "a\nb", 2 ),
			'trailing newline'      => array( "a\nb\n", 2 ),
			'blank line in middle'  => array( "a\n\nb", 3 ),
			'trailing blank line'   => array( "a\n\n", 2 ),
			'single line'           => array( 'a', 1 ),
		);
	}

	/**
	 * Oversized blocks skip server-side highlighting and fall back to Prism rather than stalling render.
	 */
	public function test_oversized_block_falls_back_to_prism() {
		$this->set_option( 'highlighting-mode', 'server' );

		$code = str_repeat( "\$foo = bar( 'baz' );\n", 8000 );

		$html = $this->render(
			array(
				'language' => 'php',
				'code'     => $code,
			)
		);

		$this->assertSame( 0, substr_count( $html, 'class="token' ) );
		$this->assertStringContainsString( 'language-php', $html );
		$this->assertStringContainsString( 'data-wzcbh-client="1"', $html );

		$this->go_to( get_feed_link() );
		Blocks::reset_server_highlight_budget();

		$feed = $this->render(
			array(
				'language' => 'php',
				'code'     => $code,
			)
		);

		$this->assertSame( 0, substr_count( $feed, 'class="token' ) );
		$this->assertStringNotContainsString( 'data-wzcbh-client', $feed );
		$this->assertStringContainsString( 'language-php', $feed );
	}

	/**
	 * Blocks beyond the per-request budget fall back to Prism, until the fallback page budget runs out.
	 */
	public function test_exhausted_request_budget_falls_back_to_prism() {
		$this->set_option( 'highlighting-mode', 'server' );
		$budget = static function () {
			return 10;
		};
		$page_budget = 10;
		$fallback    = static function () use ( &$page_budget ) {
			return $page_budget;
		};
		add_filter( 'wzcbh_server_highlight_request_budget', $budget );
		add_filter( 'wzcbh_prism_fallback_page_budget', $fallback );

		$args = array(
			'language' => 'php',
			'code'     => '$a = 1;',
		);

		$first  = $this->render( $args );
		$second = $this->render( $args );
		$third  = $this->render( $args );

		Blocks::reset_server_highlight_budget();
		$page_budget = 0;
		$this->render( $args );
		$this->render( $args );
		$unlimited = $this->render( $args );

		remove_filter( 'wzcbh_server_highlight_request_budget', $budget );
		remove_filter( 'wzcbh_prism_fallback_page_budget', $fallback );

		$this->assertStringNotContainsString( 'data-wzcbh-client', $third, 'over the fallback page budget stays plain text' );
		$this->assertSame( 0, substr_count( $third, 'class="token' ) );
		$this->assertStringContainsString( 'data-wzcbh-client="1"', $unlimited, 'a page budget of 0 disables the cap' );

		$this->assertStringContainsString( 'class="token', $first );
		$this->assertStringNotContainsString( 'data-wzcbh-client', $first );
		$this->assertSame( 0, substr_count( $second, 'class="token' ) );
		$this->assertMatchesRegularExpression( '/<pre[^>]*\sdata-wzcbh-client="1"[^>]*>/', $second );
		$this->assertStringContainsString( 'class="language-php"', $second );
	}

	/**
	 * The download file name derives from the language, and title extensions are allowlisted.
	 *
	 * @param string $language Language slug.
	 * @param string $expected Expected file name.
	 * @param string $title    Block file name / title.
	 *
	 * @dataProvider data_download_filenames
	 */
	public function test_download_filename( $language, $expected, $title = '' ) {
		$html = $this->render(
			array(
				'language' => $language,
				'title'    => $title,
			)
		);

		$this->assertStringContainsString( 'data-wzcbh-download="' . $expected . '"', $html );
	}

	/**
	 * Language slugs, titles and the file names they download as.
	 *
	 * @return array<string, array{0: string, 1: string, 2?: string}>
	 */
	public function data_download_filenames() {
		return array(
			'javascript'           => array( 'javascript', 'snippet.js' ),
			'docker'               => array( 'docker', 'Dockerfile' ),
			'unmapped'             => array( 'graphql', 'snippet.graphql' ),
			'title ext'            => array( 'javascript', 'app.ts', 'app.ts' ),
			'unmapped title ext'   => array( 'javascript', 'app.mjs', 'app.mjs' ),
			'compound name'        => array( 'yaml', 'docker-compose.yaml', 'docker-compose.yaml' ),
			'whole name title'     => array( 'docker', 'Dockerfile', 'Dockerfile' ),
			'dotted version stem'  => array( 'text', sanitize_file_name( 'v1.2 notes' ), 'v1.2 notes' ),
			'dotted release'       => array( 'text', 'release-1.4.0', 'release-1.4.0' ),
			'executable'           => array( 'php', 'evil.php', 'evil.exe' ),
			'executable uppercase' => array( 'php', 'UPPER.php', 'UPPER.EXE' ),
			'batch no lang ext'    => array( 'docker', 'fix.txt', 'fix.bat' ),
		);
	}

	/**
	 * The code a visitor copies must not be altered by the render filter.
	 *
	 * @param string $mode     Highlighting mode.
	 * @param string $language Language slug.
	 * @param string $code     Code text.
	 *
	 * @dataProvider data_round_trips
	 */
	public function test_rendered_code_text_round_trips( $mode, $language, $code ) {
		$this->set_option( 'highlighting-mode', $mode );

		$html = $this->render(
			array(
				'language'    => $language,
				'lineNumbers' => true,
				'code'        => $code,
			)
		);

		preg_match( '/<code[^>]*>([\s\S]*)<\/code>/', $html, $matches );

		// strip_tags() rather than wp_strip_all_tags(): the latter trims, which
		// would hide a lost trailing newline.
		$inner = preg_replace( '/<span aria-hidden="true" class="line-numbers-rows">[\s\S]*$/', '', $matches[1] );
		$text  = html_entity_decode( strip_tags( (string) $inner ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$this->assertSame( $code, $text );
	}

	/**
	 * Modes, languages and code that must survive rendering, including the plain-text path with and without `&`.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public function data_round_trips() {
		$code = "function foo() {\n\treturn 'a & b < c';\n}\n";

		return array(
			'client'                 => array( 'client', 'php', $code ),
			'server'                 => array( 'server', 'php', $code ),
			'server plain entity'    => array( 'server', 'text', "x &lt; y & z\n" ),
			'server plain no ampers' => array( 'server', 'text', "if ( a < b ) { return 'c'; }\n" ),
		);
	}

	/**
	 * Render core's saved markup, with every option in the block comment.
	 *
	 * @param array  $attrs Block comment attributes.
	 * @param string $code  Code text.
	 * @return string
	 */
	private function render_core_markup( array $attrs, $code = "one\ntwo" ) {
		return $this->blocks->render_code_block(
			'<pre class="wp-block-code"><code>' . esc_html( $code ) . '</code></pre>',
			array( 'attrs' => $attrs )
		);
	}

	/**
	 * Every option set in the block comment renders in client mode.
	 */
	public function test_client_mode_renders_comment_attributes_on_core_markup() {
		$html = $this->render_core_markup(
			array(
				'language'         => 'php',
				'title'            => 'demo.php',
				'lineNumbers'      => true,
				'lineNumbersStart' => 5,
				'wordWrap'         => true,
				'highlightLines'   => '2',
				'maxHeight'        => 120,
			)
		);

		preg_match( '/<pre[^>]*>/', $html, $pre );
		preg_match( '/<code[^>]*>/', $html, $code );

		foreach ( array( 'language-php', 'line-numbers', 'word-wrap', 'data-start="5"', 'data-title="demo.php"', 'data-line="2"', 'max-height:120px;overflow-y:auto;' ) as $needle ) {
			$this->assertStringContainsString( $needle, $pre[0] );
		}
		$this->assertStringContainsString( 'class="language-php"', $code[0] );
		$this->assertStringContainsString( 'wzcbh-file-tab', $html );
	}

	/**
	 * Out-of-range start line and max height values are clamped.
	 */
	public function test_oversized_numeric_attributes_are_clamped() {
		$html = $this->render_core_markup(
			array(
				'language'         => 'php',
				'lineNumbers'      => true,
				'lineNumbersStart' => PHP_INT_MAX,
				'maxHeight'        => PHP_INT_MAX,
			)
		);

		$this->assertStringContainsString( 'data-start="1000000"', $html );
		$this->assertStringContainsString( 'max-height:100000px;', $html );
	}

	/**
	 * Saved markup cannot smuggle attributes into the rewritten `<pre>` or `<code>`, and event handlers are removed.
	 *
	 * @param string $mode Highlighting mode.
	 * @param string $html Saved block HTML.
	 *
	 * @dataProvider data_attribute_smuggling
	 */
	public function test_rewritten_tags_gain_no_smuggled_attributes( $mode, $html ) {
		$this->set_option( 'highlighting-mode', $mode );

		$output = $this->blocks->render_code_block(
			$html,
			array(
				'attrs' => array(
					'language'       => 'javascript',
					'title'          => 't.js',
					'maxHeight'      => 10,
					'downloadButton' => 'show',
				),
			)
		);

		$added = array( 'class', 'style', 'data-title', 'data-wzcbh-download' );

		foreach ( array( 'PRE', 'CODE' ) as $tag ) {
			$input  = new WP_HTML_Tag_Processor( $html );
			$parsed = new WP_HTML_Tag_Processor( $output );
			if ( ! $input->next_tag( array( 'tag_name' => $tag ) ) ) {
				continue;
			}
			$this->assertTrue( $parsed->next_tag( array( 'tag_name' => $tag ) ), "no {$tag} rendered" );

			$names = (array) $parsed->get_attribute_names_with_prefix( '' );

			$this->assertSame( array(), array_intersect( $names, array( 'autofocus', 'tabindex' ) ), "smuggled attribute on {$tag}" );
			$this->assertSame( array(), array_diff( $names, (array) $input->get_attribute_names_with_prefix( '' ), $added ), "new attribute on {$tag}" );
		}

		$parsed = new WP_HTML_Tag_Processor( $output );
		while ( $parsed->next_tag() ) {
			$this->assertSame( array(), (array) $parsed->get_attribute_names_with_prefix( 'on' ), 'event handler on ' . $parsed->get_tag() );
		}

		$parsed = new WP_HTML_Tag_Processor( $output );
		$parsed->next_tag( array( 'tag_name' => 'CODE' ) );
		$this->assertTrue( $parsed->has_class( 'language-javascript' ) );
	}

	/**
	 * Saved markup whose attributes read across each other to a regex, in both modes.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_attribute_smuggling() {
		$tail = ' data-x="\'y onfocus=window.__xss=1 autofocus tabindex=1 z"';
		return $this->in_both_modes(
			array(
				'class decoy'        => '<pre data-d=\'x class="\'' . $tail . '><code>x</code></pre>',
				'download decoy'     => '<pre data-d=\'x data-wzcbh-download="\'' . $tail . ' class="wp-block-code"><code>x</code></pre>',
				'style decoy'        => '<pre data-d=\'x style="\'' . $tail . ' class="wp-block-code"><code>x</code></pre>',
				'code decoy'         => '<pre class="wp-block-code"><code data-d=\'x class="\'' . $tail . '>x</code></pre>',
				'uppercase'          => "<PRE DATA-D='x CLASS=\"' DATA-X=\"'y ONFOCUS=window.__xss=1 AUTOFOCUS TABINDEX=1 z\" ONCLICK=\"alert(1)\"><CODE ONMOUSEOVER=x>x</CODE></PRE>",
				'newlines'           => "<pre\ndata-d='x\nclass=\"'\ndata-x=\"'y\nonfocus=window.__xss=1\nautofocus\ntabindex=1 z\"\nclass=wp-block-code><code>x</code></pre>",
				'unquoted'           => '<pre class=wp-block-code data-x=a onclick=alert(1)><code class=c onfocus=y>x</code></pre>',
				'core form'          => '<pre class="wp-block-code" onclick="alert(1)"><code onfocus="x">x</code></pre>',
				'no pre'             => '<div class="wp-block-code"><code onclick="alert(1)">x</code></div>',
				'bare code'          => '<code onclick="alert(1)">x</code>',
				'close in comment'   => '<pre class="wp-block-code"><code>a<!--</code><img src=x onerror=alert(1)>--></code></pre>',
				'close in textarea'  => '<pre class="wp-block-code"><code>a<textarea></code><img src=x onerror=alert(1)></textarea></code></pre>',
				'close in script'    => '<pre class="wp-block-code"><code>a<script></code><img src=x onerror=alert(1)></script></code></pre>',
				'close in attribute' => '<pre class="wp-block-code"><code><span title="</code><img src=x onerror=alert(1)>"></span></code></pre>',
			)
		);
	}

	/**
	 * A `language-text` class is renamed in place on both tags, leaving one class attribute and no event handlers.
	 *
	 * @param string $mode Highlighting mode.
	 * @param string $html Saved block HTML.
	 *
	 * @dataProvider data_text_class_markup
	 */
	public function test_text_class_is_renamed_once( $mode, $html ) {
		$this->set_option( 'highlighting-mode', $mode );

		$output = $this->blocks->render_code_block( $html, array( 'attrs' => array( 'language' => 'text' ) ) );

		preg_match( '/<pre\b[^>]*>/', $output, $pre );

		$this->assertSame( 1, substr_count( $pre[0], 'class=' ) );
		$this->assertStringContainsString( 'language-none', $pre[0] );
		$this->assertStringNotContainsString( 'language-text', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
	}

	/**
	 * Single-quoted classes, and a `<code>` with no closing tag, in both modes.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_text_class_markup() {
		return $this->in_both_modes(
			array(
				'single-quoted' => "<pre class='wp-block-code language-text'><code class='language-text'>a</code></pre>",
				'unterminated'  => '<pre class="wp-block-code language-text"><code class="language-text" onclick="x">a',
			)
		);
	}

	/**
	 * Saved class and style text that is not valid UTF-8 survives the plugin adding its own.
	 *
	 * @param string $mode Highlighting mode.
	 * @param string $html Saved block HTML.
	 *
	 * @dataProvider data_invalid_utf8_markup
	 */
	public function test_invalid_utf8_attribute_text_is_kept( $mode, $html ) {
		$this->set_option( 'highlighting-mode', $mode );

		$output = $this->blocks->render_code_block(
			$html,
			array(
				'attrs' => array(
					'language'  => 'javascript',
					'maxHeight' => 10,
				),
			)
		);

		$parsed = new WP_HTML_Tag_Processor( $output );
		$parsed->next_tag( array( 'tag_name' => 'PRE' ) );

		$this->assertTrue( $parsed->has_class( 'wp-block-code' ) );
		$this->assertTrue( $parsed->has_class( 'language-javascript' ) );
		$this->assertStringContainsString( 'caf', (string) $parsed->get_attribute( 'class' ) );
		$this->assertStringContainsString( 'color:red', (string) $parsed->get_attribute( 'style' ) );
		$this->assertStringContainsString( 'max-height:10px', (string) $parsed->get_attribute( 'style' ) );
	}

	/**
	 * Core-form and single-quoted markup carrying an invalid UTF-8 byte, in both modes.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_invalid_utf8_markup() {
		return $this->in_both_modes(
			array(
				'core form'     => "<pre class=\"wp-block-code caf\xE9\" style=\"color:red\"><code class=\"c\xE9\">x</code></pre>",
				'single-quoted' => "<pre class='wp-block-code caf\xE9' style='color:red'><code class='c\xE9'>x</code></pre>",
			)
		);
	}

	/**
	 * Pair each saved markup sample with both highlighting modes.
	 *
	 * @param  array<string, string> $markup Name => saved block HTML.
	 * @return array<string, array{string, string}>
	 */
	private function in_both_modes( array $markup ) {
		$data = array();
		foreach ( $markup as $name => $html ) {
			$data[ "client {$name}" ] = array( 'client', $html );
			$data[ "server {$name}" ] = array( 'server', $html );
		}

		return $data;
	}

	/**
	 * Both highlighting modes.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_modes() {
		return array(
			'client' => array( 'client' ),
			'server' => array( 'server' ),
		);
	}

	/**
	 * A diff block keeps its prefixes in prefix tokens and, in server mode, one gutter row per wrapped line.
	 *
	 * @param string $mode Highlighting mode.
	 *
	 * @dataProvider data_modes
	 */
	public function test_diff_block_renders_prefixed_runs( $mode ) {
		$this->set_option( 'highlighting-mode', $mode );

		$html = $this->render(
			array(
				'language'    => 'php',
				'diff'        => true,
				'lineNumbers' => true,
				'code'        => "@@ -1 +1 @@\n-\$a = 1;\n+\$a = 2;\n unchanged\n",
			)
		);

		$this->assertStringContainsString( 'diff-highlight', $html );
		$this->assertStringContainsString( 'language-diff-php', $html );

		if ( 'server' === $mode ) {
			$this->assertStringContainsString( '<span class="token inserted-sign inserted language-php"><span class="token prefix inserted">+</span>', $html );
			$this->assertSame( 4, substr_count( $html, 'class="wzcbh-line' ), 'wrapped line count' );
			$this->assertSame( 4, substr_count( $html, '<span></span>' ), 'gutter row count' );
		}
	}

	/**
	 * A per-block color scheme wraps the block in its scope class in both modes.
	 *
	 * @param string $mode Highlighting mode.
	 *
	 * @dataProvider data_modes
	 */
	public function test_color_scheme_wraps_the_block( $mode ) {
		$this->set_option( 'highlighting-mode', $mode );

		$html = $this->render_core_markup(
			array(
				'language'    => 'php',
				'colorScheme' => 'prism-nord',
			)
		);

		$this->assertStringStartsWith( '<div class="wzcbh-theme-prism-nord">', $html );
		$this->assertSame( 1, substr_count( $html, 'wzcbh-theme-' ) );
	}

	/**
	 * An unregistered or empty scheme renders exactly as a block without one.
	 *
	 * @param string $mode Highlighting mode.
	 *
	 * @dataProvider data_modes
	 */
	public function test_invalid_color_scheme_is_ignored( $mode ) {
		$this->set_option( 'highlighting-mode', $mode );

		$expected = $this->render_core_markup( array( 'language' => 'php' ) );

		foreach ( array( '', 'prism-missing', '../prism-nord', 'prism-nord"><script>' ) as $scheme ) {
			$this->assertSame(
				$expected,
				$this->render_core_markup(
					array(
						'language'    => 'php',
						'colorScheme' => $scheme,
					)
				)
			);
		}
	}

	/**
	 * Save as default stores every block setting, including the color scheme and download override.
	 */
	public function test_save_default_settings_stores_all_block_settings() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wzcbh/v1/default-settings' );
		$request->set_body_params(
			array(
				'language'         => 'php',
				'lineNumbers'      => true,
				'lineNumbersStart' => 3,
				'wordWrap'         => true,
				'maxHeight'        => 200,
				'downloadButton'   => 'hide',
				'colorScheme'      => 'prism-nord',
			)
		);
		$response = rest_do_request( $request );
		\WebberZone\Code_Block_Highlighting\Options_API::flush_cache();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'php', wzcbh_get_option( 'default-lang' ) );
		$this->assertSame( 3, (int) wzcbh_get_option( 'default-line-numbers-start' ) );
		$this->assertSame( 200, (int) wzcbh_get_option( 'default-max-height' ) );
		$this->assertSame( 'hide', wzcbh_get_option( 'default-download-button' ) );
		$this->assertSame( 'prism-nord', wzcbh_get_option( 'default-color-scheme' ) );
		$this->assertSame( 'prism-onedark', \WebberZone\Code_Block_Highlighting\Admin\Settings::get_color_scheme_slug() );
	}

	/**
	 * An unregistered color scheme is rejected rather than stored as a default.
	 */
	public function test_save_default_settings_rejects_unknown_color_scheme() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wzcbh/v1/default-settings' );
		$request->set_body_params( array( 'colorScheme' => '../prism-nord' ) );

		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$this->assertSame( '', (string) wzcbh_get_option( 'default-color-scheme', '' ) );
	}
}
