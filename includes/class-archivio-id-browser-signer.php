<?php
/**
 * ArchivioID Browser Signer — Server-Side AJAX Handler
 *
 * Receives detached signatures created in the browser (via OpenPGP.js),
 * validates inputs, verifies server-side using the existing phpseclib +
 * OpenPGP-PHP stack (same path as ArchivioID_Verifier::verify_openpgp),
 * and persists results.
 *
 * AJAX actions:
 *   archivio_id_browser_sign   – submit a browser-generated detached sig
 *   archivio_id_get_post_hash  – fetch the ArchivioMD hex hash for a post
 *
 * HOW SIGNING WORKS (end-to-end):
 *   ArchivioMD stores: packed = "sha256:abcdef…" under _archivio_post_hash
 *   unpack()['hash'] gives the raw hex string the browser signs as plain text
 *   Server wraps it in OpenPGP_LiteralDataPacket and verifies — same as
 *   ArchivioID_Verifier::do_verify() does for uploaded .sig files.
 *
 * @package ArchivioID
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Browser_Signer {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_archivio_id_browser_sign',  array( $this, 'handle_browser_sign' ) );
		add_action( 'wp_ajax_archivio_id_get_post_hash', array( $this, 'handle_get_post_hash' ) );
	}

	// =========================================================================
	// AJAX: archivio_id_get_post_hash
	// Returns the unpacked hex hash (and metadata) for a given post ID.
	// The browser signs this hex string as plain text via OpenPGP.js.
	// =========================================================================

	public function handle_get_post_hash() {

		if ( ! check_ajax_referer( 'archivio_id_browser_sign', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'archivio-id' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archivio-id' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'archivio-id' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to sign this post.', 'archivio-id' ) ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'archivio-id' ) ), 404 );
		}

		// ArchivioMD stores packed format "sha256:hexhash" (or hmac-sha256:hexhash)
		// under _archivio_post_hash. We unpack to get the raw hex the browser signs.
		$packed_hash = get_post_meta( $post_id, '_archivio_post_hash', true );

		if ( empty( $packed_hash ) ) {
			wp_send_json_error( array(
				'message' => __( 'No ArchivioMD hash found for this post. Open the post in ArchivioMD and generate its hash first.', 'archivio-id' ),
			), 404 );
		}

		$hex_hash  = $packed_hash;
		$algorithm = 'sha256';
		$mode      = 'standard';

		if ( class_exists( 'MDSM_Hash_Helper' ) ) {
			$unpacked  = MDSM_Hash_Helper::unpack( $packed_hash );
			$hex_hash  = $unpacked['hash'];
			$algorithm = $unpacked['algorithm'];
			$mode      = $unpacked['mode'];
		}

		wp_send_json_success( array(
			'hash'        => $hex_hash,
			'packed_hash' => $packed_hash,
			'algorithm'   => $algorithm,
			'mode'        => $mode,
			'post_id'     => $post_id,
			'post_title'  => esc_html( $post->post_title ),
			'post_status' => $post->post_status,
		) );
	}

	// =========================================================================
	// AJAX: archivio_id_browser_sign
	// =========================================================================

	public function handle_browser_sign() {

		// ── 1. Security ───────────────────────────────────────────────────────
		if ( ! check_ajax_referer( 'archivio_id_browser_sign', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'archivio-id' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archivio-id' ) ), 403 );
		}

		// ── 2. Inputs ─────────────────────────────────────────────────────────
		$post_id       = isset( $_POST['post_id'] )       ? absint( $_POST['post_id'] )                                      : 0;
		$hex_hash      = isset( $_POST['hex_hash'] )      ? sanitize_text_field( wp_unslash( $_POST['hex_hash'] ) )          : '';
		$signature_asc = isset( $_POST['signature_asc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['signature_asc'] ) ) : '';
		$fingerprint   = isset( $_POST['fingerprint'] )   ? strtoupper( sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) ) : '';

		// ── 3. Validate ───────────────────────────────────────────────────────
		$errors = array();
		if ( empty( $post_id ) && empty( $hex_hash ) ) {
			$errors[] = __( 'A post ID or hex hash is required.', 'archivio-id' );
		}
		if ( ! empty( $hex_hash ) && ! preg_match( '/^[0-9a-fA-F]{32,}$/', $hex_hash ) ) {
			$errors[] = __( 'Hex hash contains invalid characters or is too short.', 'archivio-id' );
		}
		if ( empty( $signature_asc ) ) {
			$errors[] = __( 'Signature is required.', 'archivio-id' );
		} elseif ( strlen( $signature_asc ) > 65536 ) {
			$errors[] = __( 'Signature exceeds maximum allowed size.', 'archivio-id' );
		} elseif ( strpos( $signature_asc, '-----BEGIN PGP SIGNATURE-----' ) === false ) {
			$errors[] = __( 'Not a valid ASCII-armored PGP detached signature.', 'archivio-id' );
		}
		if ( empty( $fingerprint ) ) {
			$errors[] = __( 'Public key fingerprint is required.', 'archivio-id' );
		} elseif ( ! preg_match( '/^[0-9A-F]{40}$/', $fingerprint ) ) {
			$errors[] = __( 'Fingerprint must be 40 uppercase hex characters.', 'archivio-id' );
		}
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
		}

		// ── 4. Post check ─────────────────────────────────────────────────────
		if ( $post_id ) {
			if ( ! get_post( $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Post not found.', 'archivio-id' ) ), 404 );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Insufficient permissions for this post.', 'archivio-id' ) ), 403 );
			}
		}

		// ── 5. Look up public key ─────────────────────────────────────────────
		global $wpdb;
		$keys_table = ArchivioID_DB::keys_table();
		$key_row    = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$keys_table} WHERE fingerprint = %s AND is_active = 1 LIMIT 1",
				$fingerprint
			)
		);
		if ( ! $key_row ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: fingerprint */
					__( 'No active public key found for fingerprint %s. Upload the matching public key under ArchivioID → Keys first.', 'archivio-id' ),
					esc_html( $fingerprint )
				),
			), 404 );
		}

		// ── 6. Resolve signed hex hash ────────────────────────────────────────
		$hash_to_verify = '';
		$packed_hash    = '';
		$algorithm      = 'sha256';
		$mode           = 'standard';

		if ( $post_id ) {
			$packed_hash = get_post_meta( $post_id, '_archivio_post_hash', true );
			if ( empty( $packed_hash ) ) {
				wp_send_json_error( array(
					'message' => __( 'No ArchivioMD hash found for this post. Generate it in ArchivioMD first.', 'archivio-id' ),
				), 422 );
			}
			if ( class_exists( 'MDSM_Hash_Helper' ) ) {
				$unpacked       = MDSM_Hash_Helper::unpack( $packed_hash );
				$hash_to_verify = $unpacked['hash'];
				$algorithm      = $unpacked['algorithm'];
				$mode           = $unpacked['mode'];
			} else {
				$hash_to_verify = $packed_hash;
			}
		} else {
			$hash_to_verify = strtolower( $hex_hash );
			$packed_hash    = 'sha256:' . $hash_to_verify;
		}

		// ── 7. Verify signature ───────────────────────────────────────────────
		$verified      = false;
		$error_message = '';

		try {
			$verify_result = self::run_openpgp_verify(
				$signature_asc,
				$key_row->armored_key,
				$hash_to_verify   // the exact string OpenPGP.js signed in the browser
			);
			$verified      = $verify_result['verified'];
			$error_message = $verified ? '' : ( $verify_result['reason'] ?? __( 'Signature verification failed.', 'archivio-id' ) );
		} catch ( Throwable $e ) {
			archivio_id_log( 'Browser sign verification exception: ' . $e->getMessage() );
			$error_message = __( 'Internal verification error. See debug log.', 'archivio-id' );
		}

		// ── 8. Persist to browser_sigs table ──────────────────────────────────
		$row_id = ArchivioID_Browser_Sig_DB::insert( array(
			'hex_hash'               => $hash_to_verify,
			'post_id'                => $post_id ?: null,
			'signature_blob'         => $signature_asc,
			'public_key_fingerprint' => $fingerprint,
			'verified'               => $verified,
			'error_message'          => $error_message,
		) );

		// ── 9. On success: sync to main signatures table ──────────────────────
		if ( $post_id && $verified ) {
			ArchivioID_Signature_Store::upsert_upload(
				$post_id,
				(int) $key_row->id,
				$signature_asc,
				$packed_hash,
				$algorithm,
				$mode,
				get_current_user_id()
			);
			ArchivioID_Signature_Store::record_verification(
				$post_id,
				ArchivioID_Signature_Store::STATUS_VERIFIED,
				''
			);
			if ( class_exists( 'ArchivioID_Audit_Log' ) ) {
				ArchivioID_Audit_Log::log_event(
					$post_id,
					'browser_sign_verify',
					$key_row->fingerprint,
					$algorithm,
					'verified',
					''
				);
			}
		}

		// ── 10. Respond ───────────────────────────────────────────────────────
		if ( $verified ) {
			wp_send_json_success( array(
				'message'   => __( 'Signature verified successfully and saved.', 'archivio-id' ),
				'row_id'    => $row_id,
				'verified'  => true,
				'hash'      => $hash_to_verify,
				'post_id'   => $post_id ?: null,
				'key_label' => esc_html( $key_row->label ),
				'key_fp'    => esc_html( $fingerprint ),
			) );
		} else {
			wp_send_json_error( array(
				'message'  => $error_message ?: __( 'Signature verification failed.', 'archivio-id' ),
				'row_id'   => $row_id,
				'verified' => false,
				'hash'     => $hash_to_verify,
			) );
		}
	}

	// =========================================================================
	// Internal: OpenPGP verification — mirrors ArchivioID_Verifier::verify_openpgp
	// =========================================================================

	/**
	 * Verify a detached ASCII-armored PGP signature.
	 *
	 * $signed_data is the exact plaintext string the browser passed to
	 * openpgp.sign() — the hex hash string (e.g. "abcdef0123…").
	 * We wrap it in an OpenPGP_LiteralDataPacket, exactly as the existing
	 * post-verification workflow does.
	 *
	 * @param  string $armored_sig  ASCII-armored detached signature
	 * @param  string $armored_key  ASCII-armored public key
	 * @param  string $signed_data  Plaintext string that was signed
	 * @return array{ verified: bool, reason?: string }
	 */
	private static function run_openpgp_verify( $armored_sig, $armored_key, $signed_data ) {

		if ( ! class_exists( 'OpenPGP' ) || ! class_exists( 'OpenPGP_Message' ) ) {
			return array(
				'verified' => false,
				'reason'   => __( 'OpenPGP-PHP library not available.', 'archivio-id' ),
			);
		}

		try {
			// Parse detached signature
			$sig_msg = OpenPGP_Message::parse( OpenPGP::unarmor( $armored_sig, 'PGP SIGNATURE' ) );
			if ( ! $sig_msg || empty( $sig_msg->packets ) ) {
				return array(
					'verified' => false,
					'reason'   => __( 'Could not parse the detached signature.', 'archivio-id' ),
				);
			}

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
					'reason'   => __( 'No signature packet found in .asc file.', 'archivio-id' ),
				);
			}

			// Parse public key
			$key_msg = OpenPGP_Message::parse( OpenPGP::unarmor( $armored_key, 'PGP PUBLIC KEY BLOCK' ) );
			if ( ! $key_msg ) {
				return array(
					'verified' => false,
					'reason'   => __( 'Could not parse the public key.', 'archivio-id' ),
				);
			}

			// Wrap the signed string in a literal data packet and combine with sig packet
			$combined_msg = new OpenPGP_Message( array(
				new OpenPGP_LiteralDataPacket( $signed_data ),
				$sig_packet,
			) );

			// Verify via phpseclib v3 wrapper
			$verifier = new ArchivioID_OpenPGP_Verifier( $key_msg );
			$result   = $verifier->verify( $combined_msg );

			if ( $result !== null ) {
				return array( 'verified' => true );
			}

			return array(
				'verified' => false,
				'reason'   => __( 'Signature does not match. Ensure you signed the correct hash with the matching private key.', 'archivio-id' ),
			);

		} catch ( Throwable $e ) {
			archivio_id_log( 'Browser sign OpenPGP exception: ' . $e->getMessage() );
			return array(
				'verified' => false,
				'reason'   => __( 'Cryptographic verification error. See debug log for details.', 'archivio-id' ),
			);
		}
	}
}
