<?php
/**
 * ArchivioID — Post Editor Meta Box View
 *
 * Variables provided by ArchivioID_Post_Integration::render_meta_box():
 *   $post         WP_Post
 *   $packed_hash  string|'' — from _archivio_post_hash post meta
 *   $sig_row      object|null — from archivio_id_signatures table
 *   $active_keys  array — active keys for the dropdown
 *
 * @package ArchivioID
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Read any transient upload notice for this post + user.
$notice_key     = 'archivio_id_upload_notice_' . $post->ID . '_' . get_current_user_id();
$upload_notice  = get_transient( $notice_key );
if ( $upload_notice ) {
	delete_transient( $notice_key );
}

// Determine badge / status.
$status = $sig_row ? $sig_row->status : 'not_signed';

$badge_map = array(
	'not_signed' => array( 'label' => __( 'Not Signed',         'archivio-id' ), 'class' => 'not-signed'    ),
	'uploaded'   => array( 'label' => __( 'Signature Uploaded', 'archivio-id' ), 'class' => 'uploaded'      ),
	'verified'   => array( 'label' => __( 'Verified',           'archivio-id' ), 'class' => 'verified'      ),
	'invalid'    => array( 'label' => __( 'Invalid Signature',  'archivio-id' ), 'class' => 'invalid'       ),
	'error'      => array( 'label' => __( 'Error',              'archivio-id' ), 'class' => 'error'         ),
);
$badge = $badge_map[ $status ] ?? $badge_map['not_signed'];
?>

<div class="archivio-id-metabox" data-post-id="<?php echo (int) $post->ID; ?>">

	<!-- Status badge -->
	<p>
		<span class="archivio-id-status-badge archivio-id-status-<?php echo esc_attr( $badge['class'] ); ?>">
			<?php echo esc_html( $badge['label'] ); ?>
		</span>
	</p>

	<?php if ( $upload_notice ) : ?>
	<div class="archivio-id-notice archivio-id-notice-<?php echo esc_attr( $upload_notice['type'] ); ?>">
		<?php echo esc_html( $upload_notice['message'] ); ?>
	</div>
	<?php endif; ?>

	<?php if ( empty( $packed_hash ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'ArchivioMD has not generated a hash for this post yet. Enable auto-generate in Cryptographic Verification settings, then publish/update this post.', 'archivio-id' ); ?>
		</p>
	<?php else : ?>

		<!-- Current hash info -->
		<p class="description" style="word-break:break-all;">
			<strong><?php esc_html_e( 'Hash:', 'archivio-id' ); ?></strong>
			<code style="font-size:10px;"><?php echo esc_html( $packed_hash ); ?></code>
		</p>

		<?php if ( $sig_row && in_array( $sig_row->status, array( 'uploaded', 'verified', 'invalid', 'error' ), true ) ) : ?>
			<!-- Existing signature: show verify / delete -->
			<p class="description">
				<?php
				printf(
					/* translators: date string */
					esc_html__( 'Uploaded %s', 'archivio-id' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $sig_row->uploaded_at ) ) )
				);
				?>
			</p>

			<?php if ( $sig_row->failure_reason ) : ?>
			<p class="description" style="color:#d73a49;">
				<?php echo esc_html( $sig_row->failure_reason ); ?>
			</p>
			<?php endif; ?>

			<?php if ( $status === 'verified' && $sig_row->verified_at ) : ?>
			<p class="description" style="color:#0a7537;">
				<?php
				printf(
					/* translators: date string */
					esc_html__( 'Verified %s', 'archivio-id' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $sig_row->verified_at ) ) )
				);
				?>
			</p>
			<?php endif; ?>

			<p>
				<button type="button" id="archivio-id-verify-btn" class="button button-primary">
					<?php esc_html_e( 'Verify Signature', 'archivio-id' ); ?>
				</button>
				<button type="button" id="archivio-id-delete-sig-btn" class="button" style="color:#b32d2e;margin-left:4px;">
					<?php esc_html_e( 'Remove', 'archivio-id' ); ?>
				</button>
				<span id="archivio-id-action-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
			</p>
			<p id="archivio-id-verify-result" style="display:none;"></p>

		<?php else : ?>
			<!-- No signature yet: show upload form -->
			<?php if ( empty( $active_keys ) ) : ?>
			<div class="archivio-id-notice archivio-id-notice-warning">
				<?php
				printf(
					/* translators: URL to key management page */
					wp_kses(
						__( 'No active public keys found. <a href="%s">Add a key</a> in ArchivioID → Key Management first.', 'archivio-id' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'admin.php?page=archivio-id-keys' ) )
				);
				?>
			</div>
			<?php else : ?>

			<p class="description"><?php esc_html_e( 'Upload a detached GPG signature (.asc) for the hash above.', 'archivio-id' ); ?></p>

			<table class="form-table" role="presentation" style="margin:0;">
				<tr>
					<th style="padding:4px 8px 4px 0;width:80px;">
						<label for="archivio_id_key_id"><?php esc_html_e( 'Key', 'archivio-id' ); ?></label>
					</th>
					<td style="padding:4px 0;">
						<select name="archivio_id_key_id" id="archivio_id_key_id" style="max-width:100%;">
							<option value=""><?php esc_html_e( '— select key —', 'archivio-id' ); ?></option>
							<?php foreach ( $active_keys as $k ) : ?>
							<option value="<?php echo (int) $k->id; ?>">
								<?php echo esc_html( $k->label ); ?>
								(<?php echo esc_html( strtoupper( $k->key_id ) ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th style="padding:4px 8px 4px 0;">
						<label for="archivio_id_sig_file"><?php esc_html_e( '.asc file', 'archivio-id' ); ?></label>
					</th>
					<td style="padding:4px 0;">
						<input type="file" name="archivio_id_sig_file" id="archivio_id_sig_file" accept=".asc" />
						<p class="description" style="margin:2px 0 0;">
							<?php esc_html_e( 'Must be a PGP detached signature (.asc) of the hex hash string above.', 'archivio-id' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="description" style="margin-top:10px;">
				<em><?php esc_html_e( 'Save/update this post to upload the signature.', 'archivio-id' ); ?></em>
			</p>

			<?php endif; // active keys ?>
		<?php endif; // existing sig vs upload form ?>

	<?php endif; // has packed_hash ?>

</div><!-- .archivio-id-metabox -->
