<?php
/**
 * ArchivioID Algorithm Policy Settings — Partial View
 *
 * Included at the bottom of admin/views/settings.php.
 * Renders the Algorithm Enforcement Floor card.
 *
 * Variables expected:
 *   $policy  array  From ArchivioID_Algorithm_Enforcer::get_policy()
 *
 * @package ArchivioID
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enforcement_enabled  = ArchivioID_Algorithm_Enforcer::is_enforcement_enabled();
$policy               = ArchivioID_Algorithm_Enforcer::get_policy();
$all_hashes           = ArchivioID_Algorithm_Enforcer::HASH_NAMES;    // id => name
?>

<div class="archivio-id-card" style="max-width:800px;margin-top:20px;">
	<h2 style="margin-top:0;"><?php esc_html_e( 'Algorithm Enforcement Floor', 'archivio-id' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Reject signatures that use cryptographically weak algorithms. Applies at upload, REST submission, and re-verification — so tightening this policy retroactively invalidates weak existing signatures on next cron run.', 'archivio-id' ); ?>
	</p>

	<table class="form-table" role="presentation">

		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Enforcement', 'archivio-id' ); ?></th>
			<td>
				<label>
					<input type="checkbox"
					       name="archivio_id_algo_enforcement_enabled"
					       value="1"
					       <?php checked( $enforcement_enabled ); ?> />
					<?php esc_html_e( 'Enforce algorithm policy on all signature operations', 'archivio-id' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Disable temporarily if you need to verify legacy signatures during migration.', 'archivio-id' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Rejected Hash Algorithms', 'archivio-id' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Rejected Hash Algorithms', 'archivio-id' ); ?></legend>
					<?php foreach ( $all_hashes as $id => $name ) :
						$is_rejected = in_array( $id, $policy['rejected_hash_ids'], true );
						$is_strong   = in_array( $id, array( 8, 9, 10, 11 ), true ); // SHA-256+
					?>
					<label style="display:block;margin-bottom:4px;">
						<input type="checkbox"
						       name="archivio_id_algo_reject_hash_ids[]"
						       value="<?php echo esc_attr( $id ); ?>"
						       <?php checked( $is_rejected ); ?>
						       <?php disabled( $is_strong ); ?>
						       <?php if ( $is_strong ) echo 'title="' . esc_attr__( 'SHA-256 and stronger algorithms cannot be rejected.', 'archivio-id' ) . '"'; ?>
						/>
						<strong><?php echo esc_html( $name ); ?></strong>
						<?php if ( $id === 1 ) : ?>
							<span style="color:#b91c1c;font-size:.78rem"> — <?php esc_html_e( 'Broken (collision attacks)', 'archivio-id' ); ?></span>
						<?php elseif ( $id === 2 ) : ?>
							<span style="color:#b91c1c;font-size:.78rem"> — <?php esc_html_e( 'Broken (SHAttered attack)', 'archivio-id' ); ?></span>
						<?php elseif ( $id === 3 ) : ?>
							<span style="color:#b45309;font-size:.78rem"> — <?php esc_html_e( 'Legacy (not recommended)', 'archivio-id' ); ?></span>
						<?php endif; ?>
					</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Checked algorithms will cause signature rejection. SHA-256 and stronger cannot be blocked.', 'archivio-id' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Minimum RSA Key Size', 'archivio-id' ); ?></th>
			<td>
				<select name="archivio_id_algo_min_rsa_bits">
					<?php foreach ( array( 1024, 2048, 3072, 4096 ) as $bits ) : ?>
						<option value="<?php echo esc_attr( $bits ); ?>"
							<?php selected( $policy['min_rsa_bits'], $bits ); ?>>
							<?php echo esc_html( $bits ); ?> bits
							<?php if ( $bits === 2048 ) : ?>
								(<?php esc_html_e( 'recommended minimum', 'archivio-id' ); ?>)
							<?php elseif ( $bits === 4096 ) : ?>
								(<?php esc_html_e( 'high security', 'archivio-id' ); ?>)
							<?php elseif ( $bits === 1024 ) : ?>
								(<?php esc_html_e( 'not recommended', 'archivio-id' ); ?>)
							<?php endif; ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'RSA signatures from keys smaller than this size will be rejected.', 'archivio-id' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Reject Elgamal', 'archivio-id' ); ?></th>
			<td>
				<label>
					<input type="checkbox"
					       name="archivio_id_algo_reject_elgamal"
					       value="1"
					       <?php checked( $policy['reject_elgamal'] ); ?> />
					<?php esc_html_e( 'Reject signatures using Elgamal signing keys', 'archivio-id' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Elgamal is not recommended for signatures. Enabled by default.', 'archivio-id' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Reject DSA', 'archivio-id' ); ?></th>
			<td>
				<label>
					<input type="checkbox"
					       name="archivio_id_algo_reject_dsa"
					       value="1"
					       <?php checked( $policy['reject_dsa'] ); ?> />
					<?php esc_html_e( 'Reject signatures using DSA keys', 'archivio-id' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'DSA is being phased out. Enable this to require RSA, ECDSA, or Ed25519 only.', 'archivio-id' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<!-- Active policy summary -->
	<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin-top:8px;">
		<strong style="font-size:.85rem;color:#15803d;"><?php esc_html_e( 'Active Policy Summary', 'archivio-id' ); ?></strong>
		<ul style="margin:6px 0 0;padding-left:18px;font-size:.83rem;color:#374151;">
			<?php
			$summary = ArchivioID_Algorithm_Enforcer::get_policy_summary();
			if ( ! $summary['enforcement_enabled'] ) :
				echo '<li style="color:#b45309;font-weight:600">' . esc_html__( '⚠ Enforcement is currently DISABLED.', 'archivio-id' ) . '</li>';
			else :
				if ( ! empty( $summary['rejected_hashes'] ) ) :
					echo '<li>' . esc_html( sprintf(
						/* translators: %s: comma-separated list */
						__( 'Rejected hash algorithms: %s', 'archivio-id' ),
						implode( ', ', $summary['rejected_hashes'] )
					) ) . '</li>';
				endif;
				echo '<li>' . esc_html( sprintf(
					/* translators: %d: bit count */
					__( 'Minimum RSA key size: %d bits', 'archivio-id' ),
					$summary['min_rsa_bits']
				) ) . '</li>';
				if ( $summary['reject_elgamal'] ) echo '<li>' . esc_html__( 'Elgamal signing: rejected', 'archivio-id' ) . '</li>';
				if ( $summary['reject_dsa'] )     echo '<li>' . esc_html__( 'DSA keys: rejected', 'archivio-id' ) . '</li>';
			endif;
			?>
		</ul>
	</div>
</div>
