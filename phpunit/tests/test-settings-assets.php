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
	 * Every registered theme ships a CSS file whose base colours can be read.
	 *
	 * @param string $slug Theme slug.
	 *
	 * @dataProvider data_registered_themes
	 */
	public function test_every_theme_exposes_a_foreground_colour( $slug ) {
		foreach ( array( '.css', '.min.css' ) as $suffix ) {
			$path = WZCBH_PLUGIN_DIR . 'includes/assets/' . $slug . $suffix;

			$this->assertFileExists( $path );

			$colors = Settings::extract_theme_colors( $path );

			$this->assertNotSame( '', $colors['color'], "no foreground colour parsed from {$slug}{$suffix}" );
		}
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
	 * The settings page URL must match where add_options_page() registers it.
	 *
	 * Linking to admin.php?page=… resolves to a hook name that was never
	 * registered, and WordPress answers with a 403.
	 */
	public function test_settings_page_url_points_at_the_options_page() {
		$url = Settings::get_settings_page_url();

		$this->assertStringContainsString( 'options-general.php', $url );
		$this->assertStringContainsString( 'page=wzcbh_settings', $url );
		$this->assertStringNotContainsString( 'admin.php', $url );
	}

	/**
	 * Asset loading must survive a main query that returns IDs rather than posts.
	 *
	 * A pre_get_posts filter setting `fields => 'ids'` leaves the $posts global
	 * as an array of integers.
	 */
	public function test_enqueue_assets_survives_an_id_only_posts_global() {
		global $posts;

		$post_id = self::factory()->post->create(
			array( 'post_content' => '<!-- wp:code --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->' )
		);

		$original = $posts;
		$posts    = array( $post_id );

		$handler = new Styles_Handler();
		$handler->enqueue_assets();

		$posts = $original;

		// Reaching here without a TypeError is the assertion; no code block was
		// detectable in an array of integers, so nothing should be enqueued.
		$this->assertFalse( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
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
	 * Assets stay off pages with no code block.
	 */
	public function test_enqueue_assets_skips_pages_without_a_code_block() {
		global $posts;

		$post_id = self::factory()->post->create( array( 'post_content' => '<p>no code here</p>' ) );

		$original = $posts;
		$posts    = array( get_post( $post_id ) );

		$handler = new Styles_Handler();
		$handler->enqueue_assets();

		$posts = $original;

		$this->assertFalse( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
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
	 * A reference to a post that is not a synced pattern is not followed.
	 */
	public function test_enqueue_assets_ignores_refs_to_other_post_types() {
		$other   = self::factory()->post->create( array( 'post_content' => '<!-- wp:code --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->' ) );
		$post_id = self::factory()->post->create( array( 'post_content' => '<!-- wp:block {"ref":' . $other . '} /-->' ) );

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
	 * Every registered scheme ships its scoped CSS in each variant Styles_Handler can load.
	 *
	 * @param string $slug Color scheme slug.
	 *
	 * @dataProvider data_registered_color_schemes
	 */
	public function test_every_theme_has_scoped_css( $slug ) {
		foreach ( array( '', '.min', '-rtl', '-rtl.min' ) as $suffix ) {
			$path = WZCBH_PLUGIN_DIR . "includes/assets/scoped/{$slug}{$suffix}.css";

			$this->assertFileExists( $path );
			$this->assertStringContainsString( '.wzcbh-theme-' . $slug . ' ', (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
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
	 * Blocks that inherit, repeat the global scheme or name an unregistered one load no scoped CSS.
	 */
	public function test_rendering_without_an_override_loads_no_scoped_css() {
		new Styles_Handler();
		do_blocks(
			'<!-- wp:code --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->'
			. '<!-- wp:code {"colorScheme":"prism-onedark"} --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->'
			. '<!-- wp:code {"colorScheme":"../prism-nord"} --><pre class="wp-block-code"><code>x</code></pre><!-- /wp:code -->'
		);

		$this->assertTrue( wp_style_is( 'wzcbh-prism-theme', 'enqueued' ) );
		foreach ( array_keys( Settings::$color_schemes ) as $slug ) {
			$this->assertFalse( wp_style_is( 'wzcbh-prism-theme-' . $slug, 'enqueued' ), $slug );
		}
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
