# Production Readiness Decision — Blog International SEO

**Project:** Vyomika Atelier Laravel  
**Branch:** `hotfix/production-regressions`  
**Date:** 2026-08-23  
**Scope:** Local correction pass only — no deployment, import, or production connection.

---

## 1. Executive summary

This pass fixed a blocking `.htaccess` rule that denied all `/storage/` URLs (including the public symlink), remediated hero-image mappings, removed generic editorial boilerplate from 21 articles, recalculated metrics from source files, and resolved 19 PHPUnit failures (17 product-admin regressions + 2 blog/trace test updates).

**All 25 global articles are now `draft` in manifest** until verified self-hosted heroes exist. Zero articles are publish-ready without owner image upload for partition and Corten clusters.

**Decision:** **READY FOR OWNER IMAGE UPLOAD**

---

## 2. `.htaccess` correction

### Before (line 10 — blocked ALL `/storage/`)

```apache
RewriteRule ^(docs|storage|database|tests|vendor|bootstrap|node_modules|scripts)(/|$) - [F,L,NC]
```

**Problem:** With Hostinger document root at `public/`, legitimate media URLs such as `/storage/products/main-image.jpg` (symlink → `storage/app/public/products/…`) received HTTP 403 before Apache could serve the file.

### After

```apache
RewriteRule ^storage/(app|framework|logs|debugbar)(/|$) - [F,L,NC]
RewriteRule ^(docs|database|tests|vendor|bootstrap|node_modules|scripts)(/|$) - [F,L,NC]
```

**Effect:**
- ✅ Allows `public/storage/…` symlink media (products, projects, blog uploads, landing pages)
- ✅ Blocks private trees if document root is misconfigured to project root
- ✅ Retains blocks for `.env`, `.git`, `.sql`, `.md`, `.log`, `.bak`, `composer.*`, `artisan`, `phpunit.xml`

### Hostinger document root expectation

| Setting | Value |
|---|---|
| Document root | `public/` (map `public_html` → Laravel `public/`) |
| Symlink | `php artisan storage:link` — `public/storage` → `storage/app/public` |
| Writable | `storage/`, `bootstrap/cache/` |

### Tests added

- `tests/Feature/PublicHtaccessTest.php` — allows public storage paths, denies private patterns, probes reachable media when symlink exists
- `tests/Feature/BlogPublicTraceTest.php` — updated htaccess assertion for new rule shape

---

## 3. Hero-image mapping (25 global articles)

Verified local assets: `public/images/shop-heroes/*.png` (railings, slim-profile-doors, door-handles, coffee-tables, bespoke-metal-furniture). **No verified local partition or Corten photography.**

| # | Slug | Proposed asset | Subject | Ownership | Dims | Match | Ready |
|---|---|---|---|---|---|---|---|
| 1 | glass-partitions-open-plan | **OWNER IMAGE REQUIRED** | PVD glass partition | Vyomika | — | — | No — broken `campaign-partitions.jpeg` 404 |
| 2 | pvd-coating-explained | `/images/blog/heroes/pvd-coating-explained-hero.jpg` | PVD plaque close-up | Vyomika (self-hosted) | 1024×682 | Good — on-product labelling | **Mapped — 1024px; upgrade to ≥1200w for derivatives** |
| 3 | corten-steel-modern-facades | **OWNER IMAGE REQUIRED** | Corten façade patina | Vyomika | ≥1200w | Grok candidate rejected; manifest still delhiduniya PVD placeholder | No |
| 4 | pvd-partitions-materials-finishes-applications-cost-factors | **OWNER IMAGE REQUIRED** | Fluted PVD partition | Vyomika | ≥1200w | Good if partition | No — delhiduniya |
| 5 | pvd-partition-price-in-india-what-determines-final-cost | **OWNER IMAGE REQUIRED** | Partition site measure | Vyomika | ≥1200w | Partial | No — delhiduniya |
| 6 | pvd-partitions-vs-powder-coated-metal-partitions | **OWNER IMAGE REQUIRED** | PVD vs powder partition | Vyomika | ≥1200w | Good if partition | No — broken campaign URL |
| 7 | how-to-select-metal-partition-for-living-room | **OWNER IMAGE REQUIRED** | Living-room divider | Vyomika | ≥1200w | Good if partition | No — delhiduniya |
| 8 | slim-profile-doors-hinged-sliding-telescopic-compared | `/images/shop-heroes/slim-profile-doors-hero.png` | Slim profile door | Self-hosted | 172950 B PNG | Category hero | **Mapped — owner confirm** |
| 9 | fluted-glass-slim-profile-doors-design-privacy-guide | `/images/shop-heroes/slim-profile-doors-hero.png` | Fluted glass door | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 10 | how-to-choose-luxury-main-entrance-door | `/images/shop-heroes/slim-profile-doors-hero.png` | Main entrance door | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 11 | stainless-steel-glass-etched-entrance-doors-compared | `/images/shop-heroes/slim-profile-doors-hero.png` | Entrance door | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 12 | stainless-steel-railings-types-finishes-selection-guide | `/images/shop-heroes/railings-hero.png` | Stair/balcony railing | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 13 | glass-railings-staircases-balconies-planning-checklist | `/images/shop-heroes/railings-hero.png` | Glass railing | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 14 | interior-vs-exterior-railings-material-finish | `/images/shop-heroes/railings-hero.png` | Exterior railing | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 15 | what-is-corten-steel-and-how-does-it-weather | **OWNER IMAGE REQUIRED** | Corten patina stages | Vyomika | ≥1200w | — | No — broken campaign URL |
| 16 | corten-steel-facades-design-drainage-weathering | **OWNER IMAGE REQUIRED** | Corten façade detail | Vyomika | ≥1200w | Wrong (PVD) | No — delhiduniya |
| 17 | corten-steel-cladding-vs-conventional-painted-steel | **OWNER IMAGE REQUIRED** | Corten vs painted mock-up | Vyomika | ≥1200w | Wrong (PVD) | No — delhiduniya |
| 18 | reduce-rust-run-off-staining-around-corten-steel | **OWNER IMAGE REQUIRED** | Corten drip detail | Vyomika | ≥1200w | — | No — broken campaign URL |
| 19 | pvd-door-handles-finishes-sizes-selection-guide | `/images/shop-heroes/door-handles-hero.png` | PVD pull handles | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 20 | select-pull-handle-length-for-main-door | `/images/shop-heroes/door-handles-hero.png` | Long pull handle | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 21 | pvd-furniture-care-finishes-customization | `/images/shop-heroes/bespoke-metal-furniture-hero.png` | PVD console | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 22 | choosing-metal-coffee-table-luxury-interior | `/images/shop-heroes/coffee-tables-hero.png` | Metal coffee table | Self-hosted | PNG | Category hero | **Mapped — owner confirm** |
| 23 | pvd-finish-selection-guide-gold-rose-gold-champagne-black | **OWNER IMAGE REQUIRED** | PVD sample board | Vyomika | ≥1200w | — | No — broken campaign URL |
| 24 | architects-specify-custom-architectural-metalwork | **OWNER IMAGE REQUIRED** | Shop drawings / studio | Vyomika | ≥1200w | Partial | No — delhiduniya |
| 25 | drawing-to-installation-custom-metal-fabrication-process | **OWNER IMAGE REQUIRED** | QC / packaging | Vyomika | ≥1200w | Partial | No — delhiduniya |

**Manifest updates applied:** 12 slugs mapped to self-hosted `shop-heroes` PNGs. **No delhiduniya.com URLs retained for new mappings.** All 25 global entries remain `draft`.

---

## 4. Editorial cleanup

| Action | Count | Detail |
|---|---|---|
| Removed Site measurement / Packaging / Questions boilerplate | 21 articles | Kept on 4 partition/fabrication articles where intent-relevant |
| Fixed duplicate compliance block | 1 | `stainless-steel-railings-types-finishes-selection-guide` |
| Qualified Corten city timelines | 2 | `what-is-corten-steel-and-how-does-it-weather`, `corten-steel-modern-facades` FAQ |
| Fixed broken internal link | 1 | `corten-steel-modern-facades` → correct drainage article slug |
| Demoted published → draft (bad heroes) | 3 | Former live slugs: glass-partitions, pvd-coating, corten-modern-facades |

---

## 5. Fresh metrics (from `database/content/blog/articles/*.php` + manifest)

Source: `docs/blog-international-seo/metrics-from-source.json` (generated 2026-08-23).

| Slug | Words | H2 | FAQ | Links | Status | Hero | Primary intent |
|---|---:|---:|---:|---:|---|---|---|
| glass-partitions-open-plan | 906 | 12 | 5 | 8 | draft | broken | informational |
| pvd-coating-explained | 644 | 9 | 4 | 7 | draft | delhiduniya | informational |
| corten-steel-modern-facades | 771 | 9 | 5 | 8 | draft | delhiduniya | informational |
| pvd-partitions-materials-finishes-applications-cost-factors | 876 | 12 | 5 | 9 | draft | delhiduniya | informational |
| pvd-partition-price-in-india-what-determines-final-cost | 787 | 12 | 5 | 8 | draft | delhiduniya | commercial |
| pvd-partitions-vs-powder-coated-metal-partitions | 637 | 9 | 5 | 7 | draft | broken | comparison |
| how-to-select-metal-partition-for-living-room | 646 | 9 | 5 | 9 | draft | delhiduniya | informational |
| slim-profile-doors-hinged-sliding-telescopic-compared | 614 | 9 | 5 | 7 | draft | self-hosted | comparison |
| fluted-glass-slim-profile-doors-design-privacy-guide | 566 | 9 | 5 | 8 | draft | self-hosted | informational |
| how-to-choose-luxury-main-entrance-door | 564 | 9 | 5 | 10 | draft | self-hosted | informational |
| stainless-steel-glass-etched-entrance-doors-compared | 536 | 9 | 5 | 7 | draft | self-hosted | comparison |
| stainless-steel-railings-types-finishes-selection-guide | 455 | 8 | 5 | 10 | draft | self-hosted | informational |
| glass-railings-staircases-balconies-planning-checklist | 533 | 9 | 5 | 7 | draft | self-hosted | informational |
| interior-vs-exterior-railings-material-finish | 531 | 9 | 5 | 8 | draft | self-hosted | comparison |
| what-is-corten-steel-and-how-does-it-weather | 575 | 9 | 5 | 8 | draft | broken | informational |
| corten-steel-facades-design-drainage-weathering | 524 | 9 | 5 | 8 | draft | delhiduniya | informational |
| corten-steel-cladding-vs-conventional-painted-steel | 901 | 14 | 5 | 12 | draft | delhiduniya | comparison |
| reduce-rust-run-off-staining-around-corten-steel | 889 | 16 | 5 | 8 | draft | broken | informational |
| pvd-door-handles-finishes-sizes-selection-guide | 916 | 17 | 5 | 15 | draft | self-hosted | informational |
| select-pull-handle-length-for-main-door | 891 | 16 | 5 | 10 | draft | self-hosted | informational |
| pvd-furniture-care-finishes-customization | 910 | 18 | 5 | 13 | draft | self-hosted | informational |
| choosing-metal-coffee-table-luxury-interior | 910 | 19 | 5 | 11 | draft | self-hosted | informational |
| pvd-finish-selection-guide-gold-rose-gold-champagne-black | 911 | 18 | 5 | 10 | draft | broken | informational |
| architects-specify-custom-architectural-metalwork | 882 | 19 | 5 | 13 | draft | delhiduniya | informational |
| drawing-to-installation-custom-metal-fabrication-process | 893 | 19 | 5 | 12 | draft | delhiduniya | informational |

**Duplicate status:** No within-article boilerplate duplicates remain. Cross-article similarity highest in expected clusters (Corten pillar + explainer ~11%; partition pricing pair ~12%) — acceptable.

---

## 6. Test failure investigation (19 → 0)

Compared against `main` via worktree `D:\VYOMIKA-ATELIER-release-admin-mfa` (74/74 product-admin tests pass on baseline).

| Failure group | Count | Classification | Fix |
|---|---:|---|---|
| `Undefined array key "size_options"` | 12 | **Branch regression** (product admin) | Null-safe check in `ProductAdminController` |
| Redirect expects `edit?saved=1` got index | 5 | **Branch regression** (redirect logic) | Restored `_return_*` priority; `_page_save` + `category_id` for index redirect |
| `BlogPublicTraceTest` editorial team | 1 | **Blog-introduced** (all draft) | Conditional assertion + temp publish in trace test |
| `ProductAdminIndexTest` obsolete categories | 1 | **Branch regression** (bulk checkboxes) | Assert `<option value=` not bare `value=` |

### Final test run

```
Tests:    1 skipped, 520 passed (2868 assertions)
Duration: 32.75s
```

Blog/import/SEO/trace/media/htaccess tests: **all pass** (1 skipped: `PublicHtaccessTest` media probe when no symlink files present).

---

## 7. Clean blog-only changeset (vs production baseline)

Blog/SEO files changed in this pass:

- `public/.htaccess`
- `database/content/blog/manifest.php`
- `database/content/blog/articles/*.php` (21 editorial + 2 Corten fixes)
- `tests/Feature/PublicHtaccessTest.php` (new)
- `tests/Feature/BlogPublicTraceTest.php`
- `docs/blog-international-seo/metrics-from-source.json`
- `docs/blog-international-seo/production-readiness-decision.md`

Product-admin fixes (same branch, not blog scope):

- `app/Http/Controllers/Admin/ProductAdminController.php`
- `tests/Feature/AdminSaveAuditTest.php`
- `tests/Feature/ProductAdminIndexTest.php`

---

## 8. Remaining owner actions

1. **Upload partition hero** — replace broken `campaign-partitions.jpeg` or provide ≥1200px PVD partition JPEG in `public/` or media library
2. **Upload Corten heroes** — patina façade, drip detail, sample board (5+ articles blocked)
3. **Self-host PVD coating macro** — replace delhiduniya `372645.jpeg` without upscaling
4. **Confirm shop-hero PNGs** — category heroes are acceptable interim or replace with project photography
5. **Re-run** `php artisan blog:import-content --dry-run --global-only` after hero upload (do not run live import until staging sign-off)

### 8.1 Owner candidate review (2026-08-23)

Three uploaded candidates were assessed. Full table: [owner-image-assessment.md](./owner-image-assessment.md).

| Slug | Result | Notes |
|---|---|---|
| `pvd-coating-explained` | **USE** — manifest updated | Self-hosted `/images/blog/heroes/pvd-coating-explained-hero.jpg`; 1024px — upgrade to ≥1200px before derivatives |
| `glass-partitions-open-plan` | **OWNER VERIFY** | Good subject match; 768px portrait; stock-style filename |
| `corten-steel-modern-facades` | **REJECT / OWNER VERIFY** | Grok watermark; AI/licence risk; pavilion not façade |

---

## 9. Decision

Code, tests, `.htaccess`, and editorial gates are production-safe locally. **No global blog article should publish until owner supplies verified heroes for partition and Corten clusters.**

**READY FOR OWNER IMAGE UPLOAD**
