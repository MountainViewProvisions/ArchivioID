# OpenPGP-PHP Installation

ArchivioID requires the **OpenPGP-PHP** pure-PHP library for signature verification.

## Steps

1. Clone or download the library:
   ```
   git clone https://github.com/singpolyma/openpgp-php.git
   ```

2. Copy `openpgp.php` (and any required `lib/` files) into this directory:
   ```
   archivio-id/vendor/openpgp-php/openpgp.php
   ```

3. The ArchivioID overview page will show "Present ✓" when the library is detected.

## License
OpenPGP-PHP is MIT licensed — compatible with ArchivioID's GPL-2.0-or-later.

## Alternative
If you prefer Composer:
```
composer require singpolyma/openpgp-php
```
Then update the `$lib_path` in `class-archivio-id-verifier.php` to point to the
Composer autoloader location.
