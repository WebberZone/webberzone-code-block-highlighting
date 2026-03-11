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

delete_option( 'wz-cbh-color-scheme' );
delete_option( 'wz-cbh-default-lang' );
