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
| 2 | `Elegant_modern_living_room_divider-…jpg` | `glass-partitions-open-plan` | **768×1024** (portrait) | Two gold PVD-framed fluted-glass room dividers in a luxury open-plan living room with herringbone floor | Generic stock-style filename; no Vyomika branding; uncertain whether real project photo vs stock/render — **not confirmed Vyomika work** | **OWNER VERIFY** — subject match is strong but provenance and width both fail production bar | "Gold PVD-framed fluted glass room dividers separating a luxury open-plan living room" |
| 3 | `IMG_0285-f9cda93e-…jpg` | `pvd-coating-explained` | **1024×682** | Brushed gold/brass PVD metal plaque stencilled **LED PROFILE PVD PARTITION** on reflective surface; blurred partition frames in background | **Low concern** — camera-style filename, on-product Vyomika labelling, appears genuine studio/product photography | **USE** — best candidate; copied to self-hosted path (width flagged for future ≥1200px upgrade) | "Close-up of a brushed gold PVD-coated metal plaque stencilled LED PROFILE PVD PARTITION, reflected on a glossy surface" |

---

## Actions taken (approved only)

| Slug | Action |
|---|---|
| `pvd-coating-explained` | Copied source JPEG → `public/images/blog/heroes/pvd-coating-explained-hero.jpg`; manifest `image` + `hero_image_alt` updated; **no WebP derivatives** (source &lt;1200px wide) |
| `glass-partitions-open-plan` | No file copy — awaiting owner provenance confirmation and wider asset |
| `corten-steel-modern-facades` | **Rejected** — Grok watermarked candidate `m4wAc-e0a938ce-1199-4a72-bdf5-1083476eac16.jpg` not copied; manifest unchanged (still delhiduniya placeholder); **OWNER IMAGE REQUIRED** |

---

## Width flag (all three)

| Slug | Width | Min hero bar | Derivatives |
|---|---:|---|---|
| `corten-steel-modern-facades` | 1024px | 1200px | None generated |
| `glass-partitions-open-plan` | 768px | 1200px | None generated |
| `pvd-coating-explained` | 1024px | 1200px | None generated — source JPEG only |

Recommend owner re-shoot or export originals at **≥1200px wide** (ideally 1600–2400px) before publishing heroes at full quality.

---

## Per-post recommendations

### 1. `corten-steel-modern-facades` — **REJECTED (Grok candidate)**

**Rejected file:** `assets/…/m4wAc-e0a938ce-1199-4a72-bdf5-1083476eac16.jpg`

- **Owner instruction (2026-08-23):** Do not use Grok watermarked image. Not copied to `public/`. Not added to manifest.
- Grok watermark — unsuitable for production regardless of subject.
- Subject is a garden pavilion, not a modern building façade as the article title implies.
- Below minimum width (1024 &lt; 1200).
- **Status:** **OWNER IMAGE REQUIRED** — provide Vyomika Corten façade or entrance-screen photography (e.g. `/projects/corten-entrance-screen`), watermark-free, ≥1200px wide.

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
