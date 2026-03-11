<?php
/**
 * Settings class.
 *
 * @package WebberZone\Code_Block_Highlighting\Admin
 */

namespace WebberZone\Code_Block_Highlighting\Admin;

use WebberZone\Code_Block_Highlighting\Admin\Settings\Settings_API;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings class.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Available color schemes: slug => label.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public static array $color_schemes = array(
		'prism-a11y-dark'           => 'A11y Dark',
		'prism-atom-dark'           => 'Atom Dark',
		'prism-darcula'             => 'Darcula',
		'prism-dracula'             => 'Dracula',
		'prism-ghcolors'            => 'GitHub (Light)',
		'prism-gruvbox-dark'        => 'Gruvbox Dark',
		'prism-gruvbox-light'       => 'Gruvbox Light',
		'prism-material-dark'       => 'Material Dark',
		'prism-material-oceanic'    => 'Material Oceanic',
		'prism-night-owl'           => 'Night Owl',
		'prism-nord'                => 'Nord',
		'prism-onedark'             => 'One Dark',
		'prism-one-light'           => 'One Light',
		'prism-shades-of-purple'    => 'Shades of Purple',
		'prism-solarized-dark-atom' => 'Solarized Dark',
		'prism-synthwave84'         => 'Synthwave \'84',
		'prism-vs'                  => 'VS (Light)',
		'prism-vsc-dark-plus'       => 'VS Code Dark+',
	);

	/**
	 * Settings prefix.
	 *
	 * @var string
	 */
	public static string $prefix = 'wz_cbh';

	/**
	 * Settings key (option name in the DB).
	 *
	 * @var string
	 */
	public static string $settings_key = 'wz_cbh_settings';

	/**
	 * Menu slug for the settings page.
	 *
	 * @var string
	 */
	public static string $menu_slug = 'wz_cbh_settings';

	/**
	 * Settings API instance.
	 *
	 * @var Settings_API
	 */
	public Settings_API $settings_api;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'initialise_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_language_data' ), 20 );
	}

	/**
	 * Initialise the Settings_API instance.
	 *
	 * @since 1.1.0
	 */
	public function initialise_settings(): void {
		$this->settings_api = new Settings_API(
			self::$settings_key,
			self::$prefix,
			array(
				'translation_strings' => $this->get_translation_strings(),
				'props'               => $this->get_props(),
				'settings_sections'   => $this->get_settings_sections(),
				'registered_settings' => $this->get_registered_settings(),
			)
		);
	}

	/**
	 * Get translation strings for the Settings_API.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	private function get_translation_strings(): array {
		return array(
			'page_header'          => __( 'Code Block Highlighting Settings', 'webberzone-code-block-highlighting' ),
			'reset_message'        => __( 'Settings have been reset to their default values. Reload this page to view the updated settings.', 'webberzone-code-block-highlighting' ),
			'success_message'      => __( 'Settings updated.', 'webberzone-code-block-highlighting' ),
			'save_changes'         => __( 'Save Changes', 'webberzone-code-block-highlighting' ),
			'reset_settings'       => __( 'Reset all settings', 'webberzone-code-block-highlighting' ),
			'reset_button_confirm' => __( 'Do you really want to reset all these settings to their default values?', 'webberzone-code-block-highlighting' ),
			'checkbox_modified'    => __( 'Modified from default setting', 'webberzone-code-block-highlighting' ),
		);
	}

	/**
	 * Get props for the Settings_API.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	private function get_props(): array {
		return array(
			'default_tab'       => 'general',
			'menus'             => array(
				array(
					'type'          => 'options',
					'page_title'    => __( 'Code Block Highlighting Settings', 'webberzone-code-block-highlighting' ),
					'menu_title'    => __( 'Code Block Highlighting', 'webberzone-code-block-highlighting' ),
					'capability'    => 'manage_options',
					'menu_slug'     => self::$menu_slug,
					'settings_page' => true,
				),
			),
			'admin_footer_text' => sprintf(
				/* translators: %s: plugin name */
				__( 'Thank you for using %s!', 'webberzone-code-block-highlighting' ),
				'<a href="https://webberzone.com/plugins/code-block-highlighting/" target="_blank">Code Block Highlighting</a>'
			),
		);
	}

	/**
	 * Get settings sections (tabs).
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public static function get_settings_sections(): array {
		return array(
			'general' => __( 'General', 'webberzone-code-block-highlighting' ),
		);
	}

	/**
	 * Get registered settings.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public static function get_registered_settings(): array {
		return array(
			'general' => array(
				array(
					'id'      => 'color-scheme',
					'name'    => __( 'Color Scheme', 'webberzone-code-block-highlighting' ),
					'desc'    => __( 'Select the syntax highlighting color scheme applied to all code blocks.', 'webberzone-code-block-highlighting' ),
					'type'    => 'select',
					'default' => 'prism-a11y-dark',
					'options' => self::$color_schemes,
				),
				array(
					'id'               => 'default-lang',
					'name'             => __( 'Default Language', 'webberzone-code-block-highlighting' ),
					'desc'             => __( 'Automatically set this language when a code block is inserted. Leave blank to disable.', 'webberzone-code-block-highlighting' ),
					'type'             => 'csv',
					'default'          => '',
					'field_class'      => 'ts_autocomplete',
					'field_attributes' => self::get_language_field_attributes(),
				),
			),
		);
	}

	/**
	 * Get default settings values derived from registered settings.
	 *
	 * @since 1.1.0
	 *
	 * @return array Default settings.
	 */
	public static function settings_defaults(): array {
		$defaults      = array();
		$default_types = array( 'color', 'css', 'csv', 'file', 'html', 'multicheck', 'number', 'numbercsv', 'password', 'postids', 'posttypes', 'radio', 'radiodesc', 'repeater', 'select', 'sensitive', 'taxonomies', 'text', 'textarea', 'thumbsizes', 'url', 'wysiwyg' );

		foreach ( self::get_registered_settings() as $section_settings ) {
			foreach ( $section_settings as $setting ) {
				if ( ! isset( $setting['id'] ) ) {
					continue;
				}
				$type  = $setting['type'] ?? '';
				$value = '';
				if ( 'checkbox' === $type ) {
					$value = isset( $setting['default'] ) ? (int) (bool) $setting['default'] : 0;
				} elseif ( isset( $setting['default'] ) && in_array( $type, $default_types, true ) ) {
					$value = $setting['default'];
				}
				$defaults[ $setting['id'] ] = $value;
			}
		}

		return apply_filters( self::$prefix . '_settings_defaults', $defaults ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * Get field attributes for the Tom Select language picker.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public static function get_language_field_attributes(): array {
		return array(
			'data-wp-prefix'   => strtoupper( self::$prefix ),
			'data-wp-endpoint' => 'prism_languages',
			'data-ts-config'   => wp_json_encode(
				array(
					'maxItems' => 1,
					'plugins'  => array( 'dropdown_input', 'clear_button' ),
					'render'   => (object) array(),
				)
			),
		);
	}

	/**
	 * Inject Prism language list into WZTomSelectSettings for the settings page autocomplete.
	 *
	 * @since 1.1.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_language_data( string $hook ): void {
		if ( ! isset( $this->settings_api ) || $hook !== $this->settings_api->settings_page ) {
			return;
		}

		$languages = \WebberZone\Code_Block_Highlighting\Frontend\Blocks::get_languages();
		$options   = array();

		foreach ( $languages as $slug => $label ) {
			$options[] = array(
				'id'   => $slug,
				'name' => $label,
			);
		}

		wp_add_inline_script(
			'wz-' . self::$prefix . '-tom-select-init',
			'window.WZTomSelectSettings = window.WZTomSelectSettings || {}; window.WZTomSelectSettings.prism_languages = ' . wp_json_encode( $options ) . ';',
			'before'
		);
	}

	/**
	 * Get the URL (or filesystem path) to the active color scheme CSS file.
	 *
	 * Falls back to the default A11y Dark theme if the chosen file does not exist.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $return_path When true, returns the filesystem path instead of the URL.
	 * @return string
	 */
	public static function get_color_scheme_css( bool $return_path = false ): string {
		$option   = wz_cbh_get_option( 'color-scheme', 'prism-a11y-dark' );
		$rel_path = "includes/assets/{$option}.css";

		if ( ! file_exists( WZ_CBH_PLUGIN_DIR . $rel_path ) ) {
			$rel_path = 'includes/assets/prism-a11y-dark.css';
		}

		if ( $return_path ) {
			return WZ_CBH_PLUGIN_DIR . $rel_path;
		}

		/**
		 * Filter the URL of the syntax highlighting color scheme CSS.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Absolute URL of the CSS file to enqueue.
		 */
		return apply_filters( 'wz_cbh_color_scheme_css_url', WZ_CBH_PLUGIN_URL . $rel_path );
	}
}
