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

# Shared hosting: prefer file drivers (database session/cache can 500 if misconfigured)
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

1. hPanel → **Security** → **SSL**
2. Install free SSL for `vyomikaatelier.com`
3. Turn on **Force HTTPS** if available

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

## Production `.env` checklist

| Variable | Recommended on Hostinger |
|----------|------------------------|
| `APP_DEBUG` | `false` |
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` |
| `QUEUE_CONNECTION` | `sync` (unless you run `queue:work` via cron) |
| `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` | From Razorpay dashboard |
| `MAIL_*` | Hostinger or Cloudflare SMTP credentials |
| `ADMIN_EMAIL` | Your admin notification inbox |
| `MARKETING_EMAIL` | Vendor/marketing proposal inbox (optional) |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile for public forms |
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
