<?php
/**
 * ArchivioID Signature Store
 *
 * CRUD wrapper for {prefix}archivio_id_signatures.
 * One row per post; re-upload replaces the existing row.
 *
 * UPDATED v1.1.1:
 * - Added cache invalidation after database updates
 * - Enhanced debug logging for troubleshooting
 * - Improved error handling and return values
 *
 * @package ArchivioID
 * @since   1.0.0
 * @version 1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Signature_Store {

	/**
	 * Status constants — mirrors the `status` column constraint.
	 */
	const STATUS_UPLOADED = 'uploaded';
	const STATUS_VERIFIED = 'verified';
	const STATUS_INVALID  = 'invalid';
	const STATUS_ERROR    = 'error';

	// ── Read ──────────────────────────────────────────────────────────────────

	/**
	 * Retrieve the signature record for a post.
	 *
	 * @param  int        $post_id
	 * @return object|null  DB row or null.
	 */
	public static function get( $post_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ArchivioID_DB::signatures_table() . ' WHERE post_id = %d LIMIT 1',
				absint( $post_id )
			)
		);
	}

	/**
	 * Return just the human-readable status string for a post,
	 * or 'not_signed' if no row exists.
	 *
	 * @param  int    $post_id
	 * @return string
	 */
	public static function get_status( $post_id ) {
		$row = self::get( $post_id );
		return $row ? $row->status : 'not_signed';
	}

	// ── Upsert on upload ──────────────────────────────────────────────────────

	/**
	 * Store or replace a detached signature for a post.
	 *
	 * Called immediately after the user uploads an .asc file.
	 * Sets status to 'uploaded' and clears any previous verification result.
	 *
	 * UPDATED v1.1.1: Now includes cache invalidation after database update.
	 *
	 * @param  int    $post_id
	 * @param  int    $key_db_id       Row ID in archivio_id_keys.
	 * @param  string $signature_asc   Raw armored detached signature.
	 * @param  string $archivio_hash   Packed hash from _archivio_post_hash meta.
	 * @param  string $hash_algorithm  Algorithm extracted from packed hash.
	 * @param  string $hash_mode       'standard' | 'hmac'.
	 * @param  int    $uploaded_by     WP user ID.
	 * @return bool
	 */
	public static function upsert_upload(
		$post_id,
		$key_db_id,
		$signature_asc,
		$archivio_hash,
		$hash_algorithm,
		$hash_mode,
		$uploaded_by
	) {
		global $wpdb;
		$table = ArchivioID_DB::signatures_table();

		$data = array(
			'post_id'        => absint( $post_id ),
			'key_id'         => absint( $key_db_id ),
			'archivio_hash'  => sanitize_text_field( $archivio_hash ),
			'hash_algorithm' => sanitize_key( $hash_algorithm ),
			'hash_mode'      => sanitize_key( $hash_mode ),
			'signature_asc'  => sanitize_textarea_field( wp_unslash( $signature_asc ) ),
			'status'         => self::STATUS_UPLOADED,
			'verified_at'    => null,
			'failure_reason' => '',
			'uploaded_at'    => current_time( 'mysql', true ),
			'uploaded_by'    => absint( $uploaded_by ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		// Check for existing row.
		$existing_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d LIMIT 1", absint( $post_id ) )
		);

		if ( $existing_id ) {
			$result = $wpdb->update(
				$table,
				$data,
				array( 'post_id' => absint( $post_id ) ),
				$formats,
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert( $table, $data, $formats );
		}

		if ( false === $result ) {
			archivio_id_log( 'Signature upsert failed for post ' . $post_id . ' - Check database server error log for details' );
			return false;
		}

		// ══════════════════════════════════════════════════════════════════════
		// CACHE INVALIDATION: Clear cache after upload
		// ══════════════════════════════════════════════════════════════════════
		// This ensures subsequent queries return fresh data, particularly important
		// for sites using object caching (Redis, Memcached).
		// ══════════════════════════════════════════════════════════════════════
		wp_cache_delete( $post_id, 'archivio_id_signatures' );
		
		return true;
	}

	// ── Update verification result ────────────────────────────────────────────

	/**
	 * Record the outcome of a verification attempt.
	 *
	 * UPDATED v1.1.1:
	 * - Added cache invalidation after database update
	 * - Enhanced debug logging
	 * - Improved return value validation
	 *
	 * @param  int    $post_id
	 * @param  string $status          One of STATUS_* constants.
	 * @param  string $failure_reason  Empty string on success.
	 * @return bool
	 */
	public static function record_verification( $post_id, $status, $failure_reason = '' ) {
		global $wpdb;

		$allowed_statuses = array( self::STATUS_VERIFIED, self::STATUS_INVALID, self::STATUS_ERROR );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			archivio_id_log( 
				sprintf( 
					'Invalid status "%s" passed to record_verification() for post %d', 
					$status, 
					$post_id 
				) 
			);
			return false;
		}

		$data   = array( 'status' => $status, 'failure_reason' => sanitize_text_field( $failure_reason ) );
		$format = array( '%s', '%s' );

		if ( $status === self::STATUS_VERIFIED ) {
			$data['verified_at'] = current_time( 'mysql', true );
			$format[]            = '%s';
		}

		// ══════════════════════════════════════════════════════════════════════
		// ══════════════════════════════════════════════════════════════════════
		$updated = $wpdb->update(
			ArchivioID_DB::signatures_table(),
			$data,
			array( 'post_id' => absint( $post_id ) ),
			$format,
			array( '%d' )
		);

		// ══════════════════════════════════════════════════════════════════════
		// STEP 2: Cache invalidation and logging
		// ══════════════════════════════════════════════════════════════════════
		// Note: $wpdb->update() returns:
		// - false on error
		// - 0 if no rows were updated (data unchanged)
		// - number of rows updated (typically 1)
		//
		// We consider both 0 and 1 as success (0 means data was already correct).
		// ══════════════════════════════════════════════════════════════════════
		if ( false !== $updated ) {
			// Clear object cache for this post's signature data
			wp_cache_delete( $post_id, 'archivio_id_signatures' );

			// ══════════════════════════════════════════════════════════════════
			// DEBUG LOGGING: Record verification result
			// ══════════════════════════════════════════════════════════════════
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// Log the update
				archivio_id_log( 
					sprintf( 
						'Verification status recorded for post %d: status="%s", rows_affected=%d',
						$post_id,
						$status,
						$updated
					) 
				);

				// For verified status, log the timestamp
				if ( $status === self::STATUS_VERIFIED && isset( $data['verified_at'] ) ) {
					archivio_id_log( 
						sprintf( 
							'Post %d verified at %s',
							$post_id,
							$data['verified_at']
						) 
					);
				}

				// Verify the update took effect by re-querying
				$check = self::get( $post_id );
				if ( $check ) {
					archivio_id_log( 
						sprintf( 
							'Post-update verification check for post %d: DB status="%s", verified_at=%s',
							$post_id,
							$check->status,
							$check->verified_at ? $check->verified_at : 'NULL'
						) 
					);

					// Warn if status doesn't match what we just set
					if ( $check->status !== $status ) {
						archivio_id_log( 
							sprintf( 
								'WARNING: Post %d status mismatch after update. Expected "%s" but got "%s"',
								$post_id,
								$status,
								$check->status
							) 
						);
					}
				} else {
					archivio_id_log( 
						sprintf( 
							'WARNING: Could not retrieve signature row for post %d after update',
							$post_id
						) 
					);
				}
			}

			return true;
		}

		// Database update failed
		archivio_id_log( 
			sprintf( 
				'Database UPDATE failed for post %d (status: %s). Check MySQL error log.',
				$post_id,
				$status
			) 
		);
		return false;
	}

	// ── Delete ────────────────────────────────────────────────────────────────

	/**
	 * Delete the signature record for a post.
	 *
	 * UPDATED v1.1.1: Now includes cache invalidation after deletion.
	 *
	 * @param  int  $post_id
	 * @return bool
	 */
	public static function delete( $post_id ) {
		global $wpdb;
		
		$deleted = (bool) $wpdb->delete(
			ArchivioID_DB::signatures_table(),
			array( 'post_id' => absint( $post_id ) ),
			array( '%d' )
		);

		// ══════════════════════════════════════════════════════════════════════
		// CACHE INVALIDATION: Clear cache after deletion
		// ══════════════════════════════════════════════════════════════════════
		if ( $deleted ) {
			wp_cache_delete( $post_id, 'archivio_id_signatures' );
			
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				archivio_id_log( sprintf( 'Signature deleted for post %d', $post_id ) );
			}
		}

		return $deleted;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Validate that an uploaded .asc file looks like a detached PGP signature.
	 *
	 * @param  string $text  File contents.
	 * @return bool
	 */
	public static function looks_like_detached_signature( $text ) {
		return (
			strpos( $text, '-----BEGIN PGP SIGNATURE-----' ) !== false
			&& strpos( $text, '-----END PGP SIGNATURE-----' ) !== false
		);
	}
}
