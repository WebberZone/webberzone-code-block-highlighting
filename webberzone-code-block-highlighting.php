<?php
/**
 * WebberZone Code Block Highlighting.
 *
 * Extends the Gutenberg Code block with syntax highlighting powered by Prism.js.
 *
 * @package   WebberZone\Code_Block_Highlighting
 * @author    Ajay D'Souza
 * @license   GPL-2.0+
 * @link      https://webberzone.com
 * @copyright 2024 Ajay D'Souza
 *
 * @wordpress-plugin
 * Plugin Name: WebberZone Code Block Highlighting
 * Plugin URI:  https://github.com/WebberZone/webberzone-code-block-highlighting
 * Description: Extends the Gutenberg Code block with syntax highlighting powered by Prism.js.
 * Version:     1.0.0
 * Author:      WebberZone
 * Author URI:  https://webberzone.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: webberzone-code-block-highlighting
 * Domain Path: /languages
 */

namespace WebberZone\Code_Block_Highlighting;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Holds the version of WebberZone Code Block Highlighting.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZ_CBH_VERSION' ) ) {
	define( 'WZ_CBH_VERSION', '1.0.0' );
}

/**
 * Holds the full path to the plugin file.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZ_CBH_PLUGIN_FILE' ) ) {
	define( 'WZ_CBH_PLUGIN_FILE', __FILE__ );
}

/**
 * Holds the filesystem directory path (with trailing slash) for WebberZone Code Block Highlighting.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZ_CBH_PLUGIN_DIR' ) ) {
	define( 'WZ_CBH_PLUGIN_DIR', plugin_dir_path( WZ_CBH_PLUGIN_FILE ) );
}

/**
 * Holds the URL (with trailing slash) for WebberZone Code Block Highlighting.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WZ_CBH_PLUGIN_URL' ) ) {
	define( 'WZ_CBH_PLUGIN_URL', plugin_dir_url( WZ_CBH_PLUGIN_FILE ) );
}

// Load custom autoloader.
if ( ! function_exists( __NAMESPACE__ . '\autoload' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/autoloader.php';
}

if ( ! function_exists( __NAMESPACE__ . '\wz_cbh' ) ) {
	/**
	 * Returns the instance of the WebberZone Code Block Highlighting main class.
	 *
	 * @since 1.0.0
	 *
	 * @return \WebberZone\Code_Block_Highlighting\Main
	 */
	function wz_cbh() {
		return \WebberZone\Code_Block_Highlighting\Main::get_instance();
	}
}

if ( ! function_exists( __NAMESPACE__ . '\load' ) ) {
	/**
	 * Loads the plugin.
	 *
	 * @since 1.0.0
	 */
	function load(): void {
		wz_cbh();
	}
	add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );
}
