<?php
/**
 * ArchivioID Browser Signer — Admin Page
 *
 * Registers the "Browser Sign" submenu page and enqueues all JS/CSS assets
 * needed for the in-browser OpenPGP.js signing workflow.
 *
 * @package ArchivioID
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Browser_Sign_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_submenu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the "Browser Sign" submenu under the ArchivioID top-level menu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'archivio-id',
			__( 'Browser Sign — ArchivioID', 'archivio-id' ),
			__( 'Browser Sign', 'archivio-id' ),
			'edit_posts',
			'archivio-id-browser-sign',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue OpenPGP.js and the signing UI script on our page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'archivio-id-browser-sign' ) === false ) {
			return;
		}

		// OpenPGP.js — loaded from CDN (no server-side key exposure)
		wp_enqueue_script(
			'openpgpjs',
			'https://unpkg.com/openpgp@5.11.2/dist/openpgp.min.js',
			array(),
			'5.11.2',
			true
		);

		// Our signing UI controller
		wp_enqueue_script(
			'archivio-id-browser-sign',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-browser-sign.js',
			array( 'openpgpjs', 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);

		// Pass WP data to JS
		wp_localize_script(
			'archivio-id-browser-sign',
			'archivioIdBrowserSign',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'archivio_id_browser_sign' ),
				'i18n'      => array(
					'generating'      => __( 'Generating key pair…', 'archivio-id' ),
					'signing'         => __( 'Signing…', 'archivio-id' ),
					'uploading'       => __( 'Uploading & verifying…', 'archivio-id' ),
					'fetchingHash'    => __( 'Fetching post hash…', 'archivio-id' ),
					'verified'        => __( '✓ Signature verified by server.', 'archivio-id' ),
					'failed'          => __( '✗ Verification failed.', 'archivio-id' ),
					'noKey'           => __( 'No private key loaded. Generate or import a key first.', 'archivio-id' ),
					'noContent'       => __( 'Enter a post ID or a hex hash to sign.', 'archivio-id' ),
					'noPassphrase'    => __( 'Enter your key passphrase.', 'archivio-id' ),
					'copied'          => __( 'Copied!', 'archivio-id' ),
					'keyImported'     => __( 'Private key imported successfully.', 'archivio-id' ),
					'keyGenerated'    => __( 'New key pair generated.', 'archivio-id' ),
				),
			)
		);

		// Signing page styles
		wp_enqueue_style(
			'archivio-id-browser-sign',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/css/archivio-id-browser-sign.css',
			array(),
			ARCHIVIO_ID_VERSION
		);
	}

	/**
	 * Render the browser signing admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}
		require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/browser-sign.php';
	}
}
