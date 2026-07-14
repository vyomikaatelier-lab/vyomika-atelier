# Hostinger Deployment Checklist

Follow these steps in order on your Hostinger Business plan.

## Step 1 — Push code to GitHub

On your computer:

```bash
cd "D:\VYOMIKA ATELIER"
git init
git add .
git commit -m "Initial VYOMIKA ATELIER custom store"
```

Create a new repository on [github.com/new](https://github.com/new), then:

```bash
git remote add origin https://github.com/YOUR_USERNAME/vyomika-atelier.git
git branch -M main
git push -u origin main
```

## Step 2 — Enable SSH on Hostinger

1. Log in to **hPanel** → **Advanced** → **SSH Access**
2. Enable SSH and note your hostname, port, and username

## Step 3 — Create MySQL database

1. hPanel → **Databases** → **MySQL Databases**
2. Create database + user, save credentials

## Step 4 — Clone and deploy on server

```bash
ssh -p PORT USER@HOST
cd ~
git clone https://github.com/YOUR_USERNAME/vyomika-atelier.git vyomika-atelier
cd vyomika-atelier
cp .env.example .env
nano .env   # fill in DB, APP_URL, mail, Razorpay keys
```

Edit `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_DATABASE=u123456_vyomika
DB_USERNAME=u123456_admin
DB_PASSWORD=your_password

MAIL_USERNAME=hello@yourdomain.com
MAIL_PASSWORD=your_email_password
ADMIN_EMAIL=hello@yourdomain.com

RAZORPAY_KEY=rzp_test_xxxxx
RAZORPAY_SECRET=xxxxx
```

Run deploy:

```bash
chmod +x deploy.sh
APP_DIR=~/vyomika-atelier DOMAIN_DIR=~/domains/yourdomain.com/public_html ./deploy.sh
php artisan key:generate
php artisan db:seed --force
```

## Step 5 — Enable SSL

1. hPanel → **Security** → **SSL**
2. Install free SSL for your domain
3. Force HTTPS in hPanel if available

## Step 6 — Razorpay setup

1. Create account at [razorpay.com](https://razorpay.com)
2. Dashboard → **Settings** → **API Keys**
3. Copy Key ID and Secret into `.env`
4. Use test keys first, switch to live keys when ready

## Step 7 — First login

- URL: `https://yourdomain.com/admin`
- Email: `admin@vyomikaatelier.com`
- Password: `changeme123`

**Change the password immediately.**

## Troubleshooting

| Issue | Fix |
|---|---|
| 500 error | Check `storage/logs/laravel.log`, run `chmod -R 775 storage bootstrap/cache` |
| Images not showing | Run `php artisan storage:link` |
| Composer not found | `curl -sS https://getcomposer.org/installer \| php` then use `php composer.phar` |
| White screen | Set `APP_DEBUG=true` briefly to see error, then set back to `false` |
