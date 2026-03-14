<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WebberZone\Code_Block_Highlighting
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes all per-site options and transients for a single site.
 */
function wz_cbh_uninstall_site() {
	// Main settings.
	delete_option( 'wz_cbh_settings' );

	// Legacy options (pre-1.0).
	delete_option( 'wz-cbh-color-scheme' );
	delete_option( 'wz-cbh-default-lang' );

	// Setup wizard options.
	delete_option( 'wz_cbh_show_wizard' );
	delete_option( 'wz_cbh_wizard_completed' );
	delete_option( 'wz_cbh_wizard_completed_date' );
	delete_option( 'wz_cbh_wizard_current_step' );
	delete_option( 'wz_cbh_wizard_notice_dismissed' );

	// Transients.
	delete_transient( 'wz_cbh_show_wizard_activation_redirect' );
	delete_transient( 'wz_cbh_notice_dismissed_wz_cbh_wizard_notice' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		wz_cbh_uninstall_site();
		restore_current_blog();
	}
} else {
	wz_cbh_uninstall_site();
}

// Per-user dismissed notice meta (global — runs once regardless of multisite).
delete_metadata( 'user', 0, 'wz_cbh_notice_dismissed_wz_cbh_wizard_notice', '', true );
