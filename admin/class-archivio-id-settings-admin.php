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
		add_action( 'admin_menu',            array( $this, 'register_submenu' ), 25 );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets'    ) );
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
	 * Enqueue scripts for the Settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'archivio-id_page_archivio-id-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'archivio-id-settings-threshold',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-settings-threshold.js',
			array(),
			ARCHIVIO_ID_VERSION,
			true
		);
		wp_add_inline_script(
			'archivio-id-settings-threshold',
			'var archivioIdThreshold = ' . wp_json_encode( array(
				'modeGlobal' => ArchivioID_Threshold_Policy::MODE_GLOBAL,
			) ) . ';',
			'before'
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

		// v2.0.0 — key server
		register_setting( 'archivio_id_settings', 'archivio_id_allow_key_server_lookup', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		// v3.0.0 — cron re-verification
		register_setting( 'archivio_id_settings', 'archivio_id_cron_enabled', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_cron_recheck_crypto', array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		// v4.0.0 — key expiry emails
		register_setting( 'archivio_id_settings', 'archivio_id_expiry_emails', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		// ── v5.1.0: Algorithm Enforcement Floor ──────────────────────────────
		register_setting( 'archivio_id_settings', 'archivio_id_algo_enforcement_enabled', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_algo_reject_hash_ids', array(
			'type'              => 'array',
			'default'           => array( 1, 2, 3 ),
			'sanitize_callback' => array( $this, 'sanitize_hash_id_array' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_algo_min_rsa_bits', array(
			'type'              => 'integer',
			'default'           => 2048,
			'sanitize_callback' => array( $this, 'sanitize_rsa_bits' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_algo_reject_elgamal', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_algo_reject_dsa', array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => array( $this, 'sanitize_boolean' ),
		) );

		// ── v5.1.0: Multi-Signer Threshold ───────────────────────────────────
		register_setting( 'archivio_id_settings', 'archivio_id_sig_threshold', array(
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => array( $this, 'sanitize_threshold' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_sig_threshold_mode', array(
			'type'              => 'string',
			'default'           => 'global',
			'sanitize_callback' => array( $this, 'sanitize_threshold_mode' ),
		) );
		register_setting( 'archivio_id_settings', 'archivio_id_sig_threshold_by_type', array(
			'type'              => 'string',
			'default'           => '{}',
			'sanitize_callback' => array( $this, 'sanitize_threshold_by_type' ),
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
	 * Sanitize the rejected hash ID array — only allow known safe IDs (1–11).
	 * SHA-256+ (IDs 8,9,10,11) are always removed to prevent blocking strong algos.
	 *
	 * @param  mixed $value
	 * @return int[]
	 */
	public function sanitize_hash_id_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array( 1, 2, 3 ); // default
		}
		$allowed_to_reject = array( 1, 2, 3 ); // MD5, SHA-1, RIPEMD-160 only
		$sanitized = array_map( 'intval', $value );
		return array_values( array_intersect( $sanitized, $allowed_to_reject ) );
	}

	/**
	 * Sanitize RSA minimum bits — must be one of the allowed values.
	 *
	 * @param  mixed $value
	 * @return int
	 */
	public function sanitize_rsa_bits( $value ) {
		$allowed = array( 1024, 2048, 3072, 4096 );
		$int     = (int) $value;
		return in_array( $int, $allowed, true ) ? $int : 2048;
	}

	/**
	 * Sanitize the signature threshold — must be a positive integer.
	 *
	 * @param  mixed $value
	 * @return int
	 */
	public function sanitize_threshold( $value ) {
		return max( 1, min( 20, (int) $value ) );
	}

	/**
	 * Sanitize threshold mode.
	 *
	 * @param  mixed $value
	 * @return string
	 */
	public function sanitize_threshold_mode( $value ) {
		return in_array( $value, array( 'global', 'per_post_type' ), true ) ? $value : 'global';
	}

	/**
	 * Sanitize per-type threshold array — stored as JSON.
	 *
	 * @param  mixed $value  Array from form or JSON string.
	 * @return string  JSON-encoded result.
	 */
	public function sanitize_threshold_by_type( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		if ( ! is_array( $value ) ) {
			return '{}';
		}
		$sanitized = array();
		foreach ( $value as $type => $count ) {
			$type  = sanitize_key( $type );
			$count = max( 1, min( 20, (int) $count ) );
			if ( $type ) {
				$sanitized[ $type ] = $count;
			}
		}
		return wp_json_encode( $sanitized );
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
