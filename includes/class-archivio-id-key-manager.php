<?php
/**
 * ArchivioID Key Manager
 *
 * CRUD for OpenPGP public keys stored in {prefix}archivio_id_keys.
 *
 * Security contract:
 *   - Only armored PUBLIC keys accepted (never private).
 *   - Fingerprint extracted from the ASCII-armored block via pure-PHP parsing.
 *   - All inputs sanitized before persistence.
 *   - No external network calls.
 *
 * @package ArchivioID
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Key_Manager {

	// ── Insert a new key ──────────────────────────────────────────────────────

	/**
	 * Validate and store an armored public key.
	 *
	 * @param  string $armored_key  Raw text from the upload/textarea.
	 * @param  string $label        Human-readable label (name/email).
	 * @param  int    $added_by     WP user ID performing the upload.
	 * @return array{ success: bool, message: string, key_id?: int,
	 *                fingerprint?: string, key_short_id?: string }
	 */
	public static function add_key( $armored_key, $label, $added_by ) {
		// ── Sanitize inputs ──────────────────────────────────────────────────
		$armored_key = sanitize_textarea_field( wp_unslash( $armored_key ) );
		$label       = sanitize_text_field( wp_unslash( $label ) );
		$added_by    = absint( $added_by );

		if ( empty( $armored_key ) ) {
			return array( 'success' => false, 'message' => __( 'No key data provided.', 'archivio-id' ) );
		}
		if ( empty( $label ) ) {
			return array( 'success' => false, 'message' => __( 'A label is required.', 'archivio-id' ) );
		}

		// ── Structure validation ─────────────────────────────────────────────
		if ( ! self::looks_like_public_key( $armored_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'The uploaded data does not appear to be a valid armored OpenPGP PUBLIC KEY block.', 'archivio-id' ),
			);
		}
		if ( self::looks_like_private_key( $armored_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Private key material detected. Only public keys may be uploaded.', 'archivio-id' ),
			);
		}

		// ── Size guard (4 KB is generous for any real public key) ───────────
		if ( strlen( $armored_key ) > 4096 ) {
			return array( 'success' => false, 'message' => __( 'Key data exceeds maximum allowed size (4 KB).', 'archivio-id' ) );
		}

		// ── Extract fingerprint from armored block ───────────────────────────
		$fingerprint = self::extract_fingerprint( $armored_key );
		if ( false === $fingerprint ) {
			return array(
				'success' => false,
				'message' => __( 'Could not parse the key fingerprint. Ensure the key is a valid OpenPGP v4 public key.', 'archivio-id' ),
			);
		}

		$key_short_id = strtoupper( substr( $fingerprint, -16 ) );

		// ── Duplicate check ──────────────────────────────────────────────────
		global $wpdb;
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . ArchivioID_DB::keys_table() . ' WHERE fingerprint = %s LIMIT 1',
				$fingerprint
			)
		);
		if ( $existing ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: fingerprint hex string */
					__( 'A key with fingerprint %s is already stored.', 'archivio-id' ),
					'<code>' . esc_html( $fingerprint ) . '</code>'
				),
			);
		}

		// ── Persist ──────────────────────────────────────────────────────────
		$inserted = $wpdb->insert(
			ArchivioID_DB::keys_table(),
			array(
				'label'       => $label,
				'fingerprint' => $fingerprint,
				'key_id'      => $key_short_id,
				'armored_key' => $armored_key,
				'created_at'  => current_time( 'mysql', true ),
				'added_by'    => $added_by,
				'is_active'   => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false === $inserted ) {
			archivio_id_log( 'Key insert failed for user ' . $added_by . ' - Check database server error log for details' );
			return array( 'success' => false, 'message' => __( 'Database error while storing key.', 'archivio-id' ) );
		}

		return array(
			'success'     => true,
			'message'     => __( 'Public key stored successfully.', 'archivio-id' ),
			'key_id'      => (int) $wpdb->insert_id,
			'fingerprint' => $fingerprint,
			'key_short_id' => $key_short_id,
		);
	}

	// ── Soft-delete / toggle active ───────────────────────────────────────────

	public static function deactivate_key( $id ) {
		global $wpdb;
		return (bool) $wpdb->update(
			ArchivioID_DB::keys_table(),
			array( 'is_active' => 0 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	public static function activate_key( $id ) {
		global $wpdb;
		return (bool) $wpdb->update(
			ArchivioID_DB::keys_table(),
			array( 'is_active' => 1 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	public static function delete_key( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			ArchivioID_DB::keys_table(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	// ── Read helpers ──────────────────────────────────────────────────────────

	public static function get_key( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ArchivioID_DB::keys_table() . ' WHERE id = %d LIMIT 1', absint( $id ) )
		);
	}

	/**
	 * Return all active keys (for dropdown selectors).
	 */
	public static function get_active_keys() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT id, label, fingerprint, key_id, created_at FROM ' . ArchivioID_DB::keys_table()
			. " WHERE is_active = 1 ORDER BY label ASC"
		);
	}

	/**
	 * Return paginated list of all keys (including inactive) for admin table.
	 */
	public static function get_all_keys( $page = 1, $per_page = 20 ) {
		global $wpdb;
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;
		$table  = ArchivioID_DB::keys_table();
		$keys   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, label, fingerprint, key_id, created_at, added_by, is_active
				 FROM {$table}
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				(int) $per_page,
				$offset
			)
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return array( 'keys' => $keys, 'total' => $total );
	}

	// ── Key parsing helpers ───────────────────────────────────────────────────

	/**
	 * Return true if the text contains a valid PUBLIC KEY armor header.
	 */
	public static function looks_like_public_key( $text ) {
		return (
			strpos( $text, '-----BEGIN PGP PUBLIC KEY BLOCK-----' ) !== false
			&& strpos( $text, '-----END PGP PUBLIC KEY BLOCK-----' ) !== false
		);
	}

	/**
	 * Reject anything that contains private key material.
	 */
	public static function looks_like_private_key( $text ) {
		return (
			strpos( $text, '-----BEGIN PGP PRIVATE KEY BLOCK-----' ) !== false
			|| strpos( $text, '-----BEGIN PGP SECRET KEY BLOCK-----' ) !== false
		);
	}

	/**
	 * Extract the OpenPGP v4 fingerprint from an armored public key.
	 *
	 * Algorithm (RFC 4880 §12.2):
	 *   fingerprint = SHA-1( 0x99 || uint16(packet_body_length) || packet_body )
	 *
	 * We decode the base64 payload, locate the Public-Key packet (tag 6),
	 * and compute the fingerprint. Falls back to returning false if we
	 * cannot parse a v4 key from the block.
	 *
	 * Note: This is intentionally minimal and defensive — we only need the
	 * fingerprint for storage/indexing, not full packet validation.
	 *
	 * @param  string $armored
	 * @return string|false  Uppercase 40-hex fingerprint, or false on failure.
	 */
	public static function extract_fingerprint( $armored ) {
		try {
			$lines     = explode( "\n", str_replace( "\r\n", "\n", $armored ) );
			$b64_lines = array();
			$in_body   = false;

			foreach ( $lines as $line ) {
				$line = rtrim( $line );
				if ( $line === '-----BEGIN PGP PUBLIC KEY BLOCK-----' ) {
					$in_body = false; // headers follow, not base64 yet
					continue;
				}
				if ( $line === '-----END PGP PUBLIC KEY BLOCK-----' ) {
					break;
				}
				// Blank line separates armor headers from base64 body.
				if ( ! $in_body ) {
					if ( $line === '' ) {
						$in_body = true;
					}
					continue;
				}
				// Skip CRC24 line (starts with '=').
				if ( isset( $line[0] ) && $line[0] === '=' ) {
					continue;
				}
				$b64_lines[] = $line;
			}

			if ( empty( $b64_lines ) ) {
				return false;
			}

			$binary = base64_decode( implode( '', $b64_lines ), true );
			if ( false === $binary || strlen( $binary ) < 6 ) {
				return false;
			}

			//    or new-format tag 6).
			$offset = 0;
			$len    = strlen( $binary );

			while ( $offset < $len ) {
				$ctb = ord( $binary[ $offset ] );
				if ( ! ( $ctb & 0x80 ) ) {
					break; // Not a valid packet tag byte.
				}
				$offset++;

				if ( $ctb & 0x40 ) {
					// New-format packet.
					$tag     = $ctb & 0x3F;
					$first   = ord( $binary[ $offset++ ] );
					if ( $first < 192 ) {
						$body_len = $first;
					} elseif ( $first < 224 ) {
						$body_len = ( ( $first - 192 ) << 8 ) + ord( $binary[ $offset++ ] ) + 192;
					} else {
						// Partial body / 5-octet — skip.
						break;
					}
				} else {
					// Old-format packet.
					$tag      = ( $ctb & 0x3C ) >> 2;
					$len_type = $ctb & 0x03;
					switch ( $len_type ) {
						case 0: $body_len = ord( $binary[ $offset++ ] ); break;
						case 1:
							$body_len = ( ord( $binary[ $offset ] ) << 8 ) | ord( $binary[ $offset + 1 ] );
							$offset  += 2;
							break;
						case 2:
							$body_len = ( ord( $binary[ $offset ] ) << 24 )
								| ( ord( $binary[ $offset + 1 ] ) << 16 )
								| ( ord( $binary[ $offset + 2 ] ) <<  8 )
								|   ord( $binary[ $offset + 3 ] );
							$offset  += 4;
							break;
						default:
							break 2; // Indeterminate length — bail.
					}
				}

				if ( $tag === 6 ) {
					// Public-Key packet found.
					$body = substr( $binary, $offset, $body_len );
					if ( strlen( $body ) < 1 ) {
						return false;
					}
					$version = ord( $body[0] );
					if ( $version !== 4 ) {
						// v3/v2 fingerprint is MD5-based; we only support v4.
						return false;
					}
					// RFC 4880 §12.2: fingerprint = SHA-1( 0x99 || uint16(length) || body )
					$fp_input = "\x99"
						. chr( ( $body_len >> 8 ) & 0xFF )
						. chr( $body_len & 0xFF )
						. $body;

					return strtoupper( sha1( $fp_input ) );
				}

				$offset += $body_len;
			}

			return false;

		} catch ( Throwable $e ) { // PHP 7+
			archivio_id_log( 'Fingerprint extraction exception: ' . $e->getMessage() );
			return false;
		}
	}
}
