<?php
/**
 * ArchivioID Overview Admin Page
 *
 * @package ArchivioID
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$key_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ArchivioID_DB::keys_table() );
$sig_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ArchivioID_DB::signatures_table() );
$ver_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . ArchivioID_DB::signatures_table() . " WHERE status = 'verified'" );
$mdsm_ver  = defined( 'MDSM_VERSION' ) ? MDSM_VERSION : '—';
?>
<div class="wrap archivio-id-wrap">
	<h1><?php esc_html_e( 'ArchivioID', 'archivio-id' ); ?> <span class="archivio-id-version">v<?php echo esc_html( ARCHIVIO_ID_VERSION ); ?></span></h1>

	<p class="description">
		<?php esc_html_e( 'OpenPGP detached-signature layer for ArchivioMD. Manage public keys and verify per-post GPG signatures — no GnuPG binary required.', 'archivio-id' ); ?>
	</p>

	<div class="archivio-id-stats-row">
		<div class="archivio-id-stat-card">
			<span class="stat-number"><?php echo esc_html( $key_count ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'Stored Keys', 'archivio-id' ); ?></span>
		</div>
		<div class="archivio-id-stat-card">
			<span class="stat-number"><?php echo esc_html( $sig_count ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'Signatures Uploaded', 'archivio-id' ); ?></span>
		</div>
		<div class="archivio-id-stat-card verified">
			<span class="stat-number"><?php echo esc_html( $ver_count ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'Verified', 'archivio-id' ); ?></span>
		</div>
	</div>

	<table class="archivio-id-info-table widefat striped" style="max-width:520px;margin-top:24px;">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'ArchivioID version', 'archivio-id' ); ?></th>
				<td><code><?php echo esc_html( ARCHIVIO_ID_VERSION ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'ArchivioMD version', 'archivio-id' ); ?></th>
				<td><code><?php echo esc_html( $mdsm_ver ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'OpenPGP library', 'archivio-id' ); ?></th>
				<td>
				<?php
				$lib_ok = file_exists( ARCHIVIO_ID_PLUGIN_DIR . 'vendor/openpgp-php/openpgp.php' );
				if ( $lib_ok ) {
					echo '<span style="color:#0a7537;">&#10003; ' . esc_html__( 'Present', 'archivio-id' ) . '</span>';
				} else {
					echo '<span style="color:#d73a49;">&#10007; ' . esc_html__( 'Missing — install OpenPGP-PHP in /vendor/openpgp-php/', 'archivio-id' ) . '</span>';
				}
				?>
				</td>
			</tr>
		</tbody>
	</table>

	<p style="margin-top:20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=archivio-id-keys' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Manage Public Keys', 'archivio-id' ); ?>
		</a>
	</p>
</div>
