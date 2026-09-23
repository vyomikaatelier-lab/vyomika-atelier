# Staging Release Report — Blog Import (Global Only)

**Date:** 23 August 2026  
**Branch:** `hotfix/production-regressions` @ `31312a7`  
**Environment:** **LOCAL SIMULATION — not live staging**  
**Production:** NOT touched (no connect, no import, no deploy)

---

## Executive decision

**READY FOR PRODUCTION REVIEW** — with the caveat that no Hostinger staging subdomain or SSH staging path was found in repo docs; full import safety was validated locally against a production-like SQLite database seeded with 25 global manifest slugs (3 published pillars + 22 drafts).

---

## 1. Staging deploy status

| Check | Result |
|---|---|
| `.env.staging` | Not present |
| Staging URL in deploy docs | Not documented (only production Hostinger checklist in `DEPLOY.md`) |
| Staging SSH from this environment | Not available |
| Action taken | Local simulation: `migrate:fresh --seed` → seed 25 manifest slugs → dry-run → apply with backup → HTTP verification |

**Blocker documented:** Live staging deploy deferred until owner provides staging host/path. No production paths were used.

---

## 2. Pre-import manifest fix

| Requirement | Status |
|---|---|
| 3 pillar articles `published` | ✅ `glass-partitions-open-plan`, `pvd-coating-explained`, `corten-steel-modern-facades` |
| 22 other global articles `draft` | ✅ All 22 set to `draft` |
| 0 `scheduled` global articles | ✅ No `scheduled` entries in manifest (corrected on branch prior to this run; no uncommitted diff) |
| 9 regional `import_eligible: false` | ✅ Excluded from `--global-only` |

---

## 3. Backup before import

Mandatory backup verified on apply:

```
D:\VYOMIKA ATELIER\storage\app\blog-backups\blog-posts-2026-08-23_230549.json
```

Backup contains full `blog_posts` table export (38 rows in simulation DB including legacy seed catalog posts).

---

## 4. Slug preservation (pre-apply)

Compared simulation DB slugs against `BlogContentImporter::LEGACY_SLUG_MAP`:

| Live slug (must exist) | Legacy slug (must NOT exist) | Result |
|---|---|---|
| `glass-partitions-open-plan` | `glass-partitions-open-plan-without-compromise` | ✅ Live present; legacy absent |
| `pvd-coating-explained` | `pvd-coating-explained-durable-metal-finishes` | ✅ Live present; legacy absent |
| `corten-steel-modern-facades` | `why-corten-steel-is-perfect-for-modern-facades` | ✅ Live present; legacy absent |

Importer would **UPDATE** all 25 existing slugs — **0 CREATE** actions. No URL changes for pillars.

---

## 5. Publication safety

| Metric | Expected | Actual (global manifest slugs) |
|---|---|---|
| Published | 3 | 3 |
| Draft | 22 | 22 |
| Regional imported | 0 | 0 |
| Pillar `published_at` preserved | Yes | ✅ All three dates unchanged |

| Pillar slug | `published_at` after import |
|---|---|
| `glass-partitions-open-plan` | `2017-11-03 09:41:22` |
| `pvd-coating-explained` | `2018-04-27 16:08:55` |
| `corten-steel-modern-facades` | `2019-09-14 11:23:07` |

Status and `published_at` were excluded from update payloads for existing records (importer `stripPreservedFieldsFromUpdate`).

---

## 6. Dry-run

```bash
php artisan blog:import-content --dry-run --global-only
```

| Metric | Expected | Actual |
|---|---|---|
| Processed | 25 | **25** |
| CREATE | 0 | **0** |
| UPDATE | 25 | **25** |
| Regional | 0 | **0** (9 excluded) |
| Published (manifest) | 3 | 3 |
| Draft (manifest) | 22 | 22 |
| Flagged | 0* | **15** (word-count advisory flags below 900 words — non-blocking) |

\*Dry-run v3 reported 0 flags when word counts were higher; current content triggers advisory flags only. No image-gate or relationship errors.

---

## 7. Apply (LOCAL SIMULATION only)

```bash
php artisan blog:import-content --global-only --force
```

| Metric | Result |
|---|---|
| Processed | 25 |
| Created | 0 |
| Updated | 25 |
| Skipped | 0 |
| Backup | ✅ Created before write |

**NOT run on production.**

---

## 8. Verification

### HTTP checks (`http://127.0.0.1:8000`)

| URL | Status |
|---|---|
| `/blog` | **200** |
| `/blog/glass-partitions-open-plan` | **200** |
| `/blog/pvd-coating-explained` | **200** |
| `/blog/corten-steel-modern-facades` | **200** |
| `/sitemap.xml` | **200** |

### Pillar visual / SEO checks

| Slug | `<picture>` + WebP | JSON-LD schema | OG absolute URL | AI trace |
|---|---|---|---|---|
| `glass-partitions-open-plan` | ✅ | ✅ | ✅ | none |
| `pvd-coating-explained` | ✅ | ✅ | ✅ | none |
| `corten-steel-modern-facades` | ✅ | ✅ | ✅ | none |

### Hero assets (pillar articles)

| Slug | Hero JPG | Hero WebP | OG/card crop |
|---|---|---|---|
| `glass-partitions-open-plan` | ✅ | ✅ | ✅ (`-hero-card.jpg`) |
| `pvd-coating-explained` | ✅ | ✅ | ✅ (`-hero-card.jpg`) |
| `corten-steel-modern-facades` | ✅ | ✅ | ✅ (hero reused) |

### Sitemap

- **Draft slugs leaked:** none ✅
- **Simulation note:** Sitemap listed 16 blog URLs because legacy seed catalog posts (13) remain published in the simulation DB. On production (3 published only), sitemap should list exactly **3** blog article URLs plus `/blog` index.

### Blog index

- `/blog` returns **200** with no AI/internal trace markers (covered by `BlogPublicTraceTest`).
- **Simulation caveat:** Pillars may not appear on page 1 of `/blog` because seed catalog posts inflate published count; pillar URLs and sitemap entries are correct.

---

## 9. Test suite

```bash
php artisan test
```

| Result | Count |
|---|---|
| Passed | **523** |
| Failed | 0 |
| Skipped | 1 |
| Assertions | 2907 |
| Duration | ~34s |

Blog-related suites (all pass):

- `BlogContentImportTest` — 14 tests
- `BlogHeroImageTest` — 3 tests
- `BlogModuleTest` — 14 tests
- `BlogPublicTraceTest` — 5 tests
- `BlogVisibilityTest` — 2 tests
- `SeoFoundationTest` — includes sitemap draft exclusion

---

## 10. Redirects

**None created or proposed.** Legacy slug map renames in-place on existing records only; no redirect rules added.

---

## 11. Preserved production URLs

These three URLs must remain unchanged on production apply:

1. `/blog/glass-partitions-open-plan`
2. `/blog/pvd-coating-explained`
3. `/blog/corten-steel-modern-facades`

Simulation confirmed: slugs unchanged, `published_at` preserved, HTTP 200, hero images updated to local `/images/blog/heroes/` paths.

---

## 12. Next steps for owner (production path — do NOT run until approved)

1. Provision or confirm Hostinger **staging** subdomain + SSH path (separate from production).
2. Deploy `hotfix/production-regressions` to staging via `git pull` (no `reset --hard`).
3. Re-run slug check against **staging DB** (not production).
4. Run dry-run on staging; confirm 25 UPDATE / 0 CREATE.
5. Apply with backup on staging only.
6. Visual QA on staging URLs.
7. Owner sign-off → schedule production import window.

**Commands for staging ONLY (when host is available):**

```bash
php artisan blog:import-content --dry-run --global-only
php artisan blog:import-content --global-only --force   # after dry-run matches
php artisan test
```

**Never run the apply command against production without explicit owner approval.**
