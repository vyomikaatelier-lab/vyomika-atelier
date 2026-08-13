# GOOGLE_SEARCH_AUDIT.md — Vyomika Atelier

**Date:** 2026-08-13  
**Review type:** Google Search SEO release prep (SKU-safe migration, regression, Hostinger gates)  
**Branch:** `feature/google-search-seo` (from `main`)  
**Production branch:** `main` (`origin/main`)  
**Mode:** Prepared for reviewable commits — **no push/deploy performed**  
**Canonical domain source:** `APP_URL` in `.env` / `config('app.url')`

---

## 1. Executive summary

Vyomika Atelier is a **Laravel 11** custom e-commerce storefront on **Hostinger**. This changeset adds Google Search Central gaps: product/category SEO fields, **SKU-safe unique index**, slug redirects, breadcrumb JSON-LD, dynamic `robots.txt`, WebP derivatives, enriched Product/Offer schema, admin SEO previews, and automated acceptance tests.

**Regression verdict: PASS** (after SKU migration hardening and full suite re-run).

| Area | Verdict |
|------|---------|
| SKU migration safety | PASS — no silent rename; migration fails on duplicates |
| `products:audit-skus` preflight | PASS — non-zero exit + report |
| Migrations (SQLite testing) | PASS |
| Product structured data | PASS |
| Indexing / canonical / robots / sitemap | PASS |
| Image derivatives (WebP/GD) | PASS |
| Full test suite | **446 passed** (see §11) |
| Composer security | **11 advisories** — pre-existing; schedule separate upgrade |
| Production deploy | READY after preflight gates below |

---

## 2. Git branch ancestry

Commands run:

```text
git fetch --all --prune
git branch --show-current          → feature/admin-passkeys (work started here)
git merge-base --is-ancestor main HEAD → yes (main is ancestor of passkeys branch)
git log --oneline main..feature/admin-passkeys → 7 commits (includes passkeys + product admin fixes)
```

**Decision:** `origin/main` already includes the `feature/admin-passkeys` merge (`6d601ff`). `feature/google-search-seo` branches from that merge base with **four SEO-only commits** — no additional cherry-picks required.

**Excluded from commits:** `PROJECT_AUDIT_REPORT.md`, `WORKER_CHANGE_AUDIT.md`, `serve-vyomika.ps1`, `tmp-about-live.html`, database backups, storage caches.

---

## 3. Migration review & production preflight

**File:** `database/migrations/2026_08_13_180000_google_search_product_seo.php`

### Schema changes

**products** (nullable unless noted):

- `image_alt`, `material`, `finish`, `color`, `weight_kg`, `gtin`, `mpn`, `seo_keyword`
- `canonical_url` (500), `robots_index` (boolean, default `true`)
- Unique index on `sku`

**categories:** `og_image`

### SKU policy (production-critical)

| Rule | Implementation |
|------|----------------|
| Multiple `NULL` SKUs | Allowed (MySQL/SQLite unique index) |
| Blank `''` SKUs | Normalized to `NULL` before unique index |
| Duplicate non-empty SKUs | **Migration FAILS** with clear `RuntimeException` |
| Silent `{sku}-{id}` rename | **Removed** — never auto-renames |
| Admin blank SKU | Stored as `NULL` |
| Admin duplicate SKU | Validation error (`Rule::unique`) |

### Production preflight (run on production DB **before** `migrate`)

```bash
cd ~/vyomika-atelier
php artisan products:audit-skus
# Exit 0 required. On duplicates, fix SKUs in admin/DB first.
php artisan migrate --pretend --force   # optional: inspect SQL
```

On duplicates, `products:audit-skus` prints: `id`, `name`, `sku`, `status` (active/inactive), `slug`, `url`.

### Rollback plan

**Case A — migration applied but SEO fields never populated (schema only)**

```bash
php artisan migrate:rollback --step=1 --force
```

Columns removed from `products`: `image_alt`, `material`, `finish`, `color`, `weight_kg`, `gtin`, `mpn`, `seo_keyword`, `canonical_url`, `robots_index`. Unique index on `sku` dropped. `categories.og_image` removed. **No editorial SEO data lost** (columns were empty).

**Case B — migration applied and SEO fields populated**

Same rollback command removes **all data** in those columns:

| Table | Lost columns / data |
|-------|---------------------|
| `products` | `image_alt`, `material`, `finish`, `color`, `weight_kg`, `gtin`, `mpn`, `seo_keyword`, `canonical_url`, `robots_index` values |
| `categories` | `og_image` values |

**Not removed by rollback:** WebP files under `storage/app/public/products/derivatives/` (delete manually if desired). Slug redirects in `url_redirects` (created after deploy via admin). Original product images unchanged.

**Git rollback:** `git checkout <pre-deploy-commit>` + `composer install --no-dev` + `optimize:clear` + cache rebuild.

---

## 4. Composer security advisories

`composer audit --format=json` on 2026-08-13. Installed direct deps: Laravel 11.55.0, guzzle 7.15.1 (transitive), league/commonmark 2.8.3 (transitive).

| Package | Installed | Advisory | Severity | CVE | SEO deploy blocked? |
|---------|-----------|----------|----------|-----|---------------------|
| guzzlehttp/guzzle | 7.15.1 | Noncanonical host bypass | high | CVE-2026-69246 | No — schedule framework update |
| guzzlehttp/guzzle | 7.15.1 | Cookie domain scope | medium | CVE-2026-69245 | No |
| laravel/framework | 11.55.0 | Signed URL path confusion | medium | — | No |
| laravel/framework | 11.55.0 | CRLF in email rule | high | CVE-2026-48019 | No — patch in 12.60+ / 13.10+ |
| league/commonmark | 2.8.3 | Nested XML DoS | medium | — | No |
| league/commonmark | 2.8.3 | Heading slug collision DoS | high | — | No |
| league/commonmark | 2.8.3 | Footnote duplicate DoS | high | — | No |
| league/commonmark | 2.8.3 | Inline attribute DoS | high | — | No |
| league/commonmark | 2.8.3 | Quadratic Markdown DoS | high | CVE-2026-71488 | No |
| league/commonmark | 2.8.3 | unsafe-link filter bypass | medium | CVE-2026-71478 | No |

**Blocked:** No critical runtime blocker for this SEO deploy. **Recommended:** separate `composer update` for `laravel/framework`, `guzzlehttp/guzzle`, `league/commonmark` after SEO merge.

`composer.json` sets `audit.block-insecure: false` — advisories are reported but do not fail install.

---

## 5. Hostinger platform requirements

| Requirement | Value / gate |
|-------------|--------------|
| PHP | **8.2+** (`composer.json`); CI/local **8.3.32** |
| Composer | **2.10.2** (use `php composer.phar` on Hostinger) |
| Database | MySQL (`pdo_mysql`) production; SQLite testing |
| Queue | Laravel default (`sync` or configured driver) — SEO work does not require queue worker |
| Storage | `storage/app/public` writable; `php artisan storage:link` |
| **ext-gd** | **Required** — WebP derivatives (`imagewebp`) |
| **ext-sodium** | **Required** — passkeys/WebAuthn (existing MFA stack) |
| ext-fileinfo | Required (upload validation) |
| ext-mbstring, ext-openssl, ext-curl, ext-pdo | Required |

**Do not use** `--ignore-platform-req=ext-sodium` or `--ignore-platform-req=ext-gd` for production SEO deploy.

### Deployment gate commands (operator)

```bash
php -v                                    # >= 8.2
php -m | grep -E '^(gd|sodium|pdo_mysql|mbstring|openssl|curl|fileinfo)$'
php -r "var_export(function_exists('imagewebp'));"   # true
php composer.phar --version
php composer.phar validate
php composer.phar install --no-dev --optimize-autoloader --no-interaction
php artisan products:audit-skus           # exit 0
php artisan migrate --force
php artisan test                          # or CI already ran
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## 6. Product structured data, indexing, robots, sitemap, images

See prior audit sections — behaviour unchanged:

- `JsonLd::product()` — selling price, brand, seller, `NewCondition`, optional material/color/gtin/mpn, no fake ratings
- Per-product `canonical_url`, `robots_index`, shop search/sort noindex
- Dynamic `GET /robots.txt` (static `public/robots.txt` removed)
- Sitemap excludes inactive and `robots_index=false` products
- `ProductImageDerivativeService` — WebP 400/800/1200w; `images:regenerate-products`

---

## 7. Regression test results

Run after `php artisan optimize:clear` (never test with stale route/config cache).

| Command | Result |
|---------|--------|
| `php composer.phar validate` | **valid** (license warning) |
| `php composer.phar audit` | **11 advisories** (see §4) |
| `php artisan optimize:clear` | OK |
| `php artisan route:cache` | OK |
| `php artisan config:cache` | OK |
| `php artisan view:cache` | OK |
| `php artisan test` | **442 passed** (1980 assertions) |
| `php artisan test --filter=GoogleSearchSeoTest` | **18 passed** (66 assertions) |
| `php artisan test --filter=ProductSkuMigrationTest` | **9 passed** (26 assertions) |
| `vendor/bin/pint --test` (SEO paths) | OK |

No frontend build (`package.json` absent). No PHPStan/Psalm configured.

**Baseline comparison:** `origin/main` merge (`6d601ff`) **415 passed** (1887 assertions) → **+27 tests** (+9 `ProductSkuMigrationTest`, +18 `GoogleSearchSeoTest`) = **442 passed** (1980 assertions).

---

## 8. Deployment plan (operator — not executed here)

### Pre-deploy backup

```bash
mysqldump -u USER -p DATABASE > ~/backups/vyomika-$(date +%F-%H%M).sql
tar czf ~/backups/vyomika-storage-$(date +%F).tar.gz storage/app/public .env
git rev-parse HEAD > ~/backups/pre-seo-deploy-commit.txt
```

### Deploy

```bash
git fetch origin
git checkout feature/google-search-seo   # or merged main
git pull
php composer.phar install --no-dev --optimize-autoloader --no-interaction
php artisan products:audit-skus
php artisan migrate --force
php artisan images:regenerate-products   # optional backfill
php artisan storage:link
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
rm -f public/robots.txt
```

### Post-deploy

1. `curl -s https://vyomikaatelier.com/robots.txt` — Sitemap URL matches `APP_URL`
2. `curl -s https://vyomikaatelier.com/sitemap.xml | head` — valid XML
3. Rich Results Test on a live PDP
4. GSC sitemap + `GOOGLE_SITE_VERIFICATION` or admin Site Settings

---

## 9. Environment variables

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Canonical domain for sitemap, robots, canonicals |
| `GOOGLE_SITE_VERIFICATION` | Optional GSC meta fallback |

---

## 10. Four-commit structure on `feature/google-search-seo`

1. `feat(products): add validated Google product SEO fields` — migration, models, `products:audit-skus`, admin validation/forms
2. `feat(seo): product schema canonical robots sitemap` — JSON-LD, breadcrumbs, canonicals, robots, sitemap, slug redirects
3. `feat(images): responsive product image derivatives` — service, WebP, component, regen command
4. `test(seo): Google Search regression + GOOGLE_SEARCH_AUDIT.md + .env.example`

---

## 11. Known limitations

- WebP only (no AVIF)
- External image URLs skip derivatives
- No `ProductGroup` / `LocalBusiness` schema
- `canonical_url` may point off-domain (editorial override)
- Composer advisories pre-date SEO work

---

## 12. Search Console checklist

1. GSC HTML tag in admin or `GOOGLE_SITE_VERIFICATION`
2. Submit `{APP_URL}/sitemap.xml`
3. Rich Results Test on product URL
4. URL Inspection for key category pages
5. Confirm dynamic robots.txt sitemap line
6. Merchant Center only when GTIN/MPN verified
