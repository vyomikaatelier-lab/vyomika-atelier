# Production Deployment Report — Blog Import (Global Only)

**Date:** 23 August 2026  
**Operator:** Cursor agent (owner-authorized deploy attempt)  
**Approved branch:** `hotfix/production-regressions`  
**Target commit (remote HEAD):** `0f46aa72bc91c1b9d853c19d0fa3ba384dd5ad96` (`0f46aa7`)  
**Ancestor gate (`31312a7`):** ✅ PASS (verified locally via `git merge-base --is-ancestor`)  
**Final status:** **BLOCKED** — SSH to production unavailable from this environment

---

## Executive decision

**BLOCKED — no production changes applied.**

Deployment stopped at **Phase 1** because SSH authentication to Hostinger failed (`Permission denied (publickey,password)`). No code was deployed, no migrations ran, no `blog:import-content` apply ran, and no database backups were taken on production.

Owner must run the checklist below from **hPanel Browser SSH** or a machine with a registered Hostinger SSH key.

---

## Phase 1: Connect & verify

| Check | Result |
|---|---|
| SSH `82.25.106.229:65002` user `u550969814` | ❌ **BLOCKED** — `Permission denied (publickey,password)` with `BatchMode=yes` |
| Local SSH keys present | `id_ed25519_loomwalls`, `id_ed25519_printonwalls` only — **no Hostinger key** |
| SSH agent | Not running (`ssh-add: No such file or directory`) |
| `~/vyomika-atelier/artisan` | Not verified (SSH blocked) |
| `php artisan about` on production | Not run |
| Production `APP_URL` / `APP_ENV` | Not verified on server |

**Block reason:** This Cursor environment cannot authenticate to Hostinger production SSH. Documented connection string matches `HOSTINGER.md` but no usable private key is configured here.

---

## Phase 2: Read-only preflight

### Local git (verified)

```text
origin/hotfix/production-regressions = 0f46aa72bc91c1b9d853c19d0fa3ba384dd5ad96
31312a7 is ancestor: YES
Latest commit: 0f46aa7 Add staging blog import verification report and simulation scripts.
```

### Production DB / git on server

| Check | Result |
|---|---|
| Production git commit | Not read (SSH blocked) |
| `blog_posts` counts | Not read |
| 3 pillar IDs / slugs / status / `published_at` / hero / OG | Not read from DB |
| SYNC-TRACE in site settings | Not read from DB |

### Public HTTP preflight (before deploy — from this environment)

| URL | Status |
|---|---|
| `https://vyomikaatelier.com/blog` | **200** |
| `https://vyomikaatelier.com/blog/glass-partitions-open-plan` | **200** |
| `https://vyomikaatelier.com/blog/pvd-coating-explained` | **200** |
| `https://vyomikaatelier.com/blog/corten-steel-modern-facades` | **200** |
| `https://vyomikaatelier.com/sitemap.xml` | **200** |

### Public AI-trace scan (current production — pre-deploy)

Scanned homepage, `/blog`, 3 pillars, and 404 test URL for: `SYNC-TRACE`, `ChatGPT`, `OpenAI`, `Cursor`, `system prompt`, Windows paths.

| Page | Trace hits |
|---|---|
| `/` | none |
| `/blog` | none |
| 3 pillar articles | none |
| `/nonexistent-page-404-test` | none (404) |

### Sitemap (pre-deploy)

Blog URLs in sitemap: **4** (expected post-import baseline for published-only global content)

1. `https://vyomikaatelier.com/blog`
2. `https://vyomikaatelier.com/blog/corten-steel-modern-facades`
3. `https://vyomikaatelier.com/blog/glass-partitions-open-plan`
4. `https://vyomikaatelier.com/blog/pvd-coating-explained`

### Pre-deploy pillar markup baseline (public HTML)

| Slug | `<picture>` | third-party/unsplash | OG absolute | JSON-LD |
|---|---|---|---|---|
| `glass-partitions-open-plan` | NO | YES (legacy hotlinks) | YES | YES |
| `pvd-coating-explained` | NO | YES | YES | YES |
| `corten-steel-modern-facades` | NO | YES | YES | YES |

*Expected to improve after import deploys local hero assets.*

---

## Phase 3: Backups

| Action | Result |
|---|---|
| Hostinger / mysqldump DB backup | ❌ Not run (SSH blocked) |
| JSON `blog_posts` export | ❌ Not run |
| Pillar `published_at` recorded | ❌ Not read from DB |

**Expected pillar `published_at` values (from staging simulation — verify on production before apply):**

| Slug | `published_at` |
|---|---|
| `glass-partitions-open-plan` | `2017-11-03 09:41:22` |
| `pvd-coating-explained` | `2018-04-27 16:08:55` |
| `corten-steel-modern-facades` | `2019-09-14 11:23:07` |

---

## Phase 4: Deploy code

**Not executed.** All steps below require SSH.

Planned sequence (for owner):

```bash
cd ~/vyomika-atelier
git remote -v
git status   # must be clean — STOP if dirty
git fetch origin
git checkout hotfix/production-regressions
git merge --ff-only origin/hotfix/production-regressions
git log -1 --oneline   # expect 0f46aa7

composer install --no-dev --optimize-autoloader --no-interaction

php artisan down --render="maintenance"   # or project maintenance view

php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan route:cache

test -L public/storage || ln -sf ~/vyomika-atelier/storage/app/public ~/vyomika-atelier/public/storage
```

---

## Phase 5: Remove SYNC-TRACE

**Not executed.**

If `site_settings` announcement still contains `SYNC-TRACE-8C03C3B-2026`, clear it via admin or tinker after deploy. `AnnouncementGuard` blocks the marker from public output once sanitized.

Post-clear verification pages: `/`, `/blog`, 3 pillars, a product page, 404 — search for `SYNC-TRACE`, `ChatGPT`, `OpenAI`, `Cursor`, `system prompt`, Windows paths.

Public pre-deploy scan already shows **no SYNC-TRACE** on live HTML.

---

## Phase 6: Dry-run gate

**Not executed on production.**

Required pass criteria before apply:

```bash
php artisan blog:import-content --dry-run --global-only
```

| Metric | Required |
|---|---|
| Processed | 25 |
| UPDATE | 25 |
| CREATE | 0 |
| Regional | 0 |
| Published (manifest) | 3 |
| Draft (manifest) | 22 |
| Scheduled | 0 |
| Pillar `published_at` | unchanged in report table |

**If any metric fails → STOP. Do not apply.**

---

## Phase 7: Apply

**Not executed.**

```bash
php artisan blog:import-content --global-only --force
```

Expected: **25 updated, 0 created.** Importer creates JSON backup automatically in production.

**Critical constraints (do NOT violate):**

- Do not publish 22 draft articles or 9 regional articles
- Do not change slugs: `glass-partitions-open-plan`, `pvd-coating-explained`, `corten-steel-modern-facades`
- Do not overwrite production `published_at` on pillars
- No `migrate:fresh`, seeders, or PHPUnit against production DB

---

## Phase 8: DB verification

**Not executed.**

After apply, confirm on production DB:

```sql
SELECT id, slug, status, published_at FROM blog_posts
WHERE slug IN (
  'glass-partitions-open-plan',
  'pvd-coating-explained',
  'corten-steel-modern-facades'
);

SELECT status, COUNT(*) FROM blog_posts GROUP BY status;
SELECT COUNT(*) FROM blog_posts WHERE slug LIKE '%-ae' OR slug LIKE '%-uk' OR slug LIKE '%-us%';
```

Expected: 3 published, 22 draft global, 0 regional imported, pillar slugs and `published_at` unchanged, no legacy duplicate slugs from `LEGACY_SLUG_MAP`.

---

## Phase 9: Public verification

**Partially executed (pre-deploy baseline only).**

After owner completes deploy + import:

```bash
php artisan up
```

Then verify:

- [ ] `curl -I` 200 on `/blog`, 3 pillar URLs, `/sitemap.xml`
- [ ] Hero JPG/WebP/card assets HTTP 200
- [ ] `<picture>` markup on pillars; no third-party/unsplash on pillars
- [ ] OG tags use absolute URLs; JSON-LD present
- [ ] Sitemap lists published articles only (4 blog URLs)
- [ ] No SYNC-TRACE or AI trace strings
- [ ] Corten hero alt still contains “representative” / “visualised”

---

## Phase 10: Rollback procedure

If critical failure after apply:

1. `php artisan down`
2. Restore mysqldump / Hostinger backup taken in Phase 3
3. `git checkout` previous production commit (record SHA before deploy) — **no** `git reset --hard`
4. `composer install --no-dev --optimize-autoloader --no-interaction`
5. `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. `php artisan up`
7. Re-verify public URLs

---

## Phase 11: Owner-run command checklist

Run from **hPanel → Advanced → SSH → Browser terminal** (recommended if local ISP blocks `82.25.106.229:65002`).

### A. Connect & preflight

```bash
ssh -p 65002 u550969814@82.25.106.229
cd ~/vyomika-atelier
test -f artisan && pwd
php artisan about | head -20
# Confirm: APP_URL=https://vyomikaatelier.com, environment=production
git status
git remote -v
git log -1 --oneline

php artisan tinker --execute="
\$p = ['glass-partitions-open-plan','pvd-coating-explained','corten-steel-modern-facades'];
foreach (App\\Models\\BlogPost::whereIn('slug', \$p)->get(['id','slug','status','published_at']) as \$r) echo \$r->id.' '.\$r->slug.' '.\$r->status.' '.\$r->published_at.PHP_EOL;
echo 'total='.App\\Models\\BlogPost::count().' published='.App\\Models\\BlogPost::where('status','published')->count().' draft='.App\\Models\\BlogPost::where('status','draft')->count();
"
```

Check announcement for SYNC-TRACE (clear in admin if present):

```bash
php artisan tinker --execute="echo json_encode(App\\Support\\SiteContent::announcement());"
```

### B. Backups (mandatory before apply)

```bash
BACKUP_DIR=~/backups/blog-deploy-$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

# Hostinger hPanel backup OR mysqldump (use credentials from hPanel, not echoed here):
# mysqldump -u DB_USER -p DB_NAME > "$BACKUP_DIR/blog_posts_full.sql"

php artisan tinker --execute="
\$path = storage_path('app/blog-backups/pre-deploy-'.date('Y-m-d_His').'.json');
\$rows = DB::table('blog_posts')->get();
file_put_contents(\$path, json_encode(\$rows, JSON_PRETTY_PRINT));
echo \$path.' rows='.count(\$rows);
"

ls -lh "$BACKUP_DIR"
# STOP if backup files are empty
```

Record pillar `published_at` from tinker output before proceeding.

### C. Deploy code

```bash
cd ~/vyomika-atelier
git fetch origin
git checkout hotfix/production-regressions
git merge --ff-only origin/hotfix/production-regressions
# Must land on 0f46aa72bc91c1b9d853c19d0fa3ba384dd5ad96

composer install --no-dev --optimize-autoloader --no-interaction
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan route:cache
test -L public/storage || ln -sf ~/vyomika-atelier/storage/app/public ~/vyomika-atelier/public/storage
```

### D. Dry-run gate (STOP if fail)

```bash
php artisan blog:import-content --dry-run --global-only
```

Confirm: **25 processed, 25 UPDATE, 0 CREATE, 0 regional, 3 published, 22 draft, 0 scheduled**, pillar `published_at` unchanged in report.

### E. Apply (only if dry-run passes)

```bash
php artisan blog:import-content --global-only --force
```

### F. Post-apply verification

```bash
php artisan tinker --execute="
\$p = ['glass-partitions-open-plan','pvd-coating-explained','corten-steel-modern-facades'];
foreach (App\\Models\\BlogPost::whereIn('slug', \$p)->get(['slug','status','published_at']) as \$r) echo \$r->slug.' '.\$r->status.' '.\$r->published_at.PHP_EOL;
"

php artisan up

curl -sI https://vyomikaatelier.com/blog | head -1
curl -sI https://vyomikaatelier.com/blog/glass-partitions-open-plan | head -1
curl -sI https://vyomikaatelier.com/blog/pvd-coating-explained | head -1
curl -sI https://vyomikaatelier.com/blog/corten-steel-modern-facades | head -1
curl -sI https://vyomikaatelier.com/sitemap.xml | head -1
```

Visually confirm hero `<picture>` WebP, no stock hotlinks on pillars, Corten alt wording, no SYNC-TRACE.

---

## Reference: staging simulation (local — not production)

Staging release report (`docs/blog-international-seo/staging-release-report.md`) validated on local SQLite:

- Dry-run: 25 UPDATE / 0 CREATE
- Apply: 25 updated / 0 created
- Pillar `published_at` preserved
- Test suite: 523 passed

Production apply was **not** replicated here due to SSH block.

---

## Final status

### **BLOCKED**

| Item | Value |
|---|---|
| Production code deployed | No |
| Migrations run | No |
| Blog import applied | No |
| Maintenance mode used | No |
| DB backup on production | No |
| Rollback required | No (nothing changed) |
| Target commit ready on GitHub | Yes — `0f46aa72bc91c1b9d853c19d0fa3ba384dd5ad96` |
| Next action | Owner runs checklist via hPanel Browser SSH |
