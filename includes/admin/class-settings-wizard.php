<?php
/**
 * Settings Wizard for Code Block Highlighting.
 *
 * Provides a guided setup experience for new users.
 *
 * @since 1.1.0
 *
 * @package WebberZone\Code_Block_Highlighting\Admin
 */

namespace WebberZone\Code_Block_Highlighting\Admin;

use WebberZone\Code_Block_Highlighting\Util\Hook_Registry;
use WebberZone\Code_Block_Highlighting\Admin\Settings\Settings_Wizard_API;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings Wizard class for Code Block Highlighting.
 *
 * @since 1.1.0
 */
class Settings_Wizard extends Settings_Wizard_API {

	/**
	 * Main constructor class.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$settings_key = 'wzcbh_settings';
		$prefix       = 'wzcbh';

		$args = array(
			'steps'               => $this->get_wizard_steps(),
			'translation_strings' => $this->get_translation_strings(),
			'page_slug'           => 'wzcbh_wizard',
			'menu_args'           => array(
				'parent'     => 'wzcbh_settings',
				'capability' => 'manage_options',
			),
		);

		parent::__construct( $settings_key, $prefix, $args );

		$this->additional_hooks();
	}

	/**
	 * Additional hooks specific to Code Block Highlighting.
	 *
	 * @since 1.1.0
	 */
	protected function additional_hooks() {
		Hook_Registry::add_action( 'admin_init', array( $this, 'register_wizard_notice' ) );
	}

	/**
	 * Get wizard steps configuration.
	 *
	 * @since 1.1.0
	 *
	 * @return array Wizard steps.
	 */
	public function get_wizard_steps() {
		$color_schemes = Settings::$color_schemes;

		$steps = array(
			'welcome'      => array(
				'title'       => __( 'Welcome to Code Block Highlighting', 'webberzone-code-block-highlighting' ),
				'description' => __( 'Thank you for installing Code Block Highlighting! This wizard will help you configure the essential settings to get your code blocks looking great.', 'webberzone-code-block-highlighting' ),
				'settings'    => array(),
			),
			'color_scheme' => array(
				'title'       => __( 'Color Scheme', 'webberzone-code-block-highlighting' ),
				'description' => __( 'Choose the syntax highlighting color scheme applied to all code blocks on your site.', 'webberzone-code-block-highlighting' ),
				'settings'    => array(
					'color-scheme' => array(
						'id'      => 'color-scheme',
						'name'    => __( 'Color Scheme', 'webberzone-code-block-highlighting' ),
						'desc'    => __( 'Select the Prism.js theme for syntax highlighting.', 'webberzone-code-block-highlighting' ),
						'type'    => 'select',
						'default' => 'prism-a11y-dark',
						'options' => $color_schemes,
					),
				),
			),
			'default_lang' => array(
				'title'       => __( 'Default Language', 'webberzone-code-block-highlighting' ),
				'description' => __( 'Optionally set a default programming language to apply automatically when a new code block is inserted.', 'webberzone-code-block-highlighting' ),
				'settings'    => array(
					'default-lang' => array(
						'id'          => 'default-lang',
						'name'        => __( 'Default Language', 'webberzone-code-block-highlighting' ),
						'desc'        => sprintf(
							/* translators: %s: link to Prism supported languages list */
							__( 'Use a language alias from the %s. Leave blank to disable.', 'webberzone-code-block-highlighting' ),
							'<a href="https://prismjs.com/#supported-languages" target="_blank" rel="noopener noreferrer">' . __( 'supported languages list', 'webberzone-code-block-highlighting' ) . '</a>'
						),
						'type'        => 'text',
						'default'     => '',
						'placeholder' => 'javascript',
					),
				),
			),
		);

		/**
		 * Filter wizard steps.
		 *
		 * @param array $steps Wizard steps.
		 */
		return apply_filters( 'wzcbh_wizard_steps', $steps ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Get translation strings for the wizard.
	 *
	 * @since 1.1.0
	 *
	 * @return array Translation strings.
	 */
	public function get_translation_strings() {
		return array(
			'page_title'           => __( 'Code Block Highlighting Setup Wizard', 'webberzone-code-block-highlighting' ),
			'menu_title'           => __( 'Setup Wizard', 'webberzone-code-block-highlighting' ),
			'wizard_title'         => __( 'Code Block Highlighting Setup Wizard', 'webberzone-code-block-highlighting' ),
			'next_step'            => __( 'Next Step', 'webberzone-code-block-highlighting' ),
			'previous_step'        => __( 'Previous Step', 'webberzone-code-block-highlighting' ),
			'finish_setup'         => __( 'Finish Setup', 'webberzone-code-block-highlighting' ),
			'skip_wizard'          => __( 'Skip Wizard', 'webberzone-code-block-highlighting' ),
			'steps_nav_aria_label' => __( 'Setup Wizard Steps', 'webberzone-code-block-highlighting' ),
			/* translators: %1$d: Current step number, %2$d: Total number of steps */
			'step_of'              => __( 'Step %1$d of %2$d', 'webberzone-code-block-highlighting' ),
			'wizard_complete'      => __( 'Setup Complete!', 'webberzone-code-block-highlighting' ),
			'setup_complete'       => __( 'Your Code Block Highlighting plugin has been configured successfully!', 'webberzone-code-block-highlighting' ),
			'go_to_settings'       => __( 'Go to Settings', 'webberzone-code-block-highlighting' ),
		);
	}

	/**
	 * Register the wizard notice with the Admin_Notices_API.
	 *
	 * @since 1.1.0
	 */
	public function register_wizard_notice() {
		$wzcbh = \WebberZone\Code_Block_Highlighting\Main::get_instance();
		if ( ! isset( $wzcbh->admin->admin_notices_api ) ) {
			return;
		}

		$wzcbh->admin->admin_notices_api->register_notice(
			array(
				'id'          => 'wzcbh_wizard_notice',
				'message'     => sprintf(
					'<p>%s</p><p><a href="%s" class="button button-primary">%s</a></p>',
					esc_html__( 'Welcome to Code Block Highlighting! Would you like to run the setup wizard to configure the plugin?', 'webberzone-code-block-highlighting' ),
					esc_url( admin_url( 'admin.php?page=wzcbh_wizard' ) ),
					esc_html__( 'Run Setup Wizard', 'webberzone-code-block-highlighting' )
				),
				'type'        => 'info',
				'dismissible' => true,
				'capability'  => 'manage_options',
				'conditions'  => array(
					function () {
						$page = sanitize_key( (string) filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

						return ! $this->is_wizard_completed() &&
							! get_option( 'wzcbh_wizard_notice_dismissed', false ) &&
							( get_transient( 'wzcbh_show_wizard_activation_redirect' ) || get_option( 'wzcbh_show_wizard', false ) ) &&
							'wzcbh_wizard' !== $page;
					},
				),
			)
		);
	}

	/**
	 * Get the URL to redirect to after wizard completion.
	 *
	 * @since 1.1.0
	 *
	 * @return string Redirect URL.
	 */
	protected function get_completion_redirect_url() {
		return admin_url( 'admin.php?page=wzcbh_settings' );
	}
}
