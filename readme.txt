=== ArchivioID ===
Contributors: mountainviewprovisions
Tags: cryptography, digital-signature, openpgp, security, authenticity
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 5.1.0
Requires PHP: 7.4
Requires Plugins: archivio-md
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

OpenPGP signature verification, multi-signer workflows, key lifecycle management, and public proof pages for ArchivioMD.

== Description ==

**ArchivioID** is an add-on plugin for **ArchivioMD** that adds a full cryptographic identity and signature layer to your WordPress site. It manages GPG public keys, verifies detached OpenPGP signatures on posts, supports multi-signer workflows with configurable thresholds, and exposes public proof pages so anyone can verify authenticity without logging in.

= Key Features =

* **Public Key Management**: Store, manage, and rotate GPG public keys with expiry tracking and administrator alerts
* **Post Signature Verification**: Upload detached .asc signature files for posts — verified automatically using phpseclib v3
* **Browser-Based Signing**: Sign posts directly in the WordPress admin using a browser-held key — no server-side key material required
* **Multi-Signature Workflows**: Collect signatures from multiple key holders on a single post; each signer identified by key fingerprint and timestamp
* **Configurable Signature Threshold**: Require a minimum number of verified signatures before a post displays the verified badge — configurable globally or per post type
* **Algorithm Enforcement Floor**: Block weak signature algorithms (MD5, SHA-1) and enforce minimum RSA/DSA key sizes at upload, REST submission, and re-verification time
* **Automated Re-Verification**: Daily WP-Cron job re-verifies all signed posts and flags content that has changed since signing
* **Key Expiry Notifications**: Email alerts at 30, 14, and 3 days before a key expires, sent to the key owner or site admin
* **Key Rotation Workflow**: Admin UI for generating replacement keys, migrating existing signatures, and retiring old keys
* **Bulk Verification**: Verify all signed posts in a single admin action with per-post status reporting
* **REST API**: Full REST endpoint for programmatic signature submission, key retrieval, and verification status
* **Key Server**: Publishes active public keys at a stable well-known endpoint for external verifiers
* **Bundle Download**: Downloadable evidence package (hash, signatures, key fingerprints, timestamps) for any post
* **Public Proof Pages**: Stable public permalink at `/archivio-id/verify/{post_id}` — renders full chain of custody without requiring admin access
* **Audit Logging**: Immutable log of all verification attempts, key changes, and rotation events
* **WP-CLI Support**: Full CLI interface for batch operations and automated pipelines
* **Visual Status Badges**: Front-end badge showing verified / unverified / threshold-unmet status on every post

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher (tested up to PHP 8.5)
* **ArchivioMD plugin version 1.5.0 or higher** (required parent plugin)

= How It Works =

1. Upload your GPG public key via the ArchivioID → Key Management admin page
2. Create or edit a post in WordPress
3. Upload a detached .asc signature file for the post, or sign directly in the browser
4. ArchivioID verifies the signature immediately and on every subsequent automated re-verify run
5. A verification badge appears on the front end; a public proof page is available at a stable permalink

= Technical Details =

* Uses **phpseclib v3** for all cryptographic operations — no system GPG installation required
* Uses **OpenPGP-PHP** for packet parsing and key handling
* All key material and signatures stored in dedicated WordPress database tables
* Algorithm enforcement floor consulted at upload, REST submission, and re-verification time
* Multi-signature threshold evaluated before displaying the verified badge
* Public proof pages require no admin login — safe to share externally
* Fully WordPress coding standards compliant

= External Services =

This plugin can make outbound HTTP requests to the following third-party services. **All external lookups are opt-in** and can be disabled under ArchivioID → Settings → Key Server Lookup.

**keys.openpgp.org (VKS API)**
When an administrator uses the Key Management page to look up a GPG public key by fingerprint or email address, the plugin sends a GET request to `https://keys.openpgp.org/vks/v1/`. No personal data beyond the fingerprint or email address entered by the administrator is transmitted. This request is made only on explicit administrator action and only when the "Allow key server lookup" setting is enabled.
* Service: https://keys.openpgp.org
* Privacy policy: https://keys.openpgp.org/about/privacy

**WKD — Web Key Directory (user's email domain)**
When an administrator looks up a key by email address, the plugin may also query the Web Key Directory endpoint on the domain portion of that email address (e.g. `https://example.com/.well-known/openpgpkey/`). This follows the OpenPGP Web Key Directory specification (draft-koch-openpgp-webkey-service). The request is made only on explicit administrator action and only when key server lookup is enabled.
* Specification: https://datatracker.ietf.org/doc/draft-koch-openpgp-webkey-service/
* The domain contacted is determined entirely by the email address the administrator enters; it is not a fixed third-party service.

**Identity Proof URLs (user-supplied)**
Administrators may optionally store a public identity proof URL alongside each key (for example, a Keyoxide or Keybase profile page). These URLs are stored in the WordPress database and displayed as links in the frontend badge tooltip. The plugin itself makes no outbound request to these URLs; they are rendered as standard hyperlinks for visitors to follow voluntarily.
* Keyoxide: https://keyoxide.org
* Keybase: https://keybase.io
* Any HTTPS URL may be entered; the plugin validates only that the value is a well-formed HTTPS URL.

== Installation ==

1. **Install ArchivioMD first** — ArchivioID will refuse to activate without it
2. Upload the `archivio-id` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu in WordPress
4. Navigate to **Settings → Permalinks** and click Save Changes (required for public proof pages)
5. Go to **ArchivioID → Key Management** to upload your first GPG public key

Or install directly from WordPress:

1. Go to Plugins → Add New
2. Search for "ArchivioID"
3. Click Install Now then Activate
4. Flush permalinks via Settings → Permalinks → Save Changes

== Frequently Asked Questions ==

= What is ArchivioMD? =

ArchivioMD is the parent plugin that provides cryptographic content hash verification, external anchoring (RFC 3161 timestamps, Rekor transparency log, GitHub/GitLab), and document management for WordPress. ArchivioID extends this with OpenPGP identity and multi-signer workflows.

= Do I need GPG installed on my server? =

No. ArchivioID uses phpseclib v3, a pure-PHP cryptographic library. No system GPG installation, shell_exec, or command-line access is required.

= Can I verify signatures offline? =

Yes. The bundle download and public proof page provide all data needed for offline verification using standard GPG tools or any OpenPGP-compatible library.

= Does this plugin sign posts, or only verify? =

Both. You can upload a detached signature created externally with GPG, or use the in-admin browser-based signing feature to sign posts directly from the WordPress editor without server-side key material.

= What is the signature threshold? =

The threshold is a configurable minimum number of distinct verified signatures a post must have before it displays the green verified badge. This supports editorial workflows where a post must be signed off by multiple key holders before being considered verified.

= What does the algorithm enforcement floor do? =

It blocks signature files that use weak hash algorithms (MD5, SHA-1 by default) or RSA/DSA keys below a minimum bit length. The floor is enforced at upload time, REST submission time, and re-verification time — tightening the floor retroactively re-evaluates existing signatures.

= What is the public proof page? =

A stable, unauthenticated permalink at `/archivio-id/verify/{post_id}` that displays the full verification chain for a post: content hash, each signer's fingerprint, algorithm, timestamp, and key metadata. Safe to share externally or link from a published document.

= Is this compatible with other plugins? =

ArchivioID uses WordPress standards, dedicated database tables, and does not modify core files or other plugins' data.

== Screenshots ==

1. Admin interface for public key management and rotation
2. Post meta box for signature upload and browser-based signing
3. Multi-signer workflow showing threshold status
4. Front-end verification badge and public proof page
5. Algorithm enforcement floor settings
6. Bulk verification admin page

== Changelog ==

= 5.1.0 =
* Added Public Proof Pages. A stable public permalink `/archivio-id/verify/{post_id}` renders the full chain of custody for any published post — content hash, signer fingerprints, algorithms, timestamps, and key metadata — without requiring admin access. Private, draft, and password-protected posts return 404. Flush permalinks after upgrading to activate the new route.
* Added Algorithm Enforcement Floor. A configurable policy blocks signature files using weak hash algorithms (MD5, SHA-1 by default) and enforces minimum RSA/DSA key sizes (2048 bits by default). Enforcement runs at upload, REST submission, and automated re-verification time. Configurable from Settings → Algorithm Policy.
* Added Multi-Signer Threshold Policy. A configurable minimum verified-signature count must be met before a post displays the verified badge. Configurable globally or per post type from Settings → Signature Threshold.
* Fixed activation fatal error: `on_activate()` called `ArchivioID_Cron_Verifier::schedule()` and `ArchivioID_Expiry_Notifier::schedule()` before those class files were loaded. Both `on_activate()` and `on_deactivate()` now guard with `class_exists` + `require_once`.
* Fixed PHP parse error in `admin/views/settings.php` line 244: typographic apostrophe inside a single-quoted string. Replaced with an escaped straight apostrophe.

= 5.0.0 =
* Database schema version bump to 5.0.0.
* Added `identity_proof_url` column to the keys table — store a public URL alongside each key as an optional identity assertion (e.g. a Keybase proof or GitHub profile).
* Added `sign_method` column to the signatures table tracking whether a signature was file-uploaded, browser-signed, or REST-submitted.
* Added `sig_metadata` column to both the signatures table and the multi-signatures table for structured per-signature provenance metadata.
* Signature type chips added to the front-end badge surfacing the sign_method for each signature.

= 4.0.0 =
* Added Key Expiry Notifier. A daily WP-Cron job checks all stored keys for upcoming expiry and sends email alerts at 30, 14, and 3 days before the expiry date. Emails are sent to the key's recorded owner, falling back to the site admin.
* Added WP-CLI integration: `wp archivio-id verify-all`, `wp archivio-id key-list`, `wp archivio-id key-expire <id>`, `wp archivio-id prune-audit-log`.
* Added Bulk Verification admin page. Verify all signed posts in a single action with a live progress table.
* Added Key Rotation admin UI for generating replacement keys, re-signing posts, and retiring old keys without manual database operations.

= 3.0.0 =
* Added Automated Re-Verification cron. A daily WP-Cron job re-verifies all posts with stored signatures and flags any post whose content has changed since signing. Scheduled on activation; unscheduled on deactivation.
* Added Bundle Download. A downloadable evidence package for any signed post containing the canonical content hash, all .asc files, signer fingerprints, algorithms, and timestamps.

= 2.0.0 =
* Added Multi-Signature Store. Multiple key holders can independently sign the same post; each (post_id, key_id) pair tracked in a dedicated `archivio_id_multi_sigs` table with its own verification status and timestamp.
* Added REST API at `/wp-json/archivio-id/v1/` for programmatic signature submission, key listing, and verification status. All write endpoints require authentication.
* Added Key Server. Active public keys published at a stable well-known endpoint for external verifiers.

= 1.3.1 =
* Improved OpenPGP packet parsing robustness. Hardened the verifier against malformed or truncated .asc files.
* Improved error messages for unsupported key types and algorithm mismatches.

= 1.3.0 =
* Added Browser-Based Signing. Sign post content directly in the admin using a browser-held GPG key. The signing operation runs entirely client-side; only the resulting detached signature is submitted to the server.
* Added dedicated browser signature database table (`archivio_id_browser_sigs`).
* Browser signing admin interface added under ArchivioID → Browser Sign.

= 1.2.0 =
* Packaging and compliance improvements for WordPress.org.
* Security hardening: added capability check to backend info AJAX handler.
* Improved error messages during plugin activation.

= 1.1.0 =
* Complete UI layer implementation.
* Admin interfaces for key management and signature upload.
* Post meta box integration, front-end verification badge, AJAX handlers with nonce protection.
* Database audit logging for all verification attempts.

= 1.0.0 =
* Initial release.
* Core verification engine using phpseclib v3 and OpenPGP-PHP.
* GPG public key storage and detached .asc signature upload/verification per post.

== Upgrade Notice ==

= 5.1.0 =
Fixes an activation fatal error and a PHP parse error. Adds public proof pages (flush permalinks after upgrading), algorithm enforcement, and signature thresholds. Recommended for all users.

= 5.0.0 =
Database schema update. Migration runs automatically on activation — no manual action required.

= 4.0.0 =
Adds key expiry notifications, WP-CLI, bulk verification, and key rotation. The new expiry cron is scheduled automatically on activation.

= 3.0.0 =
Adds automated daily re-verification cron and bundle download. Cron scheduled automatically on activation.

= 2.0.0 =
Major update: adds multi-signature support, REST API, and key server. New database table created automatically on activation.

= 1.3.0 =
Adds browser-based signing. Requires ArchivioMD 1.5.0 or higher.

= 1.2.0 =
Security and compliance update. Recommended for all users.

= 1.1.0 =
Major update with complete UI layer. Requires ArchivioMD 1.5.0 or higher.

== Security ==

* All inputs sanitized and validated; all outputs escaped
* Nonce verification on all forms and AJAX handlers
* Capability checks (`manage_options`) on all admin actions
* REST API write endpoints require authentication
* SQL prepared statements throughout; no raw query interpolation
* Algorithm enforcement floor blocks known-weak cryptographic primitives

== Support ==

For support, please visit: https://mountainviewprovisions.com/archivio-id

== License ==

This plugin is licensed under GPLv2 or later.
