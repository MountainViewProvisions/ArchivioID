<?php
/**
 * ArchivioID Browser Signature Database Layer
 *
 * Manages the wp_archivioid_browser_sigs table used for signatures
 * created in-browser via OpenPGP.js.
 *
 * @package ArchivioID
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Browser_Sig_DB {

	const SCHEMA_VERSION    = '1.0.0';
	const SCHEMA_OPTION_KEY = 'archivio_id_browser_sig_db_version';

	/**
	 * Return the fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'archivioid_browser_sigs';
	}

	/**
	 * Lightweight guard — runs on every request, skips if schema is current.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		$installed = get_option( self::SCHEMA_OPTION_KEY, '' );
		if ( version_compare( $installed, self::SCHEMA_VERSION, '<' ) ) {
			self::create_table();
		}
	}

	/**
	 * Create / upgrade the table via dbDelta().
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE " . self::table() . " (
			id                     bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			hex_hash               char(64)            DEFAULT NULL,
			post_id                bigint(20) unsigned DEFAULT NULL,
			signature_blob         longtext            NOT NULL,
			public_key_fingerprint char(40)            NOT NULL,
			verified               tinyint(1)          NOT NULL DEFAULT 0,
			verified_at            datetime            NOT NULL,
			user_id                bigint(20) unsigned NOT NULL DEFAULT 0,
			error_message          text,
			PRIMARY KEY  (id),
			KEY hex_hash (hex_hash(16)),
			KEY post_id  (post_id),
			KEY user_id  (user_id)
		) {$collate};";

		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION_KEY, self::SCHEMA_VERSION );
	}

	/**
	 * Drop the table (uninstall only).
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() );
		// phpcs:enable
		delete_option( self::SCHEMA_OPTION_KEY );
	}

	/**
	 * Insert a new browser-signature record.
	 *
	 * @param array $data {
	 *   @type string|null $hex_hash               SHA-256 hex string (64 chars) or null.
	 *   @type int|null    $post_id                WordPress post ID or null.
	 *   @type string      $signature_blob         ASCII-armored detached signature.
	 *   @type string      $public_key_fingerprint 40-char uppercase hex fingerprint.
	 *   @type bool        $verified               Whether server-side verification passed.
	 *   @type string      $error_message          Error detail when not verified.
	 * }
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$row = array(
			'hex_hash'               => isset( $data['hex_hash'] )    ? sanitize_text_field( $data['hex_hash'] )    : null,
			'post_id'                => isset( $data['post_id'] )     ? absint( $data['post_id'] )                  : null,
			'signature_blob'         => $data['signature_blob'],
			'public_key_fingerprint' => strtoupper( sanitize_text_field( $data['public_key_fingerprint'] ) ),
			'verified'               => empty( $data['verified'] ) ? 0 : 1,
			'verified_at'            => current_time( 'mysql' ),
			'user_id'                => get_current_user_id(),
			'error_message'          => isset( $data['error_message'] ) ? sanitize_text_field( $data['error_message'] ) : null,
		);

		$format = array( '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s' );

		$result = $wpdb->insert( self::table(), $row, $format );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Fetch paginated rows for the admin log.
	 *
	 * @param int $per_page
	 * @param int $page      1-based page number.
	 * @return array
	 */
	public static function get_rows( $per_page = 20, $page = 1 ) {
		global $wpdb;
		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' ORDER BY verified_at DESC LIMIT %d OFFSET %d',
				$per_page,
				$offset
			)
		);
		// phpcs:enable
	}

	/**
	 * Total row count for pagination.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
		// phpcs:enable
	}
}
