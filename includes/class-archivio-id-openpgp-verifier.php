<?php
/**
 * ArchivioID OpenPGP phpseclib v3 Wrapper
 *
 * This class provides OpenPGP-PHP compatibility while using phpseclib v3
 * as the cryptographic backend for signature verification.
 *
 * It extends/replaces OpenPGP_Crypt_RSA to use phpseclib v3's RSA implementation.
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenPGP Verification Wrapper using phpseclib v3
 *
 * Provides the same interface as OpenPGP_Crypt_RSA but uses phpseclib v3 internally.
 */
class ArchivioID_OpenPGP_Verifier {

	/** @var OpenPGP_Message PGP public key message */
	private $key_msg;

	/** @var \phpseclib3\Crypt\RSA|null phpseclib RSA key object */
	private $rsa_key = null;

	/**
	 * Constructor.
	 *
	 * @param OpenPGP_Message $key_msg Public key message from OpenPGP-PHP
	 */
	public function __construct( $key_msg ) {
		$this->key_msg = $key_msg;
		$this->load_key();
	}

	/**
	 * Load the PGP key into phpseclib v3.
	 *
	 * Extracts RSA key material from OpenPGP packets and loads into phpseclib.
	 */
	private function load_key() {
		try {
			foreach ( $this->key_msg->packets as $packet ) {
				if ( $packet instanceof OpenPGP_PublicKeyPacket ) {
					if ( $packet->algorithm == 1 ) { // RSA Encrypt or Sign
						$this->rsa_key = $this->create_rsa_from_packet( $packet );
						break;
					}
				}
			}
		} catch ( Throwable $e ) {
			archivio_id_log( 'Error loading PGP key: ' . $e->getMessage() );
		}
	}

	/**
	 * Create phpseclib v3 RSA key from OpenPGP packet.
	 *
	 * @param  OpenPGP_PublicKeyPacket $packet
	 * @return \phpseclib3\Crypt\RSA|null
	 */
	private function create_rsa_from_packet( $packet ) {
		try {
			if ( ! isset( $packet->key ) || ! is_array( $packet->key ) ) {
				return null;
			}

			// Extract RSA components (n = modulus, e = exponent)
			$n = $packet->key['n'];
			$e = $packet->key['e'];

			// Convert to phpseclib format (XML RSA key)
			$xml = '<RSAKeyValue>';
			$xml .= '<Modulus>' . base64_encode( $n ) . '</Modulus>';
			$xml .= '<Exponent>' . base64_encode( $e ) . '</Exponent>';
			$xml .= '</RSAKeyValue>';

			// Load into phpseclib v3
			return \phpseclib3\Crypt\PublicKeyLoader::load( $xml );

		} catch ( Throwable $e ) {
			archivio_id_log( 'Error creating RSA key: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Verify a signature using phpseclib v3.
	 *
	 * This provides compatibility with OpenPGP_Crypt_RSA::verify() interface.
	 *
	 * @param  OpenPGP_Message $data_msg Literal data message
	 * @return OpenPGP_Message|null Verified message on success, null on failure
	 */
	public function verify( $data_msg ) {
		if ( ! $this->rsa_key ) {
			archivio_id_log( 'RSA key not loaded' );
			return null;
		}

		try {
			// Find signature packet in the data message
			$sig_packet = null;
			foreach ( $data_msg->packets as $packet ) {
				if ( $packet instanceof OpenPGP_SignaturePacket ) {
					$sig_packet = $packet;
					break;
				}
			}

			if ( ! $sig_packet ) {
				archivio_id_log( 'No signature packet found' );
				return null;
			}

			$signed_data = '';
			foreach ( $data_msg->packets as $packet ) {
				if ( $packet instanceof OpenPGP_LiteralDataPacket ) {
					$signed_data = $packet->data;
					break;
				}
			}

			if ( empty( $signed_data ) ) {
				archivio_id_log( 'No literal data packet found' );
				return null;
			}

			// Build the data to verify according to OpenPGP spec
			// This includes the signature trailer
			$data_to_verify = $signed_data;
			if ( $sig_packet->trailer ) {
				$data_to_verify .= $sig_packet->trailer;
			}

			$hash_algo = $this->get_hash_algorithm( $sig_packet->hash_algorithm );

			// Extract raw signature bytes
			$signature = $this->extract_signature( $sig_packet );
			if ( ! $signature ) {
				archivio_id_log( 'Could not extract signature bytes' );
				return null;
			}

			// Verify using phpseclib v3
			$verified = $this->rsa_key
				->withHash( $hash_algo )
				->withPadding( \phpseclib3\Crypt\RSA::SIGNATURE_PKCS1 )
				->verify( $data_to_verify, $signature );

			if ( $verified ) {
				return $data_msg; // Return message on success (OpenPGP-PHP convention)
			}

			return null; // Verification failed

		} catch ( Throwable $e ) {
			archivio_id_log( 'Verification error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Extract raw signature bytes from signature packet.
	 *
	 * @param  OpenPGP_SignaturePacket $sig_packet
	 * @return string|null Raw signature bytes
	 */
	private function extract_signature( $sig_packet ) {
		try {
			if ( isset( $sig_packet->data ) && is_array( $sig_packet->data ) ) {
				// For RSA, data is array with single element containing signature
				if ( isset( $sig_packet->data[0] ) ) {
					return $sig_packet->data[0];
				}
			}
			return null;
		} catch ( Throwable $e ) {
			archivio_id_log( 'Error extracting signature: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Map OpenPGP hash algorithm ID to phpseclib hash name.
	 *
	 * @param  int $hash_algo_id OpenPGP hash algorithm ID
	 * @return string phpseclib hash algorithm name
	 */
	private function get_hash_algorithm( $hash_algo_id ) {
		$hash_map = array(
			1  => 'md5',
			2  => 'sha1',
			8  => 'sha256',
			9  => 'sha384',
			10 => 'sha512',
			11 => 'sha224',
		);

		return isset( $hash_map[ $hash_algo_id ] ) ? $hash_map[ $hash_algo_id ] : 'sha256';
	}
}
