<?php
/**
 * ArchivioID Admin — Main menu + overview page.
 *
 * Registers the top-level "ArchivioID" admin menu.
 * Submenu items (Key Management, etc.) are added by their own classes.
 *
 * @package ArchivioID
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',             array( $this, 'register_menu'   ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets'  ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'ArchivioID', 'archivio-id' ),
			__( 'ArchivioID', 'archivio-id' ),
			'manage_options',
			'archivio-id',
			array( $this, 'render_overview' ),
			'dashicons-lock',
			31  // Just after ArchivioMD (position 30).
		);

		// Remove the auto-generated duplicate first submenu item.
		remove_submenu_page( 'archivio-id', 'archivio-id' );
	}

	public function render_overview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}
		require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/overview.php';
	}

	public function enqueue_assets( $hook ) {
		// Only on our pages.
		if ( strpos( $hook, 'archivio-id' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'archivio-id-admin',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/css/archivio-id-admin.css',
			array(),
			ARCHIVIO_ID_VERSION
		);
		wp_enqueue_script(
			'archivio-id-admin',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-admin.js',
			array( 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);
		wp_localize_script( 'archivio-id-admin', 'archivioIdAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'archivio_id_admin_action' ),
			'strings' => array(
				'confirmDeleteKey' => __( 'Permanently delete this key? Signatures linked to it will become unverifiable.', 'archivio-id' ),
				'deleting'         => __( 'Deleting…', 'archivio-id' ),
				'deleted'          => __( 'Key deleted.', 'archivio-id' ),
				'error'            => __( 'An error occurred.', 'archivio-id' ),
			),
		) );
	}
}
