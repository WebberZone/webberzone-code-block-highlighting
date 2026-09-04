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
	 * Build saved core/code HTML the way the block's save function does.
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
			. esc_html( $code )
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
			array_flip( array( 'language', 'lineNumbers', 'lineNumbersStart', 'wordWrap', 'title', 'highlightLines', 'maxHeight' ) )
		);

		return $this->blocks->render_code_block( $this->saved_html( $args ), array( 'attrs' => $attrs ) );
	}

	/**
	 * A file name containing regex backreference syntax must survive verbatim.
	 *
	 * The attributes are injected into the rendered HTML through a regex
	 * replacement, where `$0` and `\0` mean "the matched text" unless the
	 * replacement is built inside a callback.
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
	 * The same applies to the line-range attribute.
	 */
	public function test_backreference_syntax_in_highlight_lines_is_not_expanded() {
		$html = $this->render(
			array(
				'language'       => 'javascript',
				'highlightLines' => '$0',
			)
		);

		$this->assertStringNotContainsString( 'data-line="<pre"', $html );
	}

	/**
	 * Attributes the save function already wrote must not be emitted twice.
	 */
	public function test_pre_attributes_are_not_duplicated() {
		$html = $this->render(
			array(
				'language'       => 'javascript',
				'title'          => 'index.js',
				'highlightLines' => '1',
			)
		);

		$this->assertSame( 1, substr_count( $html, 'data-title=' ), 'data-title emitted more than once' );
		$this->assertSame( 1, substr_count( $html, 'data-line=' ), 'data-line emitted more than once' );
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
	 * Server mode must not repeat the language class the saved HTML already has.
	 */
	public function test_server_mode_does_not_duplicate_language_class() {
		$this->set_option( 'highlighting-mode', 'server' );

		$html = $this->render(
			array(
				'language' => 'php',
				'code'     => "<?php\necho 1;",
			)
		);

		preg_match( '/<pre[^>]*>/', $html, $matches );

		$this->assertSame( 1, substr_count( $matches[0], 'language-php' ) );
	}

	/**
	 * Server mode must not add an empty class attribute.
	 */
	public function test_server_mode_does_not_emit_empty_class_attribute() {
		$this->set_option( 'highlighting-mode', 'server' );

		$html = $this->blocks->render_code_block(
			'<pre><code>plain</code></pre>',
			array( 'attrs' => array() )
		);

		$this->assertStringNotContainsString( 'class=""', $html );
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
	 * A line beyond the block's last line must not gain a highlight stripe.
	 *
	 * highlight.php closes its outermost span after the trailing newline, which
	 * used to produce a phantom extra line span that the highlight could land on.
	 */
	public function test_highlight_does_not_land_on_a_phantom_trailing_line() {
		$this->set_option( 'highlighting-mode', 'server' );

		$html = $this->render(
			array(
				'language'       => 'markup',
				'lineNumbers'    => true,
				'highlightLines' => '1,3',
				'code'           => "<?php\necho 'x';\n",
			)
		);

		preg_match_all( '/data-line-number="(\d+)"/', $html, $matches );

		$this->assertSame( array( '1', '2' ), $matches[1], 'block renders more lines than it has' );
		$this->assertSame( 1, substr_count( $html, 'wzcbh-highlighted-line' ) );
	}

	/**
	 * An unbounded line range must not allocate one entry per line number.
	 */
	public function test_unbounded_line_range_is_clamped() {
		$method = new ReflectionMethod( Blocks::class, 'parse_line_ranges' );
		$method->setAccessible( true );

		// Deterministic: the parser must never return more entries than the block
		// has lines, however large the range in the attribute.
		$this->assertCount( 3, $method->invoke( null, '1-999999999', 1, 3 ) );
		$this->assertCount( 0, $method->invoke( null, '900-999999999', 1, 3 ) );
		$this->assertSame( array( 1 => true, 2 => true ), $method->invoke( null, '1-2', 1, 3 ) );

		$this->set_option( 'highlighting-mode', 'server' );

		$html = $this->render(
			array(
				'language'       => 'php',
				'lineNumbers'    => true,
				'highlightLines' => '1-999999999',
				'code'           => "a\nb\nc",
			)
		);

		$this->assertSame( 3, substr_count( $html, 'wzcbh-highlighted-line' ) );
	}

	/**
	 * Ranges are still expanded normally inside the block.
	 */
	public function test_line_ranges_within_the_block_still_highlight() {
		$this->set_option( 'highlighting-mode', 'server' );

		$html = $this->render(
			array(
				'language'       => 'php',
				'lineNumbers'    => true,
				'highlightLines' => '1,3-4',
				'code'           => "a\nb\nc\nd\ne",
			)
		);

		preg_match_all( '/wzcbh-highlighted-line" data-line-number="(\d+)"/', $html, $matches );

		$this->assertSame( array( '1', '3', '4' ), $matches[1] );
	}

	/**
	 * Oversized blocks skip server-side highlighting rather than stalling render.
	 */
	public function test_oversized_block_falls_back_to_plain_text() {
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
	}

	/**
	 * The size limit is filterable.
	 */
	public function test_highlight_size_limit_is_filterable() {
		$this->set_option( 'highlighting-mode', 'server' );

		add_filter( 'wzcbh_server_highlight_max_bytes', '__return_zero' );

		$html = $this->render(
			array(
				'language' => 'php',
				'code'     => "<?php\necho 1;",
			)
		);

		remove_filter( 'wzcbh_server_highlight_max_bytes', '__return_zero' );

		$this->assertSame( 0, substr_count( $html, 'class="token' ) );
	}

	/**
	 * The download file name derives from the language when no title is set.
	 *
	 * @param string $language Language slug.
	 * @param string $expected Expected file name.
	 *
	 * @dataProvider data_download_filenames
	 */
	public function test_download_filename( $language, $expected ) {
		$html = $this->render( array( 'language' => $language ) );

		$this->assertStringContainsString( 'data-wzcbh-download="' . $expected . '"', $html );
	}

	/**
	 * Language slugs and the file names they download as.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_download_filenames() {
		return array(
			'javascript' => array( 'javascript', 'snippet.js' ),
			'docker'     => array( 'docker', 'Dockerfile' ),
			'unmapped'   => array( 'graphql', 'snippet.graphql' ),
		);
	}

	/**
	 * The code a visitor copies must not be altered by the render filter.
	 *
	 * @param string $mode Highlighting mode.
	 *
	 * @dataProvider data_modes
	 */
	public function test_rendered_code_text_round_trips( $mode ) {
		$this->set_option( 'highlighting-mode', $mode );

		$code = "function foo() {\n\treturn 'a & b < c';\n}\n";

		$html = $this->render(
			array(
				'language'    => 'php',
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
}
