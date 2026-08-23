# Blog Hero Image Requirements

Articles imported via `blog:import-content` are **image-gated**: unsuitable or missing hero images force `draft` status regardless of manifest `status`.

## Accepted hero images

- Vyomika-hosted product or campaign photography (`vyomikaatelier.com`, `delhiduniya.com/vyomika/…`)
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

## Before publishing

1. Replace flagged heroes with relevant project or product photography
2. Write descriptive `hero_image_alt` (not title repetition alone)
3. Re-run `php artisan blog:import-content --dry-run --global-only` and confirm zero image flags for the article
4. Owner sign-off per `owner-confirmation-required.md` for export/regional copy

## Current image-gated drafts (dry-run v2)

See `dry-run-report-v2.md` — articles flagged for hero or word-count review remain draft until images and content gates pass.
