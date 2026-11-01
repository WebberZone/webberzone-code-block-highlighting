<?php
/**
 * Tests for settings resolution and frontend asset loading.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

use WebberZone\Code_Block_Highlighting\Admin\Settings;
use WebberZone\Code_Block_Highlighting\Frontend\Styles_Handler;

/**
 * Settings and asset tests.
 */
class Test_Settings_Assets extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( 'wzcbh_settings' );
		\WebberZone\Code_Block_Highlighting\Options_API::flush_cache();

		// The queues outlive a single test, so the enqueue assertions below
		// would otherwise see handles an earlier test left behind. Cleared by
		// handle rather than by dropping $wp_styles / $wp_scripts, which would
		// make WordPress rebuild its whole default registry mid-suite.
		$handles = array( 'wzcbh-prism-theme', 'wzcbh-prism-css', 'wzcbh-hljs-server' );
		foreach ( array_keys( Settings::$color_schemes ) as $slug ) {
			$handles[] = 'wzcbh-prism-theme-' . $slug;
		}

		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		foreach ( array( 'wzcbh-prism-js', 'wzcbh-hljs-clipboard' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
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
	 * The colour scheme slug is always one of the registered themes.
	 *
	 * The slug is interpolated into both a filesystem path and an asset URL, so
	 * an unrecognised stored value must never reach the filesystem.
	 *
	 * @param string $stored   Value stored in the option.
	 * @param string $expected Slug that should be resolved.
	 *
	 * @dataProvider data_color_scheme_slugs
	 */
	public function test_color_scheme_slug_is_validated( $stored, $expected ) {
		$this->set_option( 'color-scheme', $stored );

		$this->assertSame( $expected, Settings::get_color_scheme_slug() );
	}

	/**
	 * Stored colour scheme values and the slug they resolve to.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_color_scheme_slugs() {
		return array(
			'registered theme' => array( 'prism-dracula', 'prism-dracula' ),
			'unknown slug'     => array( 'not-a-theme', 'prism-onedark' ),
			'empty'            => array( '', 'prism-onedark' ),
			'traversal'        => array( '../../../../etc/passwd', 'prism-onedark' ),
			'null byte'        => array( "prism-dracula\0", 'prism-onedark' ),
		);
	}

	/**
	 * Every registered colour scheme.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_registered_themes() {
		$cases = array();

		foreach ( array_keys( Settings::$color_schemes ) as $slug ) {
			$cases[ $slug ] = array( $slug );
		}

		return $cases;
	}

	/**
	 * Assets load when a post in the loop carries a code block.
	 */
	public function test_enqueue_assets_detects_a_code_block() {
		global $posts;

		$post_id = self::factory()->post->create(
			array( 'post_content' => '<!-- wp:code --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->' )
		);

		$original = $posts;
		$posts    = array( get_post( $post_id ) );

		$handler = new Styles_Handler();
		$handler->enqueue_assets();

		$posts = $original;

		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
	}

	/**
	 * Run the up-front check against a single post in the loop.
	 *
	 * @param int $post_id Post ID.
	 */
	private function enqueue_for_post( $post_id ) {
		global $posts;

		$original = $posts;
		$posts    = array( get_post( $post_id ) );

		( new Styles_Handler() )->enqueue_assets();

		$posts = $original;
	}

	/**
	 * A code block inside a synced pattern is detected up front.
	 */
	public function test_enqueue_assets_detects_a_code_block_in_a_synced_pattern() {
		$pattern_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:code --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code --></div><!-- /wp:group -->',
			)
		);
		$post_id    = self::factory()->post->create( array( 'post_content' => '<!-- wp:block {"ref":' . $pattern_id . '} /-->' ) );

		$this->enqueue_for_post( $post_id );

		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
	}

	/**
	 * Synced patterns that reference each other do not loop.
	 */
	public function test_enqueue_assets_survives_a_synced_pattern_loop() {
		$first  = self::factory()->post->create( array( 'post_type' => 'wp_block' ) );
		$second = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:block {"ref":' . $first . '} /-->',
			)
		);
		wp_update_post(
			array(
				'ID'           => $first,
				'post_content' => '<!-- wp:block {"ref":' . $second . '} /-->',
			)
		);
		$post_id = self::factory()->post->create( array( 'post_content' => '<!-- wp:block {"ref":' . $first . '} /-->' ) );

		$this->enqueue_for_post( $post_id );

		$this->assertFalse( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
	}

	/**
	 * A code block rendered outside the main query, such as in a widget, loads the assets.
	 *
	 * @param string $mode   Highlighting mode.
	 * @param string $handle Script the mode enqueues.
	 *
	 * @dataProvider data_mode_scripts
	 */
	public function test_rendering_a_code_block_enqueues_assets( $mode, $handle ) {
		$this->set_option( 'highlighting-mode', $mode );

		new Styles_Handler();
		do_blocks( '<!-- wp:code --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->' );

		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
	}

	/**
	 * Script each highlighting mode enqueues.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_mode_scripts() {
		return array(
			'client' => array( 'client', 'wzcbh-prism-js' ),
			'server' => array( 'server', 'wzcbh-hljs-clipboard' ),
		);
	}

	/**
	 * Rendering in the admin, such as a server-side preview, loads nothing.
	 */
	public function test_rendering_in_the_admin_does_not_enqueue() {
		set_current_screen( 'edit-post' );

		$output = ( new Styles_Handler() )->enqueue_on_render( '<pre>x</pre>' );

		set_current_screen( 'front' );

		$this->assertSame( '<pre>x</pre>', $output );
		$this->assertFalse( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
	}

	/**
	 * Registered color scheme slugs.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_registered_color_schemes() {
		$data = array();
		foreach ( array_keys( Settings::$color_schemes ) as $slug ) {
			$data[ $slug ] = array( $slug );
		}
		return $data;
	}

	/**
	 * Rendering a block with its own scheme loads that scheme's scoped CSS and tab colours, once.
	 *
	 * @param string $mode   Highlighting mode.
	 * @param string $handle Script the mode enqueues.
	 *
	 * @dataProvider data_mode_scripts
	 */
	public function test_rendering_a_color_scheme_enqueues_its_scoped_css( $mode, $handle ) {
		global $wp_styles;

		$this->set_option( 'highlighting-mode', $mode );

		new Styles_Handler();
		do_blocks( str_repeat( '<!-- wp:code {"colorScheme":"prism-nord"} --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->', 2 ) );

		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme-prism-nord', 'enqueued' ) );

		$style = $wp_styles->registered['wzcbh-prism-theme-prism-nord'];
		$this->assertStringContainsString( 'includes/assets/scoped/prism-nord', $style->src );
		$this->assertSame( array( 'wzcbh-prism-theme' ), $style->deps );

		$inline = implode( '', (array) $wp_styles->get_data( 'wzcbh-prism-theme-prism-nord', 'after' ) );
		$this->assertSame( 1, substr_count( $inline, '.wzcbh-theme-prism-nord' ) );
		$this->assertStringContainsString( '--wzcbh-tab-bg:', $inline );
	}

	/**
	 * Schemes used by main-query posts, including inside synced patterns, load up front.
	 */
	public function test_enqueue_assets_loads_color_schemes_up_front() {
		$pattern_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:code {"colorScheme":"prism-dracula"} --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->',
			)
		);
		$post_id    = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:code {"colorScheme":"prism-nord"} --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code --></div><!-- /wp:group -->'
					. '<!-- wp:block {"ref":' . $pattern_id . '} /-->',
			)
		);

		$this->enqueue_for_post( $post_id );

		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme-prism-nord', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme-prism-dracula', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'wzcbh-prism-theme-prism-one-light', 'enqueued' ) );
	}
}
