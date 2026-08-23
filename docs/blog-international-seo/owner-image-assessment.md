# Owner Hero Image Assessment — Priority Blog Posts

**Project:** Vyomika Atelier Laravel  
**Branch:** `hotfix/production-regressions`  
**Date:** 2026-08-23  
**Scope:** Local assessment only — no production deploy or import.

---

## Summary

Three candidate hero images were reviewed for the priority draft articles `glass-partitions-open-plan`, `pvd-coating-explained`, and `corten-steel-modern-facades`. Dimensions were measured with PHP `getimagesize()`. Production rules require ≥1200px width for hero derivatives; all three candidates are below that threshold.

| # | Source file | Proposed slug | Dimensions | Subject (visible) | Provenance concern | Recommendation | Alt text proposal |
|---|---|---|---:|---|---|---|---|
| 1 | `m4wAc-e0a938ce-1199-4a72-bdf5-1083476eac16.jpg` (Corten pavilion) | `corten-steel-modern-facades` | **1024×687** | Weathering-steel garden pavilion / sculpture in landscaped setting — **not a building façade** | **Grok watermark visible** — AI/Grok-platform output; no Vyomika ownership or licence | **REJECTED** — owner instruction 2026-08-23; do not copy, do not add to manifest | **OWNER IMAGE REQUIRED** — supply Vyomika Corten façade or entrance-screen photography (≥1200px, watermark-free) |
| 1b | `corten-steel-modern-facades-india-hero.png-…jpg` (2026-08-23 upload) | `corten-steel-modern-facades` | **1024×576** | Modern two-storey building with weathered Corten cladding, concrete volumes, perforated Corten sunscreens and courtyard planting — **strong façade subject match** | SEO-style filename; polished architectural look — **not verified as Vyomika project** in CMS/projects (`corten-entrance-screen` uses Unsplash placeholders); no on-site branding visible | **USE WITH CAUTION** — copied for manifest mapping; confirm licence/provenance before publish | "Modern building with weathered Corten steel cladding panels, concrete volumes and perforated Corten sunscreens in a paved courtyard" |
| 2 | `Elegant_modern_living_room_divider-…jpg` | `glass-partitions-open-plan` | **768×1024** (portrait) | Two gold PVD-framed fluted-glass room dividers in a luxury open-plan living room with herringbone floor | Generic stock-style filename; no Vyomika branding; uncertain whether real project photo vs stock/render — **not confirmed Vyomika work** | **OWNER VERIFY** — subject match is strong but provenance and width both fail production bar | "Gold PVD-framed fluted glass room dividers separating a luxury open-plan living room" |
| 3 | `IMG_0285-f9cda93e-…jpg` | `pvd-coating-explained` | **1024×682** | Brushed gold/brass PVD metal plaque stencilled **LED PROFILE PVD PARTITION** on reflective surface; blurred partition frames in background | **Low concern** — camera-style filename, on-product Vyomika labelling, appears genuine studio/product photography | **USE** — best candidate; copied to self-hosted path (width flagged for future ≥1200px upgrade) | "Close-up of a brushed gold PVD-coated metal plaque stencilled LED PROFILE PVD PARTITION, reflected on a glossy surface" |

---

## Actions taken (approved only)

| Slug | Action |
|---|---|
| `pvd-coating-explained` | Copied source JPEG → `public/images/blog/heroes/pvd-coating-explained-hero.jpg`; manifest `image` + `hero_image_alt` updated; **no WebP derivatives** (source &lt;1200px wide) |
| `glass-partitions-open-plan` | No file copy — awaiting owner provenance confirmation and wider asset |
| `corten-steel-modern-facades` | Grok candidate rejected; new owner upload copied → `public/images/blog/heroes/corten-steel-modern-facades-hero.jpg`; manifest `image` + `hero_image_alt` updated; **no WebP** (1024px &lt; 1200); article stays **draft** — **OWNER VERIFY provenance** before publish |

---

## Width flag (all three)

| Slug | Width | Min hero bar | Derivatives |
|---|---:|---|---|
| `corten-steel-modern-facades` | 1024px (new candidate) | 1200px | None generated — source JPEG only |
| `glass-partitions-open-plan` | 768px | 1200px | None generated |
| `pvd-coating-explained` | 1024px | 1200px | None generated — source JPEG only |

Recommend owner re-shoot or export originals at **≥1200px wide** (ideally 1600–2400px) before publishing heroes at full quality.

---

## Per-post recommendations

### 1. `corten-steel-modern-facades` — **USE WITH CAUTION (new owner upload)**

**Rejected file (prior):** `assets/…/m4wAc-e0a938ce-1199-4a72-bdf5-1083476eac16.jpg` — Grok watermark; garden pavilion not a façade; not used.

**New candidate (2026-08-23):** `assets/…/corten-steel-modern-facades-india-hero.png-7cbaa89a-52a0-482c-a5b6-d05296182f9d.jpg`

| Check | Result |
|---|---|
| Dimensions | **1024×576** JPEG — below 1200px hero bar; no WebP derivatives |
| Subject match | **Pass** — Corten-clad modern building, concrete, perforated screens; correct topic |
| Watermarks | **Pass** — no Grok, AI platform, or stock-site logos visible |
| Provenance | **OWNER VERIFY** — SEO-style filename; not matched to any Vyomika project photo in CMS; may be stock, render, or third-party architecture photography |
| Vyomika claim | **Do not claim** as Vyomika project in alt or body until owner confirms source/licence |

- Self-hosted at `/images/blog/heroes/corten-steel-modern-facades-hero.jpg`; manifest updated; replaces incorrect delhiduniya PVD placeholder.
- Article remains **`draft`** until owner confirms provenance and optionally supplies ≥1200px version.
- **Next step:** Confirm this is owner-licensed photography (or replace with verified Vyomika Corten façade / entrance-screen site photography).

### 2. `glass-partitions-open-plan` — **Hold (owner verify provenance + wider asset)**

- Strong visual match for open-plan glass partition topic.
- Portrait 768px width is unsuitable for blog hero without a landscape crop or larger source.
- Filename pattern suggests stock or third-party origin — confirm this is a Vyomika-fabricated installation before use.
- **Next step:** Confirm project ownership; supply landscape ≥1200px photo of a Vyomika PVD glass partition install.

### 3. `pvd-coating-explained` — **Approved for manifest mapping (draft remains draft)**

- Genuine Vyomika product photography with on-image product labelling.
- Self-hosted at `/images/blog/heroes/pvd-coating-explained-hero.jpg`.
- Article stays `draft` until owner confirms publish readiness and optionally supplies ≥1200px version for WebP derivatives.
- **Next step:** Optional higher-resolution reshoot of the same plaque/profile for derivative generation.

---

## Related

See also: [production-readiness-decision.md](./production-readiness-decision.md)
