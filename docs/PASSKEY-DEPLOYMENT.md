# Admin passkeys deployment (Vyomika Atelier)

This document covers deploying **WebAuthn passkeys** for admin sign-in alongside existing password + TOTP MFA.

## Prerequisites

- HTTPS on production (`https://vyomikaatelier.com`)
- Admin TOTP MFA merged and migrated (`users.two_factor_*` columns)
- PHP 8.2+, Composer dependencies installed

## Environment

Add to production `.env` (placeholders only in `.env.example`):

```env
PASSKEY_RP_ID=vyomikaatelier.com
PASSKEY_RP_NAME="Vyomika Atelier"
PASSKEY_ORIGINS=https://vyomikaatelier.com,https://www.vyomikaatelier.com
```

Rules:

- **RP ID** must equal the registrable domain (no `https://`, no port).
- **Origins** must list every HTTPS origin admins use (apex + `www`).
- Optional `PASSKEYS_USER_HANDLE_SECRET` overrides user-handle derivation (defaults to `APP_KEY`).

## Deploy steps

1. Deploy branch `feature/admin-passkeys` (or after merge) to the server.
2. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Run migrations:
   ```bash
   php artisan migrate --force
   ```
   Creates table `passkeys` (public credential data only — no biometrics).
4. Clear caches:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```
5. Verify package discovery (no DB required):
   ```bash
   php artisan package:discover
   ```

## Enrollment (admin)

1. Sign in with **password + TOTP** (existing flow).
2. Open **Admin → Passkeys**.
3. Enter passkey name, current password, and authenticator code.
4. Complete the browser WebAuthn prompt:
   - **Mobile:** device Face ID / fingerprint / PIN.
   - **Desktop:** browser may show a **temporary QR** for phone approval (standard WebAuthn cross-device flow — not a custom QR auth system).

Password + TOTP + recovery codes remain available as fallback sign-in methods.

## Smoke tests

| Check | Expected |
|-------|----------|
| `GET /admin/login` | “Sign in with a passkey” visible |
| Passkey login (admin with registered passkey) | Dashboard directly when TOTP enabled (WebAuthn user verification satisfies MFA); password login still requires TOTP |
| Passkey login (non-admin credential) | Generic failure, no account enumeration |
| Add / rename / remove passkey | Requires password + TOTP |
| Remove last passkey without MFA | Blocked |
| Rate limits | Repeated `/admin/passkeys/login/options` returns 429 |
| Audit log | `admin.passkey_registered`, `admin.passkey_used`, `admin.passkey_renamed`, `admin.passkey_removed`, `admin.passkey_login_failed` |

## Security notes

- Only **public keys**, credential IDs, sign counters, transports metadata, names, and timestamps are stored.
- Origin / RP ID mismatches, replayed challenges, invalid signatures, and expired challenges are rejected by `web-auth/webauthn-lib` via `laravel/passkeys`.
- Passkey login does **not** bypass admin authorization, rate limits, session regeneration, disabled-account checks, or MFA enrollment when required.
- When TOTP MFA is already enrolled, passkey sign-in satisfies the second factor (WebAuthn user verification); password sign-in still requires a TOTP or recovery code.

## Rollback

1. Revert deploy to previous release **or** checkout prior commit on server.
2. Optional — remove passkey routes from use by reverting code only (DB table harmless if unused):
   ```bash
   php artisan migrate:rollback --step=1
   ```
   Rollback drops `passkeys` if the migration is reversed.
3. Clear caches (same as deploy step 4).

Admins can continue using password + TOTP + recovery codes after rollback.

## Automated tests (CI / local)

```bash
php artisan test --filter=AdminPasskeyTest
php artisan test
vendor/bin/pint --test
php artisan package:discover
```

There is no frontend npm build in this repository; static asset `public/js/admin-passkeys.js` is served directly.
