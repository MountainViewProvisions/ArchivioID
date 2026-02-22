<?php
/**
 * Admin view: Browser Sign page
 *
 * @package ArchivioID
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap archivio-browser-sign">

	<h1><?php esc_html_e( 'Browser Sign', 'archivio-id' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Create detached OpenPGP signatures directly in your browser. Your private key never leaves this device.', 'archivio-id' ); ?>
	</p>

	<?php /* ── GLOBAL NOTICE ─────────────────────────────────────────── */ ?>
	<div id="abs-notice" class="abs-notice abs-hidden" role="alert" aria-live="polite"></div>

	<?php /* ── SESSION KEY UNLOCK BANNER ──────────────────────────────── */ ?>
	<div id="abs-unlock-banner" class="abs-unlock-banner abs-hidden">
		<span class="dashicons dashicons-lock"></span>
		<strong><?php esc_html_e( 'A key from your previous session is saved. Enter your passphrase to unlock it.', 'archivio-id' ); ?></strong>
		<input type="password" id="abs-unlock-pass" class="regular-text" placeholder="<?php esc_attr_e( 'Passphrase', 'archivio-id' ); ?>" autocomplete="current-password">
		<button type="button" id="abs-btn-unlock-session" class="button button-primary">
			<?php esc_html_e( 'Unlock Key', 'archivio-id' ); ?>
		</button>
		<span class="abs-spinner spinner" id="abs-spinner-unlock"></span>
		<button type="button" id="abs-btn-discard-session" class="button button-link abs-discard-btn">
			<?php esc_html_e( 'Discard saved key', 'archivio-id' ); ?>
		</button>
	</div>

	<div class="abs-grid">

		<?php /* ══════════════════════════════════════════════════════════
		       PANEL 1 — KEY MANAGEMENT
		       ══════════════════════════════════════════════════════════ */ ?>
		<div class="abs-panel" id="abs-panel-key">
			<h2><?php esc_html_e( 'Step 1 — Load Your Private Key', 'archivio-id' ); ?></h2>

			<?php /* Generate new key */ ?>
			<div class="abs-section">
				<h3><?php esc_html_e( 'Generate a new key pair', 'archivio-id' ); ?></h3>
				<p class="description"><?php esc_html_e( 'After generating, copy the public key and add it under ArchivioID → Keys. Then return here — your key will still be active.', 'archivio-id' ); ?></p>
				<table class="form-table abs-form-table">
					<tr>
						<th><label for="abs-gen-name"><?php esc_html_e( 'Name', 'archivio-id' ); ?></label></th>
						<td><input type="text" id="abs-gen-name" class="regular-text" placeholder="<?php esc_attr_e( 'Your Name', 'archivio-id' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="abs-gen-email"><?php esc_html_e( 'Email', 'archivio-id' ); ?></label></th>
						<td><input type="email" id="abs-gen-email" class="regular-text" placeholder="<?php esc_attr_e( 'you@example.com', 'archivio-id' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="abs-gen-pass"><?php esc_html_e( 'Passphrase', 'archivio-id' ); ?></label></th>
						<td><input type="password" id="abs-gen-pass" class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="abs-gen-pass2"><?php esc_html_e( 'Confirm', 'archivio-id' ); ?></label></th>
						<td><input type="password" id="abs-gen-pass2" class="regular-text" autocomplete="new-password"></td>
					</tr>
				</table>
				<button type="button" id="abs-btn-generate" class="button button-primary">
					<?php esc_html_e( 'Generate Key Pair', 'archivio-id' ); ?>
				</button>
				<span class="abs-spinner spinner" id="abs-spinner-gen"></span>

				<div id="abs-generated-output" class="abs-hidden abs-key-output">

					<label><?php esc_html_e( 'Public Key — copy this and add it under ArchivioID → Keys', 'archivio-id' ); ?></label>
					<textarea id="abs-generated-pubkey" rows="7" readonly class="large-text code"></textarea>
					<div class="abs-btn-row">
						<button type="button" class="button abs-copy-btn" data-target="abs-generated-pubkey">
							<?php esc_html_e( 'Copy Public Key', 'archivio-id' ); ?>
						</button>
						<button type="button" class="button abs-download-btn" data-target="abs-generated-pubkey" data-filename="public-key.asc">
							<?php esc_html_e( 'Download Public Key', 'archivio-id' ); ?>
						</button>
					</div>

					<div class="abs-privkey-download-box">
						<p class="abs-privkey-warning">
							<span class="dashicons dashicons-warning"></span>
							<strong><?php esc_html_e( 'Download your private key now.', 'archivio-id' ); ?></strong>
							<?php esc_html_e( 'It is protected by your passphrase but will be lost when you close this tab. Store it somewhere safe.', 'archivio-id' ); ?>
						</p>
						<textarea id="abs-generated-privkey" rows="7" readonly class="large-text code"></textarea>
						<div class="abs-btn-row">
							<button type="button" class="button abs-copy-btn" data-target="abs-generated-privkey">
								<?php esc_html_e( 'Copy Private Key', 'archivio-id' ); ?>
							</button>
							<button type="button" class="button button-primary abs-download-btn" data-target="abs-generated-privkey" data-filename="private-key.asc">
								<?php esc_html_e( 'Download Private Key', 'archivio-id' ); ?>
							</button>
						</div>
					</div>

					<p class="description abs-tip">
						<?php esc_html_e( 'Your private key stays active in this tab. You can navigate to upload the public key and return — the key will be remembered for this browser session.', 'archivio-id' ); ?>
					</p>
				</div>
			</div>

			<hr>

			<?php /* Import existing key */ ?>
			<div class="abs-section">
				<h3><?php esc_html_e( 'Import an existing private key', 'archivio-id' ); ?></h3>
				<table class="form-table abs-form-table">
					<tr>
						<th><label for="abs-import-key"><?php esc_html_e( 'Armored Private Key', 'archivio-id' ); ?></label></th>
						<td>
							<textarea id="abs-import-key" rows="7" class="large-text code"
								placeholder="<?php esc_attr_e( '-----BEGIN PGP PRIVATE KEY BLOCK-----', 'archivio-id' ); ?>"></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="abs-import-pass"><?php esc_html_e( 'Passphrase', 'archivio-id' ); ?></label></th>
						<td><input type="password" id="abs-import-pass" class="regular-text" autocomplete="current-password"></td>
					</tr>
				</table>
				<div class="abs-btn-row">
					<button type="button" id="abs-btn-import" class="button button-secondary">
						<?php esc_html_e( 'Import Key', 'archivio-id' ); ?>
					</button>
					<input type="file" id="abs-import-file" accept=".asc,.gpg,.pgp,.txt" class="abs-file-input">
					<label for="abs-import-file" class="button button-secondary">
						<?php esc_html_e( 'Load from File', 'archivio-id' ); ?>
					</label>
				</div>
			</div>

			<?php /* Active key indicator */ ?>
			<div id="abs-active-key" class="abs-active-key abs-hidden">
				<span class="dashicons dashicons-unlock"></span>
				<span id="abs-active-key-label"></span>
				<button type="button" id="abs-btn-clear-key" class="button button-link abs-clear-key">
					<?php esc_html_e( 'Clear', 'archivio-id' ); ?>
				</button>
			</div>
		</div>

		<?php /* ══════════════════════════════════════════════════════════
		       PANEL 2 — SIGN
		       ══════════════════════════════════════════════════════════ */ ?>
		<div class="abs-panel" id="abs-panel-sign">
			<h2><?php esc_html_e( 'Step 2 — Sign & Submit', 'archivio-id' ); ?></h2>

			<div class="abs-section">
				<div class="abs-toggle-row">
					<label>
						<input type="radio" name="abs-sign-mode" value="post" checked>
						<?php esc_html_e( 'Sign a post (by ID)', 'archivio-id' ); ?>
					</label>
					<label>
						<input type="radio" name="abs-sign-mode" value="hash">
						<?php esc_html_e( 'Sign a raw hex hash', 'archivio-id' ); ?>
					</label>
				</div>

				<div id="abs-mode-post" class="abs-mode-section">
					<table class="form-table abs-form-table">
						<tr>
							<th><label for="abs-post-id"><?php esc_html_e( 'Post ID', 'archivio-id' ); ?></label></th>
							<td>
								<input type="number" id="abs-post-id" class="small-text" min="1" step="1">
								<button type="button" id="abs-btn-fetch-hash" class="button button-secondary">
									<?php esc_html_e( 'Fetch Hash', 'archivio-id' ); ?>
								</button>
								<span class="abs-spinner spinner" id="abs-spinner-fetch"></span>
							</td>
						</tr>
					</table>
					<div id="abs-fetched-hash-row" class="abs-hidden">
						<label><?php esc_html_e( 'ArchivioMD Hash (this is what gets signed)', 'archivio-id' ); ?></label>
						<code id="abs-fetched-hash" class="abs-hash-display"></code>
					</div>
				</div>

				<div id="abs-mode-hash" class="abs-mode-section abs-hidden">
					<table class="form-table abs-form-table">
						<tr>
							<th><label for="abs-manual-hash"><?php esc_html_e( 'Hex Hash', 'archivio-id' ); ?></label></th>
							<td>
								<input type="text" id="abs-manual-hash" class="large-text code"
									placeholder="<?php esc_attr_e( 'SHA-256 / SHA-512 / BLAKE2b hex string', 'archivio-id' ); ?>">
							</td>
						</tr>
					</table>
				</div>

				<button type="button" id="abs-btn-sign" class="button button-primary">
					<?php esc_html_e( 'Sign', 'archivio-id' ); ?>
				</button>
				<span class="abs-spinner spinner" id="abs-spinner-sign"></span>
			</div>

			<div id="abs-sig-output" class="abs-sig-output abs-hidden">
				<h3><?php esc_html_e( 'Detached Signature', 'archivio-id' ); ?></h3>
				<textarea id="abs-sig-text" rows="10" readonly class="large-text code"></textarea>
				<div class="abs-btn-row">
					<button type="button" class="button abs-copy-btn" data-target="abs-sig-text">
						<?php esc_html_e( 'Copy', 'archivio-id' ); ?>
					</button>
					<button type="button" class="button abs-download-btn" data-target="abs-sig-text" data-filename="detached.sig">
						<?php esc_html_e( 'Download .sig', 'archivio-id' ); ?>
					</button>
					<button type="button" id="abs-btn-upload" class="button button-primary">
						<?php esc_html_e( 'Upload & Verify on Server', 'archivio-id' ); ?>
					</button>
					<span class="abs-spinner spinner" id="abs-spinner-upload"></span>
				</div>
				<div id="abs-verification-result" class="abs-verification-result abs-hidden" aria-live="polite"></div>
			</div>
		</div>

	</div><!-- .abs-grid -->

	<div class="abs-info-footer">
		<details>
			<summary><?php esc_html_e( 'How this works', 'archivio-id' ); ?></summary>
			<ol>
				<li><?php esc_html_e( 'Generate a key pair. Copy the public key and add it under ArchivioID → Keys. The private key stays in your browser.', 'archivio-id' ); ?></li>
				<li><?php esc_html_e( 'Return here (your key persists for this browser tab session). Enter a post ID and fetch its ArchivioMD hash.', 'archivio-id' ); ?></li>
				<li><?php esc_html_e( 'Click Sign. OpenPGP.js creates a detached signature over the hash string locally.', 'archivio-id' ); ?></li>
				<li><?php esc_html_e( 'Click Upload & Verify. Only the signature is sent. The server verifies it using phpseclib + OpenPGP-PHP and logs the result.', 'archivio-id' ); ?></li>
			</ol>
			<p><strong><?php esc_html_e( 'Your private key never leaves this browser.', 'archivio-id' ); ?></strong>
			<?php esc_html_e( 'The encrypted key is held in sessionStorage (cleared when you close the tab).', 'archivio-id' ); ?></p>
		</details>
	</div>

</div>
