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
		Blocks::reset_server_highlight_budget();
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
	 * Uncached detections are capped per page request; cache hits are free, and other requests are uncapped.
	 */
	public function test_uncached_detections_are_capped_per_page_request() {
		$limit  = 2;
		$filter = static function () use ( &$limit ) {
			return $limit;
		};
		add_filter( 'wzcbh_auto_detect_request_limit', $filter );

		$render = function ( $from, $to ) {
			$html = array();
			for ( $i = $from; $i <= $to; $i++ ) {
				$html[ $i ] = $this->render( "<?php\nfunction f{$i}( \$a ) {\n\treturn array_map( 'trim', \$a );\n}\n" );
			}
			return $html;
		};

		$first = $render( 0, 2 );
		$this->assertCount( 2, $this->cached_detections() );
		$this->assertStringContainsString( 'language-php', $first[1] );
		$this->assertStringNotContainsString( 'language-php', $first[2], 'past the cap renders plain' );

		Blocks::reset_server_highlight_budget();
		$second = $render( 0, 2 );
		$this->assertCount( 3, $this->cached_detections() );
		$this->assertStringContainsString( 'language-php', $second[2], 'a later request detects it' );

		Blocks::reset_server_highlight_budget();
		$limit = 0;
		$render( 3, 5 );
		$this->assertCount( 6, $this->cached_detections(), 'a limit of 0 disables the cap' );

		$this->go_to( get_feed_link() );
		Blocks::reset_server_highlight_budget();
		$limit = 1;
		$render( 6, 8 );
		$this->assertCount( 9, $this->cached_detections(), 'feeds are uncapped' );

		remove_filter( 'wzcbh_auto_detect_request_limit', $filter );
	}
}
