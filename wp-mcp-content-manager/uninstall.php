<?php
/**
 * Cleanup on uninstall: remove all plugin options.
 *
 * @package WPMCP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpmcp_api_key' );
delete_option( 'wpmcp_api_key_hint' );
delete_option( 'wpmcp_settings' );
delete_option( 'wpmcp_audit_log' );
delete_option( 'wpmcp_oauth_clients' );
delete_option( 'wpmcp_redirects' );
delete_transient( 'wpmcp_api_key_reveal' );

// Best-effort removal of OAuth code/token transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wpmcp\_oauth\_%' OR option_name LIKE '\_transient\_timeout\_wpmcp\_oauth\_%' OR option_name LIKE '\_transient\_wpmcp\_rl\_%' OR option_name LIKE '\_transient\_timeout\_wpmcp\_rl\_%'" );
