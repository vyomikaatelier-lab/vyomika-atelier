# VYOMIKA ATELIER

Custom Laravel e-commerce website with built-in lead management. No WordPress, no third-party store platforms — your own codebase.

## Features

- **Shop** — product catalog, cart, checkout (COD, bank transfer, Razorpay)
- **Image uploads** — upload product photos in admin or use image URLs
- **Custom orders** — bespoke commission request form
- **Contact** — general inquiries saved as leads
- **Admin panel** — manage products, orders, and leads at `/admin`
- **Razorpay** — online payment with signature verification
- **Security** — CSRF protection, password hashing, SSL-ready

See **[DEPLOY.md](DEPLOY.md)** for the full Hostinger deployment checklist.

## Requirements

- PHP 8.2+
- MySQL 5.7+ / MariaDB
- Composer
- Hostinger Business plan (SSH access)

## Setup on Hostinger

### 1. Create database

In hPanel → Databases → create a MySQL database. Note the database name, username, and password.

### 2. Upload / clone project via SSH

```bash
cd ~
git clone <your-repo-url> vyomika-atelier
cd vyomika-atelier
php composer.phar install --no-dev --optimize-autoloader
cp .env.example .env
```

### 3. Configure `.env`

```env
APP_NAME="VYOMIKA ATELIER"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your_email_password
ADMIN_EMAIL=your@email.com
```

### 4. Generate key and migrate

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

### 5. Point domain to `public/` folder

```bash
# From your domain folder
ln -s ~/vyomika-atelier/public public_html
```

Or move only `public/` contents to `public_html` and update `index.php` paths.

### 6. Enable SSL

hPanel → SSL → enable free certificate.

## Default admin login

After seeding:

- **URL:** `https://yourdomain.com/admin`
- **Email:** `admin@vyomikaatelier.com`
- **Password:** `changeme123`

Change this password immediately after first login.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Visit `http://localhost:8000`

## Project structure

```
app/
  Http/Controllers/     Storefront + admin controllers
  Models/               Product, Order, Lead, etc.
  Services/             CartService
database/migrations/    Database schema
resources/views/          Blade templates (Tailwind CDN)
routes/web.php          All routes
```

## Payments

Razorpay keys go in `.env`:

```env
RAZORPAY_KEY=rzp_live_xxx
RAZORPAY_SECRET=xxx
```

Online payment integration can be wired to the checkout flow when you have Razorpay credentials.

## Security checklist for production

- [ ] Set `APP_DEBUG=false`
- [ ] Change default admin password
- [ ] Enable SSL (HTTPS)
- [ ] Use Razorpay/Stripe for cards (never store card data)
- [ ] Keep `.env` out of public access
- [ ] Run `php artisan config:cache` after deploy
