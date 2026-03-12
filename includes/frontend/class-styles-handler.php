<?php
/**
 * Styles Handler class.
 *
 * Enqueues Prism.js assets on the frontend, only when code blocks are present.
 *
 * @package WebberZone\Code_Block_Highlighting\Frontend
 */

namespace WebberZone\Code_Block_Highlighting\Frontend;

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue Prism.js script and theme CSS on the frontend.
	 *
	 * Assets are only loaded when at least one core/code block is present on
	 * the current page, unless the wz_cbh_force_load_assets filter returns true.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets(): void {
		/**
		 * Filter to force-load Prism assets regardless of whether code blocks are detected.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $force_load Whether to force-load assets. Default false.
		 */
		$force_load = apply_filters( 'wz_cbh_force_load_assets', false );

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

		$asset_file = WZ_CBH_PLUGIN_DIR . 'includes/blocks/build/frontend.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Color scheme theme CSS (copied from prism-themes via npm run build:prism).
		wp_enqueue_style(
			'wz-cbh-prism-theme',
			\WebberZone\Code_Block_Highlighting\Admin\Settings::get_color_scheme_css(),
			array(),
			self::get_color_scheme_version()
		);

		// Line-numbers plugin CSS (extracted from src/frontend.js by webpack).
		wp_enqueue_style(
			'wz-cbh-prism-css',
			WZ_CBH_PLUGIN_URL . 'includes/blocks/build/frontend.css',
			array(),
			$asset['version']
		);

		// Prism core + all language grammars + plugins, bundled by webpack.
		wp_enqueue_script(
			'wz-cbh-prism-js',
			WZ_CBH_PLUGIN_URL . 'includes/blocks/build/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$settings = array(
			'copyToClipboard' => (bool) wz_cbh_get_option( 'copy-to-clipboard', true ),
		);

		wp_add_inline_script(
			'wz-cbh-prism-js',
			'const cbhSettings = ' . wp_json_encode( $settings ) . ';',
			'before'
		);
	}

	/**
	 * Get a version string for the active color scheme CSS for cache busting.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function get_color_scheme_version(): string {
		$path = \WebberZone\Code_Block_Highlighting\Admin\Settings::get_color_scheme_css( true );

		return file_exists( $path ) ? (string) filemtime( $path ) : WZ_CBH_VERSION;
	}
}
