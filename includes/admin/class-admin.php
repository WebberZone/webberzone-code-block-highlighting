<?php
/**
 * Admin class.
 *
 * @package WebberZone\Code_Block_Highlighting\Admin
 */

namespace WebberZone\Code_Block_Highlighting\Admin;

use WebberZone\Code_Block_Highlighting\Util\Hook_Registry;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin class.
 *
 * @since 1.1.0
 */
class Admin {

	/**
	 * Settings instance.
	 *
	 * @since 1.1.0
	 *
	 * @var Settings
	 */
	public Settings $settings;

	/**
	 * Admin notices API.
	 *
	 * @since 1.1.0
	 *
	 * @var Admin_Notices_API
	 */
	public Admin_Notices_API $admin_notices_api;

	/**
	 * Settings wizard.
	 *
	 * @since 1.1.0
	 *
	 * @var Settings_Wizard
	 */
	public Settings_Wizard $settings_wizard;

	/**
	 * Admin banner.
	 *
	 * @since 1.1.0
	 *
	 * @var Admin_Banner
	 */
	public Admin_Banner $admin_banner;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->settings          = new Settings();
		$this->admin_notices_api = new Admin_Notices_API();
		$this->settings_wizard   = new Settings_Wizard();
		$this->admin_banner      = new Admin_Banner( $this->get_admin_banner_config() );

		add_filter( 'plugin_action_links_' . plugin_basename( WZ_CBH_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add action links to the plugin listing page.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=wz_cbh_settings' ) ),
			esc_html__( 'Settings', 'webberzone-code-block-highlighting' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Get the configuration array for the admin banner.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_admin_banner_config(): array {
		return array(
			'capability' => 'manage_options',
			'prefix'     => 'wz-cbh',
			'screen_ids' => array(
				'settings_page_wz_cbh_settings',
			),
			'page_slugs' => array(
				'wz_cbh_settings',
			),
			'strings'    => array(
				'region_label' => esc_html__( 'Code Block Highlighting quick links', 'webberzone-code-block-highlighting' ),
				'nav_label'    => esc_html__( 'Code Block Highlighting admin shortcuts', 'webberzone-code-block-highlighting' ),
				'eyebrow'      => esc_html__( 'WebberZone Code Block Highlighting', 'webberzone-code-block-highlighting' ),
				'title'        => esc_html__( 'Beautiful syntax highlighting for your code blocks.', 'webberzone-code-block-highlighting' ),
				'text'         => esc_html__( 'Configure your Prism.js theme and default language settings, or explore more WebberZone plugins.', 'webberzone-code-block-highlighting' ),
			),
			'sections'   => array(
				'settings' => array(
					'label'      => esc_html__( 'Settings', 'webberzone-code-block-highlighting' ),
					'url'        => admin_url( 'admin.php?page=wz_cbh_settings' ),
					'screen_ids' => array( 'settings_page_wz_cbh_settings' ),
					'page_slugs' => array( 'wz_cbh_settings' ),
				),
				'plugins'  => array(
					'label'  => esc_html__( 'WebberZone Plugins', 'webberzone-code-block-highlighting' ),
					'url'    => 'https://webberzone.com/plugins/',
					'type'   => 'secondary',
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			),
		);
	}
}
