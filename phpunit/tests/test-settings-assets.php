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
		foreach ( array( 'wzcbh-prism-theme', 'wzcbh-prism-css', 'wzcbh-hljs-server' ) as $handle ) {
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
}
