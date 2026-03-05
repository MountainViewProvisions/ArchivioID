<?php
/**
 * ArchivioID Bulk Verify Screen
 *
 * Provides an admin list table showing every post that has a signature
 * (or is published with a hash but no signature). Supports:
 *
 *   - Status filter tabs: All | Not Signed | Uploaded | Verified | Invalid | Error
 *   - Per-row "Verify" quick action
 *   - Bulk action "Re-verify selected"
 *   - Inline AJAX verify without page reload
 *
 * The list table joins archivio_id_signatures against wp_posts so the
 * admin can see all signed posts site-wide without opening each one.
 *
 * Menu position: ArchivioID → Signatures
 *
 * @package ArchivioID
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not autoloaded — include it explicitly when needed.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// ─────────────────────────────────────────────────────────────────────────────
// List Table class
// ─────────────────────────────────────────────────────────────────────────────

class ArchivioID_Signatures_List_Table extends WP_List_Table {

	/** @var string Active status filter */
	private $status_filter = '';

	public function __construct() {
		parent::__construct( array(
			'singular' => 'signature',
			'plural'   => 'signatures',
			'ajax'     => false,
		) );
	}

	// ── Column definitions ────────────────────────────────────────────────────

	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'post_title'  => esc_html__( 'Post', 'archivio-id' ),
			'post_type'   => esc_html__( 'Type', 'archivio-id' ),
			'status'      => esc_html__( 'Signature Status', 'archivio-id' ),
			'key_label'   => esc_html__( 'Key', 'archivio-id' ),
			'uploaded_at' => esc_html__( 'Uploaded', 'archivio-id' ),
			'verified_at' => esc_html__( 'Verified', 'archivio-id' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'post_title'  => array( 'post_title', false ),
			'status'      => array( 'status', false ),
			'uploaded_at' => array( 'uploaded_at', true ),
			'verified_at' => array( 'verified_at', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'bulk_verify' => esc_html__( 'Re-verify selected', 'archivio-id' ),
		);
	}

	// ── Data loading ──────────────────────────────────────────────────────────

	public function prepare_items() {
		global $wpdb;

		$this->status_filter = isset( $_GET['sig_status'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_key( $_GET['sig_status'] )           // phpcs:ignore WordPress.Security.NonceVerification
			: '';

		$per_page     = 30;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$sigs_table  = ArchivioID_DB::signatures_table();
		$keys_table  = ArchivioID_DB::keys_table();
		$posts_table = $wpdb->posts;

		// Build status WHERE
		$where = '';
		if ( $this->status_filter && $this->status_filter !== 'not_signed' ) {
			$where = $wpdb->prepare( 'AND s.status = %s', $this->status_filter );
		}

		// Sorting
		$orderby_map = array(
			'post_title'  => 'p.post_title',
			'status'      => 's.status',
			'uploaded_at' => 's.uploaded_at',
			'verified_at' => 's.verified_at',
		);
		$raw_orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'uploaded_at'; // phpcs:ignore WordPress.Security.NonceVerification
		$orderby     = $orderby_map[ $raw_orderby ] ?? 's.uploaded_at';
		$order       = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$sigs_table} s
			 INNER JOIN {$posts_table} p ON p.ID = s.post_id
			 WHERE p.post_status != 'auto-draft' {$where}"
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*, p.post_title, p.post_type, p.post_status,
			        k.label AS key_label, k.fingerprint AS key_fingerprint
			 FROM {$sigs_table} s
			 INNER JOIN {$posts_table} p ON p.ID = s.post_id
			 LEFT  JOIN {$keys_table}  k ON k.id = s.key_id
			 WHERE p.post_status != 'auto-draft' {$where}
			 ORDER BY {$orderby} {$order}
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		$this->items = $rows ?: array();

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	// ── Status tab counts ─────────────────────────────────────────────────────

	public function get_status_counts() {
		global $wpdb;

		$sigs_table  = ArchivioID_DB::signatures_table();
		$posts_table = $wpdb->posts;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = $wpdb->get_results(
			"SELECT s.status, COUNT(*) AS cnt
			 FROM {$sigs_table} s
			 INNER JOIN {$posts_table} p ON p.ID = s.post_id
			 WHERE p.post_status != 'auto-draft'
			 GROUP BY s.status"
		);

		$map = array( 'uploaded' => 0, 'verified' => 0, 'invalid' => 0, 'error' => 0 );
		foreach ( $counts as $row ) {
			$map[ $row->status ] = (int) $row->cnt;
		}
		$map['all'] = array_sum( $map );

		return $map;
	}

	// ── Column renderers ──────────────────────────────────────────────────────

	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '—';
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="signature[]" value="%d" />', (int) $item->post_id );
	}

	protected function column_post_title( $item ) {
		$edit_url  = get_edit_post_link( $item->post_id );
		$view_url  = get_permalink( $item->post_id );
		$title     = esc_html( $item->post_title ?: __( '(no title)', 'archivio-id' ) );

		$out = sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $edit_url ), $title );
		$out .= '<br><span class="row-actions">';

		// Quick action: verify
		$out .= sprintf(
			'<span class="verify"><a href="#" class="archivio-id-quick-verify" data-post-id="%d">%s</a></span>',
			(int) $item->post_id,
			esc_html__( 'Verify', 'archivio-id' )
		);

		if ( $view_url ) {
			$out .= ' | <span class="view"><a href="' . esc_url( $view_url ) . '" target="_blank">'
				. esc_html__( 'View', 'archivio-id' ) . '</a></span>';
		}

		$out .= '</span>';

		// Inline result placeholder (filled by JS on quick-verify click)
		$out .= sprintf(
			'<span class="archivio-id-row-result" data-post-id="%d" style="display:none;margin-left:6px;font-style:italic;"></span>',
			(int) $item->post_id
		);

		return $out;
	}

	protected function column_post_type( $item ) {
		$pto = get_post_type_object( $item->post_type );
		return esc_html( $pto ? $pto->labels->singular_name : $item->post_type );
	}

	protected function column_status( $item ) {
		$status_labels = array(
			'uploaded' => array( 'label' => __( 'Uploaded', 'archivio-id' ),  'color' => '#2271b1' ),
			'verified' => array( 'label' => __( 'Verified', 'archivio-id' ),  'color' => '#0a7537' ),
			'invalid'  => array( 'label' => __( 'Invalid',  'archivio-id' ),  'color' => '#b32d2e' ),
			'error'    => array( 'label' => __( 'Error',    'archivio-id' ),  'color' => '#b32d2e' ),
		);

		$s = $item->status;
		if ( isset( $status_labels[ $s ] ) ) {
			$info = $status_labels[ $s ];
			return sprintf(
				'<span style="font-weight:600;color:%s;">%s</span>',
				esc_attr( $info['color'] ),
				esc_html( $info['label'] )
			);
		}

		return esc_html( $s );
	}

	protected function column_key_label( $item ) {
		if ( empty( $item->key_label ) ) return '—';
		$fp_short = strtoupper( substr( $item->key_fingerprint ?? '', -8 ) );
		return esc_html( $item->key_label ) . '<br><code style="font-size:10px;">' . esc_html( $fp_short ) . '</code>';
	}

	protected function column_uploaded_at( $item ) {
		if ( empty( $item->uploaded_at ) ) return '—';
		return esc_html( wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $item->uploaded_at ) ) );
	}

	protected function column_verified_at( $item ) {
		if ( empty( $item->verified_at ) ) return '—';
		return esc_html( wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $item->verified_at ) ) );
	}

	// ── Status tabs (views) ───────────────────────────────────────────────────

	protected function get_views() {
		$counts  = $this->get_status_counts();
		$current = $this->status_filter ?: 'all';
		$base    = admin_url( 'admin.php?page=archivio-id-signatures' );

		$tabs = array(
			'all'      => esc_html__( 'All',      'archivio-id' ),
			'verified' => esc_html__( 'Verified', 'archivio-id' ),
			'uploaded' => esc_html__( 'Uploaded', 'archivio-id' ),
			'invalid'  => esc_html__( 'Invalid',  'archivio-id' ),
			'error'    => esc_html__( 'Error',    'archivio-id' ),
		);

		$views = array();
		foreach ( $tabs as $key => $label ) {
			$count   = $counts[ $key ] ?? 0;
			$url     = $key === 'all' ? $base : add_query_arg( 'sig_status', $key, $base );
			$class   = $key === $current ? 'current' : '';
			$views[ $key ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label ),
				$count
			);
		}

		return $views;
	}

	// ── Extra table nav ───────────────────────────────────────────────────────

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) return;
		echo '<div class="alignleft actions">'
			. '<span id="archivio-id-bulk-result" style="margin-left:8px;font-style:italic;"></span>'
			. '</div>';
	}
}


// ─────────────────────────────────────────────────────────────────────────────
// Admin screen controller
// ─────────────────────────────────────────────────────────────────────────────

class ArchivioID_Bulk_Verify_Admin {

	private static $instance = null;

	/** @var ArchivioID_Signatures_List_Table|null */
	private $list_table = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_submenu' ), 25 );
		add_action( 'admin_init',            array( $this, 'handle_bulk_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_archivio_id_quick_verify', array( $this, 'ajax_quick_verify' ) );
	}

	public function register_submenu() {
		add_submenu_page(
			'archivio-id',
			__( 'Signatures — ArchivioID', 'archivio-id' ),
			__( 'Signatures', 'archivio-id' ),
			'manage_options',
			'archivio-id-signatures',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue JS for the quick-verify button on the Signatures admin page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'archivio-id_page_archivio-id-signatures' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'archivio-id-bulk-verify',
			ARCHIVIO_ID_PLUGIN_URL . 'assets/js/archivio-id-bulk-verify.js',
			array( 'jquery' ),
			ARCHIVIO_ID_VERSION,
			true
		);
		wp_localize_script( 'archivio-id-bulk-verify', 'archivioIdBulkVerify', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'archivio_id_admin_action' ),
			'i18n'    => array(
				'verifying'     => esc_html__( 'Verifying…',       'archivio-id' ),
				'verify'        => esc_html__( 'Verify',           'archivio-id' ),
				'error'         => esc_html__( 'Error',            'archivio-id' ),
				'requestFailed' => esc_html__( 'Request failed',   'archivio-id' ),
			),
		) );
	}

	// ── Bulk action handler (server-side, runs before headers) ────────────────

	public function handle_bulk_action() {
		if ( ! isset( $_POST['action'], $_POST['_wpnonce'] ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		$action = sanitize_key( $_POST['action'] );
		if ( 'bulk_verify' !== $action && ( ! isset( $_POST['action2'] ) || 'bulk_verify' !== sanitize_key( $_POST['action2'] ) ) ) {
			return;
		}

		if ( ! check_admin_referer( 'bulk-signatures' ) ) return;

		$post_ids = array_map( 'absint', (array) ( $_POST['signature'] ?? array() ) );
		if ( empty( $post_ids ) ) return;

		$verified = 0;
		$failed   = 0;
		foreach ( $post_ids as $post_id ) {
			$result = ArchivioID_Verifier::verify_post( $post_id );
			if ( $result['success'] ) {
				$verified++;
			} else {
				$failed++;
			}
		}

		wp_safe_redirect( add_query_arg( array(
			'page'         => 'archivio-id-signatures',
			'bulk_done'    => 1,
			'bulk_ok'      => $verified,
			'bulk_fail'    => $failed,
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── AJAX: single-row quick verify ─────────────────────────────────────────

	public function ajax_quick_verify() {
		check_ajax_referer( 'archivio_id_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'archivio-id' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'archivio-id' ) ) );
		}

		$result = ArchivioID_Verifier::verify_post( $post_id );

		wp_send_json_success( array(
			'post_id' => $post_id,
			'status'  => $result['status'],
			'message' => $result['message'],
			'success' => $result['success'],
		) );
	}

	// ── Page renderer ─────────────────────────────────────────────────────────

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'archivio-id' ) );
		}

		// Instantiate and prepare the list table
		if ( null === $this->list_table ) {
			$this->list_table = new ArchivioID_Signatures_List_Table();
		}
		$this->list_table->prepare_items();

		$bulk_done = isset( $_GET['bulk_done'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$bulk_ok   = isset( $_GET['bulk_ok'] ) ? (int) $_GET['bulk_ok'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$bulk_fail = isset( $_GET['bulk_fail'] ) ? (int) $_GET['bulk_fail'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Signatures', 'archivio-id' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( $bulk_done ) : ?>
			<div class="notice notice-<?php echo esc_attr( $bulk_fail ? 'warning' : 'success' ); ?> is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: verified count, 2: failed count */
						esc_html__( 'Bulk re-verify complete: %1$d verified, %2$d failed.', 'archivio-id' ),
						$bulk_ok,
						$bulk_fail
					);
					?>
				</p>
			</div>
			<?php endif; ?>

			<form id="archivio-id-signatures-form" method="post">
				<?php
				$this->list_table->views();
				$this->list_table->search_box( __( 'Search', 'archivio-id' ), 'signature' );
				$this->list_table->display();
				?>
			</form>
		</div>

		<?php
	}
}
