# Blog Article Mockup Match Report

**Date:** 2026-08-24  
**Branch:** `hotfix/production-regressions`  
**Reference:** Editorial mockup (PVD Coating Explained — warm cream editorial layout)  
**Validation URL:** `http://127.0.0.1:8000/blog/pvd-coating-explained` @ 1440px  
**Screenshot:** `docs/blog-international-seo/screenshots/article-mockup-match-pvd-coating-explained-1440.png`

---

## What matched (already in place)

| Element | Mockup spec | Status |
|---|---|---|
| No full-width hero | Compact masthead only | ✅ |
| Container width | ~1200–1240px centered | ✅ `min(1240px, calc(100% - 48px))` |
| Masthead split | Left text / right 3:4 portrait ~280–300px | ✅ `article-masthead` grid |
| Category label | Small bronze caps | ✅ Playfair Display, uppercase, `#967236` |
| H1 | Large serif, two-line | ✅ `clamp(2rem, 3.5vw, 2.75rem)` Playfair |
| Intro/deck | Sans-serif summary | ✅ DM Sans 17px |
| Metadata | Author, date, read time from DB | ✅ Real `$post` values |
| Reading grid | ~780px body + ~270px TOC | ✅ `grid-template-areas: "body toc"` |
| Nav/header/footer | Existing Vyomika chrome | ✅ Unchanged |
| No fake spec callouts | Only render article HTML | ✅ No invented boxes added |

---

## What was adjusted (this pass)

| Element | Before | After |
|---|---|---|
| Page background | `#f3efe8` | `#f9f7f2` (mockup warm cream) |
| Masthead divider | Short 4rem bronze bar, 2px | Full-width 1px bronze line |
| Masthead image | Plain frame | 1px gold border on `.blog-image-frame` |
| Category typography | Sans-serif caps | Playfair Display serif caps |
| H1 scale | max 2.35rem | max 2.75rem, tighter line-height |
| Body prose | Global 0.95rem / 1.75 | Article-scoped 1.0625rem (17px) / 1.7 |
| H2 in body | Inherited small prose | Playfair 1.625–1.875rem serif |
| Metadata row | Dot-separated text | Icon + label items (author, calendar, clock) |
| TOC card | White rounded box, bullet list | Tan `#f3efe8` compact card, numbered links |
| TOC separators | None | Thin `#e8e2d8` horizontal rules between items |
| TOC active state | None | Bronze 3px left bar + scroll-spy via `IntersectionObserver` |
| TOC title | Mixed case | Uppercase via CSS (`letter-spacing: 0.12em`) |

### Files changed

- `public/css/amerce.css` — article editorial tokens, TOC, prose, meta
- `resources/views/blog/show.blade.php` — meta icons, numbered TOC, scroll-spy script
- `tests/Feature/BlogDesignTest.php` — assertions for new markup/CSS hooks
- `docs/blog-international-seo/screenshots/capture-article.mjs` — Playwright capture helper

---

## Gaps remaining vs mockup

| Gap | Severity | Notes |
|---|---|---|
| **Updated date** | Low | Shown only when `$post->lastUpdatedAt()` is set; PVD article in local DB has no update date, mockup shows one |
| **Share row** | Low | Functional block below body; not in mockup but intentional site feature |
| **Related products/services/FAQ/CTA** | Low | Below-fold sections exist in live template; mockup crops above fold only |
| **Breadcrumb styling** | Low | Slightly lighter grey in mockup; functional match |
| **H2 exact size** | Low | Mockup ~28–32px; implemented `clamp(1.625rem, 2.5vw, 1.875rem)` — visually close at 1440px |
| **TOC sticky offset** | Low | `top: 120px` may need fine-tuning if announce bar height changes |
| **Mockup reference file** | Info | `assets/.../ChatGPT_Image_Aug_24__2026__08_35_47_PM-3f07a20f-*.jpg` not found in repo; compared against `article-layout-1440.png` + spec brief |

---

## Test results

| Suite | Result |
|---|---|
| `php artisan test --filter=Blog` | **57 passed** |
| `php artisan test` (full) | **All green** |

---

## Visual verdict

At **1440px**, the article page is **visually close** to the approved editorial mockup for masthead proportions, cream background, bronze divider, body/TOC grid, typography hierarchy, and compact tan TOC with bronze active marker. Remaining differences are mostly below-fold content blocks and DB-driven metadata variance.
