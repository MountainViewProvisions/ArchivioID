<?php
/**
 * ArchivioID Key Management View
 *
 * Variables available from ArchivioID_Key_Admin::render_page():
 *   $key_data  ['keys' => array, 'total' => int]
 *   $page      int (current page)
 *
 * @package ArchivioID
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$keys      = $key_data['keys'];
$total     = $key_data['total'];
$per_page  = 20;
$num_pages = (int) ceil( $total / $per_page );
?>
<div class="wrap archivio-id-wrap">
	<h1><?php esc_html_e( 'Key Management', 'archivio-id' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Upload OpenPGP public keys. Only public key material is accepted — never private keys.', 'archivio-id' ); ?>
	</p>

<?php if ( get_option( 'archivio_id_allow_key_server_lookup', true ) ) : ?>
	<!-- Key Server Lookup -->
	<div class="archivio-id-card" style="max-width:720px;margin-bottom:28px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Fetch Key from Key Server', 'archivio-id' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Look up a public key by fingerprint or email address. Checks WKD first, then keys.openpgp.org. The key is previewed below — you can then add it above.', 'archivio-id' ); ?></p>

		<div id="archivio-id-keyserver-notice" style="display:none;"></div>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="archivio-id-ks-query"><?php esc_html_e( 'Fingerprint or Email', 'archivio-id' ); ?></label></th>
				<td>
					<input type="text" id="archivio-id-ks-query" class="regular-text"
						placeholder="<?php esc_attr_e( 'alice@example.com or ABCD1234...', 'archivio-id' ); ?>" />
				</td>
			</tr>
		</table>
		<p>
			<button type="button" id="archivio-id-ks-lookup-btn" class="button button-secondary">
				<?php esc_html_e( 'Fetch Key', 'archivio-id' ); ?>
			</button>
			<span id="archivio-id-ks-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
		</p>

		<div id="archivio-id-ks-result" style="display:none;">
			<label><strong><?php esc_html_e( 'Retrieved Public Key — review then copy to Add Key above', 'archivio-id' ); ?></strong></label>
			<p id="archivio-id-ks-source" class="description"></p>
			<textarea id="archivio-id-ks-key-output" rows="8" readonly class="large-text code"
				style="font-family:monospace;font-size:11px;"></textarea>
			<p>
				<button type="button" id="archivio-id-ks-use-btn" class="button button-primary">
					<?php esc_html_e( 'Copy to Add Key Form', 'archivio-id' ); ?>
				</button>
			</p>
		</div>
	</div>
<?php endif; ?>

	<!-- Add Key Form -->
	<div class="archivio-id-card" style="max-width:720px;margin-bottom:28px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Add Public Key', 'archivio-id' ); ?></h2>

		<div id="archivio-id-add-key-notice" style="display:none;"></div>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="archivio-id-key-label"><?php esc_html_e( 'Label', 'archivio-id' ); ?> <span class="description">(<?php esc_html_e( 'required', 'archivio-id' ); ?>)</span></label>
				</th>
				<td>
					<input type="text" id="archivio-id-key-label" class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. Alice Smith <alice@example.com>', 'archivio-id' ); ?>" />
					<p class="description"><?php esc_html_e( 'Human-readable name to identify this key.', 'archivio-id' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="archivio-id-armored-key"><?php esc_html_e( 'Armored Public Key', 'archivio-id' ); ?></label>
				</th>
				<td>
					<textarea id="archivio-id-armored-key" rows="10" cols="70"
						placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----&#10;...&#10;-----END PGP PUBLIC KEY BLOCK-----"
						style="font-family:monospace;font-size:12px;width:100%;max-width:600px;"
					></textarea>
					<p class="description">
						<?php esc_html_e( 'Paste the full ASCII-armored public key block. Private keys are rejected.', 'archivio-id' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p>
			<table class="form-table" role="presentation" style="margin:8px 0 0;">
				<tr>
					<th style="padding:4px 8px 4px 0;width:130px;">
						<label for="archivio-id-key-identity-url"><?php esc_html_e( 'Identity proof URL', 'archivio-id' ); ?></label>
					</th>
					<td style="padding:4px 0;">
						<input type="url" id="archivio-id-key-identity-url" placeholder="https://keyoxide.org/…" style="width:100%;max-width:420px;" />
						<p class="description" style="margin:2px 0 0;"><?php esc_html_e( 'Optional: Keyoxide, Keybase, or any HTTPS identity proof page. Shown in the frontend badge tooltip.', 'archivio-id' ); ?></p>
					</td>
				</tr>
			</table>

			<button type="button" id="archivio-id-add-key-btn" class="button button-primary">
				<?php esc_html_e( 'Save Public Key', 'archivio-id' ); ?>
			</button>
			<span id="archivio-id-add-key-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
		</p>
	</div>

	<!-- Keys Table -->
	<h2><?php
		printf(
			/* translators: %d number of keys */
			esc_html( _n( '%d key stored', '%d keys stored', $total, 'archivio-id' ) ),
			(int) $total
		);
	?></h2>

	<?php if ( empty( $keys ) ) : ?>
		<p><?php esc_html_e( 'No public keys stored yet.', 'archivio-id' ); ?></p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:22%"><?php esc_html_e( 'Label', 'archivio-id' ); ?></th>
				<th style="width:30%"><?php esc_html_e( 'Fingerprint', 'archivio-id' ); ?></th>
				<th style="width:14%"><?php esc_html_e( 'Key ID (16)', 'archivio-id' ); ?></th>
				<th style="width:10%"><?php esc_html_e( 'Status', 'archivio-id' ); ?></th>
				<th style="width:14%"><?php esc_html_e( 'Added', 'archivio-id' ); ?></th>
				<th style="width:10%"><?php esc_html_e( 'Algorithm', 'archivio-id' ); ?></th>
				<th style="width:10%"><?php esc_html_e( 'Expires', 'archivio-id' ); ?></th>
				<th style="width:12%"><?php esc_html_e( 'Identity Proof', 'archivio-id' ); ?></th>
				<th style="width:10%"><?php esc_html_e( 'Actions', 'archivio-id' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $keys as $key ) : ?>
			<tr id="archivio-id-key-row-<?php echo (int) $key->id; ?>" data-key-id="<?php echo (int) $key->id; ?>">
				<td><strong><?php echo esc_html( $key->label ); ?></strong></td>
				<td><code style="font-size:11px;word-break:break-all;"><?php
					// Display in groups of 4 for readability.
					echo esc_html( implode( ' ', str_split( strtoupper( $key->fingerprint ), 4 ) ) );
				?></code></td>
				<td><code><?php echo esc_html( strtoupper( $key->key_id ) ); ?></code></td>
				<td>
					<?php if ( $key->is_active ) : ?>
						<span class="archivio-id-badge archivio-id-badge-active"><?php esc_html_e( 'Active', 'archivio-id' ); ?></span>
					<?php else : ?>
						<span class="archivio-id-badge archivio-id-badge-inactive"><?php esc_html_e( 'Inactive', 'archivio-id' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $key->created_at ) ) ); ?></td>
				<td><?php echo esc_html( isset( $key->key_algorithm ) ? $key->key_algorithm : '—' ); ?></td>
				<td><?php
					if ( ! empty( $key->key_expires_at ) ) {
						echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $key->key_expires_at ) ) );
					} else {
						esc_html_e( 'Never', 'archivio-id' );
					}
				?></td>
				<td>
					<?php
					$proof_url = isset( $key->identity_proof_url ) ? $key->identity_proof_url : '';
					?>
					<div class="archivio-id-proof-field" data-key-id="<?php echo (int) $key->id; ?>">
						<?php if ( $proof_url ) : ?>
							<a href="<?php echo esc_url( $proof_url ); ?>" target="_blank" rel="noopener noreferrer"
							   class="archivio-id-proof-link" title="<?php esc_attr_e( 'View identity proof', 'archivio-id' ); ?>">
								<?php esc_html_e( 'View proof', 'archivio-id' ); ?> ↗
							</a><br>
						<?php endif; ?>
						<button type="button" class="button button-small archivio-id-edit-proof"
							data-key-id="<?php echo (int) $key->id; ?>"
							data-current-url="<?php echo esc_attr( $proof_url ); ?>"
							style="font-size:11px;height:auto;padding:1px 5px;">
							<?php echo $proof_url ? esc_html__( 'Edit', 'archivio-id' ) : esc_html__( 'Add proof', 'archivio-id' ); ?>
						</button>
					</div>
				</td>
				<td class="archivio-id-key-actions">
					<?php if ( $key->is_active ) : ?>
					<button type="button" class="button button-small archivio-id-deactivate-key"
						data-key-id="<?php echo (int) $key->id; ?>">
						<?php esc_html_e( 'Deactivate', 'archivio-id' ); ?>
					</button>
					<?php else : ?>
					<button type="button" class="button button-small archivio-id-activate-key"
						data-key-id="<?php echo (int) $key->id; ?>">
						<?php esc_html_e( 'Activate', 'archivio-id' ); ?>
					</button>
					<?php endif; ?>
					<button type="button" class="button button-small archivio-id-delete-key"
						data-key-id="<?php echo (int) $key->id; ?>"
						style="color:#b32d2e;">
						<?php esc_html_e( 'Delete', 'archivio-id' ); ?>
					</button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $num_pages > 1 ) : ?>
	<div class="tablenav bottom" style="margin-top:12px;">
		<div class="tablenav-pages">
			<?php
			echo paginate_links( array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $page,
				'total'   => $num_pages,
			) );
			?>
		</div>
	</div>
	<?php endif; ?>
	<?php endif; ?>

</div><!-- .wrap -->
