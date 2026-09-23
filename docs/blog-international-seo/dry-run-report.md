# Blog Import Dry-Run Report

**Date:** 23 August 2026  
**Command:** `php artisan blog:import-content --dry-run`  
**Database changes:** None (dry-run rolled back)

## Summary

| Metric | Count |
|---|---|
| Articles loaded from manifest | 34 |
| Would CREATE | 9 (regional drafts) |
| Would UPDATE | 25 (global library) |
| Would SKIP | 0 |
| Flagged | 31 |

## CREATE (regional drafts — not applied)

| Slug | Locale | Status in manifest |
|---|---|---|
| india-pvd-partition-prices-materials-size-installation | en-IN | draft |
| india-glass-railing-price-quotation-factors | en-IN | draft |
| india-choosing-metal-partitions-homes-apartments | en-IN | draft |
| uk-metal-room-dividers-interiors-specification-guide | en-GB | draft |
| uk-slimline-internal-glass-doors-hinged-sliding-fixed | en-GB | draft |
| uk-corten-steel-cladding-weathering-drainage-detailing | en-GB | draft |
| uae-pvd-stainless-steel-interiors-finishes-applications | en-AE | draft |
| uae-glass-metal-partitions-dubai-offices-villas | en-AE | draft |
| uae-corten-steel-heat-humidity-coastal-considerations | en-AE | draft |

Regional entries use `robots_index: false` in manifest until owner review.

## UPDATE (global library — not applied)

All 25 original manifest slugs would update existing or create if missing:

- glass-partitions-open-plan-without-compromise
- pvd-coating-explained-durable-metal-finishes
- why-corten-steel-is-perfect-for-modern-facades
- pvd-partitions-materials-finishes-applications-cost-factors
- pvd-partition-price-in-india-what-determines-final-cost
- pvd-partitions-vs-powder-coated-metal-partitions
- how-to-select-metal-partition-for-living-room
- slim-profile-doors-hinged-sliding-telescopic-compared
- fluted-glass-slim-profile-doors-design-privacy-guide
- how-to-choose-luxury-main-entrance-door
- stainless-steel-glass-etched-entrance-doors-compared
- stainless-steel-railings-types-finishes-selection-guide
- glass-railings-staircases-balconies-planning-checklist
- interior-vs-exterior-railings-material-finish
- what-is-corten-steel-and-how-does-it-weather
- corten-steel-facades-design-drainage-weathering
- corten-steel-cladding-vs-conventional-painted-steel
- reduce-rust-run-off-staining-around-corten-steel
- pvd-door-handles-finishes-sizes-selection-guide
- select-pull-handle-length-for-main-door
- pvd-furniture-care-finishes-customization
- choosing-metal-coffee-table-luxury-interior
- pvd-finish-selection-guide-gold-rose-gold-champagne-black
- architects-specify-custom-architectural-metalwork
- drawing-to-installation-custom-metal-fabrication-process

Three published slugs are in `BlogContentImporter::PRESERVE_PUBLISHED_SLUGS` — status would remain published on apply.

## Flag categories

### Word count below 900 (expand before publish)

Most global and regional bodies flagged. International English pass added intro notes; parallel subagent may expand bodies in a follow-up pass. Only `india-pvd-partition-prices-materials-size-installation` meets 900+ words among regional drafts.

### Excerpt length (target 140–165 chars)

Several excerpts 1–15 chars short — adjust in manifest before publish.

### Missing service slug in database

- `corten-steel-facade` referenced on UK/UAE Corten regional drafts — stored for catalogue sync; live route is `/corten-steel`.

## Gate before apply

1. Owner confirms export claims (`owner-confirmation-required.md`)
2. Expand flagged articles or accept draft status
3. Fix excerpt lengths
4. Run `php artisan blog:import-content --force` only after plan approval

## Apply command (DO NOT RUN YET)

```bash
php artisan blog:import-content --force
```

Backup exports automatically to `storage/app/blog-backups/` unless `--no-backup` is passed.
