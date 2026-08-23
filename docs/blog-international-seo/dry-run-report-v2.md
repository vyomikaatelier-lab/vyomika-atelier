# Blog Import Dry-Run Report v2

**Date:** 23 August 2026  
**Command:** `php artisan blog:import-content --dry-run --global-only --force`  
**Database changes:** None (dry-run rolled back)  
**Apply command:** NOT RUN (correction pass only)

## Summary

| Metric | Count |
|---|---|
| Manifest total | 34 |
| Global processed | 25 |
| Regional excluded (default) | 9 |
| Would CREATE | 0 |
| Would UPDATE | 25 |
| Would SKIP | 0 |
| Flagged (word count) | 9 |
| Invalid excerpts | 0 |
| Broken relationships (corten-steel-facade) | 0 |
| Regional CREATE | 0 |

## Published slug mapping (verified live URLs preserved)

| DB ID | Legacy DB slug | Manifest key | Final slug | Action | Status before → after | published_at before → after |
|---|---|---|---|---|---|---|
| 14 | `glass-partitions-open-plan-without-compromise` | `glass-partitions-open-plan` | `glass-partitions-open-plan` | UPDATE | published → published | 2026-06-15 → 2026-06-15 |
| 15 | `pvd-coating-explained-durable-metal-finishes` | `pvd-coating-explained` | `pvd-coating-explained` | UPDATE | published → published | 2026-06-10 → 2026-06-10 |
| 16 | `why-corten-steel-is-perfect-for-modern-facades` | `corten-steel-modern-facades` | `corten-steel-modern-facades` | UPDATE | published → published | 2026-06-05 → 2026-06-05 |

**LEGACY_SLUG_MAP** in `BlogContentImporter` maps manifest/content keys to verified live slugs. No longer slugs are created. On apply, legacy DB slugs are renamed to final slugs in-place (no duplicates, no redirects).

## Safety checks passed

- ✅ 25 global articles processed; 9 regional excluded without `--regional`
- ✅ 3 published pillars matched by legacy slug lookup — UPDATE not CREATE
- ✅ Zero CREATE actions (existing DB records)
- ✅ No duplicate slugs
- ✅ No regional CREATE
- ✅ All excerpts 140–165 chars (34/34)
- ✅ `corten-steel-facade` normalized to `corten-steel` landing route
- ✅ `--force` does not bypass published-slug preservation (tested)

## Word counts per global article

| Slug | Words | Gate |
|---|---|---|
| glass-partitions-open-plan | 1118 | OK (published) |
| pvd-coating-explained | 1033 | OK (published) |
| corten-steel-modern-facades | 1168 | OK (published) |
| pvd-partitions-materials-finishes-applications-cost-factors | 1073 | OK |
| pvd-partition-price-in-india-what-determines-final-cost | 986 | OK |
| pvd-partitions-vs-powder-coated-metal-partitions | 1029 | OK |
| how-to-select-metal-partition-for-living-room | 1033 | OK |
| slim-profile-doors-hinged-sliding-telescopic-compared | 1004 | OK |
| fluted-glass-slim-profile-doors-design-privacy-guide | 952 | OK |
| how-to-choose-luxury-main-entrance-door | 955 | OK |
| stainless-steel-glass-etched-entrance-doors-compared | 931 | OK |
| stainless-steel-railings-types-finishes-selection-guide | 912 | OK |
| glass-railings-staircases-balconies-planning-checklist | 922 | OK |
| interior-vs-exterior-railings-material-finish | 915 | OK |
| what-is-corten-steel-and-how-does-it-weather | 949 | OK |
| corten-steel-facades-design-drainage-weathering | 919 | OK |
| corten-steel-cladding-vs-conventional-painted-steel | 870 | **Below 900 — stays draft** |
| reduce-rust-run-off-staining-around-corten-steel | 891 | **Below 900 — stays draft** |
| pvd-door-handles-finishes-sizes-selection-guide | 868 | **Below 900 — stays draft** |
| select-pull-handle-length-for-main-door | 890 | **Below 900 — stays draft** |
| pvd-furniture-care-finishes-customization | 871 | **Below 900 — stays draft** |
| choosing-metal-coffee-table-luxury-interior | 874 | **Below 900 — stays draft** |
| pvd-finish-selection-guide-gold-rose-gold-champagne-black | 869 | **Below 900 — stays draft** |
| architects-specify-custom-architectural-metalwork | 891 | **Below 900 — stays draft** |
| drawing-to-installation-custom-metal-fabrication-process | 846 | **Below 900 — stays draft** |

## Image-gated drafts

No hero-image placeholder flags on global articles (all use Vyomika/delhiduniya photography). Image gate rules documented in `image-requirements.md`.

## Regional articles (excluded from default import)

| Slug | Locale | import_eligible | robots_index |
|---|---|---|---|
| india-pvd-partition-prices-materials-size-installation | en-IN | false | false |
| india-glass-railing-price-quotation-factors | en-IN | false | false |
| india-choosing-metal-partitions-homes-apartments | en-IN | false | false |
| uk-metal-room-dividers-interiors-specification-guide | en-GB | false | false |
| uk-slimline-internal-glass-doors-hinged-sliding-fixed | en-GB | false | false |
| uk-corten-steel-cladding-weathering-drainage-detailing | en-GB | false | false |
| uae-pvd-stainless-steel-interiors-finishes-applications | en-AE | false | false |
| uae-glass-metal-partitions-dubai-offices-villas | en-AE | false | false |
| uae-corten-steel-heat-humidity-coastal-considerations | en-AE | false | false |

Require: `php artisan blog:import-content --regional --force` + owner confirmation.

## Test results (13/13 passed)

```
php artisan test --filter=BlogContentImportTest
```

| # | Test | Result |
|---|---|---|
| 1 | dry_run_global_only_processes_25_articles_without_writing | PASS |
| 2 | global_only_excludes_regional_create_actions | PASS |
| 3 | regional_import_requires_explicit_flag | PASS |
| 4 | live_published_slugs_update_existing_records_not_create | PASS |
| 5 | legacy_longer_slugs_are_not_created | PASS |
| 6 | published_live_slugs_remain_published_with_preserved_dates | PASS |
| 7 | live_published_urls_return_http_200 | PASS |
| 8 | import_creates_global_posts_idempotently_by_slug | PASS |
| 9 | non_pillar_global_articles_import_as_draft_or_scheduled | PASS |
| 10 | no_duplicate_slugs_after_import | PASS |
| 11 | backup_file_is_created_on_apply | PASS |
| 12 | force_does_not_bypass_published_slug_preservation | PASS |
| 13 | invalid_corten_steel_facade_service_is_normalized | PASS |

## Gate before apply

1. Expand 9 articles below 900 words (or accept draft downgrade)
2. Owner confirms export copy per `owner-confirmation-required.md`
3. Run apply only after approval:

```bash
# DO NOT RUN YET
php artisan blog:import-content --global-only --force
```

Backup is mandatory in production (`--no-backup` blocked).
