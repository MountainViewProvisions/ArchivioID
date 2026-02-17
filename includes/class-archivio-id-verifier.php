<?php
/**
 * ArchivioID Signature Verifier (phpseclib v3 Backend)
 *
 * Pure-PHP OpenPGP detached signature verification.
 *
 * ARCHITECTURE:
 * - Uses OpenPGP-PHP for PGP packet parsing (proven, stable)
 * - Uses phpseclib v3 for RSA cryptographic operations (modern, maintained)
 * - Best of both worlds: PGP format support + secure crypto backend
 *
 * Design constraints:
 *   - ZERO dependency on server GnuPG binary
 *   - ZERO external API calls
 *   - Verification ONLY on explicit user action (AJAX button click)
 *   - Graceful degradation if libraries missing
 *   - Does NOT re-implement hashing — uses ArchivioMD hash
 *
 * Verification flow:
 *   1. Load stored public key from DB
 *   2. Parse armored signature using OpenPGP-PHP
 *   3. Verify signature using phpseclib v3 (via wrapper class)
 *   4. Return structured result; persist to DB
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Verifier {

	// ── Main entry point ──────────────────────────────────────────────────────

	/**
	 * Verify the detached signature stored for $post_id against its ArchivioMD hash.
	 *
	 * This is the ONLY public entry point. Verification is triggered exclusively
	 * via AJAX action 'archivio_id_verify' (user clicks "Verify" button).
	 *
	 * @param  int $post_id
	 * @return array{
	 *   success:    bool,
	 *   status:     string,   // 'verified' | 'invalid' | 'error' | 'not_signed'
	 *   message:    string,
	 *   key_label?: string,
	 *   key_fp?:    string,
	 * }
	 */
	public static function verify_post( $post_id ) {
		$post_id = absint( $post_id );

		try {
			return self::do_verify( $post_id );
		} catch ( Throwable $e ) {
			$msg = 'ArchivioID verification exception for post ' . $post_id . ': ' . $e->getMessage();
			archivio_id_log( $msg );
			ArchivioID_Signature_Store::record_verification(
				$post_id,
				ArchivioID_Signature_Store::STATUS_ERROR,
				__( 'Internal error during verification. See error log.', 'archivio-id' )
			);
			return array(
				'success' => false,
				'status'  => ArchivioID_Signature_Store::STATUS_ERROR,
				'message' => __( 'An internal error occurred. The error has been logged.', 'archivio-id' ),
			);
		}
	}

	// ── Internal verification logic ───────────────────────────────────────────

	/**
	 * Internal verification workflow.
	 *
	 * @param  int $post_id
	 * @return array Verification result
	 */
	private static function do_verify( $post_id ) {

		$sig_row = ArchivioID_Signature_Store::get( $post_id );
		if ( ! $sig_row ) {
			return array(
				'success' => false,
				'status'  => 'not_signed',
				'message' => __( 'No signature has been uploaded for this post.', 'archivio-id' ),
			);
		}

		$key_row = ArchivioID_Key_Manager::get_key( $sig_row->key_id );
		if ( ! $key_row ) {
			self::fail( $post_id, __( 'The associated public key no longer exists in the database.', 'archivio-id' ) );
			return array(
				'success' => false,
				'status'  => ArchivioID_Signature_Store::STATUS_ERROR,
				'message' => __( 'Associated public key not found.', 'archivio-id' ),
			);
		}

		$packed_hash = get_post_meta( $post_id, '_archivio_post_hash', true );
		if ( empty( $packed_hash ) ) {
			return array(
				'success' => false,
				'status'  => 'not_signed',
				'message' => __( 'ArchivioMD has not generated a hash for this post yet.', 'archivio-id' ),
			);
		}

		$unpacked = MDSM_Hash_Helper::unpack( $packed_hash );
		$hex_hash = $unpacked['hash'];  // The hex string the user signed offline

		if ( ! hash_equals( $sig_row->archivio_hash, $packed_hash ) ) {
			self::fail(
				$post_id,
				__( 'The post content hash has changed since the signature was uploaded. Re-sign and re-upload.', 'archivio-id' )
			);
			return array(
				'success' => false,
				'status'  => ArchivioID_Signature_Store::STATUS_INVALID,
				'message' => __( 'Post hash mismatch: the content was modified after signing. Please re-sign and re-upload.', 'archivio-id' ),
			);
		}

		// 6. Verify using phpseclib v3 backend
		$result = self::verify_openpgp(
			$sig_row->signature_asc,
			$key_row->armored_key,
			$hex_hash
		);

		if ( $result['verified'] === true ) {
			ArchivioID_Signature_Store::record_verification(
				$post_id,
				ArchivioID_Signature_Store::STATUS_VERIFIED
			);
			
			if ( class_exists( 'ArchivioID_Audit_Log' ) ) {
				ArchivioID_Audit_Log::log_event(
					$post_id,
					'verify',
					$key_row->fingerprint,
					$unpacked['algorithm'],
					'verified',
					''
				);
			}
			
			return array(
				'success'   => true,
				'status'    => ArchivioID_Signature_Store::STATUS_VERIFIED,
				'message'   => __( 'Signature verified successfully.', 'archivio-id' ),
				'key_label' => esc_html( $key_row->label ),
				'key_fp'    => esc_html( $key_row->fingerprint ),
			);
		}

		// Verification failed
		$reason = isset( $result['reason'] ) ? $result['reason'] : __( 'Signature did not match.', 'archivio-id' );
		self::fail( $post_id, $reason );
		
		if ( class_exists( 'ArchivioID_Audit_Log' ) ) {
			ArchivioID_Audit_Log::log_event(
				$post_id,
				'verify',
				$key_row->fingerprint,
				$unpacked['algorithm'],
				$result['status'] ?? 'invalid',
				substr( $reason, 0, 512 )
			);
		}
		
		return array(
			'success' => false,
			'status'  => $result['status'] ?? ArchivioID_Signature_Store::STATUS_INVALID,
			'message' => $reason,
		);
	}

	// ── OpenPGP verification with phpseclib v3 backend ────────────────────────

	/**
	 * Perform detached signature verification.
	 *
	 * Uses OpenPGP-PHP for packet parsing + phpseclib v3 for crypto.
	 *
	 * @param  string $armored_sig   Armored detached signature
	 * @param  string $armored_key   Armored public key
	 * @param  string $signed_data   The exact string that was signed (hex hash)
	 * @return array{ verified: bool, reason?: string, status?: string }
	 */
	private static function verify_openpgp( $armored_sig, $armored_key, $signed_data ) {
		
		// Check library availability
		$lib_check = self::check_libraries();
		if ( ! $lib_check['success'] ) {
			return $lib_check;
		}

		try {
			// Parse the armored detached signature using OpenPGP-PHP
			$sig_msg = OpenPGP_Message::parse( OpenPGP::unarmor( $armored_sig, 'PGP SIGNATURE' ) );
			if ( ! $sig_msg || ! isset( $sig_msg->packets ) ) {
				return array(
					'verified' => false,
					'status'   => ArchivioID_Signature_Store::STATUS_INVALID,
					'reason'   => __( 'Could not parse the detached signature.', 'archivio-id' ),
				);
			}

			// Find the Signature packet
			$sig_packet = null;
			foreach ( $sig_msg->packets as $pkt ) {
				if ( $pkt instanceof OpenPGP_SignaturePacket ) {
					$sig_packet = $pkt;
					break;
				}
			}

			if ( ! $sig_packet ) {
				return array(
					'verified' => false,
					'status'   => ArchivioID_Signature_Store::STATUS_INVALID,
					'reason'   => __( 'No signature packet found in .asc file.', 'archivio-id' ),
				);
			}

			// Parse the public key using OpenPGP-PHP
			$key_msg = OpenPGP_Message::parse( OpenPGP::unarmor( $armored_key, 'PGP PUBLIC KEY BLOCK' ) );
			if ( ! $key_msg ) {
				return array(
					'verified' => false,
					'status'   => ArchivioID_Signature_Store::STATUS_ERROR,
					'reason'   => __( 'Could not parse the public key.', 'archivio-id' ),
				);
			}

			// Wrap signed data in OpenPGP literal data packet
			$data_msg = new OpenPGP_Message( array( new OpenPGP_LiteralDataPacket( $signed_data ) ) );

			// For detached signatures, combine the signature packet with the data
			// This is required because verify() expects to find the signature in the message
			$combined_packets = array();
			foreach ( $data_msg->packets as $p ) {
				$combined_packets[] = $p;
			}
			$combined_packets[] = $sig_packet;
			$combined_msg = new OpenPGP_Message( $combined_packets );

			// Verify using phpseclib v3 backend (via our wrapper)
			$verify_wrapper = new ArchivioID_OpenPGP_Verifier( $key_msg );
			$result = $verify_wrapper->verify( $combined_msg );

			if ( $result !== null ) {
				// Verification succeeded (returns OpenPGP_Message on success, null on failure)
				return array( 'verified' => true );
			}

			return array(
				'verified' => false,
				'status'   => ArchivioID_Signature_Store::STATUS_INVALID,
				'reason'   => __( 'Signature verification failed: signature does not match the stored hash.', 'archivio-id' ),
			);

		} catch ( Throwable $e ) {
			archivio_id_log( 'OpenPGP verification exception: ' . $e->getMessage() );
			return array(
				'verified' => false,
				'status'   => ArchivioID_Signature_Store::STATUS_ERROR,
				'reason'   => __( 'Signature verification library error. See error log.', 'archivio-id' ),
			);
		}
	}

	/**
	 * Check if required libraries are available.
	 *
	 * @return array Status array
	 */
	private static function check_libraries() {
		// Check OpenPGP-PHP
		$openpgp_path = ARCHIVIO_ID_PLUGIN_DIR . 'vendor/openpgp-php/openpgp.php';
		if ( ! file_exists( $openpgp_path ) ) {
			archivio_id_log( 'OpenPGP-PHP library not found at ' . $openpgp_path );
			return array(
				'verified' => false,
				'status'   => ArchivioID_Signature_Store::STATUS_ERROR,
				'reason'   => __(
					'OpenPGP library is not installed. Contact your administrator.',
					'archivio-id'
				),
			);
		}

		// Load OpenPGP-PHP if not already loaded
		if ( ! class_exists( 'OpenPGP_Message' ) ) {
			require_once $openpgp_path;
		}

		// Check phpseclib v3
		if ( ! function_exists( 'archivio_id_has_phpseclib' ) || ! archivio_id_has_phpseclib() ) {
			archivio_id_log( 'phpseclib v3 is not available' );
			return array(
				'verified' => false,
				'status'   => ArchivioID_Signature_Store::STATUS_ERROR,
				'reason'   => __(
					'Cryptographic library (phpseclib v3) is not installed. Contact your administrator.',
					'archivio-id'
				),
			);
		}

		return array( 'success' => true );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Record verification failure.
	 *
	 * @param int    $post_id
	 * @param string $reason
	 */
	private static function fail( $post_id, $reason ) {
		ArchivioID_Signature_Store::record_verification(
			$post_id,
			ArchivioID_Signature_Store::STATUS_INVALID,
			$reason
		);
		archivio_id_log( 'Verification INVALID for post ' . $post_id . ': ' . $reason );
	}
}

// ── Global log helper ─────────────────────────────────────────────────────────

if ( ! function_exists( 'archivio_id_log' ) ) {
	/**
	 * Write a message to the WP debug log (only when WP_DEBUG_LOG is true).
	 * Never exposes data to the browser.
	 *
	 * @param string $message
	 */
	function archivio_id_log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[ArchivioID] ' . $message );
		}
	}
}
