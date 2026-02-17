<?php
/**
 * ArchivioID Vendor Autoloader
 *
 * WordPress-safe autoloader for phpseclib v3.
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
 * Load phpseclib v3 autoloader safely.
 *
 * This function checks for the vendor directory and loads the autoloader
 * only if it exists. It will not cause fatal errors if the library is missing.
 *
 * @return bool True if autoloader was loaded successfully, false otherwise.
 */
function archivio_id_load_vendor_autoloader() {
	static $loaded = null;

	// Return cached result
	if ( $loaded !== null ) {
		return $loaded;
	}

	$vendor_dir = ARCHIVIO_ID_PLUGIN_DIR . 'vendor';
	
	if ( ! file_exists( $vendor_dir . '/phpseclib/phpseclib/bootstrap.php' ) ) {
		archivio_id_log( 'phpseclib v3 is not installed in vendor directory' );
		$loaded = false;
		return false;
	}

	// Load bootstrap file (handles mbstring checks)
	require_once $vendor_dir . '/phpseclib/phpseclib/bootstrap.php';

	// Register PSR-4 autoloader for phpseclib3 namespace
	spl_autoload_register( function ( $class ) use ( $vendor_dir ) {
		// Only handle phpseclib3 namespace
		$prefix = 'phpseclib3\\';
		$base_dir = $vendor_dir . '/phpseclib/phpseclib/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// No, move to the next registered autoloader
			return;
		}

		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators
		// and append with .php
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it
		if ( file_exists( $file ) ) {
			require $file;
		}
	}, true, false ); // Prepend=true, throw=false

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
