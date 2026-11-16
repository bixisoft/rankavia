<?php
/**
 * Removes every option, transient and table the plugin created.
 *
 * Redirects and the 404 log are shared with the standalone "Rankavia Redirects & 404" plugin, so they
 * are only removed when that plugin is not installed; otherwise it keeps managing them.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rankavia_settings' );
delete_option( 'rankavia_version' );
delete_transient( 'rankavia_score_home' );

if ( ! file_exists( WP_PLUGIN_DIR . '/rankavia-redirects-404/rankavia-redirects-404.php' ) ) {
	global $wpdb;
	foreach ( array( 'rankavia_redirects', 'rankavia_404_log' ) as $rankavia_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall of this plugin's own tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$rankavia_table}" );
	}
	foreach ( array( 'rankavia_redirects_db', 'rankavia_redirect_rules', 'rankavia_redirect_rules_backup', 'rankavia_redirects_migration', 'rankavia_404_dropped' ) as $rankavia_option ) {
		delete_option( $rankavia_option );
	}
	wp_clear_scheduled_hook( 'rankavia_redirects_prune' );
}
