<?php
/**
 * ArchivioID Signature Download Handler
 *
 * Provides a public AJAX endpoint that serves the stored detached .asc
 * signature for a verified post as a browser download.
 *
 * Security model:
 *   - Only posts whose signature_status = 'verified' are served.
 *   - The .asc text served is the exact armored blob already stored in
 *     the signatures table — the one that was verified against the public key.
 *     No re-signing, no private key involvement.
 *   - Nonce is generated on the frontend without requiring login, so public
 *     visitors can download. WordPress nopriv AJAX is used intentionally.
 *   - Output is strictly sanitized before transmission.
 *   - Download event is appended to the audit log (event_type = 'download').
 *
 * Integration points:
 *   - ArchivioID_Frontend_Badge: enqueues the JS/CSS and localizes the nonce.
 *   - ArchivioID_Audit_Log::log_event(): records every download attempt.
 *   - ArchivioID_Signature_Store::get(): retrieves the signature row.
 *
 * @package ArchivioID
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Signature_Download {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Public (nopriv) AJAX — visitors who are not logged in can download.
		add_action( 'wp_ajax_archivio_id_download_sig',        array( $this, 'handle_download' ) );
		add_action( 'wp_ajax_nopriv_archivio_id_download_sig', array( $this, 'handle_download' ) );
	}

	// ── AJAX handler ──────────────────────────────────────────────────────────

	/**
	 * Stream the verified .asc signature for a post as a file download.
	 *
	 * Validates nonce → fetches signature row → confirms status = verified
	 * → logs download event → streams armored text with correct headers.
	 */
	public function handle_download() {
		// Nonce scoped to the specific post so the token cannot be replayed
		// across posts.
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid request.', 'archivio-id' ), 400 );
		}

		if ( ! check_ajax_referer( 'archivio_id_download_' . $post_id, 'nonce', false ) ) {
			wp_die( esc_html__( 'Security check failed.', 'archivio-id' ), 403 );
		}

		$sig_row = ArchivioID_Signature_Store::get( $post_id );

		// Serve only verified signatures.
		if ( ! $sig_row || $sig_row->status !== ArchivioID_Signature_Store::STATUS_VERIFIED ) {
			wp_die( esc_html__( 'No verified signature available for this post.', 'archivio-id' ), 404 );
		}

		// Confirm the stored text still looks like a detached signature before
		// streaming it — guards against corrupted or swapped DB rows.
		if ( ! ArchivioID_Signature_Store::looks_like_detached_signature( $sig_row->signature_asc ) ) {
			archivio_id_log( 'Download blocked: stored signature for post ' . $post_id . ' failed structural validation.' );
			wp_die( esc_html__( 'Signature data is unavailable.', 'archivio-id' ), 500 );
		}

		// Resolve public key fingerprint for the audit record.
		$key_fingerprint = '';
		$key_row         = ArchivioID_Key_Manager::get_key( $sig_row->key_id );
		if ( $key_row ) {
			$key_fingerprint = $key_row->fingerprint;
		}

		// Append a 'download' event to the audit log.
		ArchivioID_Audit_Log::log_event(
			$post_id,
			'download',
			$key_fingerprint,
			$sig_row->hash_algorithm ?? 'sha256',
			'verified',
			'Signature file downloaded by visitor'
		);

		// Build a descriptive filename: post-slug-fingerprint-short.asc
		$post      = get_post( $post_id );
		$slug      = $post ? sanitize_file_name( $post->post_name ) : 'post-' . $post_id;
		$fp_short  = $key_fingerprint ? strtolower( substr( $key_fingerprint, -8 ) ) : 'sig';
		$filename  = $slug . '-' . $fp_short . '.asc';

		// Stream the armored signature as a plain-text download.
		// No HTML escaping — the content is ASCII armor, sent as-is.
		nocache_headers();
		header( 'Content-Type: application/pgp-signature; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $sig_row->signature_asc ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );

		// Output directly — bypasses WordPress template entirely.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sig_row->signature_asc;
		exit;
	}
}
