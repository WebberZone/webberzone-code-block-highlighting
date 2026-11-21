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
function wzcbh_uninstall_site() {
	// Main settings.
	delete_option( 'wzcbh_settings' );

	// Legacy options (pre-1.0).
	delete_option( 'wzcbh-color-scheme' );
	delete_option( 'wzcbh-default-lang' );

	// Setup wizard options.
	delete_option( 'wzcbh_show_wizard' );
	delete_option( 'wzcbh_wizard_completed' );
	delete_option( 'wzcbh_wizard_completed_date' );
	delete_option( 'wzcbh_wizard_current_step' );
	delete_option( 'wzcbh_wizard_notice_dismissed' );

	// Transients.
	delete_transient( 'wzcbh_show_wizard_activation_redirect' );
	delete_transient( 'wzcbh_notice_dismissed_wzcbh_wizard_notice' );

	// Language detection and Convert Code Blocks result transients, keyed by hash or user ID.
	global $wpdb;
	foreach ( array( 'wzcbh_lang_', 'wzcbh_migrate_' ) as $prefix ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);
	}
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
		wzcbh_uninstall_site();
		restore_current_blog();
	}
} else {
	wzcbh_uninstall_site();
}

// Per-user dismissed notice meta (global — runs once regardless of multisite).
delete_metadata( 'user', 0, 'wzcbh_notice_dismissed_wzcbh_wizard_notice', '', true );
