# Blog Import Dry-Run Report v3

**Date:** 23 August 2026  
**Command:** `php artisan blog:import-content --dry-run --global-only`  
**Database changes:** None (dry-run rolled back)  
**Production apply:** NOT RUN (correction pass only)

## Summary

| Metric | Count |
|---|---|
| Manifest total | 34 |
| Global processed | 25 |
| Regional excluded (default) | 9 |
| Would CREATE | 0 |
| Would UPDATE | 25 |
| Would SKIP | 0 |
| Flagged (word count / image) | 0 |
| Invalid excerpts | 0 |
| Broken relationships | 0 |
| Duplicate slugs | 0 |
| Regional CREATE | 0 |
| Published slug changes | 0 |
| Published status changes | 0 |

## Published records — dates and status

For all existing database records, the importer **excludes `published_at`, `status`, and `slug`** from update payloads (legacy slug rename excepted). Manifest dates do **not** overwrite production values.

| Final slug | Action | Status before → after | published_at before → after |
|---|---|---|---|
| `glass-partitions-open-plan` | UPDATE | published → published | **PRESERVE DESTINATION DATABASE VALUE** |
| `pvd-coating-explained` | UPDATE | published → published | **PRESERVE DESTINATION DATABASE VALUE** |
| `corten-steel-modern-facades` | UPDATE | published → published | **PRESERVE DESTINATION DATABASE VALUE** |

All other global slugs: UPDATE with status and `published_at` preserved from destination database when records exist.

## Safety checks passed

- 25 global articles processed; 9 regional excluded without `--regional`
- Zero CREATE actions against existing production DB
- Zero slug or status changes for published pillars
- `published_at` preservation enforced in importer and regression tests (arbitrary fixture timestamps)
- No duplicate slugs
- No broken relationships (`corten-steel-facade` normalized to `corten-steel`)
- All excerpts 140–165 chars
- All 25 global articles ≥ 900 words after expansion pass
- Claim audit applied — see `claim-audit.md`

## Image-gated drafts

No image-gated drafts in global set. All 25 use Vyomika/delhiduniya photography with complete hero alt text.

| Slug | Hero relevant | Alt complete | Replacement required | Recommendation |
|---|---|---|---|---|
| All 25 global | Yes | Yes | No | Published (3 live) / Scheduled (14) / Draft (8) per manifest |

**Published pillars (3):** Hero images relevant — do not unpublish for image reasons.

**Draft articles (8):** Remain draft pending owner publish decision — images acceptable.

## Quality table

| Slug | Words | H2 | FAQ | Links | Excerpt | SEO title | Meta desc | Status | Image |
|---|---:|---:|---:|---:|---:|---:|---:|---|---|
| glass-partitions-open-plan | 1118 | 15 | 5 | 9 | 163 | 64 | 164 | published | OK |
| pvd-coating-explained | 1033 | 15 | 4 | 8 | 159 | 63 | 159 | published | OK |
| corten-steel-modern-facades | 1170 | 15 | 5 | 9 | 154 | 56 | 162 | published | OK |
| pvd-partitions-materials-finishes-applications-cost-factors | 1073 | 15 | 5 | 9 | 162 | 74 | 145 | scheduled | OK |
| pvd-partition-price-in-india-what-determines-final-cost | 986 | 15 | 5 | 8 | 160 | 70 | 161 | scheduled | OK |
| pvd-partitions-vs-powder-coated-metal-partitions | 1029 | 15 | 5 | 8 | 160 | 55 | 153 | scheduled | OK |
| how-to-select-metal-partition-for-living-room | 1033 | 15 | 5 | 10 | 161 | 66 | 145 | scheduled | OK |
| slim-profile-doors-hinged-sliding-telescopic-compared | 1004 | 15 | 5 | 8 | 161 | 75 | 144 | scheduled | OK |
| fluted-glass-slim-profile-doors-design-privacy-guide | 952 | 15 | 5 | 9 | 157 | 67 | 121 | scheduled | OK |
| how-to-choose-luxury-main-entrance-door | 955 | 15 | 5 | 11 | 161 | 59 | 147 | scheduled | OK |
| stainless-steel-glass-etched-entrance-doors-compared | 931 | 15 | 5 | 8 | 155 | 65 | 143 | scheduled | OK |
| stainless-steel-railings-types-finishes-selection-guide | 912 | 15 | 5 | 11 | 162 | 66 | 142 | scheduled | OK |
| glass-railings-staircases-balconies-planning-checklist | 922 | 15 | 5 | 8 | 155 | 51 | 145 | scheduled | OK |
| interior-vs-exterior-railings-material-finish | 915 | 15 | 5 | 9 | 162 | 57 | 118 | scheduled | OK |
| what-is-corten-steel-and-how-does-it-weather | 951 | 15 | 5 | 9 | 163 | 60 | 154 | scheduled | OK |
| corten-steel-facades-design-drainage-weathering | 921 | 15 | 5 | 9 | 163 | 67 | 131 | scheduled | OK |
| corten-steel-cladding-vs-conventional-painted-steel | 930 | 14 | 5 | 12 | 161 | 50 | 145 | scheduled | OK |
| reduce-rust-run-off-staining-around-corten-steel | 911 | 16 | 5 | 8 | 152 | 59 | 126 | scheduled | OK |
| pvd-door-handles-finishes-sizes-selection-guide | 933 | 17 | 5 | 15 | 155 | 63 | 126 | draft | OK |
| select-pull-handle-length-for-main-door | 900 | 16 | 5 | 10 | 152 | 52 | 134 | draft | OK |
| pvd-furniture-care-finishes-customization | 923 | 18 | 5 | 13 | 158 | 63 | 125 | draft | OK |
| choosing-metal-coffee-table-luxury-interior | 918 | 19 | 5 | 11 | 153 | 59 | 130 | draft | OK |
| pvd-finish-selection-guide-gold-rose-gold-champagne-black | 923 | 18 | 5 | 10 | 156 | 72 | 137 | draft | OK |
| architects-specify-custom-architectural-metalwork | 909 | 19 | 5 | 13 | 164 | 68 | 141 | draft | OK |
| drawing-to-installation-custom-metal-fabrication-process | 910 | 19 | 5 | 12 | 151 | 50 | 140 | draft | OK |

## Test results

| Suite | Passed | Failed | Skipped |
|---|---:|---:|---:|
| BlogContentImportTest | 14 | 0 | 0 |
| BlogModuleTest | 14 | 0 | 0 |
| BlogVisibilityTest | 2 | 0 | 0 |
| SeoFoundationTest | 14 | 0 | 0 |
| **Total** | **44** | **0** | **0** |

Sitemap coverage verified within BlogModuleTest (`sitemap excludes drafts…`) and SeoFoundationTest (`sitemap excludes draft blogs…`).

## Before any production action

Inspect safely — do not assume branch names or server paths:

1. `git status` — review uncommitted changes  
2. `git branch -vv` — confirm branch and remote  
3. Verify database backup through approved hosting tooling  
4. Re-run dry-run after any further edits  

Never use `git reset --hard`, `git checkout --` on production trees, or database wipe commands for blog content updates.

**Awaiting owner review—no production action taken.**
