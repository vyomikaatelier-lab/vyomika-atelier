# Article Reading Layout Fix Report

**Date:** 2026-08-24  
**Branch:** `hotfix/production-regressions`  
**Commit SHA:** `ff74eab`  
**Status:** **LOCAL VISUALLY APPROVED**

## Problem

Blog article masthead rendered correctly, but the reading grid placed the Table of Contents in the wide **left** column (~780px) and the article body in the narrow **right** sidebar (~260–280px). The container also appeared narrower than the 1240px design target.

## Root Cause

In `show.blade.php`, the DOM order is:

1. `<aside class="am-blog-article__sidebar">` (TOC) — first child
2. `<div class="am-blog-article__main">` (body) — second child

`.am-blog-article__layout` used CSS Grid with two columns (`780px | 280px`) but **no explicit `grid-template-areas` or `grid-area` assignments**. Grid auto-placement assigned the first DOM child (sidebar/TOC) to column 1 and the second child (main/body) to column 2 — reversing the intended editorial layout.

## Fix Applied

**File:** `public/css/amerce.css` (layout-only; no content/SEO changes)

```css
.am-blog-article .am-container--blog {
  width: min(1240px, calc(100% - 48px));
  max-width: 1240px;
  margin-inline: auto;
}
.am-blog-article__layout {
  grid-template-areas: "body toc";
  gap: 64px;
  /* columns unchanged: minmax(0,780px) minmax(260px,280px) */
}
.am-blog-article__main { grid-area: body; }
.am-blog-article__sidebar { grid-area: toc; max-width: 280px; }
.am-blog-article__content { max-width: none; }
.am-blog-article__sidebar .am-blog-toc { position: sticky; top: 120px; }
```

**Mobile `@media (max-width: 1024px)`:**

```css
.am-blog-article__layout { grid-template-areas: "toc" "body"; }
.am-blog-article__sidebar .am-blog-toc { position: static; }
```

No changes to `show.blade.php` markup — existing class names adapted.

## Computed Widths (Playwright, `/blog/pvd-coating-explained`)

| Viewport | Container | Body (main) | TOC (sidebar) | Body left of TOC |
|----------|-----------|-------------|---------------|------------------|
| 1440px   | 1240px    | 780px       | 280px         | ✓ yes            |
| 1024px   | 976px     | 952px (stacked) | 952px (stacked) | stacked: TOC above body |
| 390px    | 342px     | 318px (stacked) | 318px (stacked) | stacked: TOC above body |

Grid areas at 1440px: `"body toc"` — `mainGridArea: body`, `sidebarGridArea: toc`.

## Screenshots

| Viewport | Path |
|----------|------|
| 1440px   | `docs/blog-international-seo/screenshots/article-layout-1440.png` |
| 1024px   | `docs/blog-international-seo/screenshots/article-layout-1024.png` |
| 390px    | `docs/blog-international-seo/screenshots/article-layout-390.png` |

Metrics JSON: `docs/blog-international-seo/screenshots/layout-metrics.json`

## Tests

```
php artisan test --filter=Blog   → 57 passed
php artisan test                 → 532 passed, 1 skipped
```

`BlogDesignTest` updated to assert `grid-template-areas: "body toc"` and `grid-area: body/toc` rules in CSS, plus masthead/sidebar/main markup presence and absence of legacy `am-blog-article__hero`.

## Cache

```
php artisan optimize:clear && php artisan view:clear
```

## Production Deployment

Layout-only CSS change. No `blog:import-content`, migrations, or content deploy required. After merge, clear view/cache on production:

```bash
php artisan optimize:clear && php artisan view:clear
```

Or via hPanel if SSH blocked.
