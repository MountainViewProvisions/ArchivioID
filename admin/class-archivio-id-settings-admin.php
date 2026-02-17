<?php
/**
 * ArchivioID Settings Admin Page
 *
 * Handles plugin settings including front-end badge display options.
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchivioID_Settings_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 25 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register Settings submenu.
	 */
	public function register_submenu() {
		add_submenu_page(
			'archivio-id',
			__( 'Settings — ArchivioID', 'archivio-id' ),
			__( 'Settings', 'archivio-id' ),
			'manage_options',
			'archivio-id-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'archivio_id_settings', 'archivio_id_badge_enabled', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		register_setting( 'archivio_id_settings', 'archivio_id_badge_position', array(
			'type'              => 'string',
			'default'           => 'bottom-left',
			'sanitize_callback' => array( $this, 'sanitize_badge_position' ),
		) );

		register_setting( 'archivio_id_settings', 'archivio_id_show_on_pages', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		register_setting( 'archivio_id_settings', 'archivio_id_show_on_posts', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		register_setting( 'archivio_id_settings', 'archivio_id_show_backend_info', array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );
	}

	/**
	 * Sanitize boolean values.
	 *
	 * @param  mixed $value
	 * @return bool
	 */
	public function sanitize_boolean( $value ) {
		return (bool) $value;
	}

	/**
	 * Sanitize badge position.
	 *
	 * @param  string $value
	 * @return string
	 */
	public function sanitize_badge_position( $value ) {
		$valid = array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' );
		return in_array( $value, $valid, true ) ? $value : 'bottom-left';
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}
		require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
