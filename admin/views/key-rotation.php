<?php
/**
 * ArchivioID — Key Rotation Page View
 *
 * Variables provided by ArchivioID_Key_Rotation_Admin::render_page():
 *   $all_keys   array   All key rows
 *   $count_map  array   [ key_db_id => signed_post_count ]
 *
 * @package ArchivioID
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( 'archivio_id_admin_action' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Key Rotation', 'archivio-id' ); ?></h1>

	<p class="description" style="max-width:700px;">
		<?php esc_html_e( 'Key rotation re-assigns all posts signed by an old key to a replacement key. The old key is then deactivated. Post signatures are reset to "uploaded" — you must re-sign each affected post with the new key and re-upload the .asc file.', 'archivio-id' ); ?>
	</p>

	<div class="archivio-id-card" style="max-width:760px;margin-top:20px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Select Keys', 'archivio-id' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="aid-old-key"><?php esc_html_e( 'Key to retire (old)', 'archivio-id' ); ?></label>
				</th>
				<td>
					<select id="aid-old-key" style="min-width:340px;">
						<option value=""><?php esc_html_e( '— select key to retire —', 'archivio-id' ); ?></option>
						<?php foreach ( $all_keys as $key ) :
							$state    = $key->is_revoked ? '🚫 revoked' : ( ! $key->is_active ? '⬛ inactive' : '✅ active' );
							$count    = $count_map[ $key->id ] ?? 0;
							$fp_short = strtoupper( substr( $key->fingerprint, -8 ) );
							$algo     = $key->key_algorithm ? " · {$key->key_algorithm}" : '';
							$label    = "{$key->label} ({$fp_short}{$algo}) — {$count} post(s) · {$state}";
						?>
						<option value="<?php echo (int) $key->id; ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="aid-new-key"><?php esc_html_e( 'Replacement key (new)', 'archivio-id' ); ?></label>
				</th>
				<td>
					<select id="aid-new-key" style="min-width:340px;">
						<option value=""><?php esc_html_e( '— select replacement key —', 'archivio-id' ); ?></option>
						<?php foreach ( $all_keys as $key ) :
							if ( ! $key->is_active || $key->is_revoked ) continue;
							$count    = $count_map[ $key->id ] ?? 0;
							$fp_short = strtoupper( substr( $key->fingerprint, -8 ) );
							$algo     = $key->key_algorithm ? " · {$key->key_algorithm}" : '';
							$label    = "{$key->label} ({$fp_short}{$algo}) — {$count} post(s)";
						?>
						<option value="<?php echo (int) $key->id; ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Only active, non-revoked keys are shown as replacement candidates.', 'archivio-id' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" id="aid-rotation-preview" class="button button-secondary">
				<?php esc_html_e( 'Preview Affected Posts', 'archivio-id' ); ?>
			</button>
			<span id="aid-rotation-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
		</p>

		<div id="aid-rotation-error" style="display:none;" class="notice notice-error inline"><p></p></div>
	</div><!-- .archivio-id-card -->

	<!-- Preview panel (hidden until preview is fetched) -->
	<div id="aid-rotation-preview-panel" style="display:none;max-width:760px;margin-top:20px;" class="archivio-id-card">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Preview', 'archivio-id' ); ?></h2>

		<div id="aid-rotation-summary"></div>

		<div style="max-height:320px;overflow-y:auto;margin:12px 0;">
			<table class="widefat striped" id="aid-rotation-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post ID', 'archivio-id' ); ?></th>
						<th><?php esc_html_e( 'Title', 'archivio-id' ); ?></th>
						<th><?php esc_html_e( 'Post Status', 'archivio-id' ); ?></th>
						<th><?php esc_html_e( 'Sig Status', 'archivio-id' ); ?></th>
					</tr>
				</thead>
				<tbody id="aid-rotation-tbody"></tbody>
			</table>
		</div>

		<div class="notice notice-warning inline" style="margin:0 0 12px;">
			<p>
				<strong><?php esc_html_e( 'Important:', 'archivio-id' ); ?></strong>
				<?php esc_html_e( 'After rotation, all affected posts will show as "Uploaded" (not verified). You must re-sign each post offline with the new key and re-upload the .asc file. Existing .asc files were signed by the old key and cannot be verified against the new key.', 'archivio-id' ); ?>
			</p>
		</div>

		<p>
			<button type="button" id="aid-rotation-execute" class="button button-primary">
				<?php esc_html_e( 'Rotate Key', 'archivio-id' ); ?>
			</button>
			<button type="button" id="aid-rotation-cancel" class="button button-secondary" style="margin-left:4px;">
				<?php esc_html_e( 'Cancel', 'archivio-id' ); ?>
			</button>
			<span id="aid-rotation-exec-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
		</p>
	</div><!-- #aid-rotation-preview-panel -->

	<!-- Result panel (shown after execution) -->
	<div id="aid-rotation-result-panel" style="display:none;max-width:760px;margin-top:20px;" class="archivio-id-card">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Rotation Complete', 'archivio-id' ); ?></h2>
		<div id="aid-rotation-result-msg"></div>
		<h3><?php esc_html_e( 'Next steps', 'archivio-id' ); ?></h3>
		<ol style="line-height:1.8;">
			<li><?php esc_html_e( 'The old key has been deactivated.', 'archivio-id' ); ?></li>
			<li><?php esc_html_e( 'All affected posts now show as "Uploaded" (un-verified).', 'archivio-id' ); ?></li>
			<li><?php printf(
				wp_kses(
					/* translators: %s: URL to browser sign page */
					__( 'Re-sign each post with the new key. Use <a href="%s">Browser Sign</a> or your local gpg.', 'archivio-id' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( admin_url( 'admin.php?page=archivio-id-browser-sign' ) )
			); ?></li>
			<li><?php printf(
				wp_kses(
					/* translators: %s: URL to signatures list */
					__( 'Visit the <a href="%s">Signatures</a> screen and bulk re-verify once re-signed.', 'archivio-id' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( admin_url( 'admin.php?page=archivio-id-signatures' ) )
			); ?></li>
		</ol>
	</div>

</div><!-- .wrap -->
