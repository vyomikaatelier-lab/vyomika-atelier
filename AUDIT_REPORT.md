# AUDIT_REPORT.md — Vyomika Atelier administrator security

**Schema task:** audit-first administrator security hardening  
**Date:** 2026-08-06  
**Phase:** 0 discovery complete — implementation follows smallest compatible design  
**Git:** discovery on branch `cursor/deployment-smoke-whatsapp-otp-docs` (no commit/push of security work in Phase 0)

---

## 1. Detected architecture

| Area | Finding |
|------|---------|
| **Language / runtime** | PHP ^8.2 |
| **Framework** | **Laravel 11** (`laravel/framework ^11.0`) |
| **Admin UI** | Custom Blade under `/admin` — **not** Filament / Nova / Voyager |
| **Auth library** | Laravel session guard (`Auth::attempt`) + custom `AdminAccess` session flag |
| **MFA / Fortify / Jetstream / Breeze / Sanctum** | **MFA via `pragmarx/google2fa`** (Phase B); Fortify/Jetstream **not** used |
| **Customer auth** | Separate account flows (email/mobile OTP, Socialite Google/Apple) — `is_admin = false` only |
| **Database** | MySQL in production (Hostinger); tests use SQLite `:memory:` |
| **Session store** | `SESSION_DRIVER=database` (example); cookie name `vyomika_session` |
| **Hosting** | Hostinger shared hosting — docs: `HOSTINGER.md`, `DEPLOY_NOW.md`, `post-deploy.sh` |
| **CDN / edge** | Cloudflare proxy (operational; not auth) |
| **Payments** | Razorpay webhooks (storefront; not admin login) |

**Architecture selection (proven):** Extend the **existing custom admin session auth** (`Admin\AuthController` + `AdminMiddleware` + `AdminAccess`). Do **not** introduce a second guard, parallel admin users table, or Filament. MFA requires adding a **maintained** TOTP library (candidate: `pragmarx/google2fa` + QR helper) because Laravel core has no built-in TOTP without Fortify; installing Fortify solely for MFA would overlap the custom login and is deferred pending owner choice.

---

## 2. Authentication / session diagram

```text
Guest
  │
  ▼
GET /admin/login  (AuthController@showLogin)
  │
  ▼
POST /admin/login  throttle:auth (10/min by IP)
  │
  ├─ Auth::attempt(email, password) fails
  │     → "Invalid credentials." (+ email input retained)
  │
  ├─ attempt OK but !isAdmin() OR !is_active
  │     → logout + AdminAccess::revoke
  │     → "You do not have admin access."  ⚠ enumeration signal
  │
  └─ attempt OK + isAdmin + is_active
        → session()->regenerate()
        → AdminAccess::grant()  // session key admin.access_verified = true
        → redirect intended /admin

Protected /admin/*
  AdminMiddleware:
    auth + isAdmin + is_active + AdminAccess::verified
    else → redirect /admin/login

Logout POST /admin/logout
  AdminAccess::revoke → Auth::logout → session invalidate → CSRF regenerate
```

Customer login/social paths call `AdminAccess::revoke` to clear panel flag (isolation tested).

---

## 3. Admin route / action matrix (inventory)

All under `Route::prefix('admin')->middleware('admin')` except login/logout.

| Surface | Methods | Sensitivity | Auth today |
|---------|---------|-------------|------------|
| Dashboard | GET `/` | Low | admin middleware |
| Products CRUD + reorder | resource + POST reorder | Content | admin |
| Categories CRUD + sync/reorder/move | resource + extras | Content | admin |
| Orders index/show/update | resource subset | **Orders / PII** | admin |
| Leads CRUD + triage + attachment download | many POSTs + GET | **PII / leads** | admin |
| Projects, Blog, Exhibitions, Services | CRUD | Content | admin |
| Collection / Page heroes / Independent / Static SEO | edit/update | Content / SEO | admin |
| URL redirects | index/store/destroy | SEO / security | admin |
| Professional apps / Railing quotes | index/show/update + attachments | **PII** | admin |
| Customers index/show/update | GET/PUT | **PII**; can set `is_active` | admin |
| Site settings | GET/PUT/POST | **Business config** | admin |
| Legal pages | edit/update | Legal | admin |
| Media library | CRUD + download | Uploads | admin |
| Login / Logout | GET/POST | Auth | public / session |

**Authorization model today:** binary `users.is_admin` + `is_active`. No roles/policies/RBAC. Navigation hide ≠ authorization (server middleware is the control — good baseline).

---

## 4. Authorization matrix (roles)

| Capability | Admin (`is_admin`) | Active customer | Guest |
|------------|--------------------|-----------------|-------|
| `/admin/*` panel | Yes if `AdminAccess` | No | No |
| Storefront account | Possible if also customer flows | Yes | Limited |
| Elevate `is_admin` via Customer admin UI | **No** (whitelisted fields) | N/A | N/A |
| Seed elevates `ADMIN_EMAIL` | Ops / seeder | N/A | N/A |

**Owner decision needed:** multi-role RBAC (content editor vs orders vs settings) is **not** justified by current single-admin ops model — keep single admin role unless business requires split.

---

## 5. Current controls

| Control | Status |
|---------|--------|
| Password login + session regenerate on success | Present |
| `AdminAccess` second gate after customer session | Present |
| Logout invalidates session + CSRF token | Present |
| `throttle:auth` 10/min by IP on admin login | Present |
| CSRF on web mutations | Laravel default |
| `robots.txt` Disallow `/admin` | Present |
| Sitemap excludes admin | Present |
| SecurityHeaders (nosniff, SAMEORIGIN, Referrer-Policy, Permissions-Policy, HSTS if secure) | Present on `web` stack |
| Password hashing | `hashed` cast |
| Trust proxies (Cloudflare) | `trustProxies(at: '*')` in `bootstrap/app.php` |

---

## 6. High-risk gaps

| ID | Gap | Severity |
|----|-----|----------|
| G1 | **No MFA / TOTP** for administrators | Critical |
| G2 | **No admin password reset** (customer OTP excludes admins); recovery = server/seed only | High |
| G3 | **No audit log** for admin login success/failure / privilege changes | High |
| G4 | Login error text differs for non-admin vs bad password (**enumeration**) | Medium |
| G5 | `is_admin` in `$fillable` (latent mass-assignment) | Medium |
| G6 | `SESSION_SECURE_COOKIE` unset in `.env.example` | Medium |
| G7 | Admin layout **missing `noindex`** meta | Low–Medium |
| G8 | No recent-password confirmation for settings / disable customer / media destroy | High |
| G9 | No CSP header (optional; may break admin CDN Tailwind) | Low |
| G10 | Local seeder fallback password `changeme123` when `ADMIN_PASSWORD` empty | Medium (local only) |
| G11 | Single IP throttle only (no account-aware backoff) | Medium |
| G12 | No dedicated tests for SecurityHeaders / robots Disallow / MFA | Medium |

---

## 7. Proposed implementation (smallest compatible design)

### Phase A — harden existing auth (no new packages, no owner blockers)

1. Unify admin login failure messages (enumeration-safe).
2. Privacy-safe `Log::info` / `Log::warning` for admin login success/fail/logout (no secrets).
3. Remove `is_admin` from `$fillable`; seeder uses `forceFill`.
4. Document `SESSION_SECURE_COOKIE=true` in `.env.example` for production HTTPS.
5. Add `<meta name="robots" content="noindex,nofollow">` on admin layouts.
6. Require **current password** confirmation for Site Settings update and Customer `is_active` disable.
7. Expand feature tests: guest denial samples, enumeration message, session flag, robots, noindex.
8. Optional: account-aware throttle key `email|ip` for admin login only.

### Phase B — MFA (implemented per owner decisions in §11)

1. Lockfile-pinned `pragmarx/google2fa` + `bacon/bacon-qr-code` (no Fortify).
2. Columns: `two_factor_secret` (encrypted), `two_factor_recovery_codes` (hashed+encrypted), `two_factor_confirmed_at`, `two_factor_grace_ends_at`.
3. After password login: TOTP/recovery challenge before `AdminAccess::grant`; grace or forced enroll when MFA unset.
4. Recovery: single-use hashed codes; regenerate/disable require password confirmation.
5. Lost device: `php artisan admin:mfa-reset {email} --force` (audited, revokes sessions).

### Phase C — out of scope / blockers without channels

- Real-time alerting (email/Slack) — only if delivery channel exists; else document.
- Multi-admin RBAC — not required until multiple operators.
- Changing `/admin` URL — not primary control; keep unless ops requests.

---

## 8. Migration / rollback impact

| Change | Migration? | Rollback |
|--------|------------|----------|
| Phase A (logging, fillable, noindex, reauth) | None or config-only | Revert code |
| Phase B MFA columns | Additive nullable columns | Drop columns; users fall back to password-only |
| Production data | No destructive transforms | — |

**Forbidden without confirmation:** commit, push, merge, deploy, production migrate, live provider/DNS changes.

---

## 9. Verification commands

| Command | Purpose |
|---------|---------|
| `php artisan test` | Full PHPUnit (Unit + Feature) |
| `php artisan test --filter=Admin` | Admin-focused subset |
| `./vendor/bin/phpunit` | Alternate runner |
| `composer audit` | Dependency CVE triage |
| `./vendor/bin/pint --test` | Style (dev) |

**Baseline (Phase 0):** run recorded below after discovery.

---

## 10. Baseline test / audit results

| Check | Result | Notes |
|-------|--------|-------|
| `AdminLoginPageTest` + `AdminAccessIsolationTest` | **PASS** (Phase 0) | Isolation + login OK |
| `AdminSecurityHardeningTest` | **PASS** (9 tests, 37 assertions) | Phase A coverage |
| `AdminMfaTest` | **PASS** (7 tests) | Phase B |
| Full `php artisan test` | **PASS** (392 tests, 1811 assertions) | After Phase A+B test fixes |
| `composer audit` | See latest local run | Triage only; do not auto-upgrade |
| Production build | N/A | PHP/Laravel deploy caches |

---

## 11. Owner decisions (reconciled 2026-08-06)

| Question | Decision |
|----------|----------|
| MFA library | `pragmarx/google2fa` + `bacon/bacon-qr-code` — keep custom admin login (**no Fortify**) |
| Enforcement | Mandatory for **new** admins; configurable **7-day grace** for existing (`ADMIN_MFA_GRACE_DAYS`) |
| Lost device | Audited Artisan `admin:mfa-reset {email} --force` (revokes sessions) |
| Admin password reset | Remains **server-only** |
| CSP | **Out of scope** for this task |

---

## 12. Acceptance tracking

| Criterion | Status |
|-----------|--------|
| Matches detected Laravel custom admin auth | Yes |
| Every admin surface server-side auth | Yes (middleware + tests) |
| MFA mandatory where supported | **Phase B implemented** (grace + challenge) |
| Secure session lifecycle | Hardened |
| Throttling / reauth / privacy-safe logs | Phase A + MFA challenge throttle |
| Storefront preserved | Yes |
| No commit/push/deploy in this task unless asked | Observed |
| `AUDIT_REPORT.md` distinguishes completed vs blockers | Yes |

---

## 13. Phase A implementation status (code complete locally — not committed/pushed)

| Item | Done |
|------|------|
| Enumeration-safe admin login errors | Yes |
| Privacy-safe login/logout logging | Yes |
| `is_admin` removed from `$fillable`; seeder `forceFill`/`forceCreate` | Yes |
| `SESSION_SECURE_COOKIE` (+ http_only/same_site) in `.env.example` | Yes |
| Admin layouts `noindex,nofollow` | Yes |
| Current password for Site Settings save | Yes |
| Current password to disable customer | Yes |
| Account-aware + IP auth throttle | Yes |
| Focused security tests | Yes |

**Ops note:** Set `SESSION_SECURE_COOKIE=true` on production `.env` after deploy (example updated).

---

## 14. Phase B MFA implementation status (code complete locally — not committed/pushed)

| Item | Done |
|------|------|
| `pragmarx/google2fa` + `bacon/bacon-qr-code` | Yes |
| Migration: encrypted secret, recovery hashes, confirmed_at, grace_ends_at | Yes |
| Password → MFA challenge / enroll before `AdminAccess::grant` | Yes |
| Enrollment UI (QR + secret) + password confirm | Yes |
| Challenge (TOTP + single-use recovery) + rate limit + replay window | Yes |
| Manage: regenerate recovery / disable (password confirm) | Yes |
| `admin:mfa-reset --force` + session revoke + audit log | Yes |
| New admin seeder: immediate enroll (`grace_ends_at = now`) | Yes |
| Existing admins on migrate: grace window | Yes |
| Feature tests `AdminMfaTest` | Yes |
| CSP | Explicitly skipped |

**Key files (Phase B):** `app/Support/AdminMfa.php`, `MfaController`, Auth/Middleware updates, `AdminMfaResetCommand`, `config/admin_mfa.php`, migration `2026_08_06_180000_*`, MFA Blade views, `tests/Feature/AdminMfaTest.php`.

**Post-deploy ops:** `php artisan migrate`; optional `ADMIN_MFA_GRACE_DAYS=7`; enroll within grace; `php artisan admin:mfa-reset email --force` for lost devices.

**Git note:** Later stage Phase A+B as a dedicated security commit and cherry-pick onto clean `main` — do not include unrelated doc commit `6751ad2`.
