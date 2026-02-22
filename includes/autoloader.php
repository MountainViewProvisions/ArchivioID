<?php
/**
 * ArchivioID Vendor Autoloader
 *
 * WordPress-safe autoloader for phpseclib v3 and OpenPGP-PHP.
 * 
 * Design principles:
 * - No fatal errors if vendor directory is missing
 * - No namespace pollution
 * - Compatible with other plugins using different versions
 * - Graceful degradation
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load phpseclib v3 and OpenPGP-PHP safely.
 *
 * IMPORTANT load order:
 *   1. phpseclib bootstrap
 *   2. Register phpseclib PSR-4 autoloader  ← must happen BEFORE OpenPGP-PHP files
 *   3. Require OpenPGP-PHP files            ← these reference phpseclib3\ classes on include
 *
 * @return bool True if loaded successfully, false otherwise.
 */
function archivio_id_load_vendor_autoloader() {
	static $loaded = null;

	if ( $loaded !== null ) {
		return $loaded;
	}

	$vendor_dir = ARCHIVIO_ID_PLUGIN_DIR . 'vendor';

	if ( ! file_exists( $vendor_dir . '/phpseclib/phpseclib/bootstrap.php' ) ) {
		archivio_id_log( 'phpseclib v3 is not installed in vendor directory' );
		$loaded = false;
		return false;
	}

	// ── STEP 1: phpseclib bootstrap (mbstring polyfills etc.) ────────────────
	require_once $vendor_dir . '/phpseclib/phpseclib/bootstrap.php';

	// ── STEP 2: Register PSR-4 autoloader for phpseclib3\ FIRST ─────────────
	// OpenPGP-PHP files reference phpseclib3\ classes at include-time, so the
	// autoloader MUST be in place before any OpenPGP-PHP file is loaded.
	spl_autoload_register( function ( $class ) use ( $vendor_dir ) {
		$prefix   = 'phpseclib3\\';
		$base_dir = $vendor_dir . '/phpseclib/phpseclib/';
		$len      = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$file = $base_dir . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}, true, false );

	// ── STEP 3: Load OpenPGP-PHP AFTER autoloader is registered ─────────────
	// openpgp_crypt_rsa.php and openpgp_crypt_symmetric.php do a top-level
	// "new phpseclib3\Crypt\RSA()" / include_once at file scope, so phpseclib
	// must already be autoloadable when these files are require_once'd.
	$openpgp_files = array(
		'/openpgp-php/openpgp.php',
		'/openpgp-php/openpgp_crypt_symmetric.php',
		'/openpgp-php/openpgp_crypt_rsa.php',
		'/openpgp-php/openpgp_openssl_wrapper.php',
		'/openpgp-php/openpgp_mcrypt_wrapper.php',
		'/openpgp-php/openpgp_sodium.php',
	);
	foreach ( $openpgp_files as $openpgp_file ) {
		$path = $vendor_dir . $openpgp_file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	$loaded = true;
	return true;
}

/**
 * Check if phpseclib v3 is available.
 *
 * @return bool True if phpseclib v3 classes can be loaded.
 */
function archivio_id_has_phpseclib() {
	if ( ! archivio_id_load_vendor_autoloader() ) {
		return false;
	}
	return class_exists( 'phpseclib3\Crypt\PublicKeyLoader' );
}
