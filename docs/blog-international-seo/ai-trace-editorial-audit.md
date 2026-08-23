# AI-Trace, Prompt-Leakage and Human-Editorial-Quality Audit

**Project:** Vyomika Atelier Laravel (`D:\VYOMIKA ATELIER`)  
**Branch:** `hotfix/production-regressions`  
**Date:** 2026-08-23  
**Scope:** Local audit only — no deployment, production import, URL/status/date changes, or blog importer apply.

---

## A. Public AI / prompt trace scan

**Method:** Case-insensitive ripgrep across deployable app (`app/`, `resources/views/`, `public/`, `database/content/blog/`, `routes/`, `config/`) plus rendered HTML verification via feature tests.

| Pattern / term | Hits | Classification | Action |
|---|---|---|---|
| `SYNC-TRACE` | `app/Support/AnnouncementGuard.php`, `tests/Feature/SyncTraceTest.php`, `tests/Feature/BlogModuleTest.php` | Retain internal | Blocked from public announcement bar; tested |
| `ChatGPT`, `OpenAI`, `AI-generated`, `LLM`, `system prompt` | None in deployable/public content | — | None required |
| `Cursor` | CSS `cursor: pointer` (UI), `StorefrontSeoComposer`, Composer package refs | False positive | None |
| `dry-run report`, `content importer` | `docs/blog-international-seo/*.md` only | Retain internal | Not web-accessible (outside `public/`) |
| `TODO`, `FIXME`, `lorem ipsum`, `placeholder` | Admin form placeholders only | False positive | None |
| `database/content/blog`, `docs/blog-international-seo`, local paths (`D:\`, `C:\Users\`) | Internal docs and audit artefacts only | Retain internal | Not in public HTML |
| `owner-confirmation-required.md` | Internal docs path | Retain internal | Not deployed to document root |
| Commit hashes / branch names in content | None in blog bodies or Blade | — | None |
| Terminal commands in public views | None | — | None |
| Blog article bodies (25 global + 9 regional) | No AI-tool attribution, no prompt text, no generator filenames | — | Clean |

**Rendered verification:** Blog index, three live published articles, sitemap, and 404 responses scanned — no blocked markers in HTML output.

---

## B. Public-file exposure

**Document root:** `public/` (Hostinger standard Laravel layout).

| Asset class | Location | Publicly reachable? | Mitigation |
|---|---|---|---|
| `.git`, `.env`, `docs/`, `storage/`, `database/`, `tests/`, `vendor/` | Project root (parent of `public/`) | No (when docroot = `public/`) | `public/.htaccess` deny rules added for misconfiguration |
| Markdown audit reports | `docs/`, repo root | No | Deny rule + not in `public/` |
| SQL backups, manifests, logs | `database/backups/`, `storage/logs/` | No | Deny rule + not in `public/` |
| Blog content library | `database/content/blog/` | No | Deny rule |
| Source maps | None in `public/` | N/A | `.map` extension denied |
| Cursor config | `.cursor/` (repo root) | No | Hidden-path deny rule |
| Legitimate static JSON | `public/data/*.json` | Yes (intentional) | Unchanged |

**Added to `public/.htaccess`:** Rewrite rules returning 403 for hidden files, private directory prefixes (`docs`, `storage`, `database`, `tests`, `vendor`, etc.), and sensitive extensions (`.sql`, `.md`, `.log`, `.lock`, `.env`).

---

## C. Temporary task files

| File | Status |
|---|---|
| `_analyze-blog.php` | Absent |
| `final-expand-short-articles*.php` | Absent |
| `tmp-wordcount.php` | Absent |
| One-off blog generators/expanders (see `final-editorial-audit.md` §1) | Previously removed; confirmed absent from deployable tree |

**Preserved (user files, not deleted):** `PROJECT_AUDIT_REPORT.md`, `WORKER_CHANGE_AUDIT.md`, `serve-vyomika.ps1`

**Ongoing utilities retained:** `database/scripts/export-*.php`, `audit-catalog-classification.php`, `sync-projects-json.php`

---

## D. Article human-quality review (25 global)

All 25 global manifest entries (`import_eligible: true`, no `locale`) reviewed for AI filler patterns.

| Check | Result |
|---|---|
| Generic intros (`In today's world`, `When it comes to`, `Furthermore`, `Moreover`, `In conclusion` openers) | **None found** |
| `elevate your space` / keyword stuffing | **None found** |
| Excessive luxury/premium padding | Moderate, topic-appropriate; no spam density |
| Unique structure by search intent | Confirmed per cluster (PVD explainer vs pricing vs selector vs comparison) |
| Duplicate h2 blocks within articles | **Fixed** — removed second copy of MEP/designer/sliding trio in `glass-partitions-open-plan` |
| Shared Site measurement / Packaging / Questions boilerplate | Present on ~20 fabrication articles — acceptable workflow copy; shortened on Corten where inappropriate (prior pass) |
| Authorship | All use **Vyomika Atelier Editorial Team** via `BlogPost::DEFAULT_AUTHOR` |

Articles retain genuine specification language: substrate grades (304/316), mirror vs hairline PVD, drawing-to-quotation workflow, drainage/runoff detailing, glass types, fixing tolerances, architect coordination — without invented prices, clients, certifications, or project counts.

---

## E. Genuine business expertise (verified themes)

| Topic | Articles carrying verified studio workflow |
|---|---|
| Drawings for quotation | `architects-specify-custom-architectural-metalwork`, `drawing-to-installation-custom-metal-fabrication-process`, partition/railing checklists |
| Substrate / PVD vs powder coat | `pvd-coating-explained`, `pvd-partitions-vs-powder-coated-metal-partitions` |
| Mirror vs hairline finishes | `pvd-finish-selection-guide-*`, `pvd-furniture-care-*` |
| Cleaning / maintenance | PVD care sections across partitions, furniture, handles |
| Corten drainage / runoff | `corten-steel-facades-design-drainage-weathering`, `reduce-rust-run-off-staining-*` |
| Interior vs exterior | `interior-vs-exterior-railings-material-finish` |
| Architect spec questions | `architects-specify-custom-architectural-metalwork` |
| Finish families / batch matching | Finish guide, handle guide, partition materials |

No fabricated numbers, client names, prices, or certification claims added in this audit.

---

## F. Authorship

| Rule | Compliance |
|---|---|
| Author display | `Vyomika Atelier Editorial Team` only (`BlogPost::DEFAULT_AUTHOR`, blog show schema) |
| No ChatGPT / OpenAI / Cursor / AI attribution | Confirmed |
| No fake personal author names | Confirmed |
| No false "100% human written" claims | Confirmed |
| No false AI disclosure statements | Confirmed |

---

## G. Image metadata

See `final-editorial-audit.md` §3–§4 for full hero verification table.

**Summary (25 global):**

- 6 articles reference **broken** `campaign-partitions.jpeg` (404).
- 2 delhiduniya JPEGs (`372645`, `722414`) are valid Vyomika PVD partition photos but **topic-mismatched** on 18+ non-partition articles.
- All verified JPEGs are 450×600 — below 1200px card guideline.
- Alt text is descriptive; no prompt text or generator filenames in alt/caption fields.
- Third-party host (`delhiduniya.com`) — copyright/licence to be confirmed by owner before production hero promotion.
- Stock/partition photos are **not** labelled as Corten, railing, door, or handle project photography where mismatched.

---

## H. Rendered-page verification

| Page | HTTP | Checks |
|---|---|---|
| `/blog` (index) | 200 | No trace markers; author present on cards |
| `glass-partitions-open-plan` | 200 | BlogPosting, FAQPage, OG meta, Editorial Team author |
| `pvd-coating-explained` | 200 | JSON-LD, breadcrumbs, no trace markers |
| `corten-steel-modern-facades` | 200 | Same |
| `pvd-partition-price-in-india-*` | N/A (scheduled — not public until date) | Content library scan clean |
| `stainless-steel-railings-*` | N/A (draft) | Content library scan clean |
| `/sitemap.xml` | 200 | Drafts excluded; no admin paths |
| `/blog/non-existent-slug` | 404 | No trace markers |
| RSS feed | Not implemented | N/A |

**New regression tests:** `tests/Feature/BlogPublicTraceTest.php` (5 tests) — public blog responses and content library must NOT contain SYNC-TRACE, ChatGPT, OpenAI, Cursor, system prompt, dry-run-report, or local filesystem paths.

---

## I. Editorial similarity report

### Within-article (post-fix)

| Issue | Status |
|---|---|
| Duplicate MEP / designer / sliding h2 trio in `glass-partitions-open-plan` | **Removed** this session |
| Prior duplicate Site measurement / Packaging / Questions blocks (15 articles) | **Removed** prior session |

### Cross-article

| Pattern | Occurrence | Severity | Action |
|---|---|---|---|
| Shared footer CTA (`/contact`, `/professionals`) | All 34 articles | Low | Retain — standard CTA |
| Site measurement / Packaging / Questions boilerplate | ~20 global | Medium | Acceptable; optional future shorten on non-fabrication topics |
| "Office reception desks use half-height glass…" | 5 PVD partition articles | Medium | Distinct enough per article context; monitor |
| Corten cluster overlap | ~10–12% similar_text | Low | Pillar + explainer — acceptable |
| PVD pricing vs materials overlap | ~12% | Low | Distinct H1/intent |

No exact duplicate paragraphs remain. No n-gram pairs above 15% except expected regional pricing variants (`import_eligible: false`).

---

## J. Test results and intentional deployment file list

### Full suite

```
php artisan test
Tests:    19 failed, 499 passed (2691 assertions)
Duration: ~55s
Skipped:  0
```

**Failures:** Pre-existing product-admin regressions (`AdminFrontendSyncTest`, `AdminSaveAuditTest`, `CategorySectionTest`, `ProductAdminContentTest`, `ProductAdminIndexTest`, `ProductAdminPriceTest`) — unrelated to blog/AI-trace work.

### Blog / SEO / trace subset

```
php artisan test --filter="BlogPublicTraceTest|BlogContentImportTest|BlogModuleTest|SeoFoundationTest"
Tests:    47 passed (674 assertions)
```

| Suite | Tests | Result |
|---|---|---|
| `BlogPublicTraceTest` | 5 | ALL PASS |
| `BlogContentImportTest` | 14 | ALL PASS |
| `BlogModuleTest` | 14 | ALL PASS |
| `SeoFoundationTest` | 14 | ALL PASS |

### Intentional deployment file list (when owner approves)

| Path | Purpose |
|---|---|
| `database/content/blog/manifest.php` | SEO metadata, status, hero URLs |
| `database/content/blog/articles/*.php` | Article bodies and FAQs |
| `public/.htaccess` | Private-path denial rules |
| `tests/Feature/BlogPublicTraceTest.php` | AI-trace regression coverage |
| `app/Support/BlogContentImporter.php` | Import logic |
| `app/Console/Commands/ImportBlogContentCommand.php` | CLI entry |
| `tests/Feature/BlogContentImportTest.php` | Import safety |
| `tests/Feature/BlogModuleTest.php` | Storefront blog behaviour |
| `tests/Feature/SeoFoundationTest.php` | Sitemap and meta |
| `docs/blog-international-seo/ai-trace-editorial-audit.md` | This report |

**Do not deploy:** untracked root audit files (`PROJECT_AUDIT_REPORT.md`, etc.), `storage/app/blog-backups/*`, internal dry-run reports unless explicitly desired.

### Remaining owner actions

1. Replace broken/mismatched hero images (see `final-editorial-audit.md` §4).
2. Confirm delhiduniya.com image licence or migrate heroes to vyomikaatelier.com CDN.
3. Review `owner-confirmation-required.md` before any content import apply.
4. Optionally shorten shared measurement boilerplate on railing/Corten articles.

---

**AI-trace and editorial audit complete — awaiting owner review; no deployment or production import performed.**
