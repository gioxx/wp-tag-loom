<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'wpto_process_batch' );

if ( ! get_option( 'wpto_cleanup_on_uninstall', true ) ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time uninstall cleanup of the plugin's own custom tables; no core API drops custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpto_suggestions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpto_batches" );
$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => 'wpto_locked' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-time uninstall cleanup of the plugin's own term meta key.
// phpcs:enable

delete_option( 'wpto_api_key' );
delete_option( 'wpto_model' );
delete_option( 'wpto_batch_size' );
delete_option( 'wpto_ai_language' );
delete_option( 'wpto_cleanup_on_uninstall' );
delete_option( 'wpto_db_version' );
