<?php
/**
 * ArchivioID Settings Page View
 *
 * @package ArchivioID
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$badge_enabled = get_option( 'archivio_id_badge_enabled', true );
$show_on_pages = get_option( 'archivio_id_show_on_pages', true );
$show_on_posts = get_option( 'archivio_id_show_on_posts', true );
?>

<div class="wrap archivio-id-wrap">
	<h1>
		<?php esc_html_e( 'ArchivioID Settings', 'archivio-id' ); ?>
		<span class="archivio-id-version">v<?php echo esc_html( ARCHIVIO_ID_VERSION ); ?></span>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Configure how the lock emoji appears when signatures are verified.', 'archivio-id' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'archivio_id_settings' ); ?>
		
		<div class="archivio-id-card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'Lock Emoji Display', 'archivio-id' ); ?></h2>
			
			<div style="background: #e7f5fe; border-left: 4px solid #00a0d2; padding: 15px; margin-bottom: 20px;">
				<p style="margin: 0 0 10px 0; font-weight: 600;">
					<?php esc_html_e( '🔒 Lock Emoji Placement', 'archivio-id' ); ?>
				</p>
				<p style="margin: 0; color: #646970;">
					<?php esc_html_e( 'When a post has a verified PGP signature, a lock emoji (🔒) will automatically appear next to the ArchivioMD verification badge in the post title.', 'archivio-id' ); ?>
				</p>
				<p style="margin: 10px 0 0 0; color: #646970;">
					<strong><?php esc_html_e( 'Example:', 'archivio-id' ); ?></strong><br>
					<code style="background: white; padding: 8px 12px; border-radius: 3px; display: inline-block; margin-top: 5px; border: 1px solid #ddd;">
						<?php esc_html_e( 'Post Title [Verified ✓] 🔒', 'archivio-id' ); ?>
					</code>
				</p>
			</div>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Enable Lock Emoji', 'archivio-id' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" 
							       name="archivio_id_badge_enabled" 
							       value="1" 
							       <?php checked( $badge_enabled, true ); ?> />
							<?php esc_html_e( 'Display 🔒 emoji on verified posts', 'archivio-id' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, the lock emoji will appear next to the post title when a signature is verified.', 'archivio-id' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Display On', 'archivio-id' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" 
								       name="archivio_id_show_on_posts" 
								       value="1" 
								       <?php checked( $show_on_posts, true ); ?> />
								<?php esc_html_e( 'Posts', 'archivio-id' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" 
								       name="archivio_id_show_on_pages" 
								       value="1" 
								       <?php checked( $show_on_pages, true ); ?> />
								<?php esc_html_e( 'Pages', 'archivio-id' ); ?>
							</label>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select which post types should show the lock emoji.', 'archivio-id' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button( __( 'Save Settings', 'archivio-id' ), 'primary', 'submit', false ); ?>
			</p>
		</div>

		<div class="archivio-id-card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'Shortcode Usage', 'archivio-id' ); ?></h2>
			<p>
				<?php esc_html_e( 'You can also manually place the lock emoji using this shortcode:', 'archivio-id' ); ?>
			</p>
			<code style="display: block; padding: 10px; background: #f6f7f7; border-radius: 4px; margin: 10px 0;">
				[archivio_id_badge]
			</code>
			<p class="description">
				<?php esc_html_e( 'The shortcode displays 🔒 only if the post has a verified signature. Shows nothing otherwise.', 'archivio-id' ); ?>
			</p>
		</div>

		<div class="archivio-id-card" style="max-width: 800px; margin-top: 20px; background: #f6f7f7; border-color: #8c8f94;">
			<h2><?php esc_html_e( 'How It Works', 'archivio-id' ); ?></h2>
			<ol style="line-height: 1.8;">
				<li>
					<?php esc_html_e( 'ArchivioMD generates a content hash and displays its verification badge', 'archivio-id' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'You upload a PGP signature (.asc file) for the post', 'archivio-id' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'ArchivioID verifies the signature matches the content hash', 'archivio-id' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'If verified:', 'archivio-id' ); ?></strong>
					<?php esc_html_e( ' 🔒 appears next to the ArchivioMD badge', 'archivio-id' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'If not verified:', 'archivio-id' ); ?></strong>
					<?php esc_html_e( ' Nothing appears (clean and simple)', 'archivio-id' ); ?>
				</li>
			</ol>
		</div>

		<!-- ── Scheduled Re-Verification (v3.0.0) ─────────────────────────── -->
		<div class="archivio-id-card" style="max-width:800px;margin-top:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Scheduled Re-Verification', 'archivio-id' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'A daily cron job checks all verified signatures against the live post hash. If a post was edited after signing, its badge is automatically flipped to invalid.', 'archivio-id' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable daily re-verification', 'archivio-id' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="archivio_id_cron_enabled" value="1"
								<?php checked( get_option( 'archivio_id_cron_enabled', true ) ); ?> />
							<?php esc_html_e( 'Run daily content-drift check', 'archivio-id' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Full crypto re-check', 'archivio-id' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="archivio_id_cron_recheck_crypto" value="1"
								<?php checked( get_option( 'archivio_id_cron_recheck_crypto', false ) ); ?> />
							<?php esc_html_e( 'Also re-run cryptographic verification (slower — use only on small sites)', 'archivio-id' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Key server lookup', 'archivio-id' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="archivio_id_allow_key_server_lookup" value="1"
								<?php checked( get_option( 'archivio_id_allow_key_server_lookup', true ) ); ?> />
							<?php esc_html_e( 'Allow outbound lookups to keys.openpgp.org and WKD (HKP fetch in Key Management page)', 'archivio-id' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php
			$last_run = get_option( 'archivio_id_last_cron_run', null );
			$next_ts  = wp_next_scheduled( ArchivioID_Cron_Verifier::HOOK );
			?>
			<p>
				<?php if ( $next_ts ) : ?>
					<span style="color:#666;">
						<?php
						printf(
							/* translators: date string */
							esc_html__( 'Next scheduled run: %s', 'archivio-id' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $next_ts ) )
						);
						?>
					</span>
				<?php else : ?>
					<span style="color:#d73a49;"><?php esc_html_e( 'Cron not scheduled — deactivate and reactivate the plugin to reschedule.', 'archivio-id' ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( $last_run ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: 1: date, 2: checked, 3: invalidated */
					esc_html__( 'Last run: %1$s — checked %2$d, invalidated %3$d.', 'archivio-id' ),
					esc_html( $last_run['run_at'] ?? '' ),
					(int) ( $last_run['checked'] ?? 0 ),
					(int) ( $last_run['invalidated'] ?? 0 )
				);
				?>
			</p>
			<?php endif; ?>

			<p>
				<button type="button" id="archivio-id-cron-run-now" class="button button-secondary">
					<?php esc_html_e( 'Run Now', 'archivio-id' ); ?>
				</button>
				<span id="archivio-id-cron-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
				<span id="archivio-id-cron-result" style="margin-left:8px;"></span>
			</p>
		</div>


		<!-- ── Key Expiry Emails (v4.0.0) ──────────────────────────────────── -->
		<div class="archivio-id-card" style="max-width:800px;margin-top:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Key Expiry Notifications', 'archivio-id' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'When a signing key is within 30, 14, or 3 days of expiry, an email is sent to the key owner (added_by user). One email per window per key — no daily spam.', 'archivio-id' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable expiry emails', 'archivio-id' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="archivio_id_expiry_emails" value="1"
								<?php checked( get_option( 'archivio_id_expiry_emails', true ) ); ?> />
							<?php esc_html_e( 'Send warning emails at 30, 14, and 3 days before expiry', 'archivio-id' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Recipient', 'archivio-id' ); ?></th>
					<td>
						<p class="description">
							<?php esc_html_e( 'Emails go to the WordPress user recorded as the key\'s added_by, falling back to the site admin email if the user no longer exists.', 'archivio-id' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" id="archivio-id-expiry-check-now" class="button button-secondary">
					<?php esc_html_e( 'Run Expiry Check Now', 'archivio-id' ); ?>
				</button>
				<span id="archivio-id-expiry-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
				<span id="archivio-id-expiry-result" style="margin-left:8px;"></span>
			</p>
		</div>

		<?php
		// ── v5.1.0: Algorithm Enforcement Floor ──────────────────────────────
		if ( class_exists( 'ArchivioID_Algorithm_Enforcer' ) ) {
			require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/settings-algo-policy.php';
		}

		// ── v5.1.0: Multi-Signer Threshold ───────────────────────────────────
		if ( class_exists( 'ArchivioID_Threshold_Policy' ) ) {
			require ARCHIVIO_ID_PLUGIN_DIR . 'admin/views/settings-threshold.php';
		}
		?>

	</form>
</div>
