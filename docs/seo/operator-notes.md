# India SEO — Operator Notes

## Hostinger deploy

```bash
cd ~/vyomika-atelier
git pull origin main
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-sodium
php artisan migrate --force
php artisan seo:install-india-content
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Dry-run first if preferred: `php artisan seo:install-india-content --dry-run`

Rollback only installer-tagged **draft** blogs: `php artisan seo:install-india-content --rollback-tagged`

## Search Console

1. Add GSC verification token in **Admin → Site Settings → Analytics & Search Console**.
2. Submit sitemap: `https://vyomikaatelier.com/sitemap.xml`
3. Request indexing for `/`, `/railings`, `/corten-steel`, and top Studio URLs after content review.

## Image replacement checklist

Replace Unsplash / placeholder images in:

- Admin → Railings Page / Corten Steel Page (hero, galleries, supporting images)
- Admin → Collection Pages & Services
- Admin → Blog drafts (featured image before publish)
- Admin → Projects (genuine photos only)

Mark drafts **published** only after images and factual review.

## 90-day plan (summary)

- Weeks 1–2: Publish 4–6 reviewed blog drafts; confirm GA4 + GSC.
- Weeks 3–6: Publish remaining drafts at 1–2/week; add genuine project pages as photos arrive.
- Weeks 7–12: Monitor impressions/clicks; fix thin titles; no new city pages without evidence.
