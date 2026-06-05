<?php
/**
 * Styles Handler class.
 *
 * Enqueues Prism.js assets on the frontend, only when code blocks are present.
 *
 * @package WebberZone\Code_Block_Highlighting\Frontend
 */

namespace WebberZone\Code_Block_Highlighting\Frontend;

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

		if ( ! $force_load ) {
			global $posts;

			if ( empty( $posts ) ) {
				return;
			}

			$has_code_block = array_reduce(
				$posts,
				static function ( bool $carry, \WP_Post $post ): bool {
					return $carry || has_block( 'core/code', $post );
				},
				false
			);

			if ( ! $has_code_block ) {
				return;
			}
		}

		$mode = wzcbh_get_option( 'highlighting-mode', 'client' );

		if ( 'server' === $mode ) {
			$this->enqueue_server_mode_assets();
		} else {
			$this->enqueue_client_mode_assets();
		}
	}

	/**
	 * Enqueue assets for client-side (Prism.js) mode.
	 *
	 * @since 1.2.0
	 */
	private function enqueue_client_mode_assets(): void {
		$asset_file = WZCBH_PLUGIN_DIR . 'includes/blocks/build/frontend.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

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
			WZCBH_PLUGIN_URL . 'includes/blocks/build/frontend.css',
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
		);

		wp_add_inline_script(
			'wzcbh-prism-js',
			'const cbhSettings = ' . wp_json_encode( $settings ) . ';',
			'before'
		);

		$font_size = (int) wzcbh_get_option( 'font-size', 0 );
		if ( $font_size > 0 ) {
			wp_add_inline_style(
				'wzcbh-prism-css',
				':root { --wzcbh-font-size: ' . $font_size . 'px; }'
			);
		}
	}

	/**
	 * Enqueue assets for server-side (highlight.php) mode.
	 *
	 * @since 1.2.0
	 */
	private function enqueue_server_mode_assets(): void {
		$asset_file = WZCBH_PLUGIN_DIR . 'includes/blocks/build/frontend.asset.php';
		$min        = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$rtl_part   = is_rtl() ? '-rtl' : '';

		// Prism frontend.css: toolbar CSS, line-numbers CSS, CSS variables.
		// Loaded first so hljs theme can override token color rules without conflict.
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			wp_enqueue_style(
				'wzcbh-prism-css',
				WZCBH_PLUGIN_URL . 'includes/blocks/build/frontend' . $rtl_part . '.css',
				array(),
				$asset['version']
			);
		}

		// hljs theme CSS (token colors).
		wp_enqueue_style(
			'wzcbh-hljs-theme',
			self::get_hljs_theme_css_url(),
			array( 'wzcbh-prism-css' ),
			self::get_hljs_color_scheme_version()
		);

		// hljs-specific structural overrides (line-highlight, pre.hljs adjustments).
		wp_enqueue_style(
			'wzcbh-hljs-server',
			WZCBH_PLUGIN_URL . "includes/assets/hljs-server-mode{$rtl_part}{$min}.css",
			array( 'wzcbh-hljs-theme' ),
			WZCBH_VERSION
		);

		$font_size = (int) wzcbh_get_option( 'font-size', 0 );
		if ( $font_size > 0 ) {
			wp_add_inline_style(
				'wzcbh-hljs-server',
				':root { --wzcbh-font-size: ' . $font_size . 'px; }'
			);
		}

		// Copy-to-clipboard + expand/collapse script.
		wp_enqueue_script(
			'wzcbh-hljs-clipboard',
			WZCBH_PLUGIN_URL . "includes/assets/hljs-clipboard{$min}.js",
			array(),
			WZCBH_VERSION,
			true
		);
	}

	/**
	 * Build the Prism theme CSS file name, respecting SCRIPT_DEBUG and is_rtl().
	 *
	 * @since 1.0.0
	 *
	 * @return string File name relative to includes/assets/.
	 */
	private static function get_prism_theme_css_file_name(): string {
		$option   = wzcbh_get_option( 'color-scheme', 'prism-onedark' );
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
		$path = self::get_prism_theme_css_path();

		return file_exists( $path ) ? (string) filemtime( $path ) : WZCBH_VERSION;
	}

	/**
	 * Build the hljs theme CSS file name, respecting SCRIPT_DEBUG and is_rtl().
	 *
	 * @since 1.2.0
	 *
	 * @return string File name relative to includes/assets/.
	 */
	private static function get_hljs_theme_css_file_name(): string {
		$prism_slug = wzcbh_get_option( 'color-scheme', 'prism-onedark' );
		$option     = \WebberZone\Code_Block_Highlighting\Admin\Settings::get_prism_to_hljs_theme( $prism_slug );
		$min        = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$rtl_part   = is_rtl() ? '-rtl' : '';

		return "{$option}{$rtl_part}{$min}.css";
	}

	/**
	 * Get the URL for the active hljs theme CSS.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private static function get_hljs_theme_css_url(): string {
		return WZCBH_PLUGIN_URL . 'includes/assets/' . self::get_hljs_theme_css_file_name();
	}

	/**
	 * Get the filesystem path for the active hljs theme CSS.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private static function get_hljs_theme_css_path(): string {
		return WZCBH_PLUGIN_DIR . 'includes/assets/' . self::get_hljs_theme_css_file_name();
	}

	/**
	 * Get a version string for the active hljs color scheme CSS for cache busting.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private static function get_hljs_color_scheme_version(): string {
		$path = self::get_hljs_theme_css_path();

		return file_exists( $path ) ? (string) filemtime( $path ) : WZCBH_VERSION;
	}
}
