<?php
/**
 * Tests for converting code blocks saved by 1.2.3 and earlier.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

use WebberZone\Code_Block_Highlighting\Code_Block_Migrator;

/**
 * Code block migrator tests.
 */
class Test_Code_Block_Migrator extends WP_UnitTestCase {

	/**
	 * Code text shared by the fixtures.
	 *
	 * @var string
	 */
	const CODE = "echo \$a;\nreturn 3;";

	/**
	 * Wrap block HTML in its delimiters.
	 *
	 * @param string $attrs JSON attributes, or an empty string.
	 * @param string $html  Block inner HTML.
	 * @return string
	 */
	private function block( $attrs, $html ) {
		return '<!-- wp:code ' . ( $attrs ? $attrs . ' ' : '' ) . "-->\n" . $html . "\n<!-- /wp:code -->";
	}

	/**
	 * Convert content and return it with the block counts.
	 *
	 * @param string $content Content.
	 * @return array{0:string, 1:array{converted:int, skipped:int}}
	 */
	private function convert( $content ) {
		$counts = array();
		$out    = Code_Block_Migrator::convert_content( $content, $counts );

		return array( $out, $counts );
	}

	/**
	 * Each shape the old save functions wrote converts to what the editor re-saves.
	 *
	 * @param string $legacy   Markup saved by 1.2.3 or Code Syntax Block.
	 * @param string $expected Markup the editor writes after migrating it.
	 *
	 * @dataProvider data_legacy_shapes
	 */
	public function test_legacy_markup_converts_to_core_markup( $legacy, $expected ) {
		list( $out, $counts ) = $this->convert( $legacy );

		$this->assertSame( $expected, $out );
		$this->assertSame( 1, $counts['converted'] );
	}

	/**
	 * Legacy shapes and their converted form.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_legacy_shapes() {
		$code = self::CODE;
		$core = '<pre class="wp-block-code"><code>' . $code . '</code></pre>';

		return array(
			'every option'       => array(
				$this->block( '{"lineNumbers":true,"lineNumbersStart":5,"wordWrap":true,"highlightLines":"2,3-4","maxHeight":120,"downloadButton":"show"}', '<pre class="wp-block-code language-php line-numbers word-wrap" data-title="demo $0 &quot;x&quot;.php" data-start="5" data-line="2,3-4" style="max-height:120px;overflow-y:auto"><code lang="php" class="language-php">' . $code . '</code></pre>' ),
				$this->block( '{"lineNumbers":true,"lineNumbersStart":5,"wordWrap":true,"highlightLines":"2,3-4","maxHeight":120,"downloadButton":"show","language":"php","title":"demo $0 \u0022x\u0022.php"}', $core ),
			),
			'language'           => array(
				$this->block( '', '<pre class="wp-block-code language-php"><code lang="php" class="language-php">' . $code . '</code></pre>' ),
				$this->block( '{"language":"php"}', $core ),
			),
			'title'              => array(
				$this->block( '', '<pre class="wp-block-code" data-title="demo.php"><code>' . $code . '</code></pre>' ),
				$this->block( '{"title":"demo.php"}', $core ),
			),
			'line numbers only'  => array(
				$this->block( '{"lineNumbers":true}', '<pre class="wp-block-code line-numbers"><code>' . $code . '</code></pre>' ),
				$this->block( '{"lineNumbers":true}', $core ),
			),
			'word wrap only'     => array(
				$this->block( '{"wordWrap":true}', '<pre class="wp-block-code word-wrap"><code>' . $code . '</code></pre>' ),
				$this->block( '{"wordWrap":true}', $core ),
			),
			'max height only'    => array(
				$this->block( '{"maxHeight":80}', '<pre class="wp-block-code" style="max-height:80px;overflow-y:auto"><code>' . $code . '</code></pre>' ),
				$this->block( '{"maxHeight":80}', $core ),
			),
			'block supports'     => array(
				$this->block( '{"className":"my-class","style":{"spacing":{"padding":{"top":"10px"}}},"backgroundColor":"vivid-red","maxHeight":100}', '<pre class="wp-block-code language-javascript my-class has-vivid-red-background-color has-background" style="padding-top:10px;max-height:100px;overflow-y:auto"><code lang="javascript" class="language-javascript">' . $code . '</code></pre>' ),
				$this->block( '{"className":"my-class","style":{"spacing":{"padding":{"top":"10px"}}},"backgroundColor":"vivid-red","maxHeight":100,"language":"javascript"}', '<pre class="wp-block-code my-class has-vivid-red-background-color has-background" style="padding-top:10px"><code>' . $code . '</code></pre>' ),
			),
			'code syntax block'  => array(
				$this->block( '{"lineNumbers":true}', '<pre class="wp-block-code" title="old.php"><code lang="php" class="language-php line-numbers">' . $code . '</code></pre>' ),
				$this->block( '{"lineNumbers":true,"language":"php","title":"old.php"}', $core ),
			),
		);
	}

	/**
	 * A class the user added through Advanced > Additional CSS class is kept.
	 */
	public function test_user_class_matching_a_managed_name_is_kept() {
		$content = $this->block( '{"className":"line-numbers"}', '<pre class="wp-block-code line-numbers"><code>x</code></pre>' );

		list( $out, $counts ) = $this->convert( $content );

		$this->assertSame( $content, $out );
		$this->assertSame( 0, $counts['converted'] );
	}

	/**
	 * Core markup, other blocks and similarly named blocks are left byte-identical.
	 */
	public function test_other_content_is_untouched() {
		$content = "<!-- wp:paragraph -->\n<p>lang=\"php\" class=\"language-php\"</p>\n<!-- /wp:paragraph -->\n\n"
			. $this->block( '{"language":"php"}', '<pre class="wp-block-code"><code>x</code></pre>' ) . "\n\n"
			. "<!-- wp:code-example -->\n<pre class=\"line-numbers\"><code lang=\"php\">x</code></pre>\n<!-- /wp:code-example -->";

		list( $out, $counts ) = $this->convert( $content );

		$this->assertSame( $content, $out );
		$this->assertSame( 0, $counts['converted'] );
	}

	/**
	 * Converting twice changes nothing the second time.
	 */
	public function test_conversion_is_idempotent() {
		$legacy = $this->data_legacy_shapes();
		$once   = $this->convert( implode( "\n\n", array_column( $legacy, 0 ) ) )[0];

		list( $twice, $counts ) = $this->convert( $once );

		$this->assertSame( $once, $twice );
		$this->assertSame( 0, $counts['converted'] );
	}

	/**
	 * Markup that cannot be converted cleanly is reported and left alone.
	 */
	public function test_unconvertible_block_is_skipped() {
		$content = $this->block( '', '<pre class="wp-block-code language-php"></pre>' );

		list( $out, $counts ) = $this->convert( $content );

		$this->assertSame( $content, $out );
		$this->assertSame( 1, $counts['skipped'] );
	}

	/**
	 * Markup the old save would not have written for these attributes is left alone.
	 *
	 * The editor leaves such blocks invalid too; converting them would drop the
	 * option that only the HTML carries.
	 *
	 * @param string $content Block markup.
	 *
	 * @dataProvider data_mismatched_markup
	 */
	public function test_markup_not_matching_the_old_save_is_skipped( $content ) {
		list( $out, $counts ) = $this->convert( $content );

		$this->assertSame( $content, $out );
		$this->assertSame( 0, $counts['converted'] );
		$this->assertSame( 1, $counts['skipped'] );
	}

	/**
	 * Markup whose HTML disagrees with its comment attributes.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_mismatched_markup() {
		return array(
			'data-line without highlightLines' => array( $this->block( '', '<pre class="wp-block-code" data-line="2"><code>x</code></pre>' ) ),
			'line-numbers class, lineNumbers off' => array( $this->block( '{"lineNumbers":false}', '<pre class="wp-block-code line-numbers"><code>x</code></pre>' ) ),
			'max-height differs from maxHeight' => array( $this->block( '{"maxHeight":80}', '<pre class="wp-block-code" style="max-height:120px;overflow-y:auto"><code>x</code></pre>' ) ),
			'another plugin\'s code class'      => array( $this->block( '', '<pre class="wp-block-code"><code class="hljs">x</code></pre>' ) ),
		);
	}

	/**
	 * A code block nested in another block converts, and the parent's markup is untouched.
	 */
	public function test_nested_code_block_converts() {
		$legacy  = $this->data_legacy_shapes();
		$wrap    = static function ( $inner ) {
			return "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . $inner . "</div>\n<!-- /wp:group -->";
		};

		list( $out, $counts ) = $this->convert( $wrap( $legacy['every option'][0] ) );

		$this->assertSame( $wrap( $legacy['every option'][1] ), $out );
		$this->assertSame( 1, $counts['converted'] );
	}

	/**
	 * Converted content gets the escaping core's save applies, as the editor's re-save does.
	 */
	public function test_converted_content_is_escaped_like_core() {
		$content = $this->block( '', '<pre class="wp-block-code language-php"><code lang="php" class="language-php">$a[\'x\'] = [1];' . "\nhttps://example.com\n" . '</code></pre>' );

		list( $out ) = $this->convert( $content );

		$this->assertStringContainsString( '$a&#91;\'x\'] = &#91;1];', $out );
		$this->assertStringContainsString( "\nhttps:&#47;&#47;example.com\n", $out );
	}

	/**
	 * An empty class on <code> already matches core's save, so the block is not touched.
	 */
	public function test_empty_code_class_is_not_legacy() {
		$content = $this->block( '', '<pre class="wp-block-code"><code class="">x</code></pre>' );

		list( $out, $counts ) = $this->convert( $content );

		$this->assertSame( $content, $out );
		$this->assertSame( array( 'converted' => 0, 'skipped' => 0 ), $counts );
	}

	/**
	 * The batch migration converts posts, honours dry runs and leaves other posts alone.
	 */
	public function test_migrate_updates_posts() {
		$legacy = $this->data_legacy_shapes();
		$old_id = self::factory()->post->create( array( 'post_content' => $legacy['language'][0] ) );
		$new_id = self::factory()->post->create( array( 'post_content' => $legacy['language'][1] ) );

		$dry = Code_Block_Migrator::migrate( array( 'dry_run' => true ) );

		$this->assertSame( 1, $dry['changed'] );
		$this->assertSame( $legacy['language'][0], get_post_field( 'post_content', $old_id, 'raw' ) );

		$stats = Code_Block_Migrator::migrate();

		$this->assertSame( 1, $stats['changed'] );
		$this->assertTrue( $stats['done'] );
		$this->assertSame( $legacy['language'][1], get_post_field( 'post_content', $old_id, 'raw' ) );
		$this->assertSame( $legacy['language'][1], get_post_field( 'post_content', $new_id, 'raw' ) );
		$this->assertSame( 0, Code_Block_Migrator::migrate()['changed'] );
	}

	/**
	 * A limited run stops early and resumes from the last post it scanned.
	 */
	public function test_migrate_resumes_after_limit() {
		$legacy = $this->data_legacy_shapes();
		self::factory()->post->create_many( 3, array( 'post_content' => $legacy['title'][0] ) );

		$first = Code_Block_Migrator::migrate( array( 'limit' => 2 ) );
		$rest  = Code_Block_Migrator::migrate( array( 'after_id' => $first['last_id'] ) );

		$this->assertSame( 2, $first['changed'] );
		$this->assertFalse( $first['done'] );
		$this->assertSame( 1, $rest['changed'] );
		$this->assertTrue( $rest['done'] );
	}
}
