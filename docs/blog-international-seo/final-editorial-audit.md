# Final Editorial and Repository Audit

**Project:** Vyomika Atelier Laravel (`D:\VYOMIKA ATELIER`)  
**Branch:** `hotfix/production-regressions`  
**Date:** 2026-08-23  
**Scope:** Editorial QA, repository cleanup, image verification, duplication fixes — **no deployment, import, or production connection performed.**

---

## 1. Repository cleanliness

### Temporary helpers removed

The following one-off blog task scripts were **deleted** (already absent from repo root: `_analyze-blog.php`, `final-expand-short-articles.php`, `final-expand-short-articles-pass2.php`, `tmp-wordcount` scratch files):

| Removed script | Purpose (one-off) |
|---|---|
| `database/scripts/expand-blog-articles.php` | First expansion pass |
| `database/scripts/expand-blog-articles-pass2.php` | Second expansion pass |
| `database/scripts/fix-all-excerpts.php` | Excerpt batch fix |
| `database/scripts/fix-excerpts-and-short.php` | Excerpt + short-article fix |
| `database/scripts/apply-conservative-claim-defaults.php` | Claim default pass |
| `database/scripts/fix-blog-manifest.php` | Manifest rewrite |
| `database/scripts/blog-slug-and-content-fix.php` | Slug/content repair |
| `database/scripts/audit-blog-content.php` | Ad-hoc audit helper |
| `database/scripts/internationalize-global-blog-articles.php` | International English pass |
| `database/scripts/generate-blog-articles.php` | Initial article generator |
| `database/scripts/generate-regional-blog-drafts.php` | Regional draft generator |

Temporary audit runners (`_audit-temp.php`, `_dedupe-boilerplate-once.php`, `_draft-bad-heroes-once.php`) were run and **not committed**.

### Preserved (intentionally)

- `PROJECT_AUDIT_REPORT.md`, `WORKER_CHANGE_AUDIT.md`, `serve-vyomika.ps1` — pre-existing user files, **not touched**
- `database/scripts/export-*.php`, `audit-catalog-classification.php`, `sync-projects-json.php` — ongoing catalog/export utilities
- `storage/app/blog-backups/` — **gitignored**; contains production blog snapshot; **not committed**

### Content fixes applied

- Removed **duplicate boilerplate h2 blocks** (second copy of Site measurement / Packaging / Questions trio) from **15 global articles** inserted by expansion passes.
- Fixed broken internal link in `what-is-corten-steel-and-how-does-it-weather` (`corten-steels-design-drainage-weathering` → `corten-steel-facades-design-drainage-weathering`).
- Replaced Corten-inappropriate PVD boilerplate copy in `what-is-corten-steel-and-how-does-it-weather` site-measurement section.
- Manifest: hero-image gate moved **19 scheduled/draft global articles** to `draft` where hero URL is broken or topic-mismatched (see §3–§4).
- Manifest: de-cannibalised Corten cluster keyword — `corten-steel-facades-design-drainage-weathering` primary keyword → **"Corten steel facade design"**.

---

## 2. Intentional changed-file list (deploy when approved)

| File / path | Why it must ship |
|---|---|
| `database/content/blog/manifest.php` | Canonical SEO metadata, publish status, hero URLs, keyword assignments, import eligibility |
| `database/content/blog/articles/*.php` (25 global + 9 regional bodies) | Article HTML, FAQs — source of truth for `blog:import-content` |
| `app/Support/BlogContentImporter.php` | Import logic, slug map, image gating, status preservation |
| `app/Console/Commands/ImportBlogContentCommand.php` | CLI entry for content import |
| `tests/Feature/BlogContentImportTest.php` | Regression coverage for import safety |
| `tests/Feature/BlogModuleTest.php` | Blog admin + storefront behaviour |
| `tests/Feature/SeoFoundationTest.php` | Sitemap, meta, draft exclusion |
| `docs/blog-international-seo/*.md` | Strategy, schedules, this audit — owner review artefacts |
| `database/migrations/2026_08_23_*_blog*.php` | Blog SEO schema upgrades |

**Do not deploy from this audit session:** untracked root helpers (`PROJECT_AUDIT_REPORT.md`, etc.), `storage/app/blog-backups/*`, deleted one-off scripts.

---

## 3. Image verification table (25 global articles)

**Method:** Fetched live URLs; inspected JPEG bytes and dimensions. Only three unique hero URLs are used across the library.

| # | Slug | Hero URL | Source | Dims | Visible content | Matches article? | Vyomika work? | Copyright | Alt text quality | Verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | glass-partitions-open-plan | `…/campaign-partitions.jpeg` | vyomikaatelier.com | — | **404 Not Found** | N/A | Unknown | Unknown | Good (partition) | **REPLACE — broken URL** |
| 2 | pvd-coating-explained | `…/372645.jpeg` | delhiduniya.com/vyomika | 450×600 | Gold PVD geometric partition, luxury interior | Yes | Likely Vyomika product photo | Third-party host; verify licence | Good | **KEEP** (upgrade to ≥1200px wide) |
| 3 | corten-steel-modern-facades | `…/722414.jpeg` | delhiduniya.com/vyomika | 450×600 | Gold PVD partition w/ marble infill | **No — not Corten** | Likely Vyomika | Third-party host | Claims Corten patina | **REPLACE immediately** |
| 4 | pvd-partitions-materials-finishes-applications-cost-factors | `…/722414.jpeg` | delhiduniya | 450×600 | PVD partition screen | Yes | Likely Vyomika | Third-party host | Good | **KEEP** (resize) |
| 5 | pvd-partition-price-in-india-what-determines-final-cost | `…/372645.jpeg` | delhiduniya | 450×600 | PVD partition | Yes | Likely Vyomika | Third-party host | Good | **KEEP** (resize) |
| 6 | pvd-partitions-vs-powder-coated-metal-partitions | `…/campaign-partitions.jpeg` | vyomikaatelier.com | — | **404** | N/A | — | — | Good | **REPLACE — broken** |
| 7 | how-to-select-metal-partition-for-living-room | `…/722414.jpeg` | delhiduniya | 450×600 | PVD partition | Yes | Likely Vyomika | Third-party host | Good | **KEEP** (resize) |
| 8 | slim-profile-doors-hinged-sliding-telescopic-compared | `…/372645.jpeg` | delhiduniya | 450×600 | Partition, not door | **No** | Likely Vyomika | Third-party host | Claims sliding door | **REPLACE** |
| 9 | fluted-glass-slim-profile-doors-design-privacy-guide | `…/722414.jpeg` | delhiduniya | 450×600 | Partition, not door | **No** | Likely Vyomika | Third-party host | Claims fluted door | **REPLACE** |
| 10 | how-to-choose-luxury-main-entrance-door | `…/372645.jpeg` | delhiduniya | 450×600 | Partition, not entrance | **No** | Likely Vyomika | Third-party host | Claims entrance door | **REPLACE** |
| 11 | stainless-steel-glass-etched-entrance-doors-compared | `…/722414.jpeg` | delhiduniya | 450×600 | Partition, not door | **No** | Likely Vyomika | Third-party host | Claims etched door | **REPLACE** |
| 12 | stainless-steel-railings-types-finishes-selection-guide | `…/campaign-partitions.jpeg` | vyomikaatelier.com | — | **404** | N/A | — | — | Claims staircase railing | **REPLACE — broken** |
| 13 | glass-railings-staircases-balconies-planning-checklist | `…/372645.jpeg` | delhiduniya | 450×600 | Partition, not railing | **No** | Likely Vyomika | Third-party host | Claims balcony railing | **REPLACE** |
| 14 | interior-vs-exterior-railings-material-finish | `…/722414.jpeg` | delhiduniya | 450×600 | Partition, not railing | **No** | Likely Vyomika | Third-party host | Claims exterior railing | **REPLACE** |
| 15 | what-is-corten-steel-and-how-does-it-weather | `…/campaign-partitions.jpeg` | vyomikaatelier.com | — | **404** | N/A | — | — | Claims Corten patina | **REPLACE — broken** |
| 16 | corten-steel-facades-design-drainage-weathering | `…/372645.jpeg` | delhiduniya | 450×600 | PVD partition | **No** | Likely Vyomika | Third-party host | Claims Corten façade | **REPLACE** |
| 17 | corten-steel-cladding-vs-conventional-painted-steel | `…/722414.jpeg` | delhiduniya | 450×600 | PVD partition | **No** | Likely Vyomika | Third-party host | Claims Corten vs paint | **REPLACE** |
| 18 | reduce-rust-run-off-staining-around-corten-steel | `…/campaign-partitions.jpeg` | vyomikaatelier.com | — | **404** | N/A | — | — | Claims Corten drip strip | **REPLACE — broken** |
| 19 | pvd-door-handles-finishes-sizes-selection-guide | `…/372645.jpeg` | delhiduniya | 450×600 | Partition, not handles | **No** | Likely Vyomika | Third-party host | Claims pull handles | **REPLACE** |
| 20 | select-pull-handle-length-for-main-door | `…/722414.jpeg` | delhiduniya | 450×600 | Partition, not handle | **No** | Likely Vyomika | Third-party host | Claims long pull | **REPLACE** |
| 21 | pvd-furniture-care-finishes-customization | `…/372645.jpeg` | delhiduniya | 450×600 | Partition in foyer context | Partial | Likely Vyomika | Third-party host | Claims console table | **REPLACE** |
| 22 | choosing-metal-coffee-table-luxury-interior | `…/722414.jpeg` | delhiduniya | 450×600 | Partition, not table | **No** | Likely Vyomika | Third-party host | Claims coffee table | **REPLACE** |
| 23 | pvd-finish-selection-guide-gold-rose-gold-champagne-black | `…/campaign-partitions.jpeg` | vyomikaatelier.com | — | **404** | N/A | — | — | Claims sample board | **REPLACE — broken** |
| 24 | architects-specify-custom-architectural-metalwork | `…/372645.jpeg` | delhiduniya | 450×600 | Installed partition | Partial (metalwork) | Likely Vyomika | Third-party host | Claims shop drawings | **REPLACE** (project/factory photo preferred) |
| 25 | drawing-to-installation-custom-metal-fabrication-process | `…/722414.jpeg` | delhiduniya | 450×600 | Installed partition | Partial | Likely Vyomika | Third-party host | Claims packaged panels | **REPLACE** (QC/packaging photo) |

**Summary:** 6 articles use a **broken** hero URL. 2 product JPEGs (`372645`, `722414`) are valid Vyomika PVD partition photography but are **reused as generic placeholders** for Corten, railings, doors, handles, and furniture — topic mismatch on 18+ articles. All verified JPEGs are **450×600** — below the 1200px blog card guideline.

---

## 4. Image replacement list

### Published — replace before next promotion (may remain live until swap)

| Slug | Issue | Recommended replacement |
|---|---|---|
| `glass-partitions-open-plan` | Hero URL 404 | Host `campaign-partitions.jpeg` on CDN **or** use `372645`/`722414` with correct partition alt until bespoke hero ready |
| `corten-steel-modern-facades` | PVD partition photo, not weathering steel | Corten entrance screen / façade from `/projects/corten-entrance-screen` or new site photography |

### Unpublished — set to draft until hero fixed (done in manifest)

All other global articles with broken or mismatched heroes are now **`draft`** in `manifest.php` (except the 3 published above and 3 PVD articles still `scheduled` with acceptable PVD partition heroes: items 4, 5, 7).

### Asset actions for owner

1. Restore or re-upload `https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg` **or** remove all references.
2. Commission/source heroes: Corten patina panels, glass railings on stair/balcony, slim-profile door installation, PVD handle sample board, factory QC/packaging.
3. Provide ≥1200px-wide JPEG/WebP for all blog index cards.

---

## 5. Duplicate content report

### Within-article duplication (fixed)

| Issue | Articles affected | Action |
|---|---|---|
| Second copy of Site measurement / Packaging / Questions h2 trio | 15 global articles | **Removed** second block |
| Corten article referenced PVD samples in measurement copy | `what-is-corten-steel-and-how-does-it-weather` | **Rewritten** |

### Cross-article duplication (remaining — monitor)

| Pattern | Occurrence | Severity |
|---|---|---|
| Shared conclusion CTA (`contact page` + `professionals collaboration page`) | All 34 articles | Low — acceptable footer CTA |
| Shared Site measurement / Packaging / Questions boilerplate (single copy post-fix) | ~20 global articles | Medium — generic filler; acceptable if shortened later |
| Sentence: "Office reception desks use half-height glass with champagne frames…" | 5 PVD partition articles | Medium — **rewrite** in one article if refreshing |
| Regional India pricing trio share GST / catalogue pricing sentences | 3 regional drafts | Expected — regional variants |

### N-gram / similarity pairs (global, post-dedupe)

Highest `similar_text` pairs (substantive overlap, not identical):

| Pair | Similarity | Notes |
|---|---|---|
| `pvd-partition-price-in-india-*` vs `pvd-partitions-materials-*` | ~12% | Expected — both cover cost factors; distinct H1/intent |
| `corten-steel-modern-facades` vs `what-is-corten-steel-*` | ~11% | Corten cluster — acceptable pillar + explainer |
| `glass-railings-*` vs `stainless-steel-railings-*` | ~10% | Railing cluster — cross-link, not merge |
| `slim-profile-doors-*` vs `fluted-glass-slim-profile-*` | ~9% | Door cluster — acceptable |

No exact duplicate paragraphs remain after dedupe pass. **Keyword stuffing:** brand name density is moderate; no spam patterns detected.

---

## 6. Keyword cannibalisation report

| Cluster | Canonical (primary keyword) | Supporting articles | Internal link direction |
|---|---|---|---|
| **PVD Partitions** | `glass-partitions-open-plan` → *glass partitions* | `pvd-partitions-materials-*` (PVD partition), `pvd-partition-price-*` (pricing), `how-to-select-metal-partition-*`, `pvd-partitions-vs-powder-*` | Pillar → materials → price → living-room selector; all link back to `/studio/pvd-partitions` |
| **PVD Finishes** | `pvd-coating-explained` → *PVD coating* | `pvd-finish-selection-guide-*`, handle/furniture articles | Explainer → finish guide → product pages |
| **Corten** | `corten-steel-modern-facades` → *Corten steel facade* | `what-is-corten-steel-*` (what is), `corten-steel-facades-design-drainage-weathering` (**Corten steel facade design** — updated), `corten-steel-cladding-vs-*`, `reduce-rust-run-off-*` | Explainer → modern façades → design/drainage → runoff → `/corten-steel` |
| **Pricing** | `pvd-partition-price-in-india-*` → *PVD partition price in India* | `pvd-partitions-materials-*` (cost factors section) | Materials links → price; price links → materials; **no** duplicate India regional import (`import_eligible: false`) |
| **Railings** | `stainless-steel-railings-*` → *stainless steel railings* | `glass-railings-*`, `interior-vs-exterior-railings-*` | Types guide → checklist → environment comparison → `/railings` |
| **Doors** | `slim-profile-doors-*` → *slim profile doors* | `fluted-glass-slim-profile-*`, `how-to-choose-luxury-main-entrance-*`, `stainless-steel-glass-etched-*` | Comparison hub → privacy → entrance → `/studio/slim-profile-door-systems` |

**Resolved conflict:** `corten-steel-modern-facades` and `corten-steel-facades-design-drainage-weathering` both had primary keyword "Corten steel facade" — latter retargeted to **"Corten steel facade design"**.

**Latent conflict (acceptable):** `pvd-partitions-materials-*` vs `glass-partitions-open-plan` both touch partitions — differentiated by intent (materials/cost vs open-plan zoning).

---

## 7. Five-article editorial preview

### 7.1 `pvd-coating-explained`

| Field | Value |
|---|---|
| Title | PVD Coating Explained: Durable Metal Finishes |
| Primary keyword | PVD coating |
| Search intent | Informational — specification education |
| SEO title | PVD Coating Explained: Durable Metal Finishes \| Vyomika Atelier |
| Meta description | What PVD coating is, how it differs from plating and powder coat… |
| Excerpt | What PVD coating is on stainless metalwork, how it differs from powder coat and plating… |
| Word count | ~870 (post-dedupe) |
| H2 outline | What PVD coating is → vs powder → vs plating → colours → specifying → care → site measurement → packaging → questions → coordination → customisation → Conclusion |
| FAQs | 4 (peel, outdoors, TiN, tapware matching) |
| Internal links | `/blog/pvd-partitions-vs-*`, `/blog/pvd-finish-selection-*`, `/studio/pvd-partitions`, `/shop/door-handles`, `/contact`, `/professionals` |
| Claims needing confirmation | Exterior PVD suitability (flagged as project-specific in FAQ) |
| Hero | 372645 — PVD partition — **acceptable** |
| First ~250 words | Defines PVD, vacuum deposition, 304/316 stainless, contrast with plating/powder coat |
| Technical middle | "PVD compared with powder coating" — edge wear, metallic clarity |
| Conclusion | Coordinate finish across partitions, doors, hardware |

### 7.2 `pvd-partitions-materials-finishes-applications-cost-factors`

| Field | Value |
|---|---|
| Title | PVD Partitions: Materials, Finishes, Applications and Cost Factors |
| Primary keyword | PVD partition |
| Intent | Commercial investigation |
| Status | scheduled |
| Word count | ~920 |
| H2 outline | Materials → finishes → applications → structure → cost → Vyomika workflow → measurement → scenarios → handover → Conclusion |
| FAQs | 5 |
| Hero | 722414 — matches PVD partition topic |
| Claims | Grade 316 "may be considered" — OK; no fixed pricing |

### 7.3 `slim-profile-doors-hinged-sliding-telescopic-compared`

| Field | Value |
|---|---|
| Primary keyword | slim profile doors |
| Status | draft (hero mismatch) |
| Word count | ~860 |
| H2 outline | Definition → hinged → sliding → telescopic → site conditions → checklist → measurement → climate → security → Conclusion |
| FAQs | 5 |
| Hero | **Mismatch** — partition photo |
| Technical middle | "Telescopic and multi-panel stacks" — hardware complexity |
| Claims | Fire-rated corridor note — correctly defers to local consultant |

### 7.4 `stainless-steel-railings-types-finishes-selection-guide`

| Field | Value |
|---|---|
| Primary keyword | stainless steel railings |
| Status | draft (broken hero) |
| Word count | ~820 |
| H2 outline | Types → grades → finishes → code → fixing → procurement → measurement → compliance → adjacent trades → Conclusion |
| FAQs | 5 |
| Hero | **Broken URL** |
| Note | "Compliance and safety planning" appears twice under different h2 labels — minor internal overlap |

### 7.5 `what-is-corten-steel-and-how-does-it-weather`

| Field | Value |
|---|---|
| Primary keyword | what is Corten steel |
| Status | draft (broken hero) |
| Word count | ~840 |
| H2 outline | Definition → timeline → uses → limits → fabrication → client comms → measurement → weathering comms → QC markers → Conclusion |
| FAQs | 5 |
| Hero | **Broken URL** |
| Fix applied | Corten-specific measurement copy; drainage link corrected |
| Claims | Climate timelines (Delhi/Mumbai/Bangalore) — illustrative, not guaranteed |

---

## 8. Full Laravel test result

```
php artisan test
Tests:    19 failed, 494 passed (2249 assertions)
Duration: 119.13s
Skipped:  0 (none reported)
```

**Failures (pre-existing, unrelated to blog audit):**

- `AdminFrontendSyncTest` — 6 product/category sync failures
- `AdminSaveAuditTest` — product deactivate checkbox
- `CategorySectionTest` — product form validation
- `ProductAdminContentTest` — 5 failures (`size_options` undefined, redirect expectations)
- `ProductAdminIndexTest` — 4 redirect/filter failures
- `ProductAdminPriceTest` — 1 price update failure

Blog/SEO changes did **not** introduce new failures.

---

## 9. Blog/SEO test result

```
php artisan test --filter="BlogContentImportTest|BlogModuleTest|SeoFoundationTest"
Tests:    42 passed (232 assertions)
Duration: 5.25s
```

| Suite | Tests | Result |
|---|---|---|
| `BlogContentImportTest` | 14 | ALL PASS |
| `BlogModuleTest` | 14 | ALL PASS |
| `SeoFoundationTest` | 14 | ALL PASS |

---

## 10. Remaining manual actions

1. **Owner:** Replace heroes per §4 — especially published `glass-partitions-open-plan` and `corten-steel-modern-facades`.
2. **Owner:** Restore or retire `campaign-partitions.jpeg` on production CDN.
3. **Owner:** Confirm delhiduniya.com image licence / migrate heroes to vyomikaatelier.com hosting.
4. **Owner:** Review `docs/blog-international-seo/owner-confirmation-required.md` before any `blog:import-content` apply.
5. **Optional editorial:** Shorten shared Site measurement boilerplate on non-fabrication articles (railings, Corten).
6. **Optional:** Fix 19 failing product-admin tests on `hotfix/production-regressions` (separate from blog scope).

---

**Awaiting owner review — no deployment or production import performed.**
