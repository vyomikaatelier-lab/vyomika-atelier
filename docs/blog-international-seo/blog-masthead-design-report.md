# Blog Masthead Design Report

**Date:** 2026-08-24  
**Branch:** `hotfix/production-regressions`  
**Base:** `d34949d` → `9954f6b` (masthead refinement beyond `ece9cef`)  
**Status:** **LOCAL COMPLETE — PRODUCTION BLOCKED (SSH)**

---

## Phase 1 — Diagnosis

Compared post-`ece9cef` `show.blade.php` against the owner-approved spec:

| Spec requirement | Post-`ece9cef` state | Gap |
|---|---|---|
| 2-col masthead: text left, small 3:4 image right (~260–300px) | Single-column header only (category, title, excerpt, meta) | **Masthead image missing entirely** |
| `am-blog-picture` with `fetchpriority="high"` in masthead | Not rendered on article page | **Needed masthead grid** |
| No full-width `am-blog-article__hero` banner | ✓ Hero fully removed | Correct |
| Reading grid 740–780px + sticky TOC 260–280px | `50rem` (800px) + 270px TOC | Minor width tweak applied |
| Bronze divider below masthead | ✓ Present | Correct |
| Mobile: image below metadata ~240px, TOC above body | No masthead image; TOC order correct | Masthead image + mobile CSS added |
| Warm ivory container 1180–1240px | ✓ `am-container--blog` max 1240px | Correct |
| Vertical `.blog-image-frame` on index/cards/related | ✓ From `ece9cef` | Kept unchanged |

**Root cause:** Commit `ece9cef` correctly removed the full-width landscape hero but did not add the owner-approved compact masthead image beside the heading. Production at `0213aae`/`d34949d` therefore showed either the old full-width hero (if only partially deployed) or no visible hero at all (if `ece9cef` views deployed without masthead refinement).

---

## Changes implemented

### `resources/views/blog/show.blade.php`

- Wrapped header content in `.article-masthead` 2-column grid.
- **Left column** (`.article-masthead__content`): category, title, excerpt, author/dates/reading time.
- **Right column** (`.article-masthead__media`): small vertical 3:4 image via `partials.am-blog-picture` with `lazy => false` (`fetchpriority="high"`), optional caption, `itemprop="image"`.
- Bronze divider remains below masthead.
- No `am-blog-article__hero` full-width banner.
- Article copy, SEO meta, FAQs, JSON-LD schema unchanged.

### `public/css/amerce.css`

```css
.article-masthead__media { width: min(100%, 300px); justify-self: end; }
```

Additional rules:

- `.article-masthead` — `grid-template-columns: minmax(0, 1fr) minmax(260px, 300px)`
- Removed `max-width: 50rem` constraint on header (full container width)
- Reading grid: `minmax(0, 780px) minmax(260px, 280px)`
- Mobile (≤1024px): masthead stacks; image below metadata at `min(100%, 240px)`; TOC above body (existing `order: -1`)

### Tests

- **`BlogDesignTest`** — Asserts `article-masthead`, `article-masthead__media`, `blog-image-frame`, `fetchpriority="high"`; no `am-blog-article__hero`.
- **`BlogHeroImageTest`** — Pillar articles render compact masthead image + og:image/schema; no full-width banner.

### Index + related (unchanged from `ece9cef`)

- Index featured/cards use `heroImageVariant()` inside `.blog-image-frame`.
- Related articles use vertical frames via `am-blog-picture`.
- Search results share index card markup.

---

## Image mapping (3 pillars)

Verified in `database/content/blog/manifest.php` and `public/images/blog/heroes/`:

| Slug | Hero path | og_image | Self-hosted |
|---|---|---|---|
| `glass-partitions-open-plan` | `/images/blog/heroes/glass-partitions-open-plan-hero.jpg` | `…-hero-card.jpg` | ✓ (+ WebP) |
| `pvd-coating-explained` | `/images/blog/heroes/pvd-coating-explained-hero.jpg` | `…-hero-card.jpg` | ✓ (+ WebP) |
| `corten-steel-modern-facades` | `/images/blog/heroes/corten-steel-modern-facades-hero.jpg` | same as hero | ✓ (+ WebP) |

Draft articles retain external third-party hotlinks — expected; `--global-only` import preserves draft status.

---

## Local validation

| Check | Result |
|---|---|
| `php artisan test` (full suite) | **PASS** — 532 passed, 1 skipped |
| `php artisan test --filter=Blog` | **PASS** — 57 passed |
| `php artisan blog:import-content --dry-run --global-only` | **PASS** — 25 processed; 13 draft word-count flags only |
| `php artisan optimize:clear` | **DONE** |
| CSS cache busting | `layouts/store.blade.php` uses `filemtime()` on `amerce.css` |

### Viewport checklist (manual — no browser automation in this session)

| Viewport | Expected |
|---|---|
| 1440px | Masthead: text left, ~300px vertical image right; reading column ~780px; sticky TOC ~270px right |
| 1024px | Masthead stacks; TOC above body (non-sticky) |
| 768px / 390px | Image below metadata ~240px; single-column layout |

Screenshot paths: not captured (browser tools not used this session).

---

## Git

```
Branch:  hotfix/production-regressions
SHA:     9954f6b
Remote:  pushed to origin/hotfix/production-regressions
```

Temp files **not committed:** `serve-vyomika.ps1`, `PROJECT_AUDIT_REPORT.md`, `WORKER_CHANGE_AUDIT.md`

---

## Production deployment

**Result: BLOCKED**

```
ssh -p 65002 u550969814@82.25.106.229
→ Permission denied (publickey,password)
```

### Delta if owner partially deployed `0213aae` + `d34949d`

If production has `ece9cef` views but not this masthead commit:

1. Pull latest `hotfix/production-regressions` (new SHA after this commit)
2. `php artisan optimize:clear` and `php artisan view:clear`
3. No DB migration required for layout-only change
4. Re-run `blog:import-content --dry-run --global-only` only if images still external in DB
5. Verify live HTML contains `article-masthead__media` and **not** `am-blog-article__hero`

### Owner hPanel checklist (when SSH available)

Run on `~/vyomika-atelier`:

```bash
# 1. Backup blog_posts
php artisan tinker --execute="file_put_contents(storage_path('backups/blog_posts_'.date('Ymd_His').'.json'), \App\Models\BlogPost::all()->toJson(JSON_PRETTY_PRINT));"

# 2. Pull masthead fix
git fetch origin
git checkout hotfix/production-regressions
git merge --ff-only origin/hotfix/production-regressions

# 3. Clear caches
php artisan optimize:clear
php artisan view:clear

# 4. Dry-run gate (optional — layout-only deploy may skip import)
php artisan blog:import-content --dry-run --global-only

# 5. Verify live
curl -s https://vyomikaatelier.com/blog/pvd-coating-explained | grep -E 'article-masthead|am-blog-article__hero'
# Expect: article-masthead present; am-blog-article__hero absent
```

### Live verification URLs

- https://vyomikaatelier.com/blog
- https://vyomikaatelier.com/blog/pvd-coating-explained
- https://vyomikaatelier.com/blog/glass-partitions-open-plan
- https://vyomikaatelier.com/blog/corten-steel-modern-facades

Confirm: compact masthead vertical image beside title, no full-width hero, warm ivory background, sticky TOC on desktop.

---

## Final status

| Phase | Status |
|---|---|
| Diagnose | ✓ Masthead image missing post-`ece9cef` |
| Masthead grid implementation | ✓ Complete |
| CSS + reading grid | ✓ Complete |
| Tests | ✓ All pass |
| Dry-run | ✓ Pass |
| Commit + push | ✓ `9954f6b` on origin |
| Production deploy | **BLOCKED** — SSH key/password required |
| Live verification | Pending owner deploy |

**Overall: LOCAL COMPLETE — PRODUCTION BLOCKED**
