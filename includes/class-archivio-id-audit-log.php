<?php
/**
 * ArchivioID Audit Log Manager
 *
 * Handles secure logging of PGP signature verification events.
 * Logs only metadata - never private keys or raw signatures.
 *
 * @package ArchivioID
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Audit_Log {

	const TABLE_VERSION = '1.0.0';
	const TABLE_VERSION_KEY = 'archivio_id_audit_log_version';
	const MAX_LOG_AGE_DAYS = 90;

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'archivio_id_audit_log';
	}

	public static function maybe_create_table() {
		$installed = get_option( self::TABLE_VERSION_KEY, '' );
		if ( version_compare( $installed, self::TABLE_VERSION, '<' ) ) {
			self::create_table();
		}
	}

	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id          bigint(20) unsigned NOT NULL,
			post_type        varchar(20)         NOT NULL DEFAULT 'post',
			event_type       varchar(20)         NOT NULL DEFAULT 'verify',
			timestamp_utc    datetime            NOT NULL,
			key_fingerprint  char(40)            NOT NULL DEFAULT '',
			hash_algorithm   varchar(20)         NOT NULL DEFAULT 'sha256',
			signature_status varchar(20)         NOT NULL DEFAULT 'unverified',
			user_id          bigint(20) unsigned NOT NULL DEFAULT 0,
			notes            varchar(512)        NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY timestamp_utc (timestamp_utc),
			KEY signature_status (signature_status),
			KEY event_type (event_type)
		) {$collate};";

		dbDelta( $sql );
		update_option( self::TABLE_VERSION_KEY, self::TABLE_VERSION );
	}

	public static function drop_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		delete_option( self::TABLE_VERSION_KEY );
	}

	/**
	 * Log a signature verification event.
	 *
	 * @param int    $post_id          Post ID
	 * @param string $event_type       Event type: 'upload', 'verify', 'delete'
	 * @param string $key_fingerprint  40-char hex fingerprint (never private key)
	 * @param string $hash_algorithm   Hash algorithm used (sha256, sha512, etc)
	 * @param string $signature_status Status: 'unverified', 'verified', 'invalid', 'error'
	 * @param string $notes            Optional notes (max 512 chars, sanitized)
	 * @return bool Success
	 */
	public static function log_event( $post_id, $event_type, $key_fingerprint, $hash_algorithm, $signature_status, $notes = '' ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$data = array(
			'post_id'          => absint( $post_id ),
			'post_type'        => sanitize_key( $post->post_type ),
			'event_type'       => sanitize_key( $event_type ),
			'timestamp_utc'    => gmdate( 'Y-m-d H:i:s' ),
			'key_fingerprint'  => self::sanitize_fingerprint( $key_fingerprint ),
			'hash_algorithm'   => sanitize_key( $hash_algorithm ),
			'signature_status' => sanitize_key( $signature_status ),
			'user_id'          => get_current_user_id(),
			'notes'            => self::sanitize_notes( $notes ),
		);

		$result = $wpdb->insert(
			self::get_table_name(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Sanitize fingerprint to ensure it's 40 hex chars or empty.
	 * Security: Prevents injection, validates format.
	 */
	private static function sanitize_fingerprint( $fingerprint ) {
		$fingerprint = strtoupper( preg_replace( '/[^A-Fa-f0-9]/', '', $fingerprint ) );
		return strlen( $fingerprint ) === 40 ? $fingerprint : '';
	}

	/**
	 * Sanitize notes field for CSV safety and prevent injection.
	 * Security: Strips formulas, limits length, escapes dangerous chars.
	 */
	private static function sanitize_notes( $notes ) {
		$notes = trim( $notes );
		$notes = substr( $notes, 0, 512 );
		
		if ( preg_match( '/^[=+\-@]/', $notes ) ) {
			$notes = "'" . $notes;
		}
		
		return sanitize_text_field( $notes );
	}

	/**
	 * Get audit log entries with optional filters.
	 *
	 * @param array $args Query arguments
	 * @return array Array of log entries
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'limit'      => 1000,
			'offset'     => 0,
			'post_id'    => null,
			'start_date' => null,
			'end_date'   => null,
			'status'     => null,
			'orderby'    => 'timestamp_utc',
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		if ( $args['post_id'] ) {
			$where[] = 'post_id = %d';
			$where_values[] = absint( $args['post_id'] );
		}

		if ( $args['start_date'] ) {
			$where[] = 'timestamp_utc >= %s';
			$where_values[] = sanitize_text_field( $args['start_date'] );
		}

		if ( $args['end_date'] ) {
			$where[] = 'timestamp_utc <= %s';
			$where_values[] = sanitize_text_field( $args['end_date'] );
		}

		if ( $args['status'] ) {
			$where[] = 'signature_status = %s';
			$where_values[] = sanitize_key( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );
		
		$orderby = in_array( $args['orderby'], array( 'id', 'post_id', 'timestamp_utc' ), true )
			? $args['orderby']
			: 'timestamp_utc';
		
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				array_merge( $where_values, array( $limit, $offset ) )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get total count of log entries.
	 */
	public static function get_log_count( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['post_id'] ) ) {
			$where[] = 'post_id = %d';
			$where_values[] = absint( $args['post_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'signature_status = %s';
			$where_values[] = sanitize_key( $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}",
				$where_values
			);
		} else {
			$sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Delete old log entries.
	 *
	 * @param int $days Delete entries older than this many days
	 * @return int Number of deleted rows
	 */
	public static function delete_old_logs( $days = null ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( $days === null ) {
			$days = self::MAX_LOG_AGE_DAYS;
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE timestamp_utc < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Export logs to CSV format.
	 * Security: All fields are sanitized for CSV injection prevention.
	 *
	 * @param array $args Query arguments
	 * @return string CSV content
	 */
	public static function export_to_csv( $args = array() ) {
		$logs = self::get_logs( $args );

		$csv_data = array();
		
		$csv_data[] = array(
			'ID',
			'Post ID',
			'Post Title',
			'Post Type',
			'Event Type',
			'Timestamp (UTC)',
			'Key Fingerprint',
			'Hash Algorithm',
			'Signature Status',
			'User ID',
			'Username',
			'Notes',
		);

		foreach ( $logs as $log ) {
			$post = get_post( $log->post_id );
			$post_title = $post ? $post->post_title : '[Deleted]';
			
			$user = get_userdata( $log->user_id );
			$username = $user ? $user->user_login : '[Unknown]';

			$csv_data[] = array(
				self::sanitize_csv_field( $log->id ),
				self::sanitize_csv_field( $log->post_id ),
				self::sanitize_csv_field( $post_title ),
				self::sanitize_csv_field( $log->post_type ),
				self::sanitize_csv_field( $log->event_type ),
				self::sanitize_csv_field( $log->timestamp_utc ),
				self::sanitize_csv_field( $log->key_fingerprint ),
				self::sanitize_csv_field( $log->hash_algorithm ),
				self::sanitize_csv_field( $log->signature_status ),
				self::sanitize_csv_field( $log->user_id ),
				self::sanitize_csv_field( $username ),
				self::sanitize_csv_field( $log->notes ),
			);
		}

		return self::array_to_csv( $csv_data );
	}

	/**
	 * Sanitize CSV field to prevent injection attacks.
	 * Security: Prevents formula injection (=, +, -, @, etc.)
	 */
	private static function sanitize_csv_field( $value ) {
		$value = (string) $value;
		
		if ( preg_match( '/^[=+\-@]/', $value ) ) {
			$value = "'" . $value;
		}
		
		return $value;
	}

	/**
	 * Convert array to CSV string.
	 */
	private static function array_to_csv( $data ) {
		$output = fopen( 'php://temp', 'r+' );
		
		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}
		
		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );
		
		return $csv;
	}
}
