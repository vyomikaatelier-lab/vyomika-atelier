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

Payments for now: **Cash on Delivery** and **Bank Transfer** only (Razorpay can be added later).

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
git config user.email "hello@vyomikaatelier.com"
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

MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=hello@vyomikaatelier.com
MAIL_PASSWORD=YOUR_EMAIL_PASSWORD
MAIL_FROM_ADDRESS=hello@vyomikaatelier.com

ADMIN_EMAIL=hello@vyomikaatelier.com

# Leave empty until Razorpay account is ready
RAZORPAY_KEY=
RAZORPAY_SECRET=
```

Save: `Ctrl+O`, Enter, `Ctrl+X`

---

## Step 5 — Install and deploy

```bash
cd ~/vyomika-atelier
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

Link site to domain:

```bash
rm -rf ~/domains/vyomikaatelier.com/public_html
ln -s ~/vyomika-atelier/public ~/domains/vyomikaatelier.com/public_html
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
- **Password:** `changeme123`

Change password immediately after first login.

---

## If something breaks

```bash
# View error log
tail -50 ~/vyomika-atelier/storage/logs/laravel.log

# Fix permissions
chmod -R 775 ~/vyomika-atelier/storage ~/vyomika-atelier/bootstrap/cache
```

---

## Add Razorpay later

When you have keys from [dashboard.razorpay.com](https://dashboard.razorpay.com):

```bash
nano ~/vyomika-atelier/.env
# Add RAZORPAY_KEY and RAZORPAY_SECRET
php artisan config:cache
```

Online payment will appear automatically on checkout.
