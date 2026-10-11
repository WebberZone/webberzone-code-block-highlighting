<?php
/**
 * Tests for server-side language detection.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

use WebberZone\Code_Block_Highlighting\Frontend\Blocks;

/**
 * Language detection tests.
 */
class Test_Language_Detection extends WP_UnitTestCase {

	const PHP_CODE = "<?php\nfunction foo( \$a ) {\n\treturn array_map( 'trim', \$a );\n}\n";

	const PROSE = "Name: John Smith\nEmail: john@example.com\nPhone: 555-1234";

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
		$this->set_option( 'highlighting-mode', 'server' );
		$this->set_option( 'auto-detect-language', 1 );
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
	 * Render core/code markup with the given comment attributes.
	 *
	 * @param string $code  Code text.
	 * @param array  $attrs Block comment attributes.
	 * @return string
	 */
	private function render( $code, array $attrs = array() ) {
		return $this->blocks->render_code_block(
			'<pre class="wp-block-code"><code>' . esc_html( $code ) . '</code></pre>',
			array( 'attrs' => $attrs )
		);
	}

	/**
	 * Detection transients currently stored.
	 *
	 * @return array<string, string> Option name => value.
	 */
	private function cached_detections() {
		global $wpdb;

		return $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wzcbh\_lang\_%'", OBJECT_K );
	}

	/**
	 * A detected language highlights the block, labels it and names the download.
	 */
	public function test_detected_language_is_rendered() {
		$html = $this->render( self::PHP_CODE );

		$this->assertMatchesRegularExpression( '/<pre[^>]*class="[^"]*language-php/', $html );
		$this->assertStringContainsString( '<code class="language-php">', $html );
		$this->assertStringContainsString( 'class="token', $html );
		$this->assertStringContainsString( '<span aria-hidden="true">PHP</span>', $html );
		$this->assertStringContainsString( 'data-wzcbh-download="snippet.php"', $html );
	}

	/**
	 * HTML is reported by highlight.php as XML, and maps back to the HTML language.
	 */
	public function test_html_maps_to_markup() {
		$html = $this->render( "<div class=\"a\">\n  <p>Hello <strong>world</strong></p>\n</div>" );

		$this->assertStringContainsString( '<code class="language-markup">', $html );
		$this->assertStringContainsString( '<span aria-hidden="true">HTML</span>', $html );
	}

	/**
	 * Detection is off by default.
	 */
	public function test_off_by_default() {
		delete_option( 'wzcbh_settings' );
		$this->set_option( 'highlighting-mode', 'server' );

		$this->assertStringNotContainsString( 'language-php', $this->render( self::PHP_CODE ) );
	}

	/**
	 * Client mode never detects.
	 */
	public function test_client_mode_is_unchanged() {
		$this->set_option( 'highlighting-mode', 'client' );

		$this->assertStringNotContainsString( 'language-php', $this->render( self::PHP_CODE ) );
		$this->assertEmpty( $this->cached_detections() );
	}

	/**
	 * A language chosen in the block, Plain Text included, is never replaced.
	 */
	public function test_set_language_is_kept() {
		$text = $this->render( self::PHP_CODE, array( 'language' => 'text' ) );
		$bash = $this->render( self::PHP_CODE, array( 'language' => 'bash' ) );

		$this->assertStringNotContainsString( 'language-php', $text );
		$this->assertStringContainsString( 'language-none', $text );
		$this->assertStringContainsString( '<code class="language-bash">', $bash );
		$this->assertEmpty( $this->cached_detections() );
	}

	/**
	 * Text that only loosely resembles code stays plain.
	 */
	public function test_prose_stays_plain() {
		$html = $this->render( self::PROSE );

		$this->assertStringNotContainsString( 'language-', $html );
		$this->assertStringNotContainsString( 'class="token', $html );
		$this->assertStringContainsString( 'data-wzcbh-download="snippet.txt"', $html );
	}

	/**
	 * The result, including no match, is cached and read back.
	 */
	public function test_result_is_cached() {
		$this->render( self::PHP_CODE );
		$this->render( self::PROSE );

		$cached = wp_list_pluck( $this->cached_detections(), 'option_value' );
		$this->assertEqualsCanonicalizing( array( 'php', '' ), array_values( $cached ) );

		$key = array_search( 'php', $cached, true );
		set_transient( substr( $key, strlen( '_transient_' ) ), 'python', MONTH_IN_SECONDS );

		$this->assertStringContainsString( '<code class="language-python">', $this->render( self::PHP_CODE ) );
	}

	/**
	 * The size limit is filterable, and zero disables detection.
	 */
	public function test_size_limit_is_filterable() {
		add_filter( 'wzcbh_auto_detect_max_bytes', '__return_zero' );
		$html = $this->render( self::PHP_CODE );
		remove_filter( 'wzcbh_auto_detect_max_bytes', '__return_zero' );

		$this->assertStringNotContainsString( 'language-php', $html );
		$this->assertEmpty( $this->cached_detections() );
	}

	/**
	 * The candidate languages are filterable.
	 */
	public function test_candidate_languages_are_filterable() {
		$only_css = static function () {
			return array( 'css' );
		};

		add_filter( 'wzcbh_auto_detect_languages', $only_css );
		$html = $this->render( self::PHP_CODE );
		remove_filter( 'wzcbh_auto_detect_languages', $only_css );

		$this->assertStringNotContainsString( 'language-php', $html );
	}
}
