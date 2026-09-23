# Blog Hero Image Requirements

Articles imported via `blog:import-content` are **image-gated**: unsuitable or missing hero images force `draft` status regardless of manifest `status`.

## Accepted hero images

- Vyomika-hosted product or campaign photography (`vyomikaatelier.com`)
- Project-specific photography with accurate `hero_image_alt` text
- Minimum practical size: 1200px wide JPEG/WebP for blog index cards

## Rejected (placeholder / unsuitable)

The importer flags and downgrades to draft when `image` matches any of:

| Pattern | Reason |
|---|---|
| Empty / null | No hero assigned |
| `unsplash.com` | Stock placeholder — not brand photography |
| `placeholder` | Generic filler |
| `/images/exhibitions/` | Event booth assets, not article-specific |
| `.svg` | Vector placeholders |
| Grok / AI watermarks | Platform-generated output — no confirmed licence |

## Rejected owner candidates (2026-08-23)

| File | Slug | Reason |
|---|---|---|
| `m4wAc-e0a938ce-1199-4a72-bdf5-1083476eac16.jpg` | `corten-steel-modern-facades` | Grok watermark; not copied to `public/` |
| Third-party hotlinks | `glass-partitions-open-plan`, `pvd-coating-explained`, `corten-steel-modern-facades` | Replaced with self-hosted heroes |

**Owner-approved heroes (2026-08-23):** see [owner-image-assessment.md](./owner-image-assessment.md) — three pillar slugs self-hosted under `/images/blog/heroes/`; JPEG only (sources &lt;1200px wide).

## Before publishing

1. Replace flagged heroes with relevant project or product photography
2. Write descriptive `hero_image_alt` (not title repetition alone)
3. Re-run `php artisan blog:import-content --dry-run --global-only` and confirm zero image flags for the article
4. Owner sign-off per `owner-confirmation-required.md` for export/regional copy

## Current image-gated drafts (dry-run v2)

See `dry-run-report-v2.md` — articles flagged for hero or word-count review remain draft until images and content gates pass.
