<?php
/**
 * ArchivioID Front-End Badge Handler
 *
 * Displays a 🔒 lock icon next to the ArchivioMD verification badge
 * when a post has a verified PGP signature.
 *
 * v1.2.0 changes:
 *   - Lock emoji is now a clickable anchor that triggers a .asc download.
 *   - Frontend JS + CSS are enqueued only on singular, verified posts.
 *   - Download nonce is per-post to prevent cross-post token reuse.
 *
 * @package ArchivioID
 * @since   1.1.0
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Frontend_Badge {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'the_title',      array( $this, 'add_lock_to_title'  ), 15, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'archivio_id_badge', array( $this, 'badge_shortcode' ) );
	}

	// ── Title filter ──────────────────────────────────────────────────────────

	/**
	 * Append a clickable lock icon to the post title when the signature is verified.
	 *
	 * Clicking the icon sends the visitor to the public AJAX download endpoint
	 * which streams the .asc file. No metadata is displayed inline.
	 *
	 * @param string   $title   Post title (may already contain ArchivioMD badge).
	 * @param int|null $post_id Post ID.
	 * @return string Modified title.
	 */
	public function add_lock_to_title( $title, $post_id = null ) {
		if ( ! $post_id || is_admin() ) {
			return $title;
		}

		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}

		if ( $this->get_signature_status( $post_id ) !== 'verified' ) {
			return $title;
		}

		return $title . ' ' . $this->build_lock_html( $post_id );
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────

	/**
	 * Shortcode for manual badge placement.
	 *
	 * Usage: [archivio_id_badge] or [archivio_id_badge post_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Lock anchor HTML or empty string.
	 */
	public function badge_shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'post_id' => get_the_ID() ), $atts, 'archivio_id_badge' );
		$post_id = absint( $atts['post_id'] );

		if ( ! $post_id || $this->get_signature_status( $post_id ) !== 'verified' ) {
			return '';
		}

		return $this->build_lock_html( $post_id );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	/**
	 * Enqueue frontend CSS and JS only on singular posts with verified signatures.
	 *
	 * The download nonce is localized per post so JavaScript can construct the
	 * correct AJAX URL without any additional round-trip.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( $this->get_signature_status( $post_id ) !== 'verified' ) {
			return;
		}

		wp_enqueue_style(
			'archivio-id-frontend',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/css/archivio-id-frontend.css',
			array(),
			ARCHIVIO_ID_VERSION
		);

		wp_enqueue_script(
			'archivio-id-frontend',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-frontend.js',
			array( 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);

		wp_localize_script( 'archivio-id-frontend', 'archivioIdFrontend', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'postId'  => $post_id,
			// Per-post nonce scoped to the download action.
			'nonce'   => wp_create_nonce( 'archivio_id_download_' . $post_id ),
			'strings' => array(
				'downloading' => __( 'Downloading…', 'archivio-id' ),
				'unavailable' => __( 'Signature unavailable.', 'archivio-id' ),
			),
		) );
	}

	// ── HTML builder ─────────────────────────────────────────────────────────

	/**
	 * Build the clickable lock anchor HTML for a verified post.
	 *
	 * The anchor uses data-post-id so the JS handler can resolve it without
	 * relying on a global variable when multiple posts appear on one page.
	 *
	 * @param int $post_id Post ID.
	 * @return string Safe HTML string.
	 */
	private function build_lock_html( $post_id ) {
		return sprintf(
			'<a href="#" class="archivio-id-lock-link" data-post-id="%d" '
			. 'title="%s" aria-label="%s" role="button">🔒</a>',
			absint( $post_id ),
			esc_attr__( 'Download PGP signature (.asc)', 'archivio-id' ),
			esc_attr__( 'Download detached PGP signature for this post', 'archivio-id' )
		);
	}

	// ── Status helper ────────────────────────────────────────────────────────

	/**
	 * Return the signature status string for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Status string or 'not_signed'.
	 */
	private function get_signature_status( $post_id ) {
		if ( ! class_exists( 'ArchivioID_Signature_Store' ) ) {
			return 'not_signed';
		}

		$row = ArchivioID_Signature_Store::get( $post_id );
		return $row ? $row->status : 'not_signed';
	}
}
