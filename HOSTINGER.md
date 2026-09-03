# Deploy to vyomikaatelier.com (Hostinger)

Your details are pre-filled below. Copy-paste each block in order.

## Your connection info

| Item | Value |
|---|---|
| GitHub | `https://github.com/vyomikaatelier-lab/vyomika-atelier.git` |
| SSH | `ssh -p 65002 u550969814@82.25.106.229` |
| Domain | `vyomikaatelier.com` |
| App folder | `~/vyomika-atelier` |
| Web root | `~/domains/vyomikaatelier.com/public_html` |

Payments: **Razorpay deferred** until keys are added. Checkout shows a payment-unavailable notice and does not create orders without configured keys.

---

## Step 1 — Push code from your PC

```powershell
cd "D:\VYOMIKA ATELIER"
git remote add origin https://github.com/vyomikaatelier-lab/vyomika-atelier.git
git branch -M main
git commit -m "Initial VYOMIKA ATELIER store"
git push -u origin main
```

If `git commit` asks for identity, run once:

```powershell
git config user.email "namaste@vyomikaatelier.com"
git config user.name "Vyomika Atelier"
```

---

## Step 2 — SSH into Hostinger

```bash
ssh -p 65002 u550969814@82.25.106.229
```

Enter your Hostinger SSH password when prompted.

---

## Step 3 — Create database in hPanel (browser)

Before running commands on the server:

1. hPanel → **Databases** → **MySQL Databases**
2. Create database (e.g. `u550969814_vyomika`)
3. Create user + password
4. **Save** database name, username, password

---

## Step 4 — Clone and configure on server

```bash
cd ~
git clone https://github.com/vyomikaatelier-lab/vyomika-atelier.git vyomika-atelier
cd vyomika-atelier
cp .env.example .env
nano .env
```

Set these in `.env` (replace DB password with yours from hPanel):

```env
APP_NAME="VYOMIKA ATELIER"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vyomikaatelier.com

DB_HOST=127.0.0.1
DB_DATABASE=u550969814_vyomika
DB_USERNAME=u550969814_vyomika
DB_PASSWORD=YOUR_DB_PASSWORD_HERE

# Shared hosting: session may use file; payment locks still require database cache tables (see below)
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=namaste@vyomikaatelier.com
MAIL_PASSWORD=YOUR_EMAIL_PASSWORD
MAIL_FROM_ADDRESS=namaste@vyomikaatelier.com

ADMIN_EMAIL=admin@vyomikaatelier.com
ADMIN_PASSWORD=<set securely during deployment>

# Razorpay Standard Checkout (from dashboard.razorpay.com)
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
# Legacy names still work: RAZORPAY_KEY / RAZORPAY_SECRET
```

Save: `Ctrl+O`, Enter, `Ctrl+X`

---

## Step 5 — Install and deploy

```bash
cd ~/vyomika-atelier
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
php artisan optimize:clear
# Only if APP_KEY is empty in .env:
grep -q "APP_KEY=base64:" .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
chmod -R 775 storage bootstrap/cache
php artisan storefront:diagnose
```

Link site to domain:

```bash
rm -rf ~/domains/vyomikaatelier.com/public_html
ln -s ~/vyomika-atelier/public ~/domains/vyomikaatelier.com/public_html
php artisan storefront:diagnose
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 6 — Enable SSL (hPanel browser)

1. hPanel → **Websites** → **Manage** → **SSL**
2. Install free Let's Encrypt SSL for **both** `vyomikaatelier.com` **and** `www.vyomikaatelier.com`
3. Turn on **Force HTTPS** if available
4. Add a permanent (301) redirect: `www.vyomikaatelier.com` → `https://vyomikaatelier.com` (hPanel → **Redirects**, or rely on `public/.htaccess`)

**If `https://www.vyomikaatelier.com` shows `NET::ERR_CERT_COMMON_NAME_INVALID`:** the certificate only covers the apex domain. Reinstall SSL and ensure `www` is included in the certificate SAN list before the redirect can work (browsers block the connection at TLS if www is missing from the cert).

---

## Step 7 — Login to admin

- **URL:** https://vyomikaatelier.com/admin
- **Email:** `admin@vyomikaatelier.com`
- **Password:** value you set in `.env` as `ADMIN_PASSWORD` before running `db:seed`

### Change admin email or password after deployment

**Option A — `.env` + re-seed (password only, if account already exists):**

```bash
nano ~/vyomika-atelier/.env
# Set ADMIN_EMAIL=admin@vyomikaatelier.com
# Set ADMIN_PASSWORD=your-new-strong-password
php artisan db:seed --force
php artisan config:cache
```

The seeder updates the password only when `ADMIN_PASSWORD` is set; it does not overwrite an existing admin unless you explicitly set a new password in `.env`.

**After first successful login:** clear `ADMIN_PASSWORD` in `.env` (leave empty) so later `db:seed` runs cannot reset the admin password:

```bash
nano ~/vyomika-atelier/.env
# Set: ADMIN_PASSWORD=
php artisan config:cache
```

`setup-hostinger.sh` clears `ADMIN_PASSWORD` automatically after seeding.

**Option B — Laravel Tinker (recommended for email change):**

```bash
cd ~/vyomika-atelier
php artisan tinker
```

```php
$user = \App\Models\User::where('is_admin', true)->first();
$user->email = 'admin@vyomikaatelier.com';
$user->password = bcrypt('your-new-strong-password');
$user->save();
```

Use a strong unique password. Do not commit passwords to git or documentation.

**Business contact email** (`namaste@vyomikaatelier.com`) is separate from admin login and remains used for mail, footer, and legal pages.

---

## Every redeploy (after `git push`)

SSH in and run the post-deploy script (pulls latest code, re-links symlinks, migrates, exports JSON, rebuilds caches):

```bash
cd ~/vyomika-atelier
bash post-deploy.sh
```

Or manually:

```bash
cd ~/vyomika-atelier
git pull origin main
php composer.phar install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan storefront:diagnose
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Important:** Always run `php artisan storefront:diagnose` before `route:cache` / `view:cache`. Stale or broken views cached without a diagnose pass have caused public storefront 500 errors while admin still worked.

If the site shows 403 or old CSS after a Git redeploy, `post-deploy.sh` re-creates the `public_html` → `public` symlink automatically.

---

## Payment lock preflight (required before Razorpay checkout deploy)

Checkout payment safety uses `PaymentAtomicLock`, which **always** calls `Cache::store('database')->lock()` — not the default `CACHE_STORE`. Session cache and payment locks are separate: `CACHE_STORE=file` is acceptable only when the **database cache store** and its tables are available on the same MySQL connection used for `cache_locks`.

**Do not treat production as ready until this read-only preflight passes on the live server.** Do not acquire a real lock during preflight (that would write to `cache_locks`).

```bash
cd ~/vyomika-atelier

# Effective config (mask secrets in notes; do not paste DB passwords)
php artisan tinker --execute="echo json_encode([
  'cache_default' => config('cache.default'),
  'database_store_driver' => config('cache.stores.database.driver'),
  'database_store_connection' => config('cache.stores.database.connection') ?: config('database.default'),
  'cache_table' => config('cache.stores.database.table'),
  'lock_table' => config('cache.stores.database.lock_table'),
  'config_cached' => app()->configurationIsCached(),
]);"

# Read-only schema checks (SELECT / SHOW / DESCRIBE only)
mysql -h 127.0.0.1 -u YOUR_DB_USER -p YOUR_DB_NAME -e "SHOW TABLES LIKE 'cache';"
mysql -h 127.0.0.1 -u YOUR_DB_USER -p YOUR_DB_NAME -e "SHOW TABLES LIKE 'cache_locks';"
mysql -h 127.0.0.1 -u YOUR_DB_USER -p YOUR_DB_NAME -e "SHOW CREATE TABLE cache_locks\\G"
mysql -h 127.0.0.1 -u YOUR_DB_USER -p YOUR_DB_NAME -e "SELECT migration FROM migrations WHERE migration LIKE '%create_cache%';"
```

**Required:**

| Check | Expected |
|-------|----------|
| `cache.stores.database.driver` | `database` |
| Database store connection | Resolves to production MySQL (masked) |
| `cache` table | Exists |
| `cache_locks` table | Exists |
| `cache_locks` primary key | On lock name column (`key`) |
| Cache migration row | Present in `migrations` |
| `PaymentAtomicLock::assertStoreSupportsSharedLocks()` | Would pass at runtime |

**Not changed by this deploy:** `database/migrations/2026_08_24_233000_add_unique_indexes_to_order_payment_identifiers.php` — run only after separate duplicate-ID preflight approval.

**Residual risk (documented):** If Razorpay accepts a create remotely but the app crashes before persisting `razorpay_order_id`, a retry may create a second gateway order unless provider-level idempotency exists. Receipt stays deterministic (`order_number`). Failures log `razorpay.create_persist_failed` for manual reconciliation. This does not replace live preflight above.

---

## Production `.env` checklist

| Variable | Recommended on Hostinger |
|----------|------------------------|
| `APP_DEBUG` | `false` |
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` is OK **if** MySQL `cache` + `cache_locks` tables exist (payment locks use `Cache::store('database')` explicitly) |
| `QUEUE_CONNECTION` | `sync` (unless you run `queue:work` via cron) |
| `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` | From Razorpay dashboard |
| `MAIL_*` | Hostinger or Cloudflare SMTP credentials |
| `ADMIN_EMAIL` | Your admin notification inbox |
| `MARKETING_EMAIL` | Vendor/marketing proposal inbox (optional) |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile (**Managed** widget mode in dashboard) |
| `TURNSTILE_APPEARANCE` | `always` (widget visible on load) |
| `TURNSTILE_REQUIRE_MANUAL_CONFIRMATION` | `true` (required “I'm not a robot” tick on every form) |
| `LEAD_IP_HASH_SALT` | Random string for hashed lead IP fingerprints |

Order emails implement `ShouldQueue`. With `QUEUE_CONNECTION=database` and **no queue worker**, emails are marked sent but never delivered. Use `sync` on shared hosting.

---

## If something breaks

```bash
# View error log
tail -50 ~/vyomika-atelier/storage/logs/laravel.log

# Fix permissions
chmod -R 775 ~/vyomika-atelier/storage ~/vyomika-atelier/bootstrap/cache
```

---

## ISP blocking (e.g. Reliance Jio WiFi)

If visitors on **Reliance/Jio** (or another ISP) cannot reach the site but it works on mobile data, VPN, or other networks, the ISP may be blocking the **Hostinger origin IP** (`82.25.106.229`).

**Symptoms:** Browser timeout or “site can’t be reached” on Jio WiFi; `nslookup vyomikaatelier.com` returns `82.25.106.229`; same URL works on VPN or another ISP.

**Fix (recommended): Cloudflare free proxy**

Traffic goes to Cloudflare edge IPs instead of the blocked Hostinger IP. Origin hosting, SSL on Hostinger, and hPanel stay the same.

1. [Cloudflare](https://dash.cloudflare.com) → Add site → **Free** plan → import DNS.
2. **DNS records** (orange cloud = proxied):
   - `A` `@` → `82.25.106.229` — **Proxied**
   - `CNAME` `www` → `vyomikaatelier.com` — **Proxied**
   - Mail records (MX, SPF, DKIM): **DNS only** (grey cloud) — copy from hPanel if missing.
3. **hPanel** → **Domains** → **vyomikaatelier.com** → change **nameservers** to Cloudflare’s (e.g. `xxx.ns.cloudflare.com`).
4. Cloudflare → **SSL/TLS** → **Full (strict)** (requires Let’s Encrypt on Hostinger for apex + www — see Step 6 above).
5. Cloudflare → **SSL/TLS** → **Edge Certificates** → enable **Always Use HTTPS**.

**Verify:** `nslookup vyomikaatelier.com` should show Cloudflare IPs (`104.x` / `172.x`), not `82.25.106.229`.

**Stale ISP cache after proxy is enabled:** Global DNS may already show Cloudflare while Reliance WiFi still returns `82.25.106.229` for 15–30 minutes (sometimes up to 24 hours). Bypass local cache to confirm Cloudflare is live: `nslookup vyomikaatelier.com 1.1.1.1`. On Windows: `ipconfig /flushdns` then retry. The site should work on Jio once the ISP cache refreshes.

**SSH from a blocked ISP:** Direct SSH to `82.25.106.229:65002` may still fail. Use **hPanel → Advanced → SSH → Browser terminal** instead.

**Laravel behind Cloudflare:** `bootstrap/app.php` includes `trustProxies` so visitor IPs are correct for rate limits and lead tracking. After deploy, run `php artisan config:cache`.

**Temporary workarounds:** Jio mobile data (if unblocked), VPN, or wait for DNS propagation after Cloudflare (often 15 min–2 hours).

**Page speed (Cloudflare):** After proxy is on, reduce Lighthouse “cache / redirects / images” warnings:

1. **SSL/TLS → Edge Certificates** → Always Use HTTPS **On**
2. **Rules → Redirect Rules** → one 301: `www.vyomikaatelier.com` → `https://vyomikaatelier.com` (keep a single hop; avoid stacking Hostinger + Cloudflare www redirects)
3. **Caching → Configuration** → Browser Cache TTL **1 month**; Caching Level **Standard**
4. **Speed → Optimization** → Auto Minify (CSS/JS/HTML) **On**, Brotli **On**
5. Cache static paths (`/css/*`, `/js/*`, `/storage/*`) as **Eligible for cache** with long Edge TTL

Origin `.htaccess` already sets 30-day `Cache-Control` for CSS/JS/images. Redeploy after pulling speed-related commits, then re-test Page Speed.

**ISP complaint:** Report false positive block of `vyomikaatelier.com` / IP `82.25.106.229` to Jio support with your city and connection type (Fiber/WiFi).

---

## Cron — expire unpaid orders

Add this in hPanel → **Advanced** → **Cron Jobs** (runs every 15 minutes):

```bash
/usr/bin/php /home/u550969814/vyomika-atelier/artisan orders:expire-pending >> /home/u550969814/vyomika-atelier/storage/logs/cron-expire-orders.log 2>&1
```

Unpaid orders are held for `ORDER_PENDING_EXPIRY_HOURS` (default 24) before stock reservations are released.

---

## Cron — daily lead summary

Add this in hPanel → **Advanced** → **Cron Jobs** (runs daily at 8:00 AM IST):

```bash
/usr/bin/php /home/u550969814/vyomika-atelier/artisan leads:daily-summary >> /home/u550969814/vyomika-atelier/storage/logs/cron-leads-summary.log 2>&1
```

Set `MARKETING_EMAIL`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, and `LEAD_IP_HASH_SALT` in `.env` before going live. Upload the catalogue PDF to `storage/app/catalogue/vyomika-atelier-catalogue.pdf` (create the folder if needed). Run migrations after deploy:

```bash
php artisan migrate --force
```

---

## Add Razorpay later

When you have keys from [dashboard.razorpay.com](https://dashboard.razorpay.com):

```bash
nano ~/vyomika-atelier/.env
# RAZORPAY_KEY_ID=rzp_live_...
# RAZORPAY_KEY_SECRET=...
php artisan config:cache
```

Online payment will appear automatically on checkout.

Configure a webhook in the Razorpay dashboard pointing to:

`https://vyomikaatelier.com/webhooks/razorpay`

Events: `payment.captured`, `order.paid`. Set `RAZORPAY_WEBHOOK_SECRET` in `.env` to the secret Razorpay provides, then run `php artisan config:cache`.
