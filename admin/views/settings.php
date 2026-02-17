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
	</form>
</div>
