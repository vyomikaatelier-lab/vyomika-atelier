# Blog Design Deployment Report

**Date:** 2026-08-24  
**Branch:** `hotfix/production-regressions`  
**Commit:** `ece9cef`  
**Previous commit:** `0213aae`  
**Status:** **LOCAL COMPLETE — PRODUCTION BLOCKED (SSH)**

---

## Root cause

The live site showed the old blog layout because **the editorial redesign was never implemented in deployed code**, not because of a cache-only mismatch.

On commit `0213aae` (and production before this fix):

| Issue | Evidence |
|---|---|
| Full-width landscape article hero | `show.blade.php` rendered `<figure class="am-blog-article__hero">` (520px max-height, full viewport width) |
| Landscape card ratios | `amerce.css` used `aspect-ratio: 16/10` on `.am-blog-card__thumb`, `.am-blog-featured__media`, `.am-blog-related-card__thumb` |
| No vertical frame component | `am-blog-picture.blade.php` existed but had no `.blog-image-frame` wrapper |
| Card crops on index | Index used `cardImageVariant()` (landscape `-hero-card.jpg` crops) inside landscape frames |

CSS loading was **not** the problem: `layouts/store.blade.php` already loads `public/css/amerce.css` with `?v=filemtime()` cache busting (not Vite). Production and branch shared the same pre-fix markup/CSS.

The `am-blog-picture` partial and WebP support from commit `0213aae` improved image delivery but did not change layout geometry.

---

## Changes in `ece9cef`

### Views

- **`resources/views/partials/am-blog-picture.blade.php`** — Wraps all blog images in `.blog-image-frame` (3:4, WebP+JPEG, lazy/eager, alt, width/height).
- **`resources/views/blog/show.blade.php`** — Removed visible hero banner; compact left-aligned header with bronze divider; reading column + sticky TOC sidebar (270px); mobile TOC above content (non-sticky). OG/JSON-LD/meta hero data unchanged.
- **`resources/views/blog/index.blade.php`** — Featured and grid cards use `heroImageVariant()` for portrait source images inside vertical frames.

### CSS (`public/css/amerce.css`)

- Added `.blog-image-frame` (3:4, `#f3efe8` fallback, `object-fit: cover`).
- Blog index: warm ivory `#f3efe8` background, featured portrait-left grid (`minmax(220px, 340px) 1fr`).
- Article: editorial layout grid (`50rem` content + `270px` TOC), compact breadcrumbs, bronze divider.
- Related article cards: equal vertical 3:4 frames.
- Responsive: 3/2/1 grid; article layout stacks at ≤1024px with TOC first, no sticky.

### Tests

- **`tests/Feature/BlogDesignTest.php`** *(new)* — No hero banner, vertical frames on index/related, TOC sidebar.
- **`tests/Feature/BlogHeroImageTest.php`** — Updated: hero in og:image + JSON-LD only, not visible banner.

---

## Image mapping (3 pillars)

Verified in `database/content/blog/manifest.php`:

| Slug | Hero path | Status |
|---|---|---|
| `glass-partitions-open-plan` | `/images/blog/heroes/glass-partitions-open-plan-hero.jpg` | Self-hosted ✓ |
| `pvd-coating-explained` | `/images/blog/heroes/pvd-coating-explained-hero.jpg` | Self-hosted ✓ |
| `corten-steel-modern-facades` | `/images/blog/heroes/corten-steel-modern-facades-hero.jpg` | Self-hosted ✓ |

Draft articles still reference third-party hotlinks — expected; `--global-only` import preserves draft status and does not publish them.

---

## Local validation

| Check | Result |
|---|---|
| `php artisan test` (full suite) | **PASS** — all tests green |
| `php artisan test --filter=Blog` | **PASS** — 57 blog tests |
| `php artisan blog:import-content --dry-run --global-only` | **PASS** — 25 processed, 3 published pillars unchanged, 13 word-count flags on drafts only |
| CSS cache busting | Already via `filemtime()` on `amerce.css` in layout |

### Layout checklist (manual — owner/browser)

- [ ] 1440px: featured portrait left, 3-column grid, article no hero banner, TOC sticky right
- [ ] 1024px: 2-column grid, TOC above content
- [ ] 768px / 390px: single column, vertical frames maintained

---

## Git

```
Branch:  hotfix/production-regressions
SHA:     ece9cef
Remote:  pushed to origin/hotfix/production-regressions
```

Temp scripts **not committed:** `serve-vyomika.ps1`, `PROJECT_AUDIT_REPORT.md`, `WORKER_CHANGE_AUDIT.md`

---

## Production deployment

**Result: BLOCKED**

```
ssh -p 65002 u550969814@82.25.106.229
→ Permission denied (publickey,password)
```

No credentials are stored in this repo. Production deploy requires owner SSH access.

### Owner checklist (when SSH available)

Run on `~/vyomika-atelier`:

```bash
# 1. Backup blog_posts
php artisan tinker --execute="file_put_contents(storage_path('backups/blog_posts_'.date('Ymd_His').'.json'), \App\Models\BlogPost::all()->toJson(JSON_PRETTY_PRINT));"

# 2. Pull design fix
git fetch origin
git checkout hotfix/production-regressions
git merge --ff-only origin/hotfix/production-regressions   # expect ece9cef

# 3. Dependencies (if needed)
composer install --no-dev --optimize-autoloader
# npm ci && npm run build   # only if Vite assets changed (not needed for this deploy)

# 4. Migrate (safe — no fresh/seed)
php artisan migrate --force

# 5. Clear caches
php artisan optimize:clear

# 6. Dry-run gate
php artisan blog:import-content --dry-run --global-only
# Confirm: 3 pillars published, no image flags on pillars, status/published_at preserved

# 7. Import (only if dry-run passes)
php artisan blog:import-content --global-only --force

# 8. Verify live
curl -sI https://vyomikaatelier.com/blog/pvd-coating-explained | head
# Expect: no am-blog-article__hero in HTML, blog-image-frame present on /blog
```

### Live verification URLs

- https://vyomikaatelier.com/blog
- https://vyomikaatelier.com/blog/pvd-coating-explained
- https://vyomikaatelier.com/blog/glass-partitions-open-plan
- https://vyomikaatelier.com/blog/corten-steel-modern-facades

Confirm: vertical 3:4 frames, no full-width hero, no `SYNC-TRACE`, warm ivory index background.

---

## Final status

| Phase | Status |
|---|---|
| Diagnose | ✓ Complete |
| Local design implementation | ✓ Complete (`ece9cef`) |
| Tests | ✓ All pass |
| Dry-run | ✓ Pass (13 draft word-count flags only) |
| Commit + push | ✓ `ece9cef` on origin |
| Production deploy | **BLOCKED** — SSH key/password required |
| Live verification | Pending owner deploy |

**Overall: LOCAL COMPLETE — PRODUCTION BLOCKED**
