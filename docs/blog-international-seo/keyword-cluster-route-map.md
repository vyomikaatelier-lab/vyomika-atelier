# Keyword Cluster → Route Map

Maps primary keyword clusters to **existing** `routes/web.php` destinations. Regional landing URLs are proposed only (see `regional-strategy.md`).

## India

| Cluster / keyword focus | Canonical route | Route name |
|---|---|---|
| PVD partition, manufacturer, price | `/studio/pvd-partitions` | `studio.show` |
| Glass / stainless partition | `/studio/pvd-partitions` | `studio.show` |
| Metal partition living room | `/blog/how-to-select-metal-partition-for-living-room` | `blog.show` |
| Slim profile / sliding doors | `/studio/slim-profile-door-systems` | `studio.show` |
| PVD main entrance door | `/studio/main-entrance-pvd-doors` | `studio.show` |
| PVD door handles | `/shop/door-handles` | `shop.show` |
| Stainless / glass railing | `/railings` | `railings.index` |
| Corten façade / cladding | `/corten-steel` | `corten-steel.show` |
| Custom fabrication Delhi | `/custom-order` | `leads.create` |
| Architectural metalwork | `/professionals` | `professionals.index` |
| Bespoke metal furniture | `/shop/bespoke-metal-furniture` | `shop.show` |
| Partition calculator | `/services` | `services.index` |

## United Kingdom

| Cluster / keyword focus | Canonical route | Notes |
|---|---|---|
| Metal room dividers UK | `/uk/metal-room-dividers/` | **Proposed** |
| Glass partition walls UK | `/studio/pvd-partitions` + global blog | Export enquiry |
| Slimline internal glass doors | `/studio/slim-profile-door-systems` | Global blog + UK draft |
| Bespoke architectural metalwork UK | `/uk/architectural-metalwork/` | **Proposed** |
| Metal balustrades UK | `/railings` | No UK install claim |
| Corten cladding UK | `/corten-steel` + UK blog draft | |
| PVD coating (informational) | `/blog/pvd-coating-explained-durable-metal-finishes` | Global |
| Trade / export enquiry | `/contact` | `contact.index` |

## UAE

| Cluster / keyword focus | Canonical route | Notes |
|---|---|---|
| PVD stainless steel Dubai/UAE | `/uae/pvd-stainless-steel/` | **Proposed** |
| Glass / office partitions Dubai | `/studio/pvd-partitions` + UAE blog draft | |
| Villa entrance doors | `/studio/main-entrance-pvd-doors` | |
| Architectural metalwork Dubai | `/uae/architectural-metalwork/` | **Proposed** |
| Decorative metal screens | `/studio/pvd-partitions` | Laser-cut screens |
| Corten façade UAE | `/corten-steel` + UAE blog draft | |
| Custom metal furniture | `/shop/bespoke-metal-furniture` | Quote only |

## Wider Middle East

| Cluster | Canonical route |
|---|---|
| Architectural metalwork Middle East | `/contact` |
| PVD supplier Middle East | `/contact` or `/professionals` |
| Corten façade Middle East | `/corten-steel` |

## Blog hub

| Intent | Route |
|---|---|
| Expert resources / all clusters | `/blog` (`blog.index`) |
| Individual articles | `/blog/{slug}` (`blog.show`) |

## Redirects relevant to SEO

| Legacy | Target |
|---|---|
| `/services/partitions` | `/studio/pvd-partitions` |
| `/services/corten-steel-facade` | `/corten-steel` |
| `/services/slim-profile-door-system` | `/studio/slim-profile-door-systems` |

## Explicitly not mapped (forbidden)

- `/pvd-partitions-delhi/`, `/glass-partitions-mumbai/`, `/metal-room-dividers-london/`, `/partitions-dubai-near-me/` — no city near-me pages
