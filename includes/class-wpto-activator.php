<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Activator {

	const DB_VERSION = '3';

	public static function activate() {
		self::create_tables();

		add_option( 'wpto_model', '' );
		add_option( 'wpto_batch_size', 150 );
		add_option( 'wpto_ai_language', '' );
		add_option( 'wpto_cleanup_on_uninstall', 1 );
		update_option( 'wpto_db_version', self::DB_VERSION );
	}

	/**
	 * Re-runs dbDelta on plugins_loaded when the schema version changed,
	 * so already-active installs pick up new columns without reactivation.
	 * Also drops the obsolete wpto_api_key option: credentials now live in
	 * WordPress core's AI Client, not in this plugin.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'wpto_db_version' ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		delete_option( 'wpto_api_key' );
		update_option( 'wpto_db_version', self::DB_VERSION );
	}

	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$batches_table = $wpdb->prefix . 'wpto_batches';
		$sql_batches   = "CREATE TABLE {$batches_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_ids LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		$suggestions_table = $wpdb->prefix . 'wpto_suggestions';
		$sql_suggestions   = "CREATE TABLE {$suggestions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(30) NOT NULL,
			source_term_ids LONGTEXT NOT NULL,
			target_term_id BIGINT UNSIGNED NULL,
			reason TEXT NULL,
			confidence FLOAT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			applied_at DATETIME NULL,
			source_names TEXT NULL,
			target_name VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY status (status),
			KEY type (type)
		) {$charset_collate};";

		dbDelta( $sql_batches );
		dbDelta( $sql_suggestions );
	}
}
