<?php
/**
 * ArchivioID Database Layer
 *
 * Tables:
 *   {prefix}archivio_id_keys        – armored OpenPGP public keys
 *   {prefix}archivio_id_signatures  – per-post detached signature state
 *
 * @package ArchivioID
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_DB {

	const SCHEMA_VERSION    = '1.0.0';
	const SCHEMA_OPTION_KEY = 'archivio_id_db_version';

	public static function keys_table() {
		global $wpdb;
		return $wpdb->prefix . 'archivio_id_keys';
	}

	public static function signatures_table() {
		global $wpdb;
		return $wpdb->prefix . 'archivio_id_signatures';
	}

	/**
	 * Lightweight guard — runs on every request, skips if schema is current.
	 */
	public static function maybe_create_tables() {
		$installed = get_option( self::SCHEMA_OPTION_KEY, '' );
		if ( version_compare( $installed, self::SCHEMA_VERSION, '<' ) ) {
			self::create_tables();
		}
	}

	/**
	 * Create / upgrade tables via dbDelta().
	 */
	public static function create_tables() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// archivio_id_keys
		// fingerprint = 40-char uppercase hex v4 OpenPGP fingerprint (UNIQUE)
		// key_id      = last 16 hex chars of fingerprint (long key ID)
		$keys_sql = "CREATE TABLE " . self::keys_table() . " (
			id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label        varchar(255)        NOT NULL DEFAULT '',
			fingerprint  char(40)            NOT NULL DEFAULT '',
			key_id       char(16)            NOT NULL DEFAULT '',
			armored_key  longtext            NOT NULL,
			created_at   datetime            NOT NULL,
			added_by     bigint(20) unsigned NOT NULL DEFAULT 0,
			is_active    tinyint(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY   fingerprint (fingerprint),
			KEY          key_id (key_id),
			KEY          is_active (is_active)
		) {$collate};";

		// archivio_id_signatures
		// One row per post_id (UNIQUE KEY enforces this).
		// Re-upload and re-verify update the existing row in-place.
		$sigs_sql = "CREATE TABLE " . self::signatures_table() . " (
			id             bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id        bigint(20) unsigned NOT NULL,
			key_id         bigint(20) unsigned NOT NULL DEFAULT 0,
			archivio_hash  varchar(255)        NOT NULL DEFAULT '',
			hash_algorithm varchar(20)         NOT NULL DEFAULT 'sha256',
			hash_mode      varchar(10)         NOT NULL DEFAULT 'standard',
			signature_asc  text                NOT NULL,
			status         varchar(20)         NOT NULL DEFAULT 'uploaded',
			verified_at    datetime                     DEFAULT NULL,
			failure_reason varchar(512)        NOT NULL DEFAULT '',
			uploaded_at    datetime            NOT NULL,
			uploaded_by    bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY   post_id (post_id),
			KEY          key_id (key_id),
			KEY          status (status)
		) {$collate};";

		dbDelta( $keys_sql );
		dbDelta( $sigs_sql );

		update_option( self::SCHEMA_OPTION_KEY, self::SCHEMA_VERSION );
	}

	/**
	 * Hard-drop all plugin tables. Uninstall only.
	 */
	public static function drop_tables() {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::signatures_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::keys_table() );
		// phpcs:enable
		delete_option( self::SCHEMA_OPTION_KEY );
	}

	public static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;
	}
}
