# Hreflang Architecture

**Status:** Planned only. **Do not implement hreflang tags until genuine localized page equivalents exist.**

## When to implement

Hreflang is required when the same content intent is served at **distinct URLs** with **material localization** — not when a single English article merely mentions another country.

| Condition | Action |
|---|---|
| Only global `/blog/{slug}` exists | No hreflang for that article |
| Global + regional article pair (e.g. global glass partitions + UK room dividers guide) | Optional `en` + regional only if both are indexable equivalents — usually **not** equivalents (different intent) |
| Regional landing `/uk/` + default `/` both indexable with overlapping commercial intent | Consider hreflang between hubs after owner approval |
| Regional article is standalone (India pricing) | Self-referencing canonical only; no hreflang unless true EN-IN vs EN default pair |

---

## Target locale codes

| Market | Hreflang | Language tag notes |
|---|---|---|
| India | `en-IN` | Indian English |
| United Kingdom | `en-GB` | **Not** `en-UK` |
| UAE | `en-AE` | English for UAE (primary web language for B2B specifiers) |
| Default / international fallback | `x-default` | Typically homepage or primary global URL |

No `ar-AE` planned unless Arabic pages are produced with full localization.

---

## Proposed page groups (future)

### Group A — Regional hubs (when built)

```html
<!-- Example: homepage / hub equivalents -->
<link rel="alternate" hreflang="en-IN" href="https://www.vyomikaatelier.com/india/" />
<link rel="alternate" hreflang="en-GB" href="https://www.vyomikaatelier.com/uk/" />
<link rel="alternate" hreflang="en-AE" href="https://www.vyomikaatelier.com/uae/" />
<link rel="alternate" hreflang="x-default" href="https://www.vyomikaatelier.com/" />
```

Each hub page must reciprocate all four tags with **absolute URLs**.

### Group B — True localized equivalents (rare)

Only if two articles are deliberate translations/localizations of the same content:

Example (hypothetical — **not current**):

| URL | Hreflang |
|---|---|
| `/blog/pvd-partition-price-in-india-what-determines-final-cost` | `en-IN` |
| `/blog/uk-metal-partition-pricing-factors` | `en-GB` |

Current plan: **these are different articles, not equivalents** — no cross-hreflang.

---

## Canonical rules

| Scenario | Canonical |
|---|---|
| Regional landing `/uk/metal-room-dividers/` | Self-referencing |
| Global blog mentioning UK | Self-referencing `/blog/{slug}` — do **not** canonical to India |
| Draft regional blog (`status: draft`) | Noindex or unpublished — exclude from hreflang |
| Parameterized URLs | Canonical to clean URL |

**Never** canonicalize all regional pages to the India homepage.

---

## Sitemap

When hreflang goes live:

1. Add all localized URLs to `sitemap.xml` generation (`SitemapController`).
2. Optionally use sitemap `xhtml:link` alternates if the generator supports it; otherwise HTML head tags are sufficient.
3. Exclude `noindex` stubs and drafts.

---

## Implementation checklist (Laravel)

- [ ] Add `PageSeo` or layout partial for hreflang only when `config('seo.hreflang.enabled')` and page has defined alternates
- [ ] Store alternates in CMS or config array keyed by page ID — not hardcoded per blog post
- [ ] Unit test: every alternate URL appears reciprocally
- [ ] Validate with Google Search Console international targeting report

---

## Anti-patterns (forbidden)

- Hreflang on blog posts that only mention “Dubai” in one paragraph
- `en-UK` locale code
- IP-based auto-redirect for Googlebot or users
- Hreflang pointing to 404 or redirect chains
- Single-language site with fake regional URLs

---

## User-facing region selector

If multiple regional hubs launch:

- Visible footer or header selector: India | UK | UAE | International
- Persists choice in session/cookie; does **not** force redirect on first visit
- Default: international (`x-default`) or India based on product strategy — **owner decision**

---

## Current recommendation

**No hreflang output in production** until at least one regional hub pair is live, reviewed, and indexed. Document-only phase is correct for August 2026.
