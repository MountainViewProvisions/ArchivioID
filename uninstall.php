<?php
/**
 * ArchivioID Uninstall
 *
 * Removes all plugin data from the database.
 * Called by WordPress when the plugin is deleted from the Plugins screen.
 *
 * @package ArchivioID
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Guard: direct access not allowed.
}

// Load only what's needed — do not boot the full plugin.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-archivio-id-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-archivio-id-audit-log.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-archivio-id-browser-sig-db.php';

ArchivioID_DB::drop_tables();
ArchivioID_Audit_Log::drop_table();
ArchivioID_Browser_Sig_DB::drop_table();

// Core plugin options (set on activation / version tracking).
delete_option( 'archivio_id_version' );
delete_option( 'archivio_id_installed_at' );
delete_option( ArchivioID_Audit_Log::TABLE_VERSION_KEY );

// Schema version options (set by DB classes).
delete_option( 'archivio_id_db_version' );
delete_option( 'archivio_id_browser_sig_db_version' );

// Cron summary option.
delete_option( 'archivio_id_last_cron_run' );

// All options registered via register_setting() in class-archivio-id-settings-admin.php.
$registered_options = array(
	'archivio_id_badge_enabled',
	'archivio_id_badge_position',
	'archivio_id_show_on_pages',
	'archivio_id_show_on_posts',
	'archivio_id_show_backend_info',
	'archivio_id_allow_key_server_lookup',
	'archivio_id_cron_enabled',
	'archivio_id_cron_recheck_crypto',
	'archivio_id_expiry_emails',
	'archivio_id_algo_enforcement_enabled',
	'archivio_id_algo_reject_hash_ids',
	'archivio_id_algo_min_rsa_bits',
	'archivio_id_algo_reject_elgamal',
	'archivio_id_algo_reject_dsa',
	'archivio_id_sig_threshold',
	'archivio_id_sig_threshold_mode',
	'archivio_id_sig_threshold_by_type',
);

foreach ( $registered_options as $option ) {
	delete_option( $option );
}
