<?php
/**
 * Meta Box View: Signature Verification (Enhanced)
 *
 * Variables provided by ArchivioID_Post_Meta_Box::render_meta_box():
 *   @var WP_Post $post         Current post object
 *   @var object|null $sig_row  Signature data from database
 *   @var array $active_keys    Available public keys
 *   @var string $packed_hash   Hash from ArchivioMD
 *   @var string $status        Current status (not_signed, uploaded, verified, invalid, error)
 *   @var array|false $notice   Transient notice to display
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Status badge configuration
$status_badges = array(
	'not_signed' => array(
		'label' => __( 'Not Signed', 'archivio-id' ),
		'class' => 'archivio-status-not-signed',
		'icon' => '○'
	),
	'uploaded' => array(
		'label' => __( 'Signature Uploaded', 'archivio-id' ),
		'class' => 'archivio-status-uploaded',
		'icon' => '◐'
	),
	'verified' => array(
		'label' => __( 'Verified', 'archivio-id' ),
		'class' => 'archivio-status-verified',
		'icon' => '✓'
	),
	'invalid' => array(
		'label' => __( 'Invalid Signature', 'archivio-id' ),
		'class' => 'archivio-status-invalid',
		'icon' => '✗'
	),
	'error' => array(
		'label' => __( 'Error', 'archivio-id' ),
		'class' => 'archivio-status-error',
		'icon' => '!'
	)
);

$badge = $status_badges[ $status ] ?? $status_badges['not_signed'];
?>

<div class="archivio-meta-box" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
	
	<!-- Status Badge -->
	<div class="archivio-status-section">
		<div class="archivio-status-badge <?php echo esc_attr( $badge['class'] ); ?>">
			<span class="archivio-status-icon"><?php echo esc_html( $badge['icon'] ); ?></span>
			<span class="archivio-status-label"><?php echo esc_html( $badge['label'] ); ?></span>
		</div>
	</div>

	<!-- Backend Info (Optional Display) -->
	<div class="archivio-backend-info" style="display: none;">
		<small class="description">
			<span id="archivio-backend-name"><?php esc_html_e( 'Backend: Loading...', 'archivio-id' ); ?></span>
		</small>
	</div>

	<!-- Notice Display -->
	<?php if ( $notice ) : ?>
		<div class="archivio-notice archivio-notice-<?php echo esc_attr( $notice['type'] ); ?>">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Hash Information -->
	<?php if ( empty( $packed_hash ) ) : ?>
		<div class="archivio-notice archivio-notice-info">
			<p>
				<?php esc_html_e( 'ArchivioMD has not generated a hash for this post yet.', 'archivio-id' ); ?>
				<br>
				<small><?php esc_html_e( 'Enable auto-generate in ArchivioMD settings, then save this post.', 'archivio-id' ); ?></small>
			</p>
		</div>
	<?php else : ?>
		
		<!-- Hash Display -->
		<div class="archivio-hash-section">
			<label class="archivio-label">
				<?php esc_html_e( 'Post Hash:', 'archivio-id' ); ?>
			</label>
			<div class="archivio-hash-display">
				<code><?php echo esc_html( substr( $packed_hash, 0, 32 ) . '...' ); ?></code>
				<button type="button" class="button-link archivio-copy-hash" data-hash="<?php echo esc_attr( $packed_hash ); ?>">
					<?php esc_html_e( 'Copy', 'archivio-id' ); ?>
				</button>
			</div>
		</div>

		<?php if ( $sig_row && in_array( $status, array( 'uploaded', 'verified', 'invalid', 'error' ), true ) ) : ?>
			
			<!-- Existing Signature Section -->
			<div class="archivio-signature-section">
				
				<!-- Upload Info -->
				<div class="archivio-info-row">
					<span class="dashicons dashicons-upload"></span>
					<span>
						<?php
						printf(
							/* translators: date and time */
							esc_html__( 'Uploaded %s', 'archivio-id' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sig_row->uploaded_at ) ) )
						);
						?>
					</span>
				</div>

				<!-- Verification Info -->
				<?php if ( $status === 'verified' && $sig_row->verified_at ) : ?>
					<div class="archivio-info-row archivio-info-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<span>
							<?php
							printf(
								/* translators: date and time */
								esc_html__( 'Verified %s', 'archivio-id' ),
								esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sig_row->verified_at ) ) )
							);
							?>
						</span>
					</div>
				<?php endif; ?>

				<!-- Failure Reason -->
				<?php if ( $sig_row->failure_reason ) : ?>
					<div class="archivio-info-row archivio-info-error">
						<span class="dashicons dashicons-warning"></span>
						<span><?php echo esc_html( $sig_row->failure_reason ); ?></span>
					</div>
				<?php endif; ?>

				<!-- Action Buttons -->
				<div class="archivio-actions">
					<button type="button" id="archivio-verify-btn" class="button button-primary">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Verify Signature', 'archivio-id' ); ?>
					</button>
					<button type="button" id="archivio-delete-btn" class="button button-link-delete">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Remove', 'archivio-id' ); ?>
					</button>
					<span id="archivio-action-spinner" class="spinner"></span>
				</div>

				<!-- AJAX Result Display -->
				<div id="archivio-ajax-result" style="display: none;"></div>
			</div>

		<?php else : ?>
			
			<!-- Upload Form Section -->
			<div class="archivio-upload-section">
				
				<?php if ( empty( $active_keys ) ) : ?>
					<!-- No Keys Warning -->
					<div class="archivio-notice archivio-notice-warning">
						<p>
							<?php
							printf(
								/* translators: URL to key management page */
								wp_kses(
									__( 'No active public keys found. <a href="%s">Add a key</a> in Key Management first.', 'archivio-id' ),
									array( 'a' => array( 'href' => array() ) )
								),
								esc_url( admin_url( 'admin.php?page=archivio-id-keys' ) )
							);
							?>
						</p>
					</div>
				<?php else : ?>
					
					<!-- Upload Form -->
					<div class="archivio-form-group">
						<label for="archivio_id_key_id" class="archivio-label">
							<?php esc_html_e( 'Select Key:', 'archivio-id' ); ?>
						</label>
						<select name="archivio_id_key_id" id="archivio_id_key_id" class="widefat">
							<option value=""><?php esc_html_e( '— Select a key —', 'archivio-id' ); ?></option>
							<?php foreach ( $active_keys as $key ) : ?>
								<option value="<?php echo esc_attr( $key->id ); ?>">
									<?php echo esc_html( $key->label ); ?>
									(<?php echo esc_html( strtoupper( substr( $key->fingerprint, -8 ) ) ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="archivio-form-group">
						<label for="archivio_id_signature_file" class="archivio-label">
							<?php esc_html_e( 'Signature File (.asc):', 'archivio-id' ); ?>
						</label>
						<input 
							type="file" 
							name="archivio_id_signature_file" 
							id="archivio_id_signature_file" 
							accept=".asc"
							class="widefat"
						/>
						<p class="description">
							<?php esc_html_e( 'Upload a detached GPG signature (.asc) of the hash shown above.', 'archivio-id' ); ?>
						</p>
					</div>

					<div class="archivio-upload-instructions">
						<p class="description">
							<strong><?php esc_html_e( 'Instructions:', 'archivio-id' ); ?></strong>
						</p>
						<ol class="archivio-instructions-list">
							<li><?php esc_html_e( 'Copy the hash above', 'archivio-id' ); ?></li>
							<li><?php esc_html_e( 'Sign it offline with your private key', 'archivio-id' ); ?></li>
							<li><?php esc_html_e( 'Upload the resulting .asc file here', 'archivio-id' ); ?></li>
							<li><?php esc_html_e( 'Update/publish this post to save', 'archivio-id' ); ?></li>
							<li><?php esc_html_e( 'Click "Verify Signature" to confirm', 'archivio-id' ); ?></li>
						</ol>
					</div>

				<?php endif; ?>
			</div>

		<?php endif; ?>

	<?php endif; ?>

	<!-- Debug Info (Hidden by default, can be toggled) -->
	<div class="archivio-debug-info" style="display: none;">
		<details>
			<summary><?php esc_html_e( 'Debug Information', 'archivio-id' ); ?></summary>
			<dl>
				<dt><?php esc_html_e( 'Post ID:', 'archivio-id' ); ?></dt>
				<dd><?php echo esc_html( $post->ID ); ?></dd>
				
				<dt><?php esc_html_e( 'Status:', 'archivio-id' ); ?></dt>
				<dd><?php echo esc_html( $status ); ?></dd>
				
				<dt><?php esc_html_e( 'Has Hash:', 'archivio-id' ); ?></dt>
				<dd><?php echo esc_html( $packed_hash ? 'Yes' : 'No' ); ?></dd>
				
				<dt><?php esc_html_e( 'Has Signature:', 'archivio-id' ); ?></dt>
				<dd><?php echo esc_html( $sig_row ? 'Yes' : 'No' ); ?></dd>
				
				<dt><?php esc_html_e( 'Active Keys:', 'archivio-id' ); ?></dt>
				<dd><?php echo esc_html( count( $active_keys ) ); ?></dd>
			</dl>
		</details>
	</div>

</div>
