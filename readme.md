# ArchivioID

> OpenPGP detached-signature verification for WordPress posts — clickable lock icon, tamper-evident audit log, pure PHP, no server GPG required.

**ArchivioID** is an add-on plugin for [ArchivioMD](https://mountainviewprovisions.com/ArchivioMD) that brings cryptographic author proof to WordPress. Authors sign posts locally with their own GPG key, upload the `.asc` signature file through the post editor, and ArchivioID verifies it, displays a clickable 🔒 badge, and logs every event to a tamper-evident audit trail.

[![Version](https://img.shields.io/badge/version-1.3.1-667eea)](https://github.com/MountainViewProvisions/archivio-id/releases)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759B)](https://wordpress.org)
[![Requires](https://img.shields.io/badge/requires-ArchivioMD%201.5.0%2B-764ba2)](https://mountainviewprovisions.com/ArchivioMD)

---

## Table of Contents

- [Why ArchivioID](#why-archivio-id)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [How Signing Works](#how-signing-works)
  - [What Gets Signed](#what-gets-signed)
  - [⚠️ Critical: Strip the Algorithm Prefix Before Signing](#️-critical-strip-the-algorithm-prefix-before-signing)
  - [Step-by-Step Signing Guide](#step-by-step-signing-guide)
  - [Signing by Platform](#signing-by-platform)
  - [Supported Algorithms](#supported-algorithms)
  - [Common Signing Errors](#common-signing-errors)
- [Features](#features)
  - [Public Key Management](#public-key-management)
  - [Per-Post Signature Upload](#per-post-signature-upload)
  - [Verification Engine](#verification-engine)
  - [Frontend Badge & Download](#frontend-badge--download)
  - [Audit Log](#audit-log)
- [Architecture](#architecture)
- [Security](#security)
- [Audit Log Reference](#audit-log-reference)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [License](#license)

---

## Why ArchivioID

WordPress has no native mechanism to prove who wrote a post or that it has not been altered since publication. Existing PGP plugins address email encryption or contact forms — none integrate detached OpenPGP signature verification into the post editing workflow.

ArchivioID fills that gap. A detached GPG signature:

- **Proves authorship** — the signature can only be created by the holder of the private key
- **Proves integrity** — any change to the post content breaks the signature
- **Is independently verifiable** — any reader can verify using standard GPG tools, without needing WordPress, ArchivioID, or any proprietary software

Relevant use cases: investigative journalism, security disclosures, legal and compliance documentation, academic publishing, open-source release notes, whistleblower platforms, DevOps runbooks.

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.0 |
| PHP | 7.4 |
| MySQL / MariaDB | 5.7 / 10.3 |
| **ArchivioMD** | **1.5.0** (required parent plugin) |
| Server GPG | **Not required** — pure PHP crypto |

---

## Installation

### 1. Install the parent plugin first

ArchivioID will not activate without ArchivioMD ≥ 1.5.0.

```

```

### 2. Install ArchivioID

**Via WordPress admin:**
```
Plugins → Add New → Upload Plugin → archivio-id-v1.2.0.zip → Install → Activate
```

**Via FTP / SSH:**
```bash
unzip archivio-id-v1.2.0.zip -d /path/to/wp-content/plugins/
```

Then activate through **Plugins → Installed Plugins**.

### 3. Upload your public key

```
ArchivioID → Key Management → Add Key
```

Paste your ASCII-armored public key block and give it a label. The plugin extracts and stores the full 40-character fingerprint. Only public keys are accepted — private key material is rejected at import.

---

## Quick Start

```
1. Install ArchivioMD + ArchivioID
2. Upload GPG public key → ArchivioID → Key Management
3. Write and publish a post in WordPress
4. Copy the hash from the ArchivioID meta box
5. Strip the algorithm prefix (see below) and sign the hex hash locally
6. Upload the .asc file in the meta box → click Verify
7. 🔒 badge appears on the published post
```

---

## How Signing Works

### What Gets Signed

ArchivioID does **not** sign raw post content. It signs the **hex hash** that ArchivioMD generates and stores in the `_archivio_post_hash` post meta field.

When you open the post editor, the ArchivioID meta box displays:

```
Hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

This packed format includes the algorithm name followed by a colon, then the hex digest.

---

### ⚠️ Critical: Strip the Algorithm Prefix Before Signing

**You must sign only the hex portion of the hash — not the full string including the algorithm prefix.**

The meta box displays the full packed string for reference:

```
sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

Before signing, remove everything up to and including the colon. You sign only:

```
e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

**Why?** ArchivioID's verification engine internally unpacks the stored hash using `MDSM_Hash_Helper::unpack()`, which strips the algorithm prefix before passing the raw hex digest to phpseclib for verification. If you sign the full `sha256:hex` string, the bytes will not match what the verifier checks, and verification will always fail.

| What you see in meta box | What you sign |
|---|---|
| `sha256:e3b0c44298...` | `e3b0c44298...` |
| `sha512:cf83e1357e...` | `cf83e1357e...` |
| `sha3-256:a7ffc6f8...` | `a7ffc6f8...` |

**Rule:** Copy from the first character after the colon to the end of the string. Never include the prefix.

---

### Step-by-Step Signing Guide

#### Step 1 — Copy the hash from the meta box

Open your post editor. In the **ArchivioID** sidebar panel you will see:

```
Hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

#### Step 2 — Extract only the hex part

Strip everything up to and including the colon. Your working value is:

```
e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

Save it without a trailing newline:

```bash
echo -n "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855" > hash.txt
```

> **`echo -n` is required.** A trailing newline changes the byte sequence and will cause verification to fail. Standard `echo` without `-n` appends `\n`.

#### Step 3 — Create the detached signature

Match the `--digest-algo` flag to the algorithm shown in the meta box prefix:

```bash
# SHA-256 (ArchivioMD default)
gpg --digest-algo SHA256 --detach-sign --armor hash.txt
```

This creates `hash.txt.asc`. That is the file you upload.

#### Step 4 — Confirm the output format

```bash
head -1 hash.txt.asc
# Must output: -----BEGIN PGP SIGNATURE-----

tail -1 hash.txt.asc
# Must output: -----END PGP SIGNATURE-----
```

If you see `-----BEGIN PGP MESSAGE-----` you used `--encrypt` instead of `--detach-sign`.  
If you see `-----BEGIN PGP SIGNED MESSAGE-----` you used `--clearsign` instead of `--detach-sign`.

#### Step 5 — Upload and verify in WordPress

1. Edit the post
2. In the ArchivioID meta box, select your key from the dropdown
3. Upload `hash.txt.asc`
4. Click **Update** (or **Publish**)
5. Click **Verify Signature**
6. The page reloads and shows **Verified ✓**

---

### Signing by Platform

#### Linux / macOS — command line (recommended)

```bash
# One-liner: pipe directly to gpg
echo -n "YOUR_HEX_HASH_HERE" | gpg --digest-algo SHA256 --detach-sign --armor > signature.asc
```

Replace `YOUR_HEX_HASH_HERE` with the hex string only (no `sha256:` prefix).

For other algorithms:

```bash
# SHA-512
echo -n "YOUR_HEX_HASH" | gpg --digest-algo SHA512 --detach-sign --armor > signature.asc

# SHA-384
echo -n "YOUR_HEX_HASH" | gpg --digest-algo SHA384 --detach-sign --armor > signature.asc
```

To sign with a specific key when you have multiple:

```bash
echo -n "YOUR_HEX_HASH" | gpg --local-user YOUR_KEY_ID --digest-algo SHA256 --detach-sign --armor > signature.asc
```

#### macOS — GPG Suite (GUI)

1. Copy the hex hash from the meta box (without the `sha256:` prefix)
2. Open TextEdit → Format → Make Plain Text
3. Paste the hex hash
4. Save as `hash.txt`
5. Right-click `hash.txt` → Services → **OpenPGP: Sign File**
6. Select your key
7. Output: `hash.txt.asc` — upload this file

> **Note:** GPG Suite defaults to the key's preferred digest algorithm. If verification fails, use the command line and specify `--digest-algo` explicitly to match the ArchivioMD algorithm.

#### Windows — Kleopatra (GUI)

1. Copy the hex hash from the meta box (without the `sha256:` prefix)
2. Open Notepad, paste the hex, save as `hash.txt` (ANSI or UTF-8, no BOM)
3. Open Kleopatra
4. **File → Sign/Encrypt Files** → select `hash.txt`
5. Check **Create detached signature**
6. Check **ASCII armor**
7. Uncheck **Encrypt**
8. Select your signing key → **Sign**
9. Output: `hash.txt.asc` — upload this file

#### Yubikey / Hardware Token

Hardware tokens work with standard GPG. The signing process is identical to the command-line method — GPG handles communication with the token transparently:

```bash
echo -n "YOUR_HEX_HASH" | gpg --digest-algo SHA256 --detach-sign --armor > signature.asc
```

GPG will prompt for your PIN. The private key never leaves the hardware token.

---

### Supported Algorithms

| Algorithm | Meta box prefix | GPG flag | Notes |
|---|---|---|---|
| SHA-256 | `sha256:` | `--digest-algo SHA256` | Default, recommended |
| SHA-512 | `sha512:` | `--digest-algo SHA512` | High security |
| SHA-384 | `sha384:` | `--digest-algo SHA384` | |
| SHA-224 | `sha224:` | `--digest-algo SHA224` | |
| SHA3-256 | `sha3-256:` | `--digest-algo SHA3-256` | Requires GnuPG 2.2.12+ |
| BLAKE2b | `blake2b:` | Not directly supported | Use CLI workaround |
| SHA-1 | `sha1:` | `--digest-algo SHA1` | Not recommended |

The algorithm is always shown in the meta box prefix. Match `--digest-algo` to whatever prefix you see.

---

### Common Signing Errors

#### "Signature verification failed" — wrong prefix included

**Cause:** You signed `sha256:abc123...` instead of `abc123...`

**Fix:**
```bash
# ❌ Wrong — includes prefix
echo -n "sha256:e3b0c44298..." | gpg --detach-sign --armor > sig.asc

# ✅ Correct — hex only
echo -n "e3b0c44298..." | gpg --detach-sign --armor > sig.asc
```

#### "Signature verification failed" — trailing newline

**Cause:** Used `echo` without `-n`

**Fix:**
```bash
# ❌ Wrong — echo adds \n
echo "e3b0c44298..." > hash.txt

# ✅ Correct — no newline
echo -n "e3b0c44298..." > hash.txt
```

Verify byte count:
```bash
wc -c hash.txt
# SHA-256 hex = 64 bytes, not 65
```

#### "Signature verification failed" — algorithm mismatch

**Cause:** GPG used a different digest algorithm than ArchivioMD

**Fix:** Explicitly specify `--digest-algo` matching the prefix in the meta box. Without this flag, GPG uses the algorithm specified in your key preferences, which may differ.

#### "This file contains an encrypted PGP message"

**Cause:** Used `--encrypt` instead of `--detach-sign`

**Fix:**
```bash
# ❌ Wrong
gpg --encrypt --armor hash.txt

# ✅ Correct
gpg --detach-sign --armor hash.txt
```

#### "This file contains a cleartext signed message"

**Cause:** Used `--clearsign` instead of `--detach-sign`

**Fix:**
```bash
# ❌ Wrong
gpg --clearsign hash.txt

# ✅ Correct
gpg --detach-sign --armor hash.txt
```

#### Verification fails after editing the post

**Cause:** Editing post content causes ArchivioMD to regenerate the hash. The old signature no longer matches.

**Fix:** After editing, the meta box will show a new hash. Delete the old signature, re-sign the new hash, and re-upload.

---

## Features

### Public Key Management

- Upload ASCII-armored OpenPGP public keys via admin UI
- Plugin extracts and stores the full 40-character RFC 4880 fingerprint automatically
- Label keys by author, role, or device (e.g. "Alice — YubiKey 5")
- Supports multiple keys for multi-author sites
- Soft-delete (deactivate) keys without losing signature history
- Private key detection at import — any block containing private key material is rejected immediately
- 4 KB size limit per key

### Per-Post Signature Upload

- Integrated meta box on every post type (posts, pages, custom post types)
- Upload `.asc` detached signature files directly from the post editor
- AJAX verification runs immediately on upload
- Replace or delete signatures at any time
- 4 KB maximum file size (sufficient for any standard detached signature)
- Structural validation: file must contain `-----BEGIN PGP SIGNATURE-----` / `-----END PGP SIGNATURE-----`

### Verification Engine

Pure PHP — no system GPG installation required, works on shared hosting.

- **phpseclib v3** — cryptographic operations
- **OpenPGP-PHP** — OpenPGP packet parsing and decoding
- RSA, DSA, and EdDSA (Ed25519) key types supported
- Verification result stored in database: `verified`, `invalid`, or `error`
- Failure reason recorded when verification fails
- 30-second per-user rate limit to prevent verification spam

### Frontend Badge & Download

- 🔒 lock icon appended to post title on verified posts
- **Clicking the lock icon downloads the `.asc` file directly** — no page navigation
- Download filename: `<post-slug>-<fingerprint-short>.asc`
- Assets (CSS + JS) loaded only on singular posts with verified signatures — zero overhead on non-signed content
- Download event logged to audit trail
- Shortcode `[archivio_id_badge]` for manual placement
- WCAG 2.1 AA accessible: `focus-visible` ring, `aria-label`, `aria-busy` during download

.

---

## Architecture

```
archivio-id/
├── archivio-id.php                          Main plugin file, bootstrap, singleton
├── includes/
│   ├── class-archivio-id-db.php             Database schema, table name helpers
│   ├── class-archivio-id-audit-log.php      Event logging, CSV export, retention
│   ├── class-archivio-id-key-manager.php    Public key CRUD, fingerprint extraction
│   ├── class-archivio-id-signature-store.php  Per-post signature CRUD, cache invalidation
│   ├── class-archivio-id-openpgp-verifier.php  OpenPGP packet parsing layer
│   ├── class-archivio-id-verifier.php       Orchestrates verification flow
│   ├── class-archivio-id-post-integration.php  Meta box, AJAX handlers, admin assets
│   ├── class-archivio-id-frontend-badge.php   Title filter, asset enqueueing
│   ├── class-archivio-id-signature-download.php  Public AJAX download endpoint
│   └── autoloader.php                       Vendor library loader
├── admin/
│   ├── class-archivio-id-admin.php          Admin menu registration
│   ├── class-archivio-id-key-admin.php      Key management UI
│   ├── class-archivio-id-settings-admin.php  Settings page
│   ├── class-archivio-id-audit-log-admin.php  Audit log UI + CSV export
│   └── views/                               PHP view templates
├── assets/
│   ├── js/
│   │   ├── archivio-id-post.js              Admin meta box interactions
│   │   └── archivio-id-frontend.js          Frontend lock icon download handler
│   └── css/
│       ├── archivio-id-post.css             Admin meta box styles
│       └── archivio-id-frontend.css         Frontend badge + lock styles
├── vendor/
│   ├── phpseclib/                           Pure PHP crypto (RSA, DSA, EdDSA)
│   └── openpgp-php/                         OpenPGP packet parsing
└── uninstall.php                            Clean database tables on uninstall
```

### Database Tables

**`{prefix}archivio_id_keys`**

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `label` | varchar(255) | Human-readable key label |
| `fingerprint` | char(40) | 40-char uppercase hex (UNIQUE) |
| `key_id` | char(16) | Last 16 chars of fingerprint (long key ID) |
| `armored_key` | longtext | Full ASCII-armored public key block |
| `created_at` | datetime | UTC insert timestamp |
| `added_by` | bigint | WordPress user ID |
| `is_active` | tinyint(1) | Soft-delete flag |

**`{prefix}archivio_id_signatures`**

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `post_id` | bigint | WordPress post ID (UNIQUE — one signature per post) |
| `key_id` | bigint | FK to `archivio_id_keys.id` |
| `archivio_hash` | varchar(255) | Packed hash from ArchivioMD (`algorithm:hex`) |
| `hash_algorithm` | varchar(20) | Extracted algorithm name |
| `hash_mode` | varchar(10) | `standard` or `hmac` |
| `signature_asc` | text | ASCII-armored detached signature |
| `status` | varchar(20) | `uploaded`, `verified`, `invalid`, `error` |
| `verified_at` | datetime | UTC timestamp of successful verification |
| `failure_reason` | varchar(512) | Error detail if status is `invalid` or `error` |
| `uploaded_at` | datetime | UTC upload timestamp |
| `uploaded_by` | bigint | WordPress user ID |

**`{prefix}archivio_id_audit_log`**

See [Audit Log Reference](#audit-log-reference) for schema.

---

## Security

### Private Key Guarantee

Private keys are **never** uploaded, stored, or transmitted to the server. The signing workflow is entirely local. The server receives only the detached `.asc` output — a blob of data that can verify the signature but cannot be used to produce new ones.

### Input Validation

- All admin form inputs pass through `sanitize_text_field()`, `sanitize_textarea_field()`, `sanitize_key()`, and `absint()` as appropriate
- Uploaded `.asc` files: extension check, `is_uploaded_file()` guard, 4 KB size cap, structural regex validation
- Uploaded public keys: armor header check, private key material rejection, 4 KB cap, fingerprint parse validation

### Output Escaping

- All database values are escaped with `esc_html()`, `esc_attr()`, `wp_kses()` before output
- JavaScript dynamic content uses a local `escapeHtml()` function to prevent XSS
- Download endpoint outputs raw ASCII armor without HTML processing (correct for the content type)

### Authentication & Authorization

| Action | Required capability |
|---|---|
| View admin pages | `manage_options` |
| Add / deactivate keys | `manage_options` |
| Upload signature to post | `edit_post` on that post |
| Verify / delete signature | `edit_post` on that post |
| View audit log | `manage_options` |
| Export audit log CSV | `manage_options` |
| Download `.asc` (frontend) | None — public endpoint, nonce only |

All admin AJAX actions use `check_ajax_referer()`. The frontend download uses `check_ajax_referer()` with a per-post nonce (`archivio_id_download_<post_id>`) so a token for one post cannot be replayed for another.

### SQL

All database queries use `$wpdb->prepare()` with typed placeholders. No raw string interpolation of user data into SQL.

### Download Response Headers

```
Content-Type: application/pgp-signature; charset=utf-8
Content-Disposition: attachment; filename="<slug>-<fp>.asc"
X-Content-Type-Options: nosniff
X-Robots-Tag: noindex
```

---

## Audit Log Reference

### Schema

**`{prefix}archivio_id_audit_log`**

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `post_id` | bigint | WordPress post ID |
| `post_type` | varchar(20) | Post type (post, page, custom) |
| `event_type` | varchar(20) | `upload`, `verify`, `delete`, `download` |
| `timestamp_utc` | datetime | UTC event time |
| `key_fingerprint` | char(40) | 40-char public key fingerprint |
| `hash_algorithm` | varchar(20) | Algorithm name (sha256, sha512, etc.) |
| `signature_status` | varchar(20) | `unverified`, `verified`, `invalid`, `error` |
| `user_id` | bigint | WordPress user ID (0 for anonymous downloads) |
| `notes` | varchar(512) | Context or error message |

### Event Types

| Event | When it fires | Status logged |
|---|---|---|
| `upload` | `.asc` file attached to a post | `unverified` |
| `verify` | Verification button clicked | `verified`, `invalid`, or `error` |
| `delete` | Signature removed | Previous status at time of deletion |
| `download` | Visitor clicks the 🔒 icon | `verified` |

### What Is Never Logged

- Private keys
- Raw signature bytes
- Post content or the full hash value (only the algorithm name is logged)
- Passphrases or credentials

### CSV Export

Access via **ArchivioID → Audit Logs → Export CSV**.

- Filter by date range and/or status before export
- Maximum 10,000 rows per export
- Filename: `archivio-id-audit-log-YYYY-MM-DD-HHmmss.csv`
- CSV injection prevention: fields beginning with `=`, `+`, `-`, `@` are prefixed with `'`
- UTF-8 encoding

### Retention

Default retention period is 90 days. Configure at **ArchivioID → Settings → Audit Log Retention** (1–365 days). Old entries are removed automatically via WP-Cron or on manual trigger from the admin interface.

---

## Configuration

All settings at **ArchivioID → Settings**.

| Setting | Default | Description |
|---|---|---|
| Audit log retention | 90 days | How long to keep log entries (1–365) |
| Default hash algorithm | sha256 | Hash algorithm for new signatures |
| Badge position | In title | Where the 🔒 icon appears |

---

## Troubleshooting

### Enable debug logging

```php
// wp-config.php
define( 'WP_DEBUG',         true  );
define( 'WP_DEBUG_LOG',     true  );
define( 'WP_DEBUG_DISPLAY', false );
```

All ArchivioID log messages are prefixed `[ArchivioID]` in `wp-content/debug.log`.

### Success log patterns

```
[ArchivioID] Verification SUCCESS for post 42 (key: Alice, fp: ABCD...1234)
[ArchivioID] Verification status recorded for post 42: status="verified"
[ArchivioID] Cache invalidated for post 42 after verification (status: verified)
```

### Failure log patterns

```
[ArchivioID] Verification INVALID for post 42: Signature does not match the stored hash
[ArchivioID] Signature upload rejected: Uploaded format is PGP MESSAGE (expected PGP SIGNATURE)
[ArchivioID] Download blocked: stored signature for post 42 failed structural validation.
```

### "Signature verification failed" checklist

1. **Did you include the algorithm prefix?**  
   Sign `e3b0c44298...` not `sha256:e3b0c44298...`

2. **Did you use `echo -n`?**  
   A trailing newline changes the byte sequence.

3. **Does `--digest-algo` match the meta box prefix?**  
   `sha256:` → `--digest-algo SHA256`, `sha512:` → `--digest-algo SHA512`

4. **Is the key in Key Management active?**  
   Go to **ArchivioID → Key Management** and verify the key is not deactivated.

5. **Was the post edited after signing?**  
   Editing regenerates the ArchivioMD hash. Re-sign with the new hash.

6. **Correct key?**  
   `gpg --verify hash.txt.asc hash.txt` shows which private key was used for signing. Cross-check the fingerprint with what is stored in Key Management.

### Lock icon appears but download gives 404

The download endpoint serves only `status = verified` signatures. If the signature was uploaded but never verified (status = `uploaded`), the download will fail. Click **Verify Signature** in the post editor first.

### Cache issues

ArchivioID invalidates WordPress post cache and object cache (Redis/Memcached) after every write. If you use a full-page cache (WP Rocket, W3 Total Cache, etc.) and the lock icon does not appear after verification, manually clear that cache or wait for it to expire.

---

## Changelog

### 1.2.0 — 2026-02-17

**Feature: Clickable lock icon with signature download**

- The 🔒 badge in the post title is now a clickable anchor
- Clicking triggers a browser download of the verified `.asc` file
- Filename format: `<post-slug>-<fingerprint-short>.asc`
- New AJAX endpoint `archivio_id_download_sig` (public, nopriv) with per-post nonce
- Download event logged to audit log (`event_type = 'download'`)
- Frontend JS and CSS enqueued only on singular posts with verified signatures
- Lock icon shows pulse animation during download, blocks double-clicks

**New files:**
- `includes/class-archivio-id-signature-download.php`

**Modified files:**
- `includes/class-archivio-id-frontend-badge.php` — lock is now `<a>` with `data-post-id`
- `assets/js/archivio-id-frontend.js` — click handler, hidden-anchor download pattern
- `assets/css/archivio-id-frontend.css` — hover, focus-visible, downloading pulse styles

---

### 1.1.1 — 2026-02-10

**Bugfix: UI sync after verification**

- Added `clean_post_cache()` and `wp_cache_delete()` after all verification writes
- Auto page-reload (2-second delay) after successful verification to synchronize UI
- Enhanced AJAX response with fresh signature data and badge metadata
- Post-update verification check in debug mode to detect status mismatches
- `nocache_headers()` on AJAX verification responses

---

### 1.1.0 — 2026-02-01

**Complete UI layer**

- Admin interfaces: key management, audit log viewer, settings
- Post meta box with upload, verify, and delete actions
- Front-end 🔒 badge via `the_title` filter
- AJAX handlers for verify and delete
- Audit log database table and CSV export

---

### 1.0.0 — 2025-12-15

**Initial release**

- Core verification engine (phpseclib v3 + OpenPGP-PHP)
- Public key storage with fingerprint extraction

---

## License

GPL v2 or later — see [LICENSE](LICENSE) or https://www.gnu.org/licenses/gpl-2.0.html

---

## About

Built by [Mountain View Provisions LLC](https://mountainviewprovisions.com)  
Plugin home: [mountainviewprovisions.com/ArchivioID](https://mountainviewprovisions.com/ArchivioID)  
Support: [info@mountainviewprovisions.com](mailto:info@mountainviewprovisions.com)  
Parent plugin: [ArchivioMD](https://mountainviewprovisions.com/ArchivioMD)
