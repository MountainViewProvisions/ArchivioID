<?php
/**
 * ArchivioID Audit Log Admin Page
 *
 * Displays audit logs and provides CSV export functionality.
 *
 * @package ArchivioID
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Audit_Log_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 30 );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_delete_old_logs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'archivio-id',
			__( 'Audit Logs', 'archivio-id' ),
			__( 'Audit Logs', 'archivio-id' ),
			'manage_options',
			'archivio-id-audit-logs',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'archivio-id_page_archivio-id-audit-logs' ) {
			return;
		}

		wp_enqueue_style(
			'archivio-id-audit-log',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/css/admin-audit-log.css',
			array(),
			ARCHIVIO_ID_VERSION
		);
	}

	public function handle_export() {
		if ( ! isset( $_GET['archivio_id_export_logs'] ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'archivio_id_export_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'archivio-id' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}

		$args = array(
			'limit' => 10000,
		);

		if ( ! empty( $_GET['start_date'] ) ) {
			$args['start_date'] = sanitize_text_field( $_GET['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $_GET['end_date'] ) ) {
			$args['end_date'] = sanitize_text_field( $_GET['end_date'] ) . ' 23:59:59';
		}

		if ( ! empty( $_GET['status'] ) ) {
			$args['status'] = sanitize_key( $_GET['status'] );
		}

		$csv = ArchivioID_Audit_Log::export_to_csv( $args );

		$filename = 'archivio-id-audit-log-' . gmdate( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo "\xEF\xBB\xBF";
		echo $csv;
		exit;
	}

	public function handle_delete_old_logs() {
		if ( ! isset( $_POST['archivio_id_delete_old_logs'] ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'archivio_id_delete_old_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'archivio-id' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}

		$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 90;
		$deleted = ArchivioID_Audit_Log::delete_old_logs( $days );

		add_settings_error(
			'archivio_id_audit_log',
			'logs_deleted',
			sprintf(
				/* translators: %d: number of deleted entries */
				__( 'Deleted %d log entries older than %d days.', 'archivio-id' ),
				$deleted,
				$days
			),
			'updated'
		);
	}

	public function render_page() {
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 50;
		$offset = ( $paged - 1 ) * $per_page;

		$args = array(
			'limit'  => $per_page,
			'offset' => $offset,
		);

		$filter_status = '';
		if ( ! empty( $_GET['filter_status'] ) ) {
			$filter_status = sanitize_key( $_GET['filter_status'] );
			$args['status'] = $filter_status;
		}

		$logs = ArchivioID_Audit_Log::get_logs( $args );
		$total_logs = ArchivioID_Audit_Log::get_log_count( $args );
		$total_pages = ceil( $total_logs / $per_page );

		require_once ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/audit-log-page.php';
	}
}
