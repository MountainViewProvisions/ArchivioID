<?php
/**
 * Plugin Name: ArchivioID
 * Plugin URI:  https://mountainviewprovisions.com/ArchivioID
 * Description: OpenPGP detached-signature layer for ArchivioMD. Adds GPG public-key management and per-post signature upload/verification using phpseclib v3 cryptographic backend.
 * Version:     1.2.0
 * Author:      Mountain View Provisions LLC
 * Author URI:  https://mountainviewprovisions.com
 * Requires at least: 5.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: archivio-id
 * Domain Path: /languages
 *
 * This plugin REQUIRES ArchivioMD (meta-documentation-seo-manager) to be active.
 * It hooks into ArchivioMD via standard WordPress actions/filters only.
 * No ArchivioMD core files are modified.
 *
 * @package ArchivioID
 * @since   1.0.0
 */

// ═══════════════════════════════════════════════════════════════════════════
// SECURITY: Block direct access to this file
// ═══════════════════════════════════════════════════════════════════════════

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// ═══════════════════════════════════════════════════════════════════════════
// PLUGIN CONSTANTS
// Define all plugin-specific constants for paths, URLs, and version tracking
// ═══════════════════════════════════════════════════════════════════════════

define( 'ARCHIVIO_ID_VERSION',            '1.2.0' );
define( 'ARCHIVIO_ID_PLUGIN_DIR',         plugin_dir_path( __FILE__ ) );
define( 'ARCHIVIO_ID_PLUGIN_URL',         plugin_dir_url( __FILE__ ) );
define( 'ARCHIVIO_ID_PLUGIN_FILE',        __FILE__ );
define( 'ARCHIVIO_ID_PLUGIN_BASENAME',    plugin_basename( __FILE__ ) );
define( 'ARCHIVIO_ID_REQUIRED_PARENT',    '1.5.0' ); // Minimum ArchivioMD version required

// ═══════════════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// Helper functions used throughout the plugin
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Simple logging function for debugging and error tracking.
 * 
 * Logs messages to the WordPress debug.log file when WP_DEBUG_LOG is enabled.
 * This function is called by the autoloader and verification classes.
 *
 * @since 1.0.0
 * @param string $message The message to log.
 * @return void
 */
function archivio_id_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[ArchivioID] ' . $message );
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// DEPENDENCY VALIDATION
// Functions to check if ArchivioMD parent plugin is installed and active
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Check if ArchivioMD dependency is satisfied.
 * 
 * Validates that:
 * 1. ArchivioMD is installed (MDSM_VERSION constant exists)
 * 2. Version meets minimum requirement
 * 3. Required classes are available
 *
 * @since 1.0.0
 * @return bool True if all dependencies are met, false otherwise.
 */
function archivio_id_dependency_ok() {
	if ( ! defined( 'MDSM_VERSION' ) ) {
		return false;
	}
	
	if ( version_compare( MDSM_VERSION, ARCHIVIO_ID_REQUIRED_PARENT, '<' ) ) {
		return false;
	}
	
	// Check if required classes exist
	$required_classes = array( 'MDSM_Hash_Helper', 'MDSM_Archivio_Post' );
	foreach ( $required_classes as $class ) {
		if ( ! class_exists( $class ) ) {
			return false;
		}
	}
	
	return true;
}

/**
 * Display admin notice for missing dependencies.
 * 
 * Shows a user-friendly error message in the WordPress admin when
 * ArchivioMD is not installed or the version is too old.
 *
 * @since 1.0.0
 * @return void
 */
function archivio_id_missing_dependency_notice() {
	// Only show to users who can activate plugins
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	
	$defined = defined( 'MDSM_VERSION' );
	$current = $defined ? MDSM_VERSION : __( 'not installed', 'archivio-id' );
	
	if ( $defined && version_compare( MDSM_VERSION, ARCHIVIO_ID_REQUIRED_PARENT, '<' ) ) {
		// ArchivioMD is installed but version is too old
		$msg = sprintf(
			/* translators: 1: plugin name, 2: required version, 3: installed version */
			esc_html__( 'ArchivioID requires %1$s version %2$s or higher (found %3$s). Update %1$s then reactivate ArchivioID.', 'archivio-id' ),
			'<strong>ArchivioMD</strong>',
			'<code>' . esc_html( ARCHIVIO_ID_REQUIRED_PARENT ) . '</code>',
			'<code>' . esc_html( $current ) . '</code>'
		);
	} else {
		// ArchivioMD is not installed
		$msg = sprintf(
			/* translators: plugin name */
			esc_html__( 'ArchivioID requires %1$s to be installed and active. Install/activate %1$s, then reactivate ArchivioID.', 'archivio-id' ),
			'<strong>ArchivioMD</strong>'
		);
	}
	
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		wp_kses( $msg, array( 'strong' => array(), 'code' => array() ) )
	);
}

/**
 * Automatically deactivate this plugin if dependencies are not met.
 * 
 * Called on admin_init to silently deactivate the plugin when
 * dependencies are not satisfied.
 *
 * @since 1.0.0
 * @return void
 */
function archivio_id_self_deactivate() {
	deactivate_plugins( ARCHIVIO_ID_PLUGIN_BASENAME );
}

// ═══════════════════════════════════════════════════════════════════════════
// FILE LOADING
// Load all plugin classes and dependencies
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Load all plugin files and dependencies.
 * 
 * This function is called after dependency validation passes.
 * It loads the vendor autoloader and all plugin classes in the correct order.
 *
 * Load order:
 * 1. Vendor autoloader (phpseclib v3, OpenPGP-PHP)
 * 2. Core functionality classes
 * 3. Admin interface classes
 *
 * @since 1.0.0
 * @return void
 */
function archivio_id_load() {
	// ──────────────────────────────────────────────────────────────────────
	// ──────────────────────────────────────────────────────────────────────
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/autoloader.php';
	archivio_id_load_vendor_autoloader();
	
	// ──────────────────────────────────────────────────────────────────────
	// ──────────────────────────────────────────────────────────────────────
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-db.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-audit-log.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-key-manager.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-signature-store.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-openpgp-verifier.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-verifier.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-post-integration.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-frontend-badge.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-signature-download.php';
	
	// ──────────────────────────────────────────────────────────────────────
	// ──────────────────────────────────────────────────────────────────────
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'admin/class-archivio-id-admin.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'admin/class-archivio-id-key-admin.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'admin/class-archivio-id-settings-admin.php';
	require_once ARCHIVIO_ID_PLUGIN_DIR . 'admin/class-archivio-id-audit-log-admin.php';
	// Note: class-archivio-id-post-meta-box.php is deprecated - using ArchivioID_Post_Integration
}

// ═══════════════════════════════════════════════════════════════════════════
// PLUGIN BOOTSTRAP
// Initialize the plugin after WordPress loads all plugins
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Main plugin initialization.
 * 
 * Hooked to 'plugins_loaded' to ensure WordPress core is fully loaded
 * and other plugins (especially ArchivioMD) are available.
 * 
 * If dependencies are not met, shows admin notice and self-deactivates.
 * If dependencies are met, loads all classes and initializes the plugin.
 *
 * @since 1.0.0
 * @return void
 */
add_action( 'plugins_loaded', static function () {
	// Validate dependencies first
	if ( ! archivio_id_dependency_ok() ) {
		// Dependencies not met - show notice and deactivate
		add_action( 'admin_notices', 'archivio_id_missing_dependency_notice' );
		add_action( 'admin_init',    'archivio_id_self_deactivate' );
		return;
	}
	
	// Dependencies met - load plugin files and initialize
	archivio_id_load();
	ArchivioID_Plugin::get_instance();
}, 20 );

// ═══════════════════════════════════════════════════════════════════════════
// LIFECYCLE HOOKS
// Register activation and deactivation hooks
// These MUST be at file scope for WordPress to find them
// ═══════════════════════════════════════════════════════════════════════════

register_activation_hook(   __FILE__, array( 'ArchivioID_Plugin', 'on_activate'   ) );
register_deactivation_hook( __FILE__, array( 'ArchivioID_Plugin', 'on_deactivate' ) );

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PLUGIN CLASS
// Singleton pattern for the main plugin orchestrator
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Main ArchivioID Plugin Class.
 * 
 * Singleton class that orchestrates all plugin functionality:
 * - Initializes database tables
 * - Loads admin interfaces
 * - Registers post integration hooks
 * - Displays frontend badges
 * - Handles plugin activation/deactivation
 *
 * @since 1.0.0
 */
final class ArchivioID_Plugin {

	/**
	 * Single instance of this class.
	 *
	 * @since 1.0.0
	 * @var ArchivioID_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 * 
	 * Creates the instance if it doesn't exist yet.
	 *
	 * @since 1.0.0
	 * @return ArchivioID_Plugin The singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize the plugin.
	 * 
	 * Private constructor (singleton pattern).
	 * Sets up all hooks and initializes subsystems.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Load translations
		add_action( 'init', array( $this, 'load_textdomain' ) );
		
		ArchivioID_DB::maybe_create_tables();
		ArchivioID_Audit_Log::maybe_create_table();

		if ( is_admin() ) {
			ArchivioID_Admin::get_instance();
			ArchivioID_Key_Admin::get_instance();
			ArchivioID_Settings_Admin::get_instance();
			new ArchivioID_Audit_Log_Admin();
			// Note: ArchivioID_Post_Meta_Box is deprecated - using ArchivioID_Post_Integration instead
		}
		
		ArchivioID_Post_Integration::get_instance();
		
		ArchivioID_Frontend_Badge::get_instance();
		ArchivioID_Signature_Download::get_instance();
	}

	/**
	 * Load plugin text domain for translations.
	 * 
	 * Allows the plugin to be translated into other languages.
	 * Translation files should be placed in /languages directory.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'archivio-id',
			false,
			dirname( ARCHIVIO_ID_PLUGIN_BASENAME ) . '/languages'
		);
	}

	// ═══════════════════════════════════════════════════════════════════════
	// ACTIVATION HOOK
	// Runs when the plugin is activated
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Plugin activation handler.
	 * 
	 * Performs activation tasks:
	 * 1. Validates dependencies (blocks activation if not met)
	 * 2. Checks vendor libraries are present
	 * 3. Creates database tables
	 * 4. Stores version and installation timestamp
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_activate() {
		// ──────────────────────────────────────────────────────────────────
		// VALIDATION 1: Check ArchivioMD dependency
		// ──────────────────────────────────────────────────────────────────
		if ( ! archivio_id_dependency_ok() ) {
			wp_die(
				esc_html__( 'ArchivioID cannot be activated: ArchivioMD is not active. Install and activate ArchivioMD first.', 'archivio-id' ),
				esc_html__( 'Activation Error', 'archivio-id' ),
				array( 'back_link' => true )
			);
		}

		// ──────────────────────────────────────────────────────────────────
		// VALIDATION 2: Check phpseclib v3 is installed
		// ──────────────────────────────────────────────────────────────────
		$phpseclib_path = ARCHIVIO_ID_PLUGIN_DIR . 'vendor/phpseclib/phpseclib/bootstrap.php';
		if ( ! file_exists( $phpseclib_path ) ) {
			wp_die(
				esc_html__( 'ArchivioID cannot be activated: phpseclib v3 library is not installed. Run composer install or contact your administrator.', 'archivio-id' ),
				esc_html__( 'Activation Error', 'archivio-id' ),
				array( 'back_link' => true )
			);
		}

		// ──────────────────────────────────────────────────────────────────
		// VALIDATION 3: Check OpenPGP-PHP library is present
		// ──────────────────────────────────────────────────────────────────
		$openpgp_path = ARCHIVIO_ID_PLUGIN_DIR . 'vendor/openpgp-php/openpgp.php';
		if ( ! file_exists( $openpgp_path ) ) {
			wp_die(
				esc_html__( 'ArchivioID cannot be activated: OpenPGP-PHP library is not installed. See vendor/openpgp-php/INSTALL.md for instructions.', 'archivio-id' ),
				esc_html__( 'Activation Error', 'archivio-id' ),
				array( 'back_link' => true )
			);
		}

		// ──────────────────────────────────────────────────────────────────
		// DATABASE SETUP: Create tables if needed
		// ──────────────────────────────────────────────────────────────────
		if ( ! class_exists( 'ArchivioID_DB' ) ) {
			require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-db.php';
		}
		ArchivioID_DB::create_tables();
		
		if ( ! class_exists( 'ArchivioID_Audit_Log' ) ) {
			require_once ARCHIVIO_ID_PLUGIN_DIR . 'includes/class-archivio-id-audit-log.php';
		}
		ArchivioID_Audit_Log::create_table();
		
		// ──────────────────────────────────────────────────────────────────
		// STORE VERSION & INSTALLATION TIMESTAMP
		// ──────────────────────────────────────────────────────────────────
		update_option( 'archivio_id_version',      ARCHIVIO_ID_VERSION );
		update_option( 'archivio_id_installed_at', current_time( 'mysql' ) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// DEACTIVATION HOOK
	// Runs when the plugin is deactivated
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Plugin deactivation handler.
	 * 
	 * Performs cleanup tasks:
	 * - Flushes rewrite rules (in case we had custom rules)
	 * 
	 * Note: Does NOT delete data (follows WordPress best practices).
	 * Data is only deleted if user explicitly uninstalls via uninstall.php.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_deactivate() {
		flush_rewrite_rules();
	}
}
