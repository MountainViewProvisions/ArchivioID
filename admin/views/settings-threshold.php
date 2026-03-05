<?php
/**
 * ArchivioID Multi-Signer Threshold Settings — Partial View
 *
 * Included at the bottom of admin/views/settings.php.
 * Renders the Multi-Signer Threshold card.
 *
 * @package ArchivioID
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$threshold      = ArchivioID_Threshold_Policy::get_threshold();
$threshold_mode = get_option( 'archivio_id_sig_threshold_mode', ArchivioID_Threshold_Policy::MODE_GLOBAL );
$by_type_json   = get_option( 'archivio_id_sig_threshold_by_type', '{}' );
$by_type        = json_decode( $by_type_json, true ) ?: array();
$public_types   = get_post_types( array( 'public' => true ), 'objects' );
?>

<div class="archivio-id-card" style="max-width:800px;margin-top:20px;">
	<h2 style="margin-top:0;"><?php esc_html_e( 'Multi-Signer Threshold', 'archivio-id' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Require a minimum number of independently verified signatures before the 🔒 badge is displayed on a post. Useful for regulated content where a single self-signed document is insufficient.', 'archivio-id' ); ?>
	</p>

	<table class="form-table" role="presentation">

		<tr>
			<th scope="row"><?php esc_html_e( 'Threshold Mode', 'archivio-id' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;margin-bottom:8px;">
						<input type="radio"
						       name="archivio_id_sig_threshold_mode"
						       value="<?php echo esc_attr( ArchivioID_Threshold_Policy::MODE_GLOBAL ); ?>"
						       <?php checked( $threshold_mode, ArchivioID_Threshold_Policy::MODE_GLOBAL ); ?> />
						<?php esc_html_e( 'Global — same threshold for all post types', 'archivio-id' ); ?>
					</label>
					<label style="display:block;">
						<input type="radio"
						       name="archivio_id_sig_threshold_mode"
						       value="<?php echo esc_attr( ArchivioID_Threshold_Policy::MODE_PER_TYPE ); ?>"
						       <?php checked( $threshold_mode, ArchivioID_Threshold_Policy::MODE_PER_TYPE ); ?> />
						<?php esc_html_e( 'Per post type — different thresholds per content type', 'archivio-id' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<!-- Global threshold -->
		<tr id="archivio-threshold-global-row"
		    <?php if ( $threshold_mode !== ArchivioID_Threshold_Policy::MODE_GLOBAL ) : ?>
		        style="display:none"
		    <?php endif; ?>>
			<th scope="row">
				<label for="archivio_id_sig_threshold">
					<?php esc_html_e( 'Required Verified Signatures', 'archivio-id' ); ?>
				</label>
			</th>
			<td>
				<input type="number"
				       id="archivio_id_sig_threshold"
				       name="archivio_id_sig_threshold"
				       value="<?php echo esc_attr( $threshold ); ?>"
				       min="1"
				       max="20"
				       class="small-text" />
				<p class="description">
					<?php
					echo wp_kses(
						__( 'The 🔒 badge only appears when at least this many <strong>distinct</strong> keys have each been verified for the post.', 'archivio-id' ),
						array( 'strong' => array() )
					);
					?>
				</p>
				<?php if ( $threshold === 1 ) : ?>
					<p class="description" style="color:#6b7280;">
						<?php esc_html_e( 'Currently set to 1 (default) — any single verified signature shows the badge.', 'archivio-id' ); ?>
					</p>
				<?php else : ?>
					<p class="description" style="color:#0a7537;">
						<?php echo esc_html( sprintf(
							/* translators: %d: threshold count */
							__( 'Currently requiring %d verified signatures before the badge appears.', 'archivio-id' ),
							$threshold
						) ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<!-- Per-type thresholds -->
		<tr id="archivio-threshold-per-type-row"
		    <?php if ( $threshold_mode !== ArchivioID_Threshold_Policy::MODE_PER_TYPE ) : ?>
		        style="display:none"
		    <?php endif; ?>>
			<th scope="row"><?php esc_html_e( 'Per-Type Thresholds', 'archivio-id' ); ?></th>
			<td>
				<table class="widefat" style="max-width:400px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post Type', 'archivio-id' ); ?></th>
							<th><?php esc_html_e( 'Required Signatures', 'archivio-id' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $public_types as $type_key => $type_obj ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $type_obj->labels->singular_name ); ?></strong>
								<br><small style="color:#9ca3af;"><?php echo esc_html( $type_key ); ?></small>
							</td>
							<td>
								<input type="number"
								       name="archivio_id_threshold_by_type[<?php echo esc_attr( $type_key ); ?>]"
								       value="<?php echo esc_attr( $by_type[ $type_key ] ?? 1 ); ?>"
								       min="1"
								       max="20"
								       class="small-text" />
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description" style="margin-top:8px;">
					<?php esc_html_e( 'Set the minimum verified signatures required per content type. Falls back to 1 for any type not listed.', 'archivio-id' ); ?>
				</p>
			</td>
		</tr>

	</table>

	<!-- Live status: posts not meeting threshold -->
	<?php if ( $threshold > 1 || $threshold_mode === ArchivioID_Threshold_Policy::MODE_PER_TYPE ) :
		global $wpdb;
		// Quick count of posts that have at least one verified sig but haven't met threshold.
		// We only do this for global mode with a simple threshold for performance.
		if ( $threshold_mode === ArchivioID_Threshold_Policy::MODE_GLOBAL && $threshold > 1 ) :
			$table = ArchivioID_DB::multi_sigs_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$unmet_count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$table}
				 WHERE status = 'verified'
				 GROUP BY post_id HAVING COUNT(*) < %d",
				$threshold
			) );
			if ( $unmet_count > 0 ) :
			?>
			<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:12px 16px;margin-top:8px;">
				<strong style="font-size:.85rem;color:#c2410c;">
					<?php echo esc_html( sprintf(
						/* translators: %d: count of posts */
						_n(
							'%d post has verified signatures but does not meet the current threshold.',
							'%d posts have verified signatures but do not meet the current threshold.',
							$unmet_count,
							'archivio-id'
						),
						$unmet_count
					) ); ?>
				</strong>
				<p style="margin:4px 0 0;font-size:.82rem;color:#6b7280;">
					<?php esc_html_e( 'These posts will not show the badge until enough additional signers verify them.', 'archivio-id' ); ?>
				</p>
			</div>
			<?php
			endif;
		endif;
	endif; ?>

</div>
