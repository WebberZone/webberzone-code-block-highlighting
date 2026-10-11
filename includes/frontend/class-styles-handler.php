<?php
/**
 * Styles Handler class.
 *
 * Enqueues Prism.js assets on the frontend, only when code blocks are present.
 *
 * @package WebberZone\Code_Block_Highlighting\Frontend
 */

namespace WebberZone\Code_Block_Highlighting\Frontend;

use WebberZone\Code_Block_Highlighting\Admin\Settings;
use WebberZone\Code_Block_Highlighting\Util\Hook_Registry;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Styles Handler class.
 *
 * @since 1.0.0
 */
class Styles_Handler {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		Hook_Registry::add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		Hook_Registry::add_filter( 'render_block_core/code', array( $this, 'enqueue_on_render' ), 5, 2 );
	}

	/**
	 * Enqueue syntax highlighting assets on the frontend.
	 *
	 * Assets are only loaded when at least one core/code block is present on
	 * the current page, unless the wzcbh_force_load_assets filter returns true.
	 * Delegates to the client-mode or server-mode enqueue helper based on the
	 * active highlighting-mode setting.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets(): void {
		/**
		 * Filter to force-load assets regardless of whether code blocks are detected.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $force_load Whether to force-load assets. Default false.
		 */
		$force_load = apply_filters( 'wzcbh_force_load_assets', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		global $posts;

		if ( ! $force_load ) {
			if ( empty( $posts ) || ! is_array( $posts ) ) {
				return;
			}

			// instanceof, not a WP_Post type hint: a main query switched to
			// `fields => 'ids'` leaves $posts as an array of integers.
			$has_code_block = false;

			foreach ( $posts as $post ) {
				if ( $post instanceof \WP_Post && self::post_has_code_block( $post ) ) {
					$has_code_block = true;
					break;
				}
			}

			if ( ! $has_code_block ) {
				return;
			}
		}

		$this->enqueue_mode_assets();

		// Found up front so classic themes print them in the head, not the footer.
		if ( ! empty( $posts ) && is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				if ( $post instanceof \WP_Post ) {
					array_map( array( __CLASS__, 'enqueue_color_scheme' ), self::get_post_color_schemes( $post ) );
				}
			}
		}
	}

	/**
	 * Enqueue the assets when a code block renders outside the posts checked up front.
	 *
	 * Covers templates, template parts, widgets and anything else outside the
	 * main query. Block themes render the template before `wp_head`, so the
	 * assets still print in the head; classic themes print them in the footer.
	 *
	 * @since 1.3.0
	 *
	 * @param  string               $block_content The rendered block HTML.
	 * @param  array<string, mixed> $block         The block data array.
	 * @return string Unchanged block HTML.
	 */
	public function enqueue_on_render( string $block_content, array $block = array() ): string {
		if ( is_admin() || wp_is_json_request() || is_feed() || wp_doing_ajax() ) {
			return $block_content;
		}

		$this->enqueue_mode_assets();
		self::enqueue_color_scheme( Settings::sanitize_block_color_scheme( (string) ( $block['attrs']['colorScheme'] ?? '' ) ) );

		return $block_content;
	}

	/**
	 * Enqueue the assets for the active highlighting mode, once.
	 *
	 * @since 1.3.0
	 */
	private function enqueue_mode_assets(): void {
		if ( wp_style_is( 'wzcbh-prism-css', 'enqueued' ) ) {
			return;
		}

		$mode = wzcbh_get_option( 'highlighting-mode', 'client' );

		if ( 'server' === $mode ) {
			$this->enqueue_server_mode_assets();
		} else {
			$this->enqueue_client_mode_assets();
		}
	}

	/**
	 * Whether a post contains a code block, including inside the synced patterns it uses.
	 *
	 * @since 1.3.0
	 *
	 * @param  \WP_Post $post Post to check.
	 * @return bool
	 */
	private static function post_has_code_block( \WP_Post $post ): bool {
		if ( has_block( 'core/code', $post ) ) {
			return true;
		}

		if ( ! has_block( 'core/block', $post ) ) {
			return false;
		}

		$seen = array();

		return self::walk_blocks(
			parse_blocks( $post->post_content ),
			$seen,
			static function ( array $block ): bool {
				return 'core/code' === $block['blockName'];
			}
		);
	}

	/**
	 * Per-block color schemes used by a post's code blocks, including inside its synced patterns.
	 *
	 * @since 1.3.0
	 *
	 * @param  \WP_Post $post Post to check.
	 * @return string[] Registered scheme slugs.
	 */
	private static function get_post_color_schemes( \WP_Post $post ): array {
		if ( false === strpos( $post->post_content, 'colorScheme' ) && ! has_block( 'core/block', $post ) ) {
			return array();
		}

		$seen    = array();
		$schemes = array();

		self::walk_blocks(
			parse_blocks( $post->post_content ),
			$seen,
			static function ( array $block ) use ( &$schemes ): bool {
				if ( 'core/code' === $block['blockName'] ) {
					$slug = Settings::sanitize_block_color_scheme( (string) ( $block['attrs']['colorScheme'] ?? '' ) );
					if ( '' !== $slug ) {
						$schemes[ $slug ] = true;
					}
				}
				return false;
			}
		);

		return array_keys( $schemes );
	}

	/**
	 * Walk parsed blocks, following synced pattern references, until the visitor returns true.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param  array<int, true>                 $seen   Synced pattern IDs already followed, so nested references cannot loop.
	 * @param  callable                         $visit  Receives each block; returning true stops the walk.
	 * @return bool Whether the visitor stopped the walk.
	 */
	private static function walk_blocks( array $blocks, array &$seen, callable $visit ): bool {
		foreach ( $blocks as $block ) {
			if ( $visit( $block ) ) {
				return true;
			}

			$ref = (int) ( $block['attrs']['ref'] ?? 0 );

			if ( 'core/block' === $block['blockName'] && $ref && ! isset( $seen[ $ref ] ) ) {
				$seen[ $ref ] = true;
				$pattern      = get_post( $ref );

				if ( $pattern instanceof \WP_Post && 'wp_block' === $pattern->post_type && self::walk_blocks( parse_blocks( $pattern->post_content ), $seen, $visit ) ) {
					return true;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && self::walk_blocks( $block['innerBlocks'], $seen, $visit ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue assets for client-side (Prism.js) mode.
	 *
	 * @since 1.1.0
	 */
	private function enqueue_client_mode_assets(): void {
		$asset_file = WZCBH_PLUGIN_DIR . 'includes/blocks/build/frontend.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset    = require $asset_file;
		$rtl_part = is_rtl() ? '-rtl' : '';

		// Color scheme theme CSS (copied from prism-themes via npm run build:prism).
		wp_enqueue_style(
			'wzcbh-prism-theme',
			self::get_prism_theme_css_url(),
			array(),
			self::get_prism_color_scheme_version()
		);

		// Line-numbers plugin CSS (extracted from src/frontend.js by webpack).
		wp_enqueue_style(
			'wzcbh-prism-css',
			WZCBH_PLUGIN_URL . 'includes/blocks/build/frontend' . $rtl_part . '.css',
			array(),
			$asset['version']
		);

		// Prism core + all language grammars + plugins, bundled by webpack.
		wp_enqueue_script(
			'wzcbh-prism-js',
			WZCBH_PLUGIN_URL . 'includes/blocks/build/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$settings = array(
			'copyToClipboard'   => (bool) wzcbh_get_option( 'copy-to-clipboard', true ),
			'showLanguageLabel' => (bool) wzcbh_get_option( 'show-language-label', true ),
			'showFileName'      => (bool) wzcbh_get_option( 'show-file-name', true ),
			'fileNameStyle'     => (string) wzcbh_get_option( 'file-name-style', 'tab' ),
		);

		wp_add_inline_script(
			'wzcbh-prism-js',
			'const cbhSettings = ' . wp_json_encode( $settings ) . ';',
			'before'
		);

		self::add_root_css_vars();
	}

	/**
	 * Inject the runtime CSS custom properties onto the frontend stylesheet.
	 *
	 * Sets the configured font size, plus the file-name tab colours pulled from
	 * the active Prism theme so the tab matches the code block it sits above.
	 * `frontend.css` declares fallbacks for all of these, so a theme whose
	 * colours cannot be parsed degrades to a readable tab rather than an
	 * invisible one.
	 *
	 * @since 1.2.0
	 */
	private static function add_root_css_vars(): void {
		$vars = array();

		$font_size = (int) wzcbh_get_option( 'font-size', 0 );
		if ( $font_size > 0 ) {
			$vars[] = '--wzcbh-font-size: ' . $font_size . 'px;';
		}

		$colors = Settings::extract_theme_colors( self::get_prism_theme_css_path() );

		if ( '' !== $colors['background'] ) {
			$vars[] = '--wzcbh-tab-bg: ' . $colors['background'] . ';';
		}
		if ( '' !== $colors['color'] ) {
			$vars[] = '--wzcbh-tab-color: ' . $colors['color'] . ';';
		}

		if ( ! $vars ) {
			return;
		}

		wp_add_inline_style(
			'wzcbh-prism-css',
			':root {' . implode( ' ', $vars ) . '}'
		);
	}

	/**
	 * Enqueue assets for server-side (highlight.php) mode.
	 *
	 * @since 1.1.0
	 */
	private function enqueue_server_mode_assets(): void {
		$asset_file = WZCBH_PLUGIN_DIR . 'includes/blocks/build/frontend.asset.php';
		$min        = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$rtl_part   = is_rtl() ? '-rtl' : '';

		// Prism frontend.css: toolbar CSS, line-numbers CSS, font-size, CSS variables.
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			wp_enqueue_style(
				'wzcbh-prism-css',
				WZCBH_PLUGIN_URL . 'includes/blocks/build/frontend' . $rtl_part . '.css',
				array(),
				$asset['version']
			);
		}

		// Prism theme CSS (token colors) — same file used in client mode.
		wp_enqueue_style(
			'wzcbh-prism-theme',
			self::get_prism_theme_css_url(),
			array( 'wzcbh-prism-css' ),
			self::get_prism_color_scheme_version()
		);

		// Server-mode structural overrides (line-highlight).
		wp_enqueue_style(
			'wzcbh-hljs-server',
			WZCBH_PLUGIN_URL . "includes/assets/hljs-server-mode{$rtl_part}{$min}.css",
			array( 'wzcbh-prism-theme' ),
			WZCBH_VERSION
		);

		self::add_root_css_vars();

		// Copy-to-clipboard + expand/collapse script.
		wp_enqueue_script(
			'wzcbh-hljs-clipboard',
			WZCBH_PLUGIN_URL . "includes/assets/hljs-clipboard{$min}.js",
			array(),
			WZCBH_VERSION,
			true
		);

		wp_add_inline_script(
			'wzcbh-hljs-clipboard',
			'var wzcbhI18n = ' . wp_json_encode(
				array(
					'copy'        => __( 'Copy', 'webberzone-code-block-highlighting' ),
					'copied'      => __( 'Copied!', 'webberzone-code-block-highlighting' ),
					'copySuccess' => __( 'Copied code to clipboard.', 'webberzone-code-block-highlighting' ),
					'copyError'   => __( 'Unable to copy code to clipboard.', 'webberzone-code-block-highlighting' ),
					'expand'      => __( 'Expand', 'webberzone-code-block-highlighting' ),
					'collapse'    => __( 'Collapse', 'webberzone-code-block-highlighting' ),
					/* translators: %s: file name of the downloaded snippet */
					'downloaded'  => __( 'Downloaded code as %s.', 'webberzone-code-block-highlighting' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Build the Prism theme CSS file name, respecting SCRIPT_DEBUG and is_rtl().
	 *
	 * The slug is validated, since it is interpolated into a filesystem path as
	 * well as the enqueued URL.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $slug A registered color scheme slug; defaults to the global scheme.
	 * @return string File name relative to includes/assets/.
	 */
	private static function get_prism_theme_css_file_name( string $slug = '' ): string {
		$option   = '' !== $slug ? $slug : Settings::get_color_scheme_slug();
		$min      = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$rtl_part = is_rtl() ? '-rtl' : '';

		return "{$option}{$rtl_part}{$min}.css";
	}

	/**
	 * Get the URL for the active Prism theme CSS.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function get_prism_theme_css_url(): string {
		return WZCBH_PLUGIN_URL . 'includes/assets/' . self::get_prism_theme_css_file_name();
	}

	/**
	 * Get the filesystem path for the active Prism theme CSS.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function get_prism_theme_css_path(): string {
		return WZCBH_PLUGIN_DIR . 'includes/assets/' . self::get_prism_theme_css_file_name();
	}

	/**
	 * Get a version string for the active Prism color scheme CSS for cache busting.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function get_prism_color_scheme_version(): string {
		return self::get_file_version( self::get_prism_theme_css_path() );
	}

	/**
	 * Get a cache-busting version string for an asset file.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $path Absolute filesystem path.
	 * @return string
	 */
	private static function get_file_version( string $path ): string {
		return file_exists( $path ) ? (string) filemtime( $path ) : WZCBH_VERSION;
	}

	/**
	 * Enqueue the scoped CSS and file tab colours for a per-block color scheme, once.
	 *
	 * @since 1.3.0
	 *
	 * @param  string $slug A registered color scheme slug, or empty for none.
	 */
	private static function enqueue_color_scheme( string $slug ): void {
		if ( '' === $slug || Settings::get_color_scheme_slug() === $slug ) {
			return;
		}

		$handle = 'wzcbh-prism-theme-' . $slug;

		if ( wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}

		$file_name = self::get_prism_theme_css_file_name( $slug );

		wp_enqueue_style(
			$handle,
			WZCBH_PLUGIN_URL . 'includes/assets/scoped/' . $file_name,
			array( 'wzcbh-prism-theme' ),
			self::get_file_version( WZCBH_PLUGIN_DIR . 'includes/assets/scoped/' . $file_name )
		);

		$colors = Settings::extract_theme_colors( WZCBH_PLUGIN_DIR . 'includes/assets/' . $file_name );
		$vars   = '--wzcbh-tab-bg: ' . ( '' !== $colors['background'] ? $colors['background'] : Settings::FALLBACK_TAB_BACKGROUND ) . ';';

		if ( '' !== $colors['color'] ) {
			$vars .= ' --wzcbh-tab-color: ' . $colors['color'] . ';';
		}

		wp_add_inline_style( $handle, '.' . Blocks::get_color_scheme_class( $slug ) . ' {' . $vars . '}' );
	}
}
