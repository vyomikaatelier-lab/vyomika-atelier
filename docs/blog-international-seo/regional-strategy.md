# Regional Strategy — Landing Pages & Content

**Status:** Proposed only. No regional routes are registered in `routes/web.php` as of 23 August 2026.

## Verified business capability (from site inspection)

| Region | Physical presence | Manufacturing | Delivery / projects | Commerce |
|---|---|---|---|---|
| **India** | Delhi studio (verified address in `config/legal.php`) | Yes — primary | Pan-India delivery (`config/site.php`, shipping policy) | INR / Razorpay checkout |
| **United Kingdom** | No office | India-based export **unconfirmed commercially** | UKCW 2025 exhibition (`config/about.php`); blog mentions FOB Mumbai — **owner confirm** | Quote via contact; no GBP checkout |
| **UAE** | No office | India-based export **unconfirmed** | No verified UAE projects in CMS | Quote via contact |
| **Wider Middle East** | No office | Same as UAE | Enquiry-only unless export verified | Contact only |

**Accurate positioning:** Vyomika Atelier is an **India-based design-led metal fabrication studio** (Delhi). International visibility targets architects, designers and developers who may specify Indian-fabricated metalwork for export or India-based projects — not local installation networks unless confirmed.

---

## Proposed regional hub structure

Build only after owner confirms export/process for each market.

```
/india/                          → India hub (domestic focus, INR quotation)
/india/pvd-partitions/           → Commercial: partitions + calculator CTA
/india/architectural-metalwork/  → Commercial: professionals + custom order

/uk/                             → UK hub (export/project partner positioning)
/uk/metal-room-dividers/         → Commercial: room dividers / partitions
/uk/architectural-metalwork/     → Commercial: bespoke metalwork export enquiry

/uae/                            → UAE hub (export/project partner positioning)
/uae/pvd-stainless-steel/        → Commercial: PVD finishes & partitions
/uae/architectural-metalwork/    → Commercial: villa/office metalwork enquiry
```

### Stub implementation note

If a minimal stub is needed for routing tests, use a single controller returning a draft Blade view with `noindex` until content is approved. **Do not** add to sitemap or hreflang until reviewed.

---

## Materially distinct content per regional page

Each regional URL must include unique sections — not city-name swaps:

| Section | India | UK | UAE |
|---|---|---|---|
| Business location statement | Delhi studio; Pan-India delivery | India manufacture; UK projects via export/local install partner | India manufacture; UAE projects via export/local install partner |
| Terminology | PVD partition, main entrance door, glass railing | Metal room divider, balustrade, slimline internal doors | PVD stainless steel, villa entrance doors, decorative screens |
| Currency / quote | INR; ₹ only with approved prices | GBP quotation on request; no fake GBP prices | AED quotation on request |
| Lead time | 3–4 weeks post-approval (verified) | Export + shipping — **confirm with owner** | Export + shipping — **confirm with owner** |
| Climate / design | Monsoon, dust, coastal India notes | UK weathering, drainage (no unverified Building Regs claims) | Heat, humidity, coastal salt — no Civil Defence claims |
| Projects | Indian project slugs from `/projects` | Only if verified export/UK-relevant | Only if verified UAE/Middle East |
| FAQs | GST, Pan-India logistics | Export crating, UK install responsibility | Shipping to GCC, local compliance boundaries |
| CTA | `/contact`, `/custom-order`, `/professionals` | `/contact` (export enquiry subject line) | `/contact` (UAE/GCC enquiry) |

---

## Explicitly not creating

- City “near me” pages (Delhi/Mumbai/London/Dubai local pack spam)
- Fake UK or UAE office addresses in schema or footer
- Separate Middle East country pages (Saudi, Qatar, etc.) without verified capability
- Duplicate blog copies per country for universal technical topics

---

## Content layers

| Layer | Count | Purpose |
|---|---|---|
| Global technical library | 25 articles | Universal specification guides (international English) |
| Regional commercial articles | 9 drafts | Price, climate, terminology where intent differs |
| Regional landing pages | 6 proposed | Transactional keywords |
| Existing service/shop pages | 15+ routes | Primary commercial canonicals |

---

## Rollout sequence (recommended)

1. **Phase 0 (current):** Documentation, global article internationalisation, regional drafts — no DB import, no regional routes.
2. **Phase 1:** Owner confirms export claims → publish India regional blog drafts → import global library dry-run reviewed.
3. **Phase 2:** Build `/india/` hub if domestic positioning needs separation from default homepage.
4. **Phase 3:** After export SOP documented → build `/uk/` and `/uae/` stubs with `noindex`, then index when content verified.
5. **Phase 4:** Implement hreflang (see `hreflang-architecture.md`) only when ≥2 genuine localized equivalents exist per URL set.

---

## Linking strategy

- Global blogs link to `/studio/*`, `/shop/*`, `/railings`, `/corten-steel` — not to unbuilt regional URLs until live.
- Regional drafts link to global technical articles for depth + to contact for quotes.
- Footer remains single India business address; optional text link “International enquiries” → `/contact`.
