<?php
/**
 * ArchivioID Post Integration
 *
 * Extends the ArchivioMD post-editor experience using standard WP hooks only.
 * No ArchivioMD files are modified.
 *
 * Hooks used:
 *   add_meta_boxes           — inject our meta box into post edit screens
 *   save_post                — handle signature upload (multipart form data)
 *   wp_ajax_archivio_id_*   — AJAX handlers for verify + delete actions
 *   admin_enqueue_scripts    — load our JS/CSS only on relevant screens
 *
 * @package ArchivioID
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Post_Integration {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes',              array( $this, 'register_meta_box'      ) );
		add_action( 'save_post',                   array( $this, 'handle_signature_upload' ), 20, 2 );
		add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_assets'          ) );
		add_action( 'post_edit_form_tag',          array( $this, 'add_enctype_to_form'    ) );

		// AJAX handlers.
		add_action( 'wp_ajax_archivio_id_verify',           array( $this, 'ajax_verify'           ) );
		add_action( 'wp_ajax_archivio_id_delete_signature', array( $this, 'ajax_delete_signature' ) );
		add_action( 'wp_ajax_archivio_id_get_status',       array( $this, 'ajax_get_status'       ) );
	}

	// ── Enable file uploads ───────────────────────────────────────────────────

	/**
	 * Add enctype attribute to post editor form to enable file uploads.
	 */
	public function add_enctype_to_form() {
		echo ' enctype="multipart/form-data"';
	}

	// ── Meta box ──────────────────────────────────────────────────────────────

	public function register_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		add_meta_box(
			'archivio_id_signature',
			__( 'ArchivioID — GPG Signature', 'archivio-id' ),
			array( $this, 'render_meta_box' ),
			$post_types,
			'side',
			'default'
		);
	}

	public function render_meta_box( $post ) {
		// Only useful when ArchivioMD has generated a hash.
		$packed_hash = get_post_meta( $post->ID, '_archivio_post_hash', true );
		$sig_row     = ArchivioID_Signature_Store::get( $post->ID );
		$active_keys = ArchivioID_Key_Manager::get_active_keys();

		wp_nonce_field( 'archivio_id_upload_sig_' . $post->ID, 'archivio_id_sig_nonce' );

		require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/meta-box-signature.php';
	}

	// ── Signature upload (via save_post form submission) ──────────────────────

	/**
	 * Process the .asc file upload attached to the post save action.
	 *
	 * We deliberately do NOT block or redirect on failure; we just skip silently
	 * or store an admin transient. The post save must never be broken.
	 */
	public function handle_signature_upload( $post_id, $post ) {
		// Guard: autosave, revisions, no nonce.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: Autosave detected' );
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: Revision/autosave detected' );
			return;
		}
		if ( ! isset( $_POST['archivio_id_sig_nonce'] ) ) {
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: No nonce found in POST data' );
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['archivio_id_sig_nonce'] ) ),
			'archivio_id_upload_sig_' . $post_id
		) ) {
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: Nonce verification failed' );
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: No permission to edit post' );
			return;
		}

		// Has the user selected a key?
		$key_db_id = isset( $_POST['archivio_id_key_id'] ) ? absint( $_POST['archivio_id_key_id'] ) : 0;
		if ( ! $key_db_id ) {
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: No key selected (key_id is ' . $key_db_id . ')' );
			return;
		}

		// Was a file uploaded?
		if ( empty( $_FILES['archivio_id_sig_file']['tmp_name'] ) ) {
			$file_info = isset( $_FILES['archivio_id_sig_file'] ) ? json_encode( $_FILES['archivio_id_sig_file'] ) : 'FILES array empty';
			$this->set_upload_notice( $post_id, 'error', 'DEBUG: No file uploaded. FILES data: ' . $file_info );
			return;
		}

		$upload_error = $_FILES['archivio_id_sig_file']['error'] ?? UPLOAD_ERR_NO_FILE;
		if ( $upload_error !== UPLOAD_ERR_OK ) {
			$this->set_upload_notice( $post_id, 'error', __( 'File upload failed. Please try again.', 'archivio-id' ) );
			return;
		}

		// ── Validate file type ────────────────────────────────────────────────
		$orig_name = sanitize_file_name( wp_unslash( $_FILES['archivio_id_sig_file']['name'] ?? '' ) );
		$ext       = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( $ext !== 'asc' ) {
			$this->set_upload_notice( $post_id, 'error', __( 'Only .asc files are accepted.', 'archivio-id' ) );
			return;
		}

		// ── File size guard (4 KB is more than enough for any detached sig) ─
		$tmp_path = $_FILES['archivio_id_sig_file']['tmp_name'];
		if ( ! is_uploaded_file( $tmp_path ) ) {
			return;
		}
		if ( filesize( $tmp_path ) > 4096 ) {
			$this->set_upload_notice( $post_id, 'error', __( 'Signature file exceeds maximum size (4 KB).', 'archivio-id' ) );
			return;
		}

		// ── Read contents ────────────────────────────────────────────────────
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$sig_contents = file_get_contents( $tmp_path );
		if ( false === $sig_contents ) {
			$this->set_upload_notice( $post_id, 'error', __( 'Could not read uploaded file.', 'archivio-id' ) );
			return;
		}

		// ── Validate signature structure ──────────────────────────────────────
		if ( ! ArchivioID_Signature_Store::looks_like_detached_signature( $sig_contents ) ) {
			$this->set_upload_notice( $post_id, 'error', __( 'The uploaded file does not appear to be a PGP detached signature.', 'archivio-id' ) );
			return;
		}

		// ── Get hash metadata from ArchivioMD meta ────────────────────────────
		$packed_hash = get_post_meta( $post_id, '_archivio_post_hash', true );
		$hash_algo   = 'sha256';
		$hash_mode   = 'standard';

		if ( ! empty( $packed_hash ) && class_exists( 'MDSM_Hash_Helper' ) ) {
			$unpacked   = MDSM_Hash_Helper::unpack( $packed_hash );
			$hash_algo  = $unpacked['algorithm'];
			$hash_mode  = $unpacked['mode'];
		}

		// ── Verify key exists ────────────────────────────────────────────────
		$key_row = ArchivioID_Key_Manager::get_key( $key_db_id );
		if ( ! $key_row || ! $key_row->is_active ) {
			$this->set_upload_notice( $post_id, 'error', __( 'Selected key not found or inactive.', 'archivio-id' ) );
			return;
		}

		// ── Store the signature ───────────────────────────────────────────────
		$stored = ArchivioID_Signature_Store::upsert_upload(
			$post_id,
			$key_db_id,
			$sig_contents,
			$packed_hash,
			$hash_algo,
			$hash_mode,
			get_current_user_id()
		);

		if ( $stored ) {
			if ( class_exists( 'ArchivioID_Audit_Log' ) ) {
				ArchivioID_Audit_Log::log_event(
					$post_id,
					'upload',
					$key_row->fingerprint,
					$hash_algo,
					'unverified',
					''
				);
			}
			
			$this->set_upload_notice( $post_id, 'success', __( 'Signature uploaded. Click "Verify Signature" to confirm.', 'archivio-id' ) );
		} else {
			$this->set_upload_notice( $post_id, 'error', __( 'Database error storing signature. Please try again.', 'archivio-id' ) );
		}
	}

	// ── AJAX: verify ──────────────────────────────────────────────────────────

	/**
	 * AJAX handler for signature verification.
	 *
	 * UPDATED: Now includes comprehensive cache invalidation and enhanced response data
	 * to ensure UI updates properly reflect the verified state.
	 *
	 * Flow:
	 * 1. Verify nonce and permissions
	 * 2. Check rate limiting (30-second cooldown per user per post)
	 * 3. Perform PGP signature verification
	 * 4. **CACHE INVALIDATION**: Clear WordPress and object caches
	 * 5. Return enhanced JSON response with fresh signature data
	 *
	 * @since 1.0.0
	 * @since 1.1.1 Added cache invalidation and enhanced response data
	 */
	public function ajax_verify() {
		check_ajax_referer( 'archivio_id_post_action', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'archivio-id' ) ) );
		}

		// Rate limiting: prevent spam verification requests.
		$user_id  = get_current_user_id();
		$rate_key = 'archivio_id_verify_limit_' . $user_id . '_' . $post_id;

		if ( get_transient( $rate_key ) ) {
			wp_send_json_error( array(
				'message' => __( 'Please wait 30 seconds before verifying this signature again.', 'archivio-id' ),
			) );
		}

		// ══════════════════════════════════════════════════════════════════════
		// ══════════════════════════════════════════════════════════════════════
		$result = ArchivioID_Verifier::verify_post( $post_id );

		set_transient( $rate_key, true, 30 );

		// ══════════════════════════════════════════════════════════════════════
		// STEP 2: CACHE INVALIDATION (Fix for UI sync issue)
		// ══════════════════════════════════════════════════════════════════════
		// After verification, we must clear all caches to ensure the UI reflects
		// the updated signature status immediately.
		//
		// Why this is needed:
		// - WordPress post cache may store stale post data
		// - Object cache (Redis/Memcached) may cache signature rows
		// - Browser/proxy caches may serve stale AJAX responses
		//
		// This ensures that subsequent database queries return fresh data.
		// ══════════════════════════════════════════════════════════════════════
		
		clean_post_cache( $post_id );                              // Clear WordPress post cache
		wp_cache_delete( $post_id, 'archivio_id_signatures' );    // Clear object cache for signature data
		nocache_headers();                                          // Prevent browser/proxy caching of this response

		// Log cache invalidation for debugging
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			archivio_id_log( sprintf(
				'Cache invalidated for post %d after verification (status: %s)',
				$post_id,
				$result['status'] ?? 'unknown'
			) );
		}

		// ══════════════════════════════════════════════════════════════════════
		// ══════════════════════════════════════════════════════════════════════
		// Fetch fresh signature data from database AFTER cache clear to ensure
		// JavaScript receives the most current state.
		//
		// This allows the front-end to update badges immediately without needing
		// a separate AJAX call or page reload (though reload is still recommended
		// for persistent UI elements).
		// ══════════════════════════════════════════════════════════════════════

		if ( $result['success'] ) {
			// Fetch fresh signature row AFTER cache invalidation
			$sig_row = ArchivioID_Signature_Store::get( $post_id );

			// Build enhanced response with all data needed for UI update
			$enhanced_result = array_merge( $result, array(
				// Badge display data
				'badge_label' => $this->get_badge_label( $sig_row ? $sig_row->status : 'not_signed' ),
				'badge_class' => $sig_row ? $sig_row->status : 'not_signed',
				
				// Fresh signature metadata
				'sig_data' => array(
					'status'         => $sig_row ? $sig_row->status : 'not_signed',
					'verified_at'    => $sig_row && $sig_row->verified_at ? $sig_row->verified_at : null,
					'uploaded_at'    => $sig_row && $sig_row->uploaded_at ? $sig_row->uploaded_at : null,
					'failure_reason' => $sig_row && $sig_row->failure_reason ? $sig_row->failure_reason : '',
				),
				
				// UI guidance flag (tells JavaScript to reload page for full refresh)
				'should_reload' => true,
			) );

			wp_send_json_success( $enhanced_result );
		} else {
			// Even on failure, include fresh signature data for error display
			$sig_row = ArchivioID_Signature_Store::get( $post_id );

			$result['badge_label'] = $this->get_badge_label( $result['status'] ?? 'error' );
			$result['badge_class'] = $result['status'] ?? 'error';
			$result['sig_data'] = array(
				'status'         => $sig_row ? $sig_row->status : 'not_signed',
				'verified_at'    => null,
				'uploaded_at'    => $sig_row && $sig_row->uploaded_at ? $sig_row->uploaded_at : null,
				'failure_reason' => $sig_row && $sig_row->failure_reason ? $sig_row->failure_reason : ( $result['message'] ?? '' ),
			);

			wp_send_json_error( $result );
		}
	}

	// ── AJAX: delete signature ────────────────────────────────────────────────

	/**
	 * AJAX handler for signature deletion.
	 *
	 * UPDATED: Now includes cache invalidation after deletion.
	 *
	 * @since 1.0.0
	 * @since 1.1.1 Added cache invalidation
	 */
	public function ajax_delete_signature() {
		check_ajax_referer( 'archivio_id_post_action', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'archivio-id' ) ) );
		}

		// Get signature info before deletion for audit log
		$sig_row = null;
		$key_fingerprint = '';
		$hash_algo = 'sha256';
		
		if ( class_exists( 'ArchivioID_Audit_Log' ) ) {
			$sig_row = ArchivioID_Signature_Store::get( $post_id );
			if ( $sig_row ) {
				$key_row = ArchivioID_Key_Manager::get_key( $sig_row->key_id );
				$key_fingerprint = $key_row ? $key_row->fingerprint : '';
				$hash_algo = $sig_row->hash_algorithm ?? 'sha256';
			}
		}
		
		$deleted = ArchivioID_Signature_Store::delete( $post_id );
		
		// ══════════════════════════════════════════════════════════════════════
		// CACHE INVALIDATION: Clear caches after deletion
		// ══════════════════════════════════════════════════════════════════════
		if ( $deleted ) {
			if ( class_exists( 'ArchivioID_Audit_Log' ) && $sig_row ) {
				ArchivioID_Audit_Log::log_event(
					$post_id,
					'delete',
					$key_fingerprint,
					$hash_algo,
					$sig_row->status ?? 'unknown',
					'Signature deleted by user'
				);
			}
			
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'archivio_id_signatures' );
			
			wp_send_json_success( array( 'message' => __( 'Signature removed.', 'archivio-id' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not remove signature.', 'archivio-id' ) ) );
		}
	}

	// ── AJAX: get status ──────────────────────────────────────────────────────

	/**
	 * AJAX handler to fetch current signature status.
	 *
	 * Used by JavaScript to confirm status after verification or for periodic checks.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_status() {
		check_ajax_referer( 'archivio_id_post_action', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'archivio-id' ) ) );
		}

		$status  = ArchivioID_Signature_Store::get_status( $post_id );
		$sig_row = ArchivioID_Signature_Store::get( $post_id );

		wp_send_json_success( array(
			'status'         => $status,
			'badge_label'    => $this->get_badge_label( $status ),
			'badge_class'    => $status,
			'verified_at'    => $sig_row ? $sig_row->verified_at   : null,
			'uploaded_at'    => $sig_row ? $sig_row->uploaded_at   : null,
			'failure_reason' => $sig_row ? $sig_row->failure_reason : '',
		) );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( $hook ) {
		// Only on post edit screens.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'archivio-id-post',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/css/archivio-id-post.css',
			array(),
			ARCHIVIO_ID_VERSION
		);

		wp_enqueue_script(
			'archivio-id-post',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-post.js',
			array( 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);

		wp_localize_script( 'archivio-id-post', 'archivioIdPost', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'archivio_id_post_action' ),
			'postId'  => (int) get_the_ID(),
			'strings' => array(
				'verifying'       => __( 'Verifying…',           'archivio-id' ),
				'verified'        => __( 'Verified ✓',           'archivio-id' ),
				'invalid'         => __( 'Invalid ✗',            'archivio-id' ),
				'error'           => __( 'Error',                 'archivio-id' ),
				'confirmDelete'   => __( 'Remove this signature?', 'archivio-id' ),
				'deleted'         => __( 'Signature removed.',   'archivio-id' ),
				'requestFailed'   => __( 'Request failed. Please try again.', 'archivio-id' ),
				'reloadingPage'   => __( 'Signature verified! Refreshing page...', 'archivio-id' ),
			),
		) );
	}

	// ── Admin transient notice helpers ────────────────────────────────────────

	private function set_upload_notice( $post_id, $type, $message ) {
		set_transient(
			'archivio_id_upload_notice_' . $post_id . '_' . get_current_user_id(),
			array( 'type' => $type, 'message' => $message ),
			60
		);
	}

	// ── Badge label helper ────────────────────────────────────────────────────

	/**
	 * Get human-readable badge label for a given status.
	 *
	 * @since 1.1.1
	 * @param string $status Status code (not_signed, uploaded, verified, invalid, error)
	 * @return string Translated badge label
	 */
	private function get_badge_label( $status ) {
		$labels = array(
			'not_signed' => __( 'Not Signed',         'archivio-id' ),
			'uploaded'   => __( 'Signature Uploaded', 'archivio-id' ),
			'verified'   => __( 'Verified',           'archivio-id' ),
			'invalid'    => __( 'Invalid Signature',  'archivio-id' ),
			'error'      => __( 'Error',              'archivio-id' ),
		);

		return $labels[ $status ] ?? $labels['error'];
	}
}
