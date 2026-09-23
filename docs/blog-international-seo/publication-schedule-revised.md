# Revised Publication Schedule

Incorporates international SEO strategy: 25 global articles (international English) + 9 regional drafts (unpublished until owner review). **No database import until this plan is approved.**

## Legend

| Status | Meaning |
|---|---|
| **published** | Live or preserved on import |
| **scheduled** | Dated future publish |
| **draft** | Hold — review before publish |
| **regional-draft** | New international drafts — owner + export confirm |

---

## Phase 1 — Global library (25 articles)

Already in `database/content/blog/manifest.php`. After international English revision:

| Week | Date | Slug | Status | Market |
|---|---|---|---|---|
| — | 2026-06-15 | glass-partitions-open-plan-without-compromise | published | Global |
| — | 2026-06-10 | pvd-coating-explained-durable-metal-finishes | published | Global |
| — | 2026-06-05 | why-corten-steel-is-perfect-for-modern-facades | published | Global |
| W35 | 2026-08-26 | pvd-partitions-materials-finishes-applications-cost-factors | scheduled | Global |
| W35 | 2026-08-29 | pvd-partition-price-in-india-what-determines-final-cost | scheduled | India-focused |
| W36 | 2026-09-02 | pvd-partitions-vs-powder-coated-metal-partitions | scheduled | Global |
| W36 | 2026-09-05 | how-to-select-metal-partition-for-living-room | scheduled | Global (India examples) |
| W37 | 2026-09-09 | slim-profile-doors-hinged-sliding-telescopic-compared | scheduled | Global |
| W37 | 2026-09-12 | fluted-glass-slim-profile-doors-design-privacy-guide | scheduled | Global |
| W38 | 2026-09-16 | how-to-choose-luxury-main-entrance-door | scheduled | Global |
| W38 | 2026-09-19 | stainless-steel-glass-etched-entrance-doors-compared | scheduled | Global |
| W39 | 2026-09-23 | stainless-steel-railings-types-finishes-selection-guide | scheduled | Global |
| W39 | 2026-09-26 | glass-railings-staircases-balconies-planning-checklist | scheduled | Global |
| W40 | 2026-09-30 | interior-vs-exterior-railings-material-finish | scheduled | Global |
| W40 | 2026-10-03 | what-is-corten-steel-and-how-does-it-weather | scheduled | Global |
| W41 | 2026-10-07 | corten-steel-facades-design-drainage-weathering | scheduled | Global |
| W41 | 2026-10-10 | corten-steel-cladding-vs-conventional-painted-steel | scheduled | Global |
| W42 | 2026-10-14 | reduce-rust-run-off-staining-around-corten-steel | scheduled | Global |
| Hold | TBC | pvd-door-handles-finishes-sizes-selection-guide | draft | Global |
| Hold | TBC | select-pull-handle-length-for-main-door | draft | Global |
| Hold | TBC | pvd-furniture-care-finishes-customization | draft | Global |
| Hold | TBC | choosing-metal-coffee-table-luxury-interior | draft | Global |
| Hold | TBC | pvd-finish-selection-guide-gold-rose-gold-champagne-black | draft | Global |
| Hold | TBC | architects-specify-custom-architectural-metalwork | draft | Global |
| Hold | TBC | drawing-to-installation-custom-metal-fabrication-process | draft | Global |

**Cadence:** 1 article every 3–4 days while scheduled queue runs; drafts after queue completes.

---

## Phase 2 — Regional drafts (9 articles) — DO NOT PUBLISH YET

Add to manifest as `status: draft`, `locale` metadata. Publish only after owner confirmation (see `owner-confirmation-required.md`).

| # | Slug | Title | Target | Earliest publish |
|---|---|---|---|---|
| 1 | india-pvd-partition-prices-materials-size-installation | PVD Partition Prices in India: Materials, Size and Installation Factors | IN | After owner confirms pricing approach |
| 2 | india-glass-railing-price-quotation-factors | Glass Railing Price in India: What Affects a Project Quotation? | IN | After owner confirms pricing approach |
| 3 | india-choosing-metal-partitions-homes-apartments | Choosing Metal Partitions for Indian Homes and Apartments | IN | After domestic positioning review |
| 4 | uk-metal-room-dividers-interiors-specification-guide | Metal Room Dividers for UK Interiors: Design and Specification Guide | UK | After export capability confirmed |
| 5 | uk-slimline-internal-glass-doors-hinged-sliding-fixed | Slimline Internal Glass Doors: Hinged, Sliding or Fixed? | UK | After export capability confirmed |
| 6 | uk-corten-steel-cladding-weathering-drainage-detailing | Corten Steel Cladding in the UK: Weathering, Drainage and Detailing | UK | After export + compliance review |
| 7 | uae-pvd-stainless-steel-interiors-finishes-applications | PVD Stainless Steel for UAE Interiors: Finishes and Applications | UAE | After export/GCC capability confirmed |
| 8 | uae-glass-metal-partitions-dubai-offices-villas | Glass and Metal Partitions for Dubai Offices and Villas | UAE | After export/GCC capability confirmed |
| 9 | uae-corten-steel-heat-humidity-coastal-considerations | Corten Steel in UAE Conditions: Heat, Humidity and Coastal Considerations | UAE | After export/GCC capability confirmed |

**Suggested cadence after approval:** 1 regional article per 2 weeks, interleaved with remaining global drafts — avoid publishing all 9 in one week.

---

## Phase 3 — Regional landing pages (not scheduled)

Build only after Phase 2 copy approved:

- `/india/` — optional if homepage already serves India
- `/uk/`, `/uae/` hubs — after export SOP
- Supporting commercial paths per `regional-strategy.md`

---

## Import gating

| Step | Command | When |
|---|---|---|
| Dry-run | `php artisan blog:import-content --dry-run` | **Now** — document only |
| Apply | `php artisan blog:import-content --force` | After owner reviews sections 1–11 of final output |
| Regional apply | Same command after manifest includes regional drafts | After export/pricing sign-off |

---

## Dependencies

1. Owner sign-off on export claims (items 1–6 in owner-confirmation doc)
2. Hero images: replace Unsplash placeholders flagged by importer
3. Related product/service slugs sync with catalogue
4. International English pass on 25 global bodies complete
5. No hreflang until regional hubs live

---

## 90-day outlook (international)

| Period | Focus |
|---|---|
| Days 1–14 | Approve plan; dry-run; publish scheduled global queue |
| Days 15–45 | Release global drafts; monitor GSC by country |
| Days 46–60 | If export confirmed: publish 3 India regional drafts |
| Days 61–90 | If UK/UAE confirmed: publish regional drafts + build `/uk/` `/uae/` stubs |

No city pages. No fake local schema. No invented search volumes in reporting.
