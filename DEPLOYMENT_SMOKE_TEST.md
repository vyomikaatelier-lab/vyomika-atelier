# VYOMIKA ATELIER — Deployment & Runtime Smoke Test

Production target: **https://vyomikaatelier.com**  
App path: **~/vyomika-atelier**  
Web root: **~/domains/vyomikaatelier.com/public_html** → symlink to `~/vyomika-atelier/public`

**Never run:** `php artisan migrate:fresh` (destroys data).

---

## 1. Admin CMS verification

All modules below are in `resources/views/layouts/admin.blade.php`, protected by `admin` middleware, and registered in `routes/web.php`.

| Module | Nav route | Controller | Views | Validation | Permissions |
|--------|-----------|------------|-------|------------|-------------|
| Dashboard | `admin.dashboard` | `DashboardController` | `admin/dashboard.blade.php` | N/A | `admin` middleware |
| Products | `admin.products.*` | `ProductAdminController` | `admin/products/*` | Server-side rules | `admin` |
| Orders | `admin.orders.*` | `OrderAdminController` | `admin/orders/*` | Status update rules | `admin` |
| Leads | `admin.leads.*` | `LeadAdminController` | `admin/leads/*` | Dynamic status per type | `admin` |
| Categories | `admin.categories.*` | `CategoryAdminController` | `admin/categories/*` | CRUD + reassignment on delete | `admin` |
| Projects | `admin.projects.*` | `ProjectAdminController` | `admin/projects/*` | Full project fields | `admin` |
| Blog | `admin.blog.*` | `BlogAdminController` | `admin/blog/*` | Draft/publish, SEO | `admin` |
| Exhibitions | `admin.exhibitions.*` | `ExhibitionAdminController` | `admin/exhibitions/*` | Event fields + reorder | `admin` |
| Professional Applications | `admin.professional-applications.*` | `ProfessionalApplicationAdminController` | `admin/professional-applications/*` | Application statuses | `admin` |
| Railing Quotes | `admin.railing-quotes.*` | `RailingQuoteAdminController` | `admin/railing-quotes/*` | Quote statuses | `admin` |
| Customers | `admin.customers.*` | `CustomerAdminController` | `admin/customers/*` | Enable/disable account | `admin` |
| Site Settings | `admin.settings.*` | `SiteSettingAdminController` | `admin/settings/edit.blade.php` | Business/social/SEO fields | `admin` |
| Legal Pages | `admin.legal.*` | `LegalPageAdminController` | `admin/legal/*` | JSON sections validation | `admin` |
| Media | `admin.media.*` | `MediaAdminController` | `admin/media/index.blade.php` | File type/size; delete guard | `admin` |

**Attachment downloads** (admin only): `admin.leads.attachment`, `admin.railing-quotes.attachment`, `admin.professional-applications.attachment`, `admin.media.download`

**Guest access:** `/admin/login` only. Inactive admins and non-admin users are rejected.

---

## 2. Deployment file audit

| File / area | Status | Notes |
|-------------|--------|-------|
| `composer.json` | OK | PHP ^8.2, Laravel ^11. No Razorpay SDK (HTTP API via `RazorpayService`). |
| `composer.lock` | **WARNING** | Not in repo. Run `composer install` on server; commit lock file after first successful install for reproducibility. |
| `composer.json` `cafile` | **WARNING** | Windows path in config — Hostinger script uses local `cacert.pem` instead. |
| Migrations (13 files) | OK | Includes `2026_07_15_100000_admin_cms_extensions.php`. All reversible. No `migrate:fresh`. |
| `DatabaseSeeder` | OK | Catalog only if `Product::count() === 0`. Admin password only updated when `ADMIN_PASSWORD` set. |
| `CmsContentSeeder` | OK | Exhibitions + legal use `firstOrCreate` (safe re-run). |
| `.env.example` | OK | `ADMIN_EMAIL`, `ADMIN_PASSWORD`, Razorpay/WhatsApp placeholders empty. |
| `.env` in git | OK | Listed in `.gitignore`. |
| `setup-hostinger.sh` | OK | Composer, migrate, seed, storage link, permissions, cache. |
| `HOSTINGER.md` / `DEPLOY.md` / `DEPLOY_NOW.md` | OK | Admin credentials aligned. No plaintext production passwords. |
| Public storage | OK | `public/storage` → `storage/app/public`. Private files on `local` disk (`storage/app/`). |
| Writable dirs | OK | `storage/`, `bootstrap/cache/` — `chmod -R 775` on deploy. |
| Production env | **Required** | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://vyomikaatelier.com` |

---

## 3. Exact Hostinger deployment sequence

SSH: `ssh -p 65002 u550969814@82.25.106.229`

```bash
# 1. Pull or clone project
cd ~
git clone https://github.com/vyomikaatelier-lab/vyomika-atelier.git vyomika-atelier
# OR: cd ~/vyomika-atelier && git pull

cd ~/vyomika-atelier

# 2. Document root → public/ (symlink web root)
rm -rf ~/domains/vyomikaatelier.com/public_html
ln -s ~/vyomika-atelier/public ~/domains/vyomikaatelier.com/public_html

# 3. Production .env
cp .env.example .env
nano .env
```

**Required `.env` values:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vyomikaatelier.com

DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

ADMIN_EMAIL=admin@vyomikaatelier.com
ADMIN_PASSWORD=<your-strong-password>

RAZORPAY_KEY=
RAZORPAY_SECRET=

MAIL_USERNAME=...
MAIL_PASSWORD=...
```

```bash
# 4. Composer
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader

# 5. App key (only if APP_KEY is empty)
grep -q "APP_KEY=base64:" .env || php artisan key:generate --force

# 6. Clear stale caches
php artisan optimize:clear

# 7. Database (safe — no fresh)
php artisan migrate --force
php artisan db:seed --force

# 8. Storage
php artisan storage:link
# If exec() disabled on Hostinger:
#   mkdir -p storage/app/public && ln -sf ~/vyomika-atelier/storage/app/public ~/vyomika-atelier/public/storage

# 9. Permissions
chmod -R 775 storage bootstrap/cache

# 10. Production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 11. SSL — enable in hPanel → Security → SSL → Force HTTPS
```

**One-command alternative** (after `.env` is edited):

```bash
cd ~/vyomika-atelier && bash setup-hostinger.sh
```

---

## 4. Runtime smoke-test checklist

### Public website

| # | Test | URL / action | Expected |
|---|------|--------------|----------|
| 1 | Homepage | `/` | 200, hero and nav load |
| 2 | Shop | `/shop` | Product grid loads |
| 3 | Product page | `/shop/{slug}` | PDP, add to cart works |
| 4 | Projects | `/projects` | List loads; filter works |
| 5 | Blog | `/blog` | Published posts only (drafts hidden) |
| 6 | Exhibitions / About | `/about` | Exhibition journey from DB or config fallback |
| 7 | Professionals | `/professionals` | Form loads |
| 8 | Railings | `/studio/railings` | Quote form loads |
| 9 | Legal pages | `/privacy-policy`, `/terms-and-conditions`, etc. | Content renders |
| 10 | Contact | `/contact` | Form submits → lead in admin |
| 11 | Custom order | `/custom-order` | Form submits → lead in admin |
| 12 | Account register | `/account/register` | Shows WhatsApp-not-configured message if env empty |
| 13 | Account login | `/account/login` | OTP/email flows; disabled account blocked |
| 14 | Cart | `/cart` | Items persist in session |
| 15 | Buy Now | PDP → cart/checkout | Adds to cart |
| 16 | Checkout (no Razorpay) | `/checkout` | Notice: *"Online payment is not configured yet. Please contact the studio to complete your order."* |
| 17 | Checkout submit | POST checkout | **Blocked** — redirect with error; **no order created** |

### Admin (`/admin`)

| # | Test | Expected |
|---|------|----------|
| 1 | Login | `admin@vyomikaatelier.com` + `ADMIN_PASSWORD` |
| 2 | Dashboard | Counters for products, categories, orders, projects, blog, exhibitions, leads, apps, quotes, customers, media |
| 3 | Category | Create → edit → reorder (↑↓) → deactivate |
| 4 | Project | Create → edit → publish toggle |
| 5 | Blog | Create draft → publish → verify hidden on public `/blog` until published |
| 6 | Exhibition | Edit seeded event → reorder |
| 7 | Professional application | Submit public form → appears in admin → status + notes |
| 8 | Railing quote | Submit with drawing → admin shows metadata → **Download file** (admin only) |
| 9 | Customer | View registered user → disable → confirm cannot log in |
| 10 | Site settings | Save business/SEO → public footer/contact reflects after cache clear |
| 11 | Legal page | Edit JSON sections → public page updates |
| 12 | Media — public image | Upload without “Private” → preview visible in admin |
| 13 | Media — private PDF | Upload with “Private” → download via admin only |
| 14 | Private file security | Open media/lead file URL while logged out → **403/404** |
| 15 | Unauthorized admin | Visit `/admin` logged out → redirect to login |

---

## 5. Seed & credential safety

| Rule | Confirmed |
|------|-----------|
| Admin password not overwritten on re-seed unless `ADMIN_PASSWORD` is set in `.env` | Yes — `DatabaseSeeder::seedAdminUser()` |
| Clear `ADMIN_PASSWORD` from `.env` after first seed | Yes — `setup-hostinger.sh` clears it automatically; do manually if deploying step-by-step |
| Catalog not wiped on re-seed | Yes — `seedCatalog()` only when `Product::count() === 0` |
| Exhibitions / legal defaults | Yes — `CmsContentSeeder` uses `firstOrCreate` by slug |
| No plaintext password in docs | Yes — placeholder `<set securely during deployment>` |
| No API secrets in repo | Yes — `.env` gitignored; only empty placeholders in `.env.example` |
| Local-only dev fallback | `changeme123` only when `APP_ENV=local` and `ADMIN_PASSWORD` unset (not production) |

---

## 6. Payment & OTP status

### Razorpay — deferred

- Leave `RAZORPAY_KEY` and `RAZORPAY_SECRET` empty until live keys are ready.
- **Checkout UI message** (when keys missing): *"Online payment is not configured yet. Please contact the studio to complete your order."*
- **Server guard:** `CheckoutController::store()` rejects order creation without configured keys.
- **Payment page guard:** `PaymentController::show()` redirects if keys missing.
- **No fake payments:** `PaymentController::verify()` requires valid Razorpay HMAC signature.
- **After keys added:** set env vars → `php artisan config:cache` → checkout button enables.

### WhatsApp OTP — deferred

- Code ready in `WhatsappOtpService` / `AccountAuthController`.
- Registration/login OTP **blocked** when `WHATSAPP_ACCESS_TOKEN` / `WHATSAPP_PHONE_NUMBER_ID` empty — user sees contact-studio message.
- **No fake OTP success** — verification requires real provider response.

---

## 7. Post-deployment commands

```bash
cd ~/vyomika-atelier

# Verification (run immediately after deploy)
php artisan about
php artisan migrate:status
php artisan route:list
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Then complete all checks in §4 (Runtime smoke-test checklist)
```

# Run tests (if PHPUnit installed via dev deps — production uses --no-dev, so run on staging or with dev deps)
php artisan test
# OR: ./vendor/bin/phpunit

# Failed queue jobs
php artisan queue:failed

# Laravel log (last 100 lines)
tail -100 storage/logs/laravel.log

# Clear all caches
php artisan optimize:clear

# Rebuild production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Change admin password (Tinker — recommended)
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'admin@vyomikaatelier.com')->first();
$user->password = bcrypt('new-strong-password');
$user->save();
```

Or set `ADMIN_PASSWORD` in `.env` and run `php artisan db:seed --force` (updates password only when env var is set).

---

## 8. Rollback guidance

| Situation | Action |
|-----------|--------|
| Bad deploy / 500 after cache | `php artisan optimize:clear` then fix `.env` |
| Migration problem | `php artisan migrate:rollback --step=1` (review migration first; do **not** use `migrate:fresh`) |
| Restore previous code | `git checkout <previous-commit>` → `composer install --no-dev` → `php artisan migrate` → `php artisan config:cache` |
| Broken storage link | `rm public/storage && php artisan storage:link` |
| Permission errors | `chmod -R 775 storage bootstrap/cache` |
| Admin lockout | Reset via Tinker (above) or hPanel phpMyAdmin on `users` table |

---

## Deployment readiness summary

| Area | Result |
|------|--------|
| CMS modules | **PASS** — 14 admin modules wired |
| Migrations / seeders | **PASS** — safe, non-destructive |
| Credentials / secrets | **PASS** — no secrets in repo |
| Composer lock | **BLOCKED locally** — generate on Hostinger via `composer install`; commit after successful install |
| Route cache | **PASS** — legal routes use `LegalController` methods; `route:cache` supported |
| Runtime verification | **PENDING** — execute smoke tests on server after deploy |
| Razorpay | **DEFERRED** — by design |
| WhatsApp OTP | **DEFERRED** — env vars required |

**Overall: WARNING** — ready to deploy; run `composer install` on Hostinger to generate `composer.lock`, then commit lock file; complete smoke tests on server.

**Route-cache status:** Legal closure routes replaced with controller methods — `php artisan route:cache` should succeed once `vendor/` is installed (verify on server).
