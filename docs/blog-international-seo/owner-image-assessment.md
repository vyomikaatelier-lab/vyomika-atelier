# Owner Hero Image Assessment — Priority Blog Posts

**Project:** Vyomika Atelier Laravel  
**Branch:** `hotfix/production-regressions`  
**Date:** 2026-08-23 (owner confirmations applied)  
**Scope:** Local assessment only — no production deploy or import.

---

## Owner confirmations (2026-08-23)

| Slug | Owner decision | Provenance | Self-hosted path | WebP |
|---|---|---|---|---|
| `glass-partitions-open-plan` | **APPROVED** — genuine Vyomika work | Living-room gold divider photo; may identify as real Vyomika work; no invented client/city/dimensions/price/project name | `/images/blog/heroes/glass-partitions-open-plan-hero.jpg` | **No** — 768×1024 portrait below 1200px bar |
| `pvd-coating-explained` | **APPROVED** — IMG_0285 authorized for website | Vyomika studio/product photography (on-product labelling) | `/images/blog/heroes/pvd-coating-explained-hero.jpg` | **No** — 1024×682; do not upscale |
| `corten-steel-modern-facades` | **APPROVED** — OpenAI-generated editorial visual | Representative visual **not** a completed Vyomika project or photographed Indian building; alt must not imply Vyomika site work | `/images/blog/heroes/corten-steel-modern-facades-hero.jpg` | **No** — 1024×576; do not upscale |

**Rejected (prior):** Grok watermark candidate `m4wAc-e0a938ce-…jpg`; third-party hotlinks for these three slugs.

**Originals preserved:** `public/images/blog/heroes/originals/{slug}-source.jpg` (unchanged source copies).

**Manifest:** All three entries `status => published` for new-record import semantics. `BlogContentImporter::stripPreservedFieldsFromUpdate()` preserves existing DB `status` and `published_at` on apply — manifest dates do not overwrite live production timestamps.

---

## Final mapping

| Slug | Dimensions | File size | Alt | OG image |
|---|---:|---:|---|---|
| `glass-partitions-open-plan` | 768×1024 JPEG | 163 KB | Gold-finished metal and glass partition installed in a Vyomika Atelier living-room project | Same as hero |
| `pvd-coating-explained` | 1024×682 JPEG | 112 KB | Close-up of a brushed gold PVD-coated metal plaque stencilled LED PROFILE PVD PARTITION, reflected on a glossy surface | Same as hero |
| `corten-steel-modern-facades` | 1024×576 JPEG | 134 KB | Representative contemporary Indian building visualised with weathered Corten steel façade panels and perforated screens | Same as hero |

---

## Width / derivative policy

Production guidance recommends ≥1200px wide for blog index cards and WebP derivatives. All three approved sources are below that threshold — **JPEG only, no artificial upscaling**. Recommend owner re-shoot or export ≥1200px originals before generating WebP when higher-resolution sources become available.

---

## Per-post notes

### `glass-partitions-open-plan` — **APPROVED (Vyomika project photo)**

- Source: owner-confirmed living-room gold PVD glass divider installation.
- Portrait orientation (768×1024) — acceptable for article hero; not suitable for wide card crops without a landscape reshoot.
- Alt describes Vyomika work without invented project metadata.

### `pvd-coating-explained` — **APPROVED (Vyomika product photo)**

- IMG_0285 verified at self-hosted path; no re-copy required.
- Genuine on-product labelling; best provenance of the three.

### `corten-steel-modern-facades` — **APPROVED (editorial AI visual)**

- OpenAI-generated, authorized for editorial use only.
- Alt explicitly frames as representative visualisation — do not claim as Vyomika completed project in body or captions.

---

## Related

See also: [production-readiness-decision.md](./production-readiness-decision.md), [image-requirements.md](./image-requirements.md)
