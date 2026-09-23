# Search Intent Map — International SEO

Maps each intent type to page category and canonical URL. Commercial intents must land on service/collection/regional pages — not blog alone.

## Intent taxonomy

| Intent | User goal | Primary page types |
|---|---|---|
| **Informational** | Learn, compare, specify | Blog articles, FAQ sections |
| **Commercial investigation** | Evaluate options, pricing factors, materials | Blog + studio/shop pages |
| **Transactional** | Enquire, quote, buy | Studio, shop, railings, corten, regional landing, contact |

---

## Informational intents

| Query theme | Example keywords | Canonical URL | Supporting blog slug |
|---|---|---|---|
| What is PVD coating? | PVD coating, PVD finishes for metal | `/blog/pvd-coating-explained-durable-metal-finishes` | — |
| PVD vs powder coating | PVD partition vs powder coating | `/blog/pvd-partitions-vs-powder-coated-metal-partitions` | Links to `/studio/pvd-partitions` |
| What is Corten steel? | what is Corten steel | `/blog/what-is-corten-steel-and-how-does-it-weather` | Links to `/corten-steel` |
| Corten weathering & drainage | Corten rust staining, façade design | `/blog/corten-steel-facades-design-drainage-weathering`, `/blog/reduce-rust-run-off-staining-around-corten-steel` | — |
| How to choose glass partition | glass partitions, internal glass partition | `/blog/glass-partitions-open-plan-without-compromise` | `/studio/pvd-partitions` |
| Door system comparisons | slim profile doors, slimline internal glass doors | `/blog/slim-profile-doors-hinged-sliding-telescopic-compared` | `/studio/slim-profile-door-systems` |
| UK slimline doors | slimline internal glass doors | `/blog/uk-slimline-internal-glass-doors-hinged-sliding-fixed` | draft |
| Entrance door selection | luxury main entrance door | `/blog/how-to-choose-luxury-main-entrance-door` | `/studio/main-entrance-pvd-doors` |
| Railing planning | glass railings, glass balustrade | `/blog/glass-railings-staircases-balconies-planning-checklist` | `/railings` |
| Interior vs exterior railings | exterior stainless railing | `/blog/interior-vs-exterior-railings-material-finish` | `/railings` |
| PVD finish colours | PVD finish colours | `/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black` | — |
| Fabrication process | custom metal fabrication process | `/blog/drawing-to-installation-custom-metal-fabrication-process` | `/custom-order` |
| Architect specification | architectural metalwork | `/blog/architects-specify-custom-architectural-metalwork` | `/professionals` |
| UK Corten detailing | Corten steel cladding UK | `/blog/uk-corten-steel-cladding-weathering-drainage-detailing` | draft |
| UAE climate & PVD | PVD stainless steel UAE | `/blog/uae-pvd-stainless-steel-interiors-finishes-applications` | draft |
| UAE Corten conditions | Corten steel cladding UAE | `/blog/uae-corten-steel-heat-humidity-coastal-considerations` | draft |

---

## Commercial investigation intents

| Query theme | Example keywords | Primary URL | Blog support |
|---|---|---|---|
| PVD partition price (India) | PVD partition price in India | `/blog/pvd-partition-price-in-india-what-determines-final-cost` | `/studio/pvd-partitions`, `/services` calculator |
| India partition pricing (regional) | PVD partition prices materials size | `/blog/india-pvd-partition-prices-materials-size-installation` | draft |
| Glass railing price (India) | glass railing price in India | `/blog/india-glass-railing-price-quotation-factors` | draft |
| Best partition material | metal vs glass partitions | `/blog/pvd-partitions-vs-powder-coated-metal-partitions`, `/blog/glass-partitions-open-plan-without-compromise` | `/studio/pvd-partitions` |
| Living room partition | metal partition for living room | `/blog/how-to-select-metal-partition-for-living-room` | India draft: `/blog/india-choosing-metal-partitions-homes-apartments` |
| UK room dividers | metal room dividers UK | `/blog/uk-metal-room-dividers-interiors-specification-guide` | proposed `/uk/metal-room-dividers/` |
| UAE office partitions | office glass partitions Dubai | `/blog/uae-glass-metal-partitions-dubai-offices-villas` | proposed `/uae/architectural-metalwork/` |
| PVD partition materials & cost factors | PVD partition | `/blog/pvd-partitions-materials-finishes-applications-cost-factors` | `/studio/pvd-partitions` |
| Corten vs painted steel | Corten steel cladding | `/blog/corten-steel-cladding-vs-conventional-painted-steel` | `/corten-steel` |
| Pull handle sizing | main door pull handle | `/blog/select-pull-handle-length-for-main-door` | `/shop/door-handles` |
| PVD furniture care | PVD furniture | `/blog/pvd-furniture-care-finishes-customization` | `/shop/bespoke-metal-furniture` |
| Luxury coffee table | luxury metal coffee table | `/blog/choosing-metal-coffee-table-luxury-interior` | `/shop/coffee-tables` |

---

## Transactional / commercial intents

| Query theme | Example keywords | Primary URL | Notes |
|---|---|---|---|
| PVD partition manufacturer | PVD partition manufacturer, Delhi | `/studio/pvd-partitions` | India-based studio; no fake branches |
| Request partition quotation | custom order, contact | `/custom-order`, `/contact` | |
| Bespoke metal room dividers UK | bespoke metal room dividers | `/uk/metal-room-dividers/` | **Proposed** — export enquiry |
| Architectural metalwork supplier UK | bespoke architectural metalwork UK | `/uk/architectural-metalwork/` | **Proposed** |
| Glass partitions Dubai | glass partitions Dubai | `/uae/architectural-metalwork/` or contact | **Proposed** |
| PVD stainless steel Dubai | PVD stainless steel Dubai | `/uae/pvd-stainless-steel/` | **Proposed** |
| Stainless fabrication Dubai | stainless steel fabrication Dubai | `/uae/architectural-metalwork/` | **Proposed** |
| Corten façade supplier | Corten steel façade India/UK/UAE | `/corten-steel` | Enquiry-only |
| Railings quotation | stainless steel railing, metal balustrades | `/railings` | Quotation form |
| PVD door handles buy | PVD door handles | `/shop/door-handles` | INR checkout |
| Bespoke metal furniture | bespoke metal furniture | `/shop/bespoke-metal-furniture` | |
| Architect / trade partnership | architectural metalwork India | `/professionals` | |
| Middle East export enquiry | architectural metalwork Middle East | `/contact` | No thin country pages |

---

## Cluster → route summary (from `routes/web.php`)

| Cluster | Commercial URLs | Blog pillar |
|---|---|---|
| PVD Partitions | `/studio/pvd-partitions`, `/services` | `glass-partitions-open-plan-without-compromise` |
| Slim Profile / Entrance Doors | `/studio/slim-profile-door-systems`, `/studio/main-entrance-pvd-doors` | `slim-profile-doors-hinged-sliding-telescopic-compared` |
| Railings | `/railings` | `stainless-steel-railings-types-finishes-selection-guide` |
| Corten Steel | `/corten-steel` | `why-corten-steel-is-perfect-for-modern-facades` |
| PVD Hardware / Furniture | `/shop/door-handles`, `/shop/bespoke-metal-furniture`, `/shop/coffee-tables` | `pvd-coating-explained-durable-metal-finishes` |
| Architectural Metalwork | `/professionals`, `/custom-order`, `/projects` | `architects-specify-custom-architectural-metalwork` |

---

## Rules

1. Do not expect blog-only URLs to rank for transactional manufacturer queries without strong service pages.
2. Regional commercial keywords map to **proposed** `/india/`, `/uk/`, `/uae/` paths — not live until built and verified.
3. One canonical URL per primary intent (see also `docs/seo/keyword-to-url-map.md` for India baseline).
4. Comparison and cost articles link upward to studio/shop — not duplicate commercial pages.
