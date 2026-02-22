<?php
/**
 * ArchivioID Post Meta Box Handler
 *
 * Handles the signature verification meta box on post/page edit screens.
 * Implements AJAX-based verification with proper security and error handling.
 *
 * Security Features:
 * - Nonce verification on all AJAX requests
 * - Capability checks (edit_post)
 * - File type and size validation
 * - Rate limiting on verification attempts
 * - Safe error handling with logging
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Post_Meta_Box {

	/**
	 * Singleton instance.
	 *
	 * @var ArchivioID_Post_Meta_Box|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ArchivioID_Post_Meta_Box
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - register hooks.
	 */
	private function __construct() {
		// Register meta box
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		
		// Handle signature upload on post save
		add_action( 'save_post', array( $this, 'handle_signature_upload' ), 20, 2 );
		
		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_archivio_id_verify_signature', array( $this, 'ajax_verify_signature' ) );
		add_action( 'wp_ajax_archivio_id_delete_signature', array( $this, 'ajax_delete_signature' ) );
		add_action( 'wp_ajax_archivio_id_get_backend_info', array( $this, 'ajax_get_backend_info' ) );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Meta Box Registration
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * Register the signature verification meta box.
	 */
	public function register_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		add_meta_box(
			'archivio_id_signature_verification',
			__( 'Signature Verification', 'archivio-id' ),
			array( $this, 'render_meta_box' ),
			$post_types,
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		$sig_row = ArchivioID_Signature_Store::get( $post->ID );
		
		$active_keys = ArchivioID_Key_Manager::get_active_keys();
		
		$packed_hash = get_post_meta( $post->ID, '_archivio_post_hash', true );
		
		$notice_key = 'archivio_id_notice_' . $post->ID . '_' . get_current_user_id();
		$notice = get_transient( $notice_key );
		if ( $notice ) {
			delete_transient( $notice_key );
		}
		
		// Determine status
		$status = $sig_row ? $sig_row->status : 'not_signed';
		
		// Nonce for security
		wp_nonce_field( 'archivio_id_signature_action_' . $post->ID, 'archivio_id_nonce' );
		
		// Include the view
		include ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/meta-box-signature-enhanced.php';
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Signature Upload Handler
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * Handle signature file upload on post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function handle_signature_upload( $post_id, $post ) {
		// Security checks
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		
		if ( ! isset( $_POST['archivio_id_nonce'] ) ) {
			return;
		}
		
		if ( ! wp_verify_nonce( 
			sanitize_text_field( wp_unslash( $_POST['archivio_id_nonce'] ) ), 
			'archivio_id_signature_action_' . $post_id 
		) ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		$key_id = isset( $_POST['archivio_id_key_id'] ) ? absint( $_POST['archivio_id_key_id'] ) : 0;
		if ( ! $key_id ) {
			return; // No key selected, nothing to upload
		}
		
		if ( empty( $_FILES['archivio_id_signature_file']['tmp_name'] ) ) {
			return;
		}
		
		// Validate file upload
		$upload_result = $this->validate_signature_upload();
		if ( is_wp_error( $upload_result ) ) {
			$this->set_notice( $post_id, 'error', $upload_result->get_error_message() );
			return;
		}
		
		// Read file contents
		$signature_content = $upload_result['content'];
		
		$packed_hash = get_post_meta( $post_id, '_archivio_post_hash', true );
		if ( empty( $packed_hash ) ) {
			$this->set_notice( $post_id, 'warning', __( 'No hash found for this post. Please ensure ArchivioMD has generated a hash.', 'archivio-id' ) );
			return;
		}
		
		// Extract hash algorithm and mode
		$hash_algo = 'sha256';
		$hash_mode = 'standard';
		
		if ( class_exists( 'MDSM_Hash_Helper' ) ) {
			$unpacked = MDSM_Hash_Helper::unpack( $packed_hash );
			$hash_algo = $unpacked['algorithm'];
			$hash_mode = $unpacked['mode'];
		}
		
		// Verify key exists and is active
		$key_row = ArchivioID_Key_Manager::get_key( $key_id );
		if ( ! $key_row || ! $key_row->is_active ) {
			$this->set_notice( $post_id, 'error', __( 'Selected key not found or is inactive.', 'archivio-id' ) );
			return;
		}
		
		// Store the signature
		$stored = ArchivioID_Signature_Store::upsert_upload(
			$post_id,
			$key_id,
			$signature_content,
			$packed_hash,
			$hash_algo,
			$hash_mode,
			get_current_user_id()
		);
		
		if ( $stored ) {
			$this->set_notice( $post_id, 'success', __( 'Signature uploaded successfully. Click "Verify Signature" to confirm.', 'archivio-id' ) );
		} else {
			$this->set_notice( $post_id, 'error', __( 'Database error storing signature. Please try again.', 'archivio-id' ) );
		}
	}

	/**
	 * Validate uploaded signature file.
	 *
	 * @return array|WP_Error Array with 'content' key on success, WP_Error on failure.
	 */
	private function validate_signature_upload() {
		// Check upload error
		$upload_error = $_FILES['archivio_id_signature_file']['error'] ?? UPLOAD_ERR_NO_FILE;
		if ( $upload_error !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', __( 'File upload failed. Please try again.', 'archivio-id' ) );
		}
		
		// Validate file extension
		$filename = sanitize_file_name( wp_unslash( $_FILES['archivio_id_signature_file']['name'] ?? '' ) );
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		
		if ( $ext !== 'asc' ) {
			return new WP_Error( 'invalid_extension', __( 'Only .asc files are accepted.', 'archivio-id' ) );
		}
		
		// Validate file size (4KB max for detached signatures)
		$tmp_path = $_FILES['archivio_id_signature_file']['tmp_name'];
		
		if ( ! is_uploaded_file( $tmp_path ) ) {
			return new WP_Error( 'not_uploaded', __( 'Invalid file upload.', 'archivio-id' ) );
		}
		
		$filesize = filesize( $tmp_path );
		if ( $filesize > 4096 ) {
			return new WP_Error( 'file_too_large', __( 'Signature file exceeds maximum size (4 KB).', 'archivio-id' ) );
		}
		
		// Read contents
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $tmp_path );
		
		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Could not read uploaded file.', 'archivio-id' ) );
		}
		
		// Validate signature structure
		if ( ! ArchivioID_Signature_Store::looks_like_detached_signature( $content ) ) {
			return new WP_Error( 'invalid_signature', __( 'The uploaded file does not appear to be a valid PGP detached signature.', 'archivio-id' ) );
		}
		
		return array( 'content' => $content );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// AJAX Handlers
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * AJAX handler for signature verification.
	 */
	public function ajax_verify_signature() {
		// Verify nonce
		check_ajax_referer( 'archivio_id_ajax_action', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'archivio-id' ) ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'archivio-id' ) ) );
		}
		
		// Rate limiting (30 seconds per user per post)
		$rate_key = 'archivio_id_verify_' . get_current_user_id() . '_' . $post_id;
		if ( get_transient( $rate_key ) ) {
			wp_send_json_error( array( 
				'message' => __( 'Please wait 30 seconds before verifying again.', 'archivio-id' ),
				'status' => 'rate_limited'
			) );
		}
		
		// Perform verification
		try {
			$result = ArchivioID_Verifier::verify_post( $post_id );
			
			set_transient( $rate_key, true, 30 );
			
			$backend = $this->get_backend_info();
			
			if ( $result['success'] ) {
				wp_send_json_success( array_merge( $result, array( 'backend' => $backend ) ) );
			} else {
				wp_send_json_error( array_merge( $result, array( 'backend' => $backend ) ) );
			}
			
		} catch ( Throwable $e ) {
			archivio_id_log( 'AJAX verification exception: ' . $e->getMessage() );
			wp_send_json_error( array( 
				'message' => __( 'An internal error occurred. Please try again.', 'archivio-id' ),
				'status' => 'error'
			) );
		}
	}

	/**
	 * AJAX handler for signature deletion.
	 */
	public function ajax_delete_signature() {
		// Verify nonce
		check_ajax_referer( 'archivio_id_ajax_action', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'archivio-id' ) ) );
		}
		
		// Check capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'archivio-id' ) ) );
		}
		
		// Delete signature
		$deleted = ArchivioID_Signature_Store::delete( $post_id );
		
		if ( $deleted ) {
			wp_send_json_success( array( 
				'message' => __( 'Signature removed successfully.', 'archivio-id' ),
				'status' => 'not_signed'
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not remove signature.', 'archivio-id' ) ) );
		}
	}

	/**
	 * AJAX handler for backend info.
	 */
	public function ajax_get_backend_info() {
		check_ajax_referer( 'archivio_id_ajax_action', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'archivio-id' ) ) );
		}

		$backend = $this->get_backend_info();
		wp_send_json_success( array( 'backend' => $backend ) );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Assets
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * Enqueue scripts and styles for meta box.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only on post edit screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		
		// Enqueue styles
		wp_enqueue_style(
			'archivio-id-meta-box',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/css/meta-box.css',
			array(),
			ARCHIVIO_ID_VERSION
		);
		
		// Enqueue scripts
		wp_enqueue_script(
			'archivio-id-meta-box',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/meta-box.js',
			array( 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);
		
		// Localize script with data
		wp_localize_script( 'archivio-id-meta-box', 'archivioIdMetaBox', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'archivio_id_ajax_action' ),
			'postId' => get_the_ID(),
			'strings' => array(
				'verifying' => __( 'Verifying...', 'archivio-id' ),
				'verified' => __( 'Verified ✓', 'archivio-id' ),
				'invalid' => __( 'Invalid ✗', 'archivio-id' ),
				'error' => __( 'Error', 'archivio-id' ),
				'confirmDelete' => __( 'Are you sure you want to remove this signature?', 'archivio-id' ),
				'requestFailed' => __( 'Request failed. Please try again.', 'archivio-id' ),
			)
		) );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Utility Methods
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * Set a transient notice for the user.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Notice type (success, error, warning, info).
	 * @param string $message Notice message.
	 */
	private function set_notice( $post_id, $type, $message ) {
		$notice_key = 'archivio_id_notice_' . $post_id . '_' . get_current_user_id();
		set_transient( $notice_key, array(
			'type' => $type,
			'message' => $message
		), 60 );
	}

	/**
	 * Get information about the cryptographic backend being used.
	 *
	 * @return array Backend information.
	 */
	private function get_backend_info() {
		$has_phpseclib = function_exists( 'archivio_id_has_phpseclib' ) && archivio_id_has_phpseclib();
		$has_openpgp = class_exists( 'OpenPGP_Message' );
		
		if ( $has_phpseclib && $has_openpgp ) {
			return array(
				'name' => 'phpseclib v3 + OpenPGP-PHP',
				'version' => '3.0.49',
				'status' => 'optimal'
			);
		} elseif ( $has_openpgp ) {
			return array(
				'name' => 'OpenPGP-PHP only',
				'version' => 'legacy',
				'status' => 'fallback'
			);
		}
		
		return array(
			'name' => 'None',
			'version' => 'N/A',
			'status' => 'error'
		);
	}
}
