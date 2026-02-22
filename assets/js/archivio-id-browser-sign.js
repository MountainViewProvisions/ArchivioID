/**
 * ArchivioID — Browser Sign
 * Client-side OpenPGP.js signing controller.
 *
 * Key lifecycle:
 *  1. Generate or import → private key decrypted in memory, encrypted armored
 *     copy saved to sessionStorage so it survives page navigation.
 *  2. On page load: if sessionStorage has a saved key, show unlock prompt.
 *  3. At sign time: key is already decrypted in memory (no passphrase needed
 *     again in the same session). On fresh page load after navigate-away, user
 *     re-enters passphrase once to unlock the sessionStorage copy.
 *  4. Private key bytes are NEVER sent to the server. Only the ASCII-armored
 *     detached signature + metadata travel over the network.
 *
 * @package ArchivioID
 * @since   1.3.0
 */

/* global openpgp, archivioIdBrowserSign, jQuery */

(function ($) {
	'use strict';

	// ── Module state ──────────────────────────────────────────────────────────
	let loadedPrivateKey    = null;  // openpgp.PrivateKey (decrypted, in memory only)
	let loadedFingerprint   = '';    // uppercase 40-char hex fingerprint
	let lastSignedSig       = '';    // last produced ASCII-armored signature
	let lastSignMode        = 'post';
	let lastPostId          = 0;
	let lastHexHash         = '';
	let generatedPrivArmored = '';   // raw armored private key string for download
	let generatedPubArmored  = '';   // raw armored public key string for download

	const SESSION_KEY      = 'archivio_id_priv_key_armored'; // sessionStorage key (re-encrypted blob)
	const SESSION_PASS_KEY = 'archivio_id_session_pass';     // sessionStorage key for session passphrase

	/**
	 * Generate a random session passphrase used to re-encrypt the key before
	 * storing in sessionStorage.  This is NOT the user's passphrase — it's a
	 * random secret that lives only in sessionStorage alongside the key blob.
	 * It exists purely to satisfy openpgp.encryptKey() so we always store a
	 * properly encrypted key object (never a bare decrypted armored key).
	 */
	function getOrCreateSessionPass() {
		let sp;
		try { sp = sessionStorage.getItem(SESSION_PASS_KEY); } catch (e) {}
		if (sp) return sp;
		const arr = new Uint8Array(32);
		crypto.getRandomValues(arr);
		sp = Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
		try { sessionStorage.setItem(SESSION_PASS_KEY, sp); } catch (e) {}
		return sp;
	}

	const cfg  = window.archivioIdBrowserSign || {};
	const i18n = cfg.i18n || {};

	// ── DOM refs ──────────────────────────────────────────────────────────────
	const $notice         = $('#abs-notice');
	const $activeKey      = $('#abs-active-key');
	const $activeKeyLabel = $('#abs-active-key-label');
	const $fetchedHashRow = $('#abs-fetched-hash-row');
	const $fetchedHash    = $('#abs-fetched-hash');
	const $sigOutput      = $('#abs-sig-output');
	const $sigText        = $('#abs-sig-text');
	const $verifResult    = $('#abs-verification-result');
	const $genOutput      = $('#abs-generated-output');
	const $unlockBanner   = $('#abs-unlock-banner');

	// ── Utilities ─────────────────────────────────────────────────────────────

	function showNotice(msg, type) {
		$notice
			.removeClass('notice-success notice-error notice-info notice-warning abs-hidden')
			.addClass('notice notice-' + (type || 'info'))
			.html('<p>' + escHtml(msg) + '</p>');
	}

	function hideNotice() {
		$notice.addClass('abs-hidden');
	}

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function spinner(id, show) {
		const $s = $('#' + id);
		show ? $s.addClass('is-active') : $s.removeClass('is-active');
	}

	function getFingerprint(key) {
		try {
			const fp = key.getFingerprint();
			// v5 returns a hex string directly
			if (typeof fp === 'string') {
				return fp.toUpperCase();
			}
			// Older path: Uint8Array
			if (fp instanceof Uint8Array) {
				return Array.from(fp).map(b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
			}
			return String(fp).toUpperCase();
		} catch (e) {
			return '';
		}
	}

	function getKeyLabel(key) {
		try {
			const uid = key.getUserIDs()[0] || '';
			const fp  = getFingerprint(key);
			return uid + ' [' + fp.slice(-8) + ']';
		} catch (e) {
			return '[unknown key]';
		}
	}

	/**
	 * Activate a decrypted key in memory and re-encrypt it with a random
	 * session passphrase before saving to sessionStorage.
	 *
	 * We always re-encrypt rather than trusting whatever armored string was
	 * passed in — this guarantees the sessionStorage blob is always a valid
	 * encrypted key regardless of whether the source was generated, imported,
	 * or came from a previous session.
	 *
	 * @param {openpgp.PrivateKey} decryptedKey  Already-decrypted key object
	 */
	async function activateKey(decryptedKey) {
		loadedPrivateKey  = decryptedKey;
		loadedFingerprint = getFingerprint(decryptedKey);

		const label = getKeyLabel(decryptedKey);
		$activeKeyLabel.text(label);
		$activeKey.removeClass('abs-hidden');
		$unlockBanner.addClass('abs-hidden');

		// Re-encrypt with a random session passphrase and persist to sessionStorage.
		// This guarantees we always store a properly-encrypted key blob — never a
		// bare decrypted key — regardless of how the key was originally loaded.
		try {
			const sessionPass   = getOrCreateSessionPass();
			const reEncrypted   = await openpgp.encryptKey({ privateKey: decryptedKey, passphrase: sessionPass });
			const reArmoredBlob = await reEncrypted.armor();
			sessionStorage.setItem(SESSION_KEY, reArmoredBlob);
		} catch (e) {
			// sessionStorage unavailable or re-encrypt failed — key lives in memory only
			console.warn('ArchivioID: could not persist key to sessionStorage:', e);
		}
	}

	function clearKey() {
		loadedPrivateKey  = null;
		loadedFingerprint = '';
		$activeKey.addClass('abs-hidden');
		$activeKeyLabel.text('');
		try {
			sessionStorage.removeItem(SESSION_KEY);
			sessionStorage.removeItem(SESSION_PASS_KEY);
		} catch (e) {}
	}

	// ── PAGE LOAD: restore key from sessionStorage if available ───────────────

	(function restoreSessionKey() {
		let saved, savedPass;
		try {
			saved     = sessionStorage.getItem(SESSION_KEY);
			savedPass = sessionStorage.getItem(SESSION_PASS_KEY);
		} catch (e) {}

		if (!saved || !savedPass) return;

		// A key from a previous page visit is available.  Because we always
		// re-encrypt with a random session passphrase (not the user's passphrase),
		// we can unlock it silently here without asking the user again.
		(async function silentUnlock() {
			try {
				const encKey      = await openpgp.readPrivateKey({ armoredKey: saved });
				const decryptedKey = await openpgp.decryptKey({ privateKey: encKey, passphrase: savedPass });
				// Set state directly — don't call activateKey() to avoid re-encrypting again
				loadedPrivateKey  = decryptedKey;
				loadedFingerprint = getFingerprint(decryptedKey);
				$activeKeyLabel.text(getKeyLabel(decryptedKey));
				$activeKey.removeClass('abs-hidden');
				$unlockBanner.addClass('abs-hidden');
				showNotice('Session key restored — ready to sign.', 'success');
			} catch (e) {
				// Session passphrase didn't work (e.g. storage was corrupted).
				// Clear stale data and show the manual unlock banner as fallback.
				try {
					sessionStorage.removeItem(SESSION_KEY);
					sessionStorage.removeItem(SESSION_PASS_KEY);
				} catch (ex) {}
				$unlockBanner.removeClass('abs-hidden');
			}
		}());
	}());

	// The unlock banner is now only shown as a fallback when silent unlock fails.
	// The passphrase field it contains prompts for the USER's original passphrase
	// so they can manually re-import if sessionStorage was corrupted or cleared.
	$('#abs-btn-unlock-session').on('click', async function () {
		let saved;
		try { saved = sessionStorage.getItem(SESSION_KEY); } catch (e) {}

		// If the stored blob is from the new session-passphrase scheme, silent
		// unlock already failed — clear stale storage and ask user to re-import.
		if (!saved) {
			$unlockBanner.addClass('abs-hidden');
			showNotice('Session expired. Please re-import your private key.', 'info');
			return;
		}

		const passphrase = $('#abs-unlock-pass').val();
		if (!passphrase) {
			showNotice('Enter your passphrase to unlock the saved key.', 'error');
			return;
		}

		spinner('abs-spinner-unlock', true);
		$(this).prop('disabled', true);

		try {
			// Try to decrypt the stored blob with the USER's passphrase.
			// This handles legacy sessions where we stored the original encrypted armor.
			const encKey       = await openpgp.readPrivateKey({ armoredKey: saved });
			const decryptedKey = await openpgp.decryptKey({ privateKey: encKey, passphrase });
			await activateKey(decryptedKey);
			showNotice('Key unlocked and ready to sign.', 'success');
		} catch (err) {
			showNotice('Could not unlock key: ' + err.message + ' — check your passphrase, or use "Discard saved key" and re-import.', 'error');
		} finally {
			spinner('abs-spinner-unlock', false);
			$(this).prop('disabled', false);
		}
	});

	$('#abs-btn-discard-session').on('click', function () {
		clearKey();
		$unlockBanner.addClass('abs-hidden');
		showNotice('Saved key discarded.', 'info');
	});

	// ── KEY GENERATION ────────────────────────────────────────────────────────

	$('#abs-btn-generate').on('click', async function () {
		hideNotice();
		const name  = $('#abs-gen-name').val().trim();
		const email = $('#abs-gen-email').val().trim();
		const pass  = $('#abs-gen-pass').val();
		const pass2 = $('#abs-gen-pass2').val();

		if (!name || !email) {
			showNotice('Please enter a name and email address.', 'error');
			return;
		}
		if (!pass) {
			showNotice('Please enter a passphrase to protect the key.', 'error');
			return;
		}
		if (pass !== pass2) {
			showNotice('Passphrases do not match.', 'error');
			return;
		}

		spinner('abs-spinner-gen', true);
		$(this).prop('disabled', true);

		try {
			// OpenPGP.js v5: use format:'armored' to get strings back directly.
			// If privateKey comes back as an object (some builds), call .armor().
			let genResult;
			try {
				genResult = await openpgp.generateKey({
					type:       'ecc',
					curve:      'curve25519',
					userIDs:    [{ name, email }],
					passphrase: pass,
					format:     'armored',
				});
			} catch (e) {
				// Fallback: generate without format option
				genResult = await openpgp.generateKey({
					type:      'ecc',
					curve:     'curve25519',
					userIDs:   [{ name, email }],
					passphrase: pass,
				});
			}

			const { privateKey: privResult, publicKey: pubResult } = genResult;

			// Resolve to strings — handle both armored strings and Key objects
			const armoredPublic = (typeof pubResult === 'string')
				? pubResult
				: await pubResult.armor();

			const armoredPrivate = (typeof privResult === 'string')
				? privResult
				: await privResult.armor();

			if (!armoredPrivate || !armoredPublic) {
				throw new Error('Key generation returned empty armored output. privResult type: ' + typeof privResult);
			}

			// Parse the encrypted private key object to decrypt in memory.
			// Use privResult directly if it's already a Key object, otherwise parse.
			const privKeyObj = (typeof privResult === 'string')
				? await openpgp.readPrivateKey({ armoredKey: armoredPrivate })
				: privResult;

			// Decrypt into memory immediately — user just typed the passphrase
			const decryptedKey = await openpgp.decryptKey({ privateKey: privKeyObj, passphrase: pass });

			// Activate: decrypted key in memory, re-encrypted copy saved to sessionStorage
			await activateKey(decryptedKey);

			// Store raw armored strings — used by download/copy, NOT read from textarea
			generatedPrivArmored = armoredPrivate;
			generatedPubArmored  = armoredPublic;

			// Populate textareas for visual confirmation only
			$('#abs-generated-pubkey').val(armoredPublic);
			$('#abs-generated-privkey').val(armoredPrivate);
			$genOutput.removeClass('abs-hidden');

			showNotice(
				(i18n.keyGenerated || 'New key pair generated.') +
				' Upload the public key to ArchivioID → Keys, then return here to sign.',
				'success'
			);
		} catch (err) {
			showNotice('Key generation failed: ' + err.message + ' (see browser console for details)', 'error');
			console.error('ArchivioID generateKey error:', err);
		} finally {
			spinner('abs-spinner-gen', false);
			$(this).prop('disabled', false);
		}
	});

	// ── KEY IMPORT (paste) ────────────────────────────────────────────────────

	$('#abs-btn-import').on('click', async function () {
		await importKeyFromText($('#abs-import-key').val(), $('#abs-import-pass').val());
	});

	// ── KEY IMPORT (file) ─────────────────────────────────────────────────────

	$('#abs-import-file').on('change', async function () {
		const file = this.files[0];
		if (!file) return;
		const text = await file.text();
		$('#abs-import-key').val(text);
	});

	async function importKeyFromText(armoredKey, passphrase) {
		hideNotice();
		armoredKey = (armoredKey || '').trim();
		if (!armoredKey) {
			showNotice('Paste or load a private key first.', 'error');
			return;
		}

		spinner('abs-spinner-gen', true);

		try {
			const encKey = await openpgp.readPrivateKey({ armoredKey });

			// Decrypt if passphrase provided
			let decryptedKey = encKey;
			if (!encKey.isDecrypted()) {
				if (!passphrase) {
					showNotice('This key is passphrase-protected. Enter the passphrase.', 'error');
					return;
				}
				decryptedKey = await openpgp.decryptKey({ privateKey: encKey, passphrase });
			}

			await activateKey(decryptedKey);
			showNotice(i18n.keyImported || 'Private key imported and ready.', 'success');
		} catch (err) {
			showNotice('Key import failed: ' + err.message, 'error');
		} finally {
			spinner('abs-spinner-gen', false);
		}
	}

	// ── CLEAR KEY ─────────────────────────────────────────────────────────────

	$('#abs-btn-clear-key').on('click', function () {
		clearKey();
		hideNotice();
		showNotice('Key cleared from memory and session.', 'info');
	});

	// ── SIGN MODE TOGGLE ──────────────────────────────────────────────────────

	$('input[name="abs-sign-mode"]').on('change', function () {
		lastSignMode = this.value;
		$('#abs-mode-post').toggleClass('abs-hidden', this.value !== 'post');
		$('#abs-mode-hash').toggleClass('abs-hidden', this.value === 'post');
		$sigOutput.addClass('abs-hidden');
		$verifResult.addClass('abs-hidden');
	});

	// ── FETCH ARCHIVIOMD HASH ─────────────────────────────────────────────────

	$('#abs-btn-fetch-hash').on('click', async function () {
		hideNotice();
		const postId = parseInt($('#abs-post-id').val(), 10);
		if (!postId || postId < 1) {
			showNotice('Enter a valid post ID.', 'error');
			return;
		}

		spinner('abs-spinner-fetch', true);
		$(this).prop('disabled', true);

		try {
			const response = await $.ajax({
				url:      cfg.ajaxUrl,
				method:   'POST',
				data:     { action: 'archivio_id_get_post_hash', nonce: cfg.nonce, post_id: postId },
				dataType: 'json',
			});

			if (response.success) {
				const { hash, post_title, algorithm, mode } = response.data;
				$fetchedHash.text(hash);
				$fetchedHashRow.removeClass('abs-hidden');
				lastPostId  = postId;
				lastHexHash = hash;
				// Show algorithm info
				const algoLabel = algorithm + (mode === 'hmac' ? ' (HMAC)' : '');
				showNotice('Hash fetched for "' + (post_title || 'Post #' + postId) + '" — algorithm: ' + algoLabel, 'info');
			} else {
				showNotice((response.data && response.data.message) || 'Could not fetch hash.', 'error');
			}
		} catch (xhr) {
			showNotice(xhrErrorMessage(xhr), 'error');
		} finally {
			spinner('abs-spinner-fetch', false);
			$(this).prop('disabled', false);
		}
	});

	// ── SIGN ──────────────────────────────────────────────────────────────────

	$('#abs-btn-sign').on('click', async function () {
		hideNotice();
		$sigOutput.addClass('abs-hidden');
		$verifResult.addClass('abs-hidden');

		if (!loadedPrivateKey) {
			showNotice(i18n.noKey || 'No private key loaded. Generate or import a key first.', 'error');
			return;
		}

		let hexHash = '';
		let postId  = 0;

		if (lastSignMode === 'post') {
			postId  = parseInt($('#abs-post-id').val(), 10) || 0;
			hexHash = $fetchedHash.text().trim();
			if (!postId) { showNotice('Enter a post ID and fetch its hash first.', 'error'); return; }
			if (!hexHash) { showNotice('Fetch the post hash before signing.', 'error'); return; }
		} else {
			hexHash = $('#abs-manual-hash').val().trim().toLowerCase();
			if (!/^[0-9a-f]{32,}$/.test(hexHash)) {
				showNotice('Enter a valid hex hash (at least 32 hex characters).', 'error');
				return;
			}
		}

		spinner('abs-spinner-sign', true);
		$(this).prop('disabled', true);

		try {
			// Key is already decrypted in memory — sign directly.
			// (Passphrase field is no longer needed in the normal flow;
			//  it's only used on the unlock-session path above.)
			const signingKey = loadedPrivateKey;

			if (!signingKey.isDecrypted()) {
				// Shouldn't happen in normal flow, but handle gracefully
				showNotice('Key is locked. Please unlock it first using the unlock prompt.', 'error');
				return;
			}

			const message     = await openpgp.createMessage({ text: hexHash });
			const detachedSig = await openpgp.sign({
				message,
				signingKeys: signingKey,
				detached:    true,
			});

			lastSignedSig = detachedSig;
			lastHexHash   = hexHash;
			lastPostId    = postId;

			$sigText.val(detachedSig);
			$sigOutput.removeClass('abs-hidden');
			showNotice('Signature created. Upload it to verify on the server.', 'success');
		} catch (err) {
			showNotice('Signing failed: ' + err.message, 'error');
		} finally {
			spinner('abs-spinner-sign', false);
			$(this).prop('disabled', false);
		}
	});

	// ── UPLOAD & VERIFY ───────────────────────────────────────────────────────

	$('#abs-btn-upload').on('click', async function () {
		hideNotice();
		$verifResult.removeClass('abs-hidden').html('');

		if (!lastSignedSig) { showNotice('Sign content first.', 'error'); return; }
		if (!loadedFingerprint) { showNotice('No key fingerprint available. Reload your key.', 'error'); return; }

		spinner('abs-spinner-upload', true);
		$(this).prop('disabled', true);

		try {
			const postData = {
				action:        'archivio_id_browser_sign',
				nonce:         cfg.nonce,
				signature_asc: lastSignedSig,
				fingerprint:   loadedFingerprint,
				hex_hash:      lastHexHash,
			};
			if (lastPostId) postData.post_id = lastPostId;

			const response = await $.ajax({
				url:      cfg.ajaxUrl,
				method:   'POST',
				data:     postData,
				dataType: 'json',
			});

			if (response.success) {
				$verifResult
					.removeClass('abs-result-error').addClass('abs-result-success')
					.html('<span class="dashicons dashicons-yes-alt"></span> ' +
						escHtml(response.data.message || i18n.verified));
				showNotice(response.data.message || i18n.verified, 'success');
			} else {
				const errMsg = (response.data && response.data.message) || (i18n.failed || 'Verification failed.');
				$verifResult
					.removeClass('abs-result-success').addClass('abs-result-error')
					.html('<span class="dashicons dashicons-warning"></span> ' + escHtml(errMsg));
				showNotice(errMsg, 'error');
			}
		} catch (xhr) {
			showNotice(xhrErrorMessage(xhr), 'error');
		} finally {
			spinner('abs-spinner-upload', false);
			$(this).prop('disabled', false);
		}
	});

	// ── COPY TO CLIPBOARD ─────────────────────────────────────────────────────

	$(document).on('click', '.abs-copy-btn', async function () {
		const targetId = $(this).data('target');

		let text;
		if (targetId === 'abs-generated-privkey') {
			text = generatedPrivArmored;
		} else if (targetId === 'abs-generated-pubkey') {
			text = generatedPubArmored;
		} else {
			text = $('#' + targetId).val();
		}

		if (!text) {
			showNotice('Nothing to copy yet.', 'error');
			return;
		}

		try {
			await navigator.clipboard.writeText(text);
			const $btn = $(this), orig = $btn.text();
			$btn.text(i18n.copied || 'Copied!');
			setTimeout(() => $btn.text(orig), 1500);
		} catch (e) {
			showNotice('Copy failed — please select and copy manually.', 'error');
		}
	});

	// ── DOWNLOAD ──────────────────────────────────────────────────────────────

	$(document).on('click', '.abs-download-btn', function () {
		const targetId = $(this).data('target');
		const filename = $(this).data('filename') || 'download.asc';

		// Use raw stored strings for generated keys — avoids textarea val() mangling
		let text;
		if (targetId === 'abs-generated-privkey') {
			text = generatedPrivArmored;
		} else if (targetId === 'abs-generated-pubkey') {
			text = generatedPubArmored;
		} else {
			text = $('#' + targetId).val();
		}

		if (!text) {
			showNotice('Nothing to download yet.', 'error');
			return;
		}

		// Use data: URI — more reliable than createObjectURL on Android Chrome
		try {
			const dataUri = 'data:text/plain;charset=utf-8,' + encodeURIComponent(text);
			const a = document.createElement('a');
			a.href     = dataUri;
			a.download = filename;
			a.style.display = 'none';
			document.body.appendChild(a);
			a.click();
			setTimeout(() => a.remove(), 500);
		} catch (e) {
			// Final fallback: open in new tab so user can save manually
			const w = window.open('', '_blank');
			if (w) {
				w.document.write('<pre>' + text.replace(/</g, '&lt;') + '</pre>');
				w.document.title = filename;
				showNotice('Opened in new tab — use your browser\'s Save option to download.', 'info');
			} else {
				showNotice('Download blocked. Copy the key manually.', 'error');
			}
		}
	});

	// ── HELPERS ───────────────────────────────────────────────────────────────

	function xhrErrorMessage(xhr) {
		if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
			return xhr.responseJSON.data.message;
		}
		let msg = 'Request failed';
		if (xhr && xhr.status) msg += ' (HTTP ' + xhr.status + ')';
		if (xhr && xhr.responseText) {
			const preview = xhr.responseText.replace(/<[^>]+>/g, '').trim().slice(0, 150);
			if (preview) msg += ': ' + preview;
		}
		return msg;
	}

}(jQuery));
