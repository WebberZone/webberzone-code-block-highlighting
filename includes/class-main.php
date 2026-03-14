<?php
/**
 * Main plugin class.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

namespace WebberZone\Code_Block_Highlighting;

use WebberZone\Code_Block_Highlighting\Util\Hook_Registry;

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Main {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @var Main
	 */
	private static ?self $instance = null;

	/**
	 * Admin instance.
	 *
	 * @since 1.1.0
	 *
	 * @var Admin\Admin|null
	 */
	public ?Admin\Admin $admin = null;

	/**
	 * Blocks handler.
	 *
	 * @since 1.0.0
	 *
	 * @var Frontend\Blocks
	 */
	public Frontend\Blocks $blocks;

	/**
	 * Styles handler.
	 *
	 * @since 1.0.0
	 *
	 * @var Frontend\Styles_Handler
	 */
	public Frontend\Styles_Handler $styles;

	/**
	 * Gets the instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @return Main
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * A dummy constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Do nothing.
	}

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	private function init(): void {
		require_once WZ_CBH_PLUGIN_DIR . 'includes/options-api.php';

		$this->blocks = new Frontend\Blocks();
		$this->styles = new Frontend\Styles_Handler();

		Hook_Registry::add_action( 'init', array( $this, 'init_admin' ) );
	}

	/**
	 * Initialize admin components.
	 *
	 * @since 1.0.0
	 */
	public function init_admin(): void {
		if ( is_admin() ) {
			$this->admin = new Admin\Admin();
		}
	}
}
