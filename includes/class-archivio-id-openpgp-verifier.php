<?php
/**
 * ArchivioID OpenPGP Verifier
 *
 * Verifies detached OpenPGP signatures using the correct backend per key type:
 *
 *   - EdDSA / Ed25519 (algorithm 22) — via libsodium (sodium_make_verifier)
 *   - RSA              (algorithm  1) — via phpseclib v3
 *   - ECDSA            (algorithm 19) — via phpseclib v3
 *
 * OpenPGP.js v5 generates Ed25519 keys by default, so EdDSA is the primary
 * path for browser-generated keys.
 *
 * Uses OpenPGP_Message::verified_signatures() which correctly builds the
 * data-to-verify (literal data + trailer) per RFC 4880.
 *
 * @package ArchivioID
 * @since   1.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_OpenPGP_Verifier {

	/** @var OpenPGP_Message Public key message */
	private $key_msg;

	/**
	 * @param OpenPGP_Message $key_msg  Parsed public key message
	 */
	public function __construct( $key_msg ) {
		$this->key_msg = $key_msg;
	}

	/**
	 * Verify a combined message (LiteralDataPacket + SignaturePacket).
	 *
	 * Returns the message on success, null on failure — matching the
	 * interface the rest of the plugin expects.
	 *
	 * @param  OpenPGP_Message $data_msg
	 * @return OpenPGP_Message|null
	 */
	public function verify( $data_msg ) {
		try {
			// Determine key algorithm from the public key packet
			$key_packet = null;
			foreach ( $this->key_msg->packets as $pkt ) {
				if ( $pkt instanceof OpenPGP_PublicKeyPacket ) {
					$key_packet = $pkt;
					break;
				}
			}

			if ( ! $key_packet ) {
				archivio_id_log( 'OpenPGP_Verifier: no public key packet found' );
				return null;
			}

			archivio_id_log( 'OpenPGP_Verifier: key algorithm = ' . $key_packet->algorithm );

			// Build the combined message the way verified_signatures() expects:
			// [ LiteralDataPacket, ..., SignaturePacket ]
			// We need to pass it as an OpenPGP_Message with signatures() working.
			// The easiest path: re-use $data_msg directly with verified_signatures().

			switch ( (int) $key_packet->algorithm ) {

				case 22: // EdDSA / Ed25519
					return $this->verify_eddsa( $data_msg, $key_packet );

				case 1:  // RSA Sign or Encrypt
				case 3:  // RSA Sign-Only
					return $this->verify_rsa( $data_msg, $key_packet );

				case 19: // ECDSA
					return $this->verify_ecdsa( $data_msg, $key_packet );

				default:
					archivio_id_log( 'OpenPGP_Verifier: unsupported key algorithm ' . $key_packet->algorithm );
					return null;
			}
		} catch ( Throwable $e ) {
			archivio_id_log( 'OpenPGP_Verifier: exception — ' . $e->getMessage() );
			return null;
		}
	}

	// ── EdDSA / Ed25519 ──────────────────────────────────────────────────────

	/**
	 * Verify using libsodium (Ed25519).
	 * Uses sodium_make_verifier() from openpgp_sodium.php.
	 */
	private function verify_eddsa( $data_msg, $key_packet ) {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			archivio_id_log( 'OpenPGP_Verifier: libsodium not available for EdDSA verification' );
			return null;
		}
		if ( ! function_exists( 'sodium_make_verifier' ) ) {
			archivio_id_log( 'OpenPGP_Verifier: sodium_make_verifier() not available' );
			return null;
		}

		try {
			$verifier  = sodium_make_verifier( $key_packet );
			$verifiers = array( 'EdDSA' => $verifier );
			$results   = $data_msg->verified_signatures( $verifiers );

			foreach ( $results as $signed ) {
				$sigs = end( $signed );
				if ( ! empty( $sigs ) ) {
					archivio_id_log( 'OpenPGP_Verifier: EdDSA verification succeeded' );
					return $data_msg;
				}
			}

			archivio_id_log( 'OpenPGP_Verifier: EdDSA verification failed' );
			return null;
		} catch ( Throwable $e ) {
			archivio_id_log( 'OpenPGP_Verifier: EdDSA exception — ' . $e->getMessage() );
			return null;
		}
	}

	// ── RSA ──────────────────────────────────────────────────────────────────

	/**
	 * Verify RSA signature via phpseclib v3.
	 * Wraps phpseclib into the verifier-callback format verified_signatures() expects.
	 */
	private function verify_rsa( $data_msg, $key_packet ) {
		try {
			if ( ! isset( $key_packet->key['n'], $key_packet->key['e'] ) ) {
				archivio_id_log( 'OpenPGP_Verifier: RSA key material missing' );
				return null;
			}

			$xml  = '<RSAKeyValue>';
			$xml .= '<Modulus>'  . base64_encode( $key_packet->key['n'] ) . '</Modulus>';
			$xml .= '<Exponent>' . base64_encode( $key_packet->key['e'] ) . '</Exponent>';
			$xml .= '</RSAKeyValue>';

			$pub_key = \phpseclib3\Crypt\PublicKeyLoader::load( $xml );

			$hash_map = array(
				1 => 'md5', 2 => 'sha1', 8 => 'sha256',
				9 => 'sha384', 10 => 'sha512', 11 => 'sha224',
			);

			$verifier = function( $data, $sig ) use ( $pub_key, $hash_map ) {
				$hash_name = isset( $hash_map[ $sig->hash_algorithm ] )
					? $hash_map[ $sig->hash_algorithm ]
					: 'sha256';

				$raw_sig = isset( $sig->data[0] ) ? $sig->data[0] : null;
				if ( ! $raw_sig ) return false;

				return $pub_key
					->withHash( $hash_name )
					->withPadding( \phpseclib3\Crypt\RSA::SIGNATURE_PKCS1 )
					->verify( $data, $raw_sig );
			};

			$results = $data_msg->verified_signatures( array( 'RSA' => $verifier ) );

			foreach ( $results as $signed ) {
				$sigs = end( $signed );
				if ( ! empty( $sigs ) ) {
					archivio_id_log( 'OpenPGP_Verifier: RSA verification succeeded' );
					return $data_msg;
				}
			}

			archivio_id_log( 'OpenPGP_Verifier: RSA verification failed' );
			return null;
		} catch ( Throwable $e ) {
			archivio_id_log( 'OpenPGP_Verifier: RSA exception — ' . $e->getMessage() );
			return null;
		}
	}

	// ── ECDSA ─────────────────────────────────────────────────────────────────

	/**
	 * Verify ECDSA signature via phpseclib v3.
	 */
	private function verify_ecdsa( $data_msg, $key_packet ) {
		try {
			if ( ! isset( $key_packet->key['p'] ) ) {
				archivio_id_log( 'OpenPGP_Verifier: ECDSA key material missing' );
				return null;
			}

			// Extract the OID to determine the curve
			$oid_hex = isset( $key_packet->key['oid'] ) ? bin2hex( $key_packet->key['oid'] ) : '';

			// Common curve OID mappings
			$oid_curve_map = array(
				'2a8648ce3d030107' => 'P-256',
				'2b81040022'       => 'P-384',
				'2b81040023'       => 'P-521',
			);

			$curve = isset( $oid_curve_map[ $oid_hex ] ) ? $oid_curve_map[ $oid_hex ] : 'P-256';

			// Build uncompressed point public key
			$point = $key_packet->key['p'];
			// phpseclib expects the point in SEC1 uncompressed form (0x04 prefix already present)
			$key_data = "-----BEGIN PUBLIC KEY-----\n" .
				base64_encode( $point ) .
				"\n-----END PUBLIC KEY-----";

			$pub_key = \phpseclib3\Crypt\PublicKeyLoader::load( $key_data );

			$hash_map = array(
				8 => 'sha256', 9 => 'sha384', 10 => 'sha512',
				2 => 'sha1',  11 => 'sha224',
			);

			$verifier = function( $data, $sig ) use ( $pub_key, $hash_map ) {
				$hash_name = isset( $hash_map[ $sig->hash_algorithm ] ) ? $hash_map[ $sig->hash_algorithm ] : 'sha256';
				// ECDSA sig is array of two MPI values (r, s)
				if ( ! isset( $sig->data[0], $sig->data[1] ) ) return false;
				// Build DER-encoded signature from r and s
				$raw_sig = $this->ecdsa_der( $sig->data[0], $sig->data[1] );
				return $pub_key->withHash( $hash_name )->verify( $data, $raw_sig );
			};

			$results = $data_msg->verified_signatures( array( 'ECDSA' => $verifier ) );

			foreach ( $results as $signed ) {
				$sigs = end( $signed );
				if ( ! empty( $sigs ) ) {
					archivio_id_log( 'OpenPGP_Verifier: ECDSA verification succeeded' );
					return $data_msg;
				}
			}

			archivio_id_log( 'OpenPGP_Verifier: ECDSA verification failed' );
			return null;
		} catch ( Throwable $e ) {
			archivio_id_log( 'OpenPGP_Verifier: ECDSA exception — ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Build DER-encoded ECDSA signature from raw r and s MPI byte strings.
	 */
	private function ecdsa_der( $r, $s ) {
		$encode_int = function( $bytes ) {
			// Strip leading zero bytes but keep one if needed for sign bit
			$bytes = ltrim( $bytes, "\x00" );
			if ( ord( $bytes[0] ) >= 0x80 ) {
				$bytes = "\x00" . $bytes;
			}
			return "\x02" . chr( strlen( $bytes ) ) . $bytes;
		};
		$r_der = $encode_int( $r );
		$s_der = $encode_int( $s );
		$seq   = $r_der . $s_der;
		return "\x30" . chr( strlen( $seq ) ) . $seq;
	}
}
