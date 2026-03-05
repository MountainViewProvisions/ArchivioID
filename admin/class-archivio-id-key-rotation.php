<?php
/**
 * ArchivioID Key Rotation
 *
 * Provides a UI workflow to formally retire one signing key and
 * re-assign all posts that carry its signature to a replacement key.
 *
 * What "rotation" does
 * ────────────────────
 * 1.  The user selects an OLD key (being retired) and a NEW key
 *     (already imported and verified).
 * 2.  Every row in archivio_id_signatures that references old_key_id
 *     has its key_id column updated to new_key_id.
 * 3.  The signature status is reset to 'uploaded' so the admin must
 *     explicitly re-verify each post against the new key. This is
 *     intentional: a rotated signature has not yet been proven valid
 *     against the new key.
 * 4.  The old key's is_active flag is set to 0 (deactivated, NOT
 *     deleted — audit history is preserved).
 * 5.  Every affected post_id is written to the audit log as
 *     event_type = 'key_rotation'.
 *
 * Why not auto-verify?
 * ────────────────────
 * Because the existing .asc file was signed by the OLD key. The NEW key
 * cannot verify a signature it never created. Rotation only re-assigns
 * the DB relationship — the user must re-sign posts offline with the new
 * key and re-upload. The UI makes this clear.
 *
 * Menu position: ArchivioID → Key Rotation
 *
 * @package ArchivioID
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Key_Rotation_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',                              array( $this, 'register_submenu' ), 27 );
		add_action( 'admin_enqueue_scripts',                   array( $this, 'enqueue_assets'   ) );
		add_action( 'wp_ajax_archivio_id_rotation_preview',   array( $this, 'ajax_preview'     ) );
		add_action( 'wp_ajax_archivio_id_rotation_execute',   array( $this, 'ajax_execute'     ) );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	/**
	 * Enqueue JS for the Key Rotation page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'archivio-id_page_archivio-id-key-rotation' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'archivio-id-key-rotation',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-key-rotation.js',
			array( 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);
		wp_localize_script( 'archivio-id-key-rotation', 'archivioIdRotation', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'archivio_id_admin_action' ),
			'i18n'    => array(
				'selectBothKeys' => esc_html__( 'Please select both keys.',                 'archivio-id' ),
				'keysMustDiffer' => esc_html__( 'Old and new keys must be different.',      'archivio-id' ),
				'previewFailed'  => esc_html__( 'Preview failed.',                         'archivio-id' ),
				'rotationFailed' => esc_html__( 'Rotation failed.',                        'archivio-id' ),
				'requestFailed'  => esc_html__( 'Request failed.',                         'archivio-id' ),
				'noPostsSigned'  => esc_html__( 'No posts signed by this key.',            'archivio-id' ),
				'oldKey'         => esc_html__( 'Old key:',                                'archivio-id' ),
				'newKey'         => esc_html__( 'New key:',                                'archivio-id' ),
				'postsAffected'  => esc_html__( 'Posts affected:',                         'archivio-id' ),
			),
		) );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function register_submenu() {
		add_submenu_page(
			'archivio-id',
			__( 'Key Rotation — ArchivioID', 'archivio-id' ),
			__( 'Key Rotation', 'archivio-id' ),
			'manage_options',
			'archivio-id-key-rotation',
			array( $this, 'render_page' )
		);
	}

	// ── AJAX: preview ─────────────────────────────────────────────────────────

	/**
	 * Return the list of posts that would be re-assigned.
	 * Called before the user confirms the rotation.
	 */
	public function ajax_preview() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archivio-id' ) ), 403 );
		}

		$old_id = isset( $_POST['old_key_id'] ) ? absint( $_POST['old_key_id'] ) : 0;
		$new_id = isset( $_POST['new_key_id'] ) ? absint( $_POST['new_key_id'] ) : 0;

		$validation = $this->validate_keys( $old_id, $new_id );
		if ( ! $validation['success'] ) {
			wp_send_json_error( array( 'message' => $validation['message'] ) );
		}

		global $wpdb;
		$sigs_table = ArchivioID_DB::signatures_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.post_id, s.status, s.uploaded_at
				 FROM {$sigs_table} s
				 WHERE s.key_id = %d
				 ORDER BY s.post_id ASC",
				$old_id
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$post    = get_post( (int) $row->post_id );
			$items[] = array(
				'post_id'     => (int) $row->post_id,
				'title'       => $post ? $post->post_title : __( '(post not found)', 'archivio-id' ),
				'post_status' => $post ? $post->post_status : '—',
				'sig_status'  => $row->status,
				'uploaded_at' => $row->uploaded_at,
			);
		}

		wp_send_json_success( array(
			'count' => count( $items ),
			'posts' => $items,
			'old_key' => array(
				'id'          => $validation['old_key']->id,
				'label'       => $validation['old_key']->label,
				'fingerprint' => $validation['old_key']->fingerprint,
			),
			'new_key' => array(
				'id'          => $validation['new_key']->id,
				'label'       => $validation['new_key']->label,
				'fingerprint' => $validation['new_key']->fingerprint,
			),
		) );
	}

	// ── AJAX: execute ─────────────────────────────────────────────────────────

	/**
	 * Perform the rotation after the user has confirmed the preview.
	 */
	public function ajax_execute() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archivio-id' ) ), 403 );
		}

		$old_id = isset( $_POST['old_key_id'] ) ? absint( $_POST['old_key_id'] ) : 0;
		$new_id = isset( $_POST['new_key_id'] ) ? absint( $_POST['new_key_id'] ) : 0;

		$validation = $this->validate_keys( $old_id, $new_id );
		if ( ! $validation['success'] ) {
			wp_send_json_error( array( 'message' => $validation['message'] ) );
		}

		$result = $this->perform_rotation( $old_id, $new_id );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: 1: post count, 2: key label */
					__( 'Rotation complete. %1$d post(s) re-assigned. Key "%2$s" deactivated.', 'archivio-id' ),
					$result['rotated'],
					$validation['old_key']->label
				),
				'rotated' => $result['rotated'],
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	// ── Core rotation logic (shared with CLI) ─────────────────────────────────

	/**
	 * Execute the key rotation.
	 *
	 * Public so the CLI command can call it directly.
	 *
	 * @param  int $old_id  Key DB id being retired
	 * @param  int $new_id  Key DB id of the replacement
	 * @return array{ success: bool, rotated: int, message: string }
	 */
	public function perform_rotation( $old_id, $new_id ) {
		global $wpdb;

		$sigs_table = ArchivioID_DB::signatures_table();
		$keys_table = ArchivioID_DB::keys_table();

		// Collect affected post IDs before the UPDATE so we can audit each one
		$affected_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT post_id FROM {$sigs_table} WHERE key_id = %d", $old_id )
		);

		if ( empty( $affected_ids ) ) {
			// Nothing to do — still deactivate the old key
			$this->deactivate_key( $old_id, $new_id );
			return array( 'success' => true, 'rotated' => 0, 'message' => '' );
		}

		// ── Re-assign key_id and reset status to 'uploaded' ──────────────────
		// Status is intentionally reset: the .asc on file was signed by the
		// old key. The new key has not yet been used to sign these posts.
		// The admin must re-sign each post offline and re-upload.
		$updated = $wpdb->update(
			$sigs_table,
			array(
				'key_id'         => $new_id,
				'status'         => ArchivioID_Signature_Store::STATUS_UPLOADED,
				'verified_at'    => null,
				'failure_reason' => __( 'Key rotated — please re-sign and re-upload.', 'archivio-id' ),
				'sig_metadata'   => null,
			),
			array( 'key_id' => $old_id ),
			array( '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $updated === false ) {
			return array(
				'success' => false,
				'rotated' => 0,
				'message' => __( 'Database update failed during rotation.', 'archivio-id' ),
			);
		}

		// ── Deactivate old key ────────────────────────────────────────────────
		$this->deactivate_key( $old_id, $new_id );

		// ── Audit log ─────────────────────────────────────────────────────────
		$old_key = ArchivioID_Key_Manager::get_key( $old_id );
		$new_key = ArchivioID_Key_Manager::get_key( $new_id );

		if ( class_exists( 'ArchivioID_Audit_Log' ) ) {
			$note = sprintf(
				'Key rotation: old=%s new=%s',
				$old_key ? $old_key->fingerprint : $old_id,
				$new_key ? $new_key->fingerprint : $new_id
			);
			foreach ( $affected_ids as $pid ) {
				ArchivioID_Audit_Log::log_event(
					(int) $pid,
					'key_rotation',
					$old_key ? $old_key->fingerprint : '',
					'—',
					'uploaded',
					substr( $note, 0, 512 )
				);
				clean_post_cache( (int) $pid );
			}
		}

		archivio_id_log( sprintf(
			'Key rotation: old_key=%d → new_key=%d  affected posts=%d',
			$old_id, $new_id, count( $affected_ids )
		) );

		return array(
			'success' => true,
			'rotated' => count( $affected_ids ),
			'message' => '',
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Validate old/new key IDs and return the resolved key objects.
	 *
	 * @param  int $old_id
	 * @param  int $new_id
	 * @return array{ success: bool, message?: string, old_key?: object, new_key?: object }
	 */
	private function validate_keys( $old_id, $new_id ) {
		if ( ! $old_id || ! $new_id ) {
			return array( 'success' => false, 'message' => __( 'Both keys must be selected.', 'archivio-id' ) );
		}

		if ( $old_id === $new_id ) {
			return array( 'success' => false, 'message' => __( 'Old and new keys must be different.', 'archivio-id' ) );
		}

		$old_key = ArchivioID_Key_Manager::get_key( $old_id );
		if ( ! $old_key ) {
			return array( 'success' => false, 'message' => __( 'Old key not found.', 'archivio-id' ) );
		}

		$new_key = ArchivioID_Key_Manager::get_key( $new_id );
		if ( ! $new_key ) {
			return array( 'success' => false, 'message' => __( 'New key not found.', 'archivio-id' ) );
		}

		if ( ! $new_key->is_active ) {
			return array( 'success' => false, 'message' => __( 'New key must be active.', 'archivio-id' ) );
		}

		if ( $new_key->is_revoked ) {
			return array( 'success' => false, 'message' => __( 'New key has been revoked.', 'archivio-id' ) );
		}

		return array( 'success' => true, 'old_key' => $old_key, 'new_key' => $new_key );
	}

	/**
	 * Set the old key to inactive and record which key replaced it.
	 *
	 * @param int $old_id
	 * @param int $new_id
	 */
	private function deactivate_key( $old_id, $new_id ) {
		global $wpdb;
		$wpdb->update(
			ArchivioID_DB::keys_table(),
			array( 'is_active' => 0 ),
			array( 'id' => $old_id ),
			array( '%d' ),
			array( '%d' )
		);
		// Store the successor key ID in options so the UI can show it
		update_option( 'archivio_id_key_successor_' . $old_id, $new_id, false );
	}

	// ── Page renderer ─────────────────────────────────────────────────────────

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}

		global $wpdb;
		$keys_table = ArchivioID_DB::keys_table();
		$all_keys   = $wpdb->get_results(
			"SELECT id, label, fingerprint, key_id, key_algorithm, key_expires_at, is_active, is_revoked
			 FROM {$keys_table}
			 ORDER BY is_active DESC, created_at DESC"
		);

		// Count how many posts each key has signed
		$sig_table = ArchivioID_DB::signatures_table();
		$sig_counts = $wpdb->get_results(
			"SELECT key_id, COUNT(*) AS cnt FROM {$sig_table} GROUP BY key_id"
		);
		$count_map = array();
		foreach ( $sig_counts as $row ) {
			$count_map[ $row->key_id ] = (int) $row->cnt;
		}

		require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/key-rotation.php';
	}
}
