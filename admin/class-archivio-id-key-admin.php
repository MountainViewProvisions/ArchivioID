<?php
/**
 * ArchivioID Key Management Admin Page
 *
 * Handles the "Key Management" submenu under ArchivioID:
 *   - Lists all stored keys with status, fingerprint, Key ID.
 *   - Add-new-key form (armored key textarea + label).
 *   - Activate / Deactivate / Delete key actions.
 *   - All actions are nonce-protected and capability-checked.
 *
 * @package ArchivioID
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Key_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );

		// AJAX handlers for key actions.
		add_action( 'wp_ajax_archivio_id_add_key',        array( $this, 'ajax_add_key'        ) );
		add_action( 'wp_ajax_archivio_id_delete_key',     array( $this, 'ajax_delete_key'     ) );
		add_action( 'wp_ajax_archivio_id_deactivate_key', array( $this, 'ajax_deactivate_key' ) );
		add_action( 'wp_ajax_archivio_id_activate_key',   array( $this, 'ajax_activate_key'   ) );
		add_action( 'wp_ajax_archivio_id_revoke_key',     array( $this, 'ajax_revoke_key'     ) );
		add_action( 'wp_ajax_archivio_id_update_identity_proof', array( $this, 'ajax_update_identity_proof' ) );
	}

	public function register_submenu() {
		add_submenu_page(
			'archivio-id',
			__( 'Key Management — ArchivioID', 'archivio-id' ),
			__( 'Key Management', 'archivio-id' ),
			'manage_options',
			'archivio-id-keys',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}
		$page      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$key_data  = ArchivioID_Key_Manager::get_all_keys( $page, 20 );
		require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/key-management.php';
	}

	// ── AJAX: Add key ─────────────────────────────────────────────────────────

	public function ajax_add_key() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'archivio-id' ) ) );
		}

		$armored       = isset( $_POST['armored_key'] )       ? wp_unslash( $_POST['armored_key'] )       : '';
		$label         = isset( $_POST['label'] )             ? wp_unslash( $_POST['label'] )             : '';
		$identity_url  = isset( $_POST['identity_proof_url'] ) ? wp_unslash( $_POST['identity_proof_url'] ) : '';

		$result = ArchivioID_Key_Manager::add_key( $armored, $label, get_current_user_id(), $identity_url );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	// ── AJAX: Delete key ──────────────────────────────────────────────────────

	public function ajax_delete_key() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'archivio-id' ) ) );
		}

		$id = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid key ID.', 'archivio-id' ) ) );
		}

		$deleted = ArchivioID_Key_Manager::delete_key( $id );
		if ( $deleted ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Key deleted.', 'archivio-id' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Could not delete key.', 'archivio-id' ) ) );
		}
	}

	// ── AJAX: Toggle active state ─────────────────────────────────────────────

	public function ajax_deactivate_key() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'archivio-id' ) ) );
		}
		$id = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
		ArchivioID_Key_Manager::deactivate_key( $id )
			? wp_send_json_success( array( 'message' => esc_html__( 'Key deactivated.', 'archivio-id' ) ) )
			: wp_send_json_error(   array( 'message' => esc_html__( 'Could not deactivate key.', 'archivio-id' ) ) );
	}

	public function ajax_activate_key() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'archivio-id' ) ) );
		}
		$id = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
		ArchivioID_Key_Manager::activate_key( $id )
			? wp_send_json_success( array( 'message' => esc_html__( 'Key activated.', 'archivio-id' ) ) )
			: wp_send_json_error(   array( 'message' => esc_html__( 'Could not activate key.', 'archivio-id' ) ) );
	}

	// ── AJAX: revoke key ──────────────────────────────────────────────────────

	/**
	 * Import a revocation certificate for a key.
	 * Marks the key as revoked and inactive.
	 */
	public function ajax_revoke_key() {
		check_ajax_referer( 'archivio_id_key_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'archivio-id' ) ) );
		}

		$key_id        = isset( $_POST['key_id'] )        ? absint( $_POST['key_id'] )                                        : 0;
		$revocation_asc = isset( $_POST['revocation_asc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['revocation_asc'] ) ) : '';

		if ( ! $key_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid key ID.', 'archivio-id' ) ) );
		}
		if ( empty( $revocation_asc ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Revocation certificate is required.', 'archivio-id' ) ) );
		}

		$result = ArchivioID_Key_Manager::import_revocation( $key_id, $revocation_asc );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	// ── AJAX: Update identity proof URL ──────────────────────────────────────

	/**
	 * Update the Keyoxide / Keybase identity proof URL for an existing key.
	 * Accepting an empty string clears the field.
	 */
	public function ajax_update_identity_proof() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'archivio-id' ) ) );
		}

		$key_id = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;
		$url    = isset( $_POST['identity_proof_url'] ) ? wp_unslash( $_POST['identity_proof_url'] ) : '';

		if ( ! $key_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid key ID.', 'archivio-id' ) ) );
		}

		$ok = ArchivioID_Key_Manager::update_identity_proof( $key_id, $url );

		if ( $ok ) {
			wp_send_json_success( array(
				'message' => esc_html__( 'Identity proof URL updated.', 'archivio-id' ),
				'url'     => ArchivioID_Key_Manager::sanitize_proof_url( $url ),
			) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Could not update identity proof URL.', 'archivio-id' ) ) );
		}
	}


}
