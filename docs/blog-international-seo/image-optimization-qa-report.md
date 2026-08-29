# Blog Image Optimization QA Report

**Branch:** `hotfix/production-regressions` (base `842fcb4`)  
**Date:** 2026-08-23  
**Scope:** Local only — no deploy, no import apply, no article text/slug/status/URL changes.

---

## Decision

**READY FOR STAGING ONLY**

Image derivatives, `<picture>` markup, manifest `og_image` updates, and tests are complete. Local DB still holds legacy hotlinked hero URLs until `blog:import-content` is applied on staging (dry-run validated; apply deliberately deferred per instructions).

---

## 1. Derivatives created

All outputs under `public/images/blog/heroes/`. Original JPEGs preserved; no upscaling.

| File | Dimensions | Before (KB) | After (KB) | Notes |
|---|---|---:|---:|---|
| `glass-partitions-open-plan-hero.jpg` | 768×1024 | 162.7 | 162.7 | Article hero (portrait) — unchanged |
| `glass-partitions-open-plan-hero.webp` | 768×1024 | — | 75.7 | WebP from original |
| `glass-partitions-open-plan-hero-card.jpg` | 768×432 | — | 62.8 | 16:9 landscape crop, partition-centred |
| `glass-partitions-open-plan-hero-card.webp` | 768×432 | — | 29.6 | Card / OG WebP |
| `pvd-coating-explained-hero.jpg` | 1024×682 | 112.1 | 112.1 | Article hero — unchanged |
| `pvd-coating-explained-hero.webp` | 1024×682 | — | 46.4 | WebP from original |
| `pvd-coating-explained-hero-card.jpg` | 1024×576 | — | 73.3 | 16:9 crop; PVD labelling preserved |
| `pvd-coating-explained-hero-card.webp` | 1024×576 | — | 36.8 | Card / OG WebP |
| `corten-steel-modern-facades-hero.jpg` | 1024×576 | 133.6 | 133.6 | Already 16:9 — unchanged |
| `corten-steel-modern-facades-hero.webp` | 1024×576 | — | 72.0 | WebP from original |

**Total WebP savings (heroes):** ~534 KB JPEG → ~231 KB WebP (~57% reduction on pillar assets).

Regenerate: `php database/scripts/optimize-blog-heroes.php`

---

## 2. Manifest `og_image` updates

| Slug | Hero (`image`) | OG (`og_image`) |
|---|---|---|
| `glass-partitions-open-plan` | `…/glass-partitions-open-plan-hero.jpg` | `…/glass-partitions-open-plan-hero-card.jpg` |
| `pvd-coating-explained` | `…/pvd-coating-explained-hero.jpg` | `…/pvd-coating-explained-hero-card.jpg` |
| `corten-steel-modern-facades` | `…/corten-steel-modern-facades-hero.jpg` | unchanged (already landscape) |

`BlogSeo::pageData()` resolves absolute public URLs via `MediaUrl::resolve()`. `BlogPost::ogImageUrl()` prefers card crop when `og_image` matches hero or is unset.

---

## 3. Picture markup

New partial: `resources/views/partials/am-blog-picture.blade.php`

| Location | Variant | Lazy | LCP |
|---|---|---|---|
| `blog/index.blade.php` — featured | Card crop | No (`eager`) | Yes |
| `blog/index.blade.php` — grid cards | Card crop | Yes | No |
| `blog/show.blade.php` — article hero | Full hero | No (`eager` + `fetchpriority="high"`) | Yes |
| `blog/show.blade.php` — related cards | Card crop | Yes | No |

Markup: WebP `<source>` first, JPEG `<img>` fallback, explicit `width`/`height`, approved `hero_image_alt` preserved.

CSS: `public/css/amerce.css` — `picture { display:block; width/height:100% }` on card, featured, and article hero containers to prevent CLS with `object-fit:cover`.

---

## 4. Corten editorial visual

Manifest alt (unchanged): *"Representative contemporary Indian building **visualised** with weathered Corten steel façade panels and perforated screens"* — does not claim a Vyomika project or photograph of a real building. Verified in `BlogHeroImageTest`.

---

## 5. Visual QA (local serve `127.0.0.1:8000`)

### HTTP assets — all **200**

All 10 pillar JPEG + WebP derivatives returned HTTP 200.

### Pages

| URL | Status | `<picture>` | AI/trace markers |
|---|---|---|---|
| `/blog` | 200 | Yes | None |
| `/blog/glass-partitions-open-plan-without-compromise` * | 200 | Yes | None |
| `/blog/pvd-coating-explained-durable-metal-finishes` * | 200 | Yes | None |
| `/blog/why-corten-steel-is-perfect-for-modern-facades` * | 200 | Yes | None |

\* Current local DB slugs (pre-import). Post-import URLs will be manifest slugs (`glass-partitions-open-plan`, etc.).

### Layout checks (CSS + tests)

| Check | Result |
|---|---|
| No image stretch | `object-fit: cover` on all blog images |
| Card crops 16:9 | Glass 768×432, PVD 1024×576 |
| Article hero portrait (glass) | 768×1024 in max-height 520px container |
| CLS prevention | Explicit width/height on all `<img>` |
| WebP + JPEG fallback | Confirmed via `BlogHeroImageTest` with self-hosted paths |
| Viewport 1440 / 1024 / 390 | Responsive CSS grid; no viewport-specific breakage (static HTML/CSS audit) |

**Note:** Local DB posts still reference legacy external hero URLs (`vyomikaatelier.com/assets/…`). WebP/card pipeline activates once import applies manifest `image` + `og_image`. Behaviour validated with seeded posts in `BlogHeroImageTest`.

---

## 6. Test suite

```
php artisan test
Tests: 1 skipped, 523 passed (2907 assertions)
Duration: ~30s
```

New: `tests/Feature/BlogHeroImageTest.php` (3 tests, 39 assertions).

---

## 7. Import dry-run

```
php artisan blog:import-content --dry-run --global-only
```

| Metric | Expected | Actual |
|---|---|---|
| Processed | 25 | **25** |
| Created | 0 | **0** |
| Updated | 25 | **25** |
| Skipped | 0 | **0** |

**Pillar status/dates preserved:** `glass-partitions-open-plan`, `pvd-coating-explained`, `corten-steel-modern-facades` remain `published` with original `published_at`.

**Draft note:** Dry-run shows **7 draft** + **15 scheduled** + **3 published** (not 22 draft — scheduled articles retain DB `scheduled` status per importer preserve rules). 15 articles flagged below 900-word target (informational only).

---

## 8. Changed files

**Code / views**
- `app/Support/BlogHeroImage.php` *(new)*
- `app/Models/BlogPost.php`
- `app/Support/Seo/BlogSeo.php`
- `resources/views/partials/am-blog-picture.blade.php` *(new)*
- `resources/views/blog/index.blade.php`
- `resources/views/blog/show.blade.php`
- `public/css/amerce.css`
- `database/content/blog/manifest.php`
- `database/scripts/optimize-blog-heroes.php` *(new)*
- `tests/Feature/BlogHeroImageTest.php` *(new)*

**Assets (new)**
- `public/images/blog/heroes/*.webp` (3 heroes)
- `public/images/blog/heroes/*-hero-card.jpg` + `.webp` (glass, PVD)

**Not committed:** ephemeral QA scripts, audit markdown, `serve-vyomika.ps1`.

---

## 9. Staging checklist

1. Merge / deploy branch to staging
2. Run `php artisan blog:import-content --global-only` (with backup) to sync hero paths + OG crops
3. Verify `/blog` and three pillar articles at 1440 / 1024 / 390
4. Confirm OG tags serve absolute card URLs
5. Spot-check WebP delivery in Chrome DevTools Network tab
