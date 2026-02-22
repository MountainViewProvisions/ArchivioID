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

delete_option( 'archivio_id_version' );
delete_option( 'archivio_id_installed_at' );
delete_option( ArchivioID_Audit_Log::TABLE_VERSION_KEY );
