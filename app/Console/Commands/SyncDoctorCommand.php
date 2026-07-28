<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Exhibition;
use App\Models\LegalPage;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Support\CmsSettings;
use App\Support\ShopCatalog;
use App\Support\StorefrontRoutes;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Answers one question on a live host: when the storefront shows stale content,
 * is the code wrong, is the deploy behind, or is a cache serving old bytes?
 *
 * Everything here runs in-process against the same database the storefront uses,
 * so a "resolved" value that matches the database proves the code is correct and
 * points the blame at a cache or CDN in front of PHP.
 */
class SyncDoctorCommand extends Command
{
    protected $signature = 'vyomika:sync-doctor';

    protected $description = 'Diagnose admin-to-frontend content sync: deploy, database, caches, and resolved frontend values';

    public function handle(): int
    {
        $this->section('Deploy');
        $this->line('Commit: '.$this->deployedCommit());
        $this->line('Catalog gating fixed: '.$this->yesNo($this->catalogGatingFixed()));
        $this->line('Empty-section fallback fixed: '.$this->yesNo($this->emptySectionFallbackFixed()));

        $this->section('Database');
        $this->reportDatabase();

        $this->section('Caches');
        $this->reportCaches();

        $this->section('Content freshness (database)');
        $this->reportFreshness();

        $this->section('Resolved frontend values');
        $this->reportResolvedValues();

        $this->newLine();
        $this->line('If a resolved value above matches the database but the live page does not,');
        $this->line('the code and database are correct: clear the caches listed above, then look');
        $this->line('at a CDN / proxy / browser cache in front of PHP.');

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('== '.$title.' ==');
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'NO — deploy is behind';
    }

    /** Read the checked-out commit without shell_exec, which many hosts disable. */
    private function deployedCommit(): string
    {
        $head = base_path('.git/HEAD');

        if (! is_file($head)) {
            return '(no .git directory — deployed from an archive)';
        }

        $contents = trim((string) file_get_contents($head));

        if (! str_starts_with($contents, 'ref: ')) {
            return substr($contents, 0, 12).' (detached)';
        }

        $ref = substr($contents, 5);
        $refFile = base_path('.git/'.$ref);

        if (is_file($refFile)) {
            return substr(trim((string) file_get_contents($refFile)), 0, 12).' ('.$ref.')';
        }

        $packed = base_path('.git/packed-refs');
        if (is_file($packed)) {
            foreach (preg_split('/\R/', (string) file_get_contents($packed)) ?: [] as $line) {
                if (str_ends_with(trim($line), ' '.$ref)) {
                    return substr(trim($line), 0, 12).' ('.$ref.')';
                }
            }
        }

        return '(unknown) on '.$ref;
    }

    /** The shop scope must union admin categories instead of only the curated list. */
    private function catalogGatingFixed(): bool
    {
        $source = (string) @file_get_contents(app_path('Support/ShopCatalog.php'));

        return str_contains($source, "where('section', Product::SECTION_SHOP)");
    }

    private function emptySectionFallbackFixed(): bool
    {
        $source = (string) @file_get_contents(app_path('Support/CmsSettings.php'));

        return str_contains($source, 'Exhibition::query()->exists()');
    }

    private function reportDatabase(): void
    {
        try {
            $connection = DB::connection();
            $this->line('Connection: '.$connection->getName());
            $this->line('Database: '.$connection->getDatabaseName());
            $this->line('Host: '.(config('database.connections.'.$connection->getName().'.host') ?: '(local)'));
            $this->line('Server version: '.($connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: 'unknown'));
        } catch (\Throwable $e) {
            $this->error('Cannot connect: '.$e->getMessage());
        }
    }

    private function reportCaches(): void
    {
        $caches = [
            'config' => base_path('bootstrap/cache/config.php'),
            'routes' => base_path('bootstrap/cache/routes-v7.php'),
            'events' => base_path('bootstrap/cache/events.php'),
        ];

        foreach ($caches as $label => $path) {
            if (! is_file($path)) {
                $this->line(ucfirst($label).' cache: not cached');

                continue;
            }

            $this->line(sprintf(
                '%s cache: cached %s%s',
                ucfirst($label),
                date('Y-m-d H:i:s', (int) filemtime($path)),
                $label === 'config' && $this->configCacheIsStale($path) ? ' — STALE, run php artisan config:clear' : ''
            ));
        }

        $views = glob(storage_path('framework/views/*.php')) ?: [];
        $this->line('Compiled views: '.count($views));

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            $enabled = is_array($status) && ($status['opcache_enabled'] ?? false);
            $this->line('OPcache: '.($enabled ? 'enabled — restart PHP-FPM after deploying' : 'disabled'));
        } else {
            $this->line('OPcache: not available');
        }

        $this->line('Cache store: '.config('cache.default'));
    }

    private function configCacheIsStale(string $path): bool
    {
        $cachedAt = (int) filemtime($path);

        foreach (glob(config_path('*.php')) ?: [] as $configFile) {
            if ((int) filemtime($configFile) > $cachedAt) {
                return true;
            }
        }

        return false;
    }

    private function reportFreshness(): void
    {
        /** @var array<string, class-string<Model>> $models */
        $models = [
            'Products' => Product::class,
            'Categories' => Category::class,
            'Projects' => Project::class,
            'Exhibitions' => Exhibition::class,
            'Blog posts' => BlogPost::class,
            'Services' => Service::class,
            'Legal pages' => LegalPage::class,
            'Media files' => MediaFile::class,
            'Site settings' => SiteSetting::class,
        ];

        foreach ($models as $label => $model) {
            $table = (new $model)->getTable();

            if (! Schema::hasTable($table)) {
                $this->line($label.': table missing — run php artisan migrate --force');

                continue;
            }

            $total = $model::query()->count();
            $latest = $model::query()->max('updated_at');
            $active = Schema::hasColumn($table, 'is_active')
                ? $model::query()->where('is_active', true)->count()
                : null;

            $this->line(sprintf(
                '%s: %d row(s)%s, last saved %s',
                $label,
                $total,
                $active === null ? '' : ", {$active} active",
                $latest ?: 'never'
            ));
        }
    }

    private function reportResolvedValues(): void
    {
        CmsSettings::hydrate();

        $this->line('Brand name: '.(config('site.brand.name') ?: '(unset)'));
        $this->line('Brand email: '.(config('site.brand.email') ?: '(unset)'));
        $this->line('Announcement text: '.(config('site.announcement.text') ?: '(hidden)'));
        $this->line('Hero slide 1 title: '.(data_get(config('site.hero.slides'), '0.title') ?: '(unset)'));

        $exhibitions = CmsSettings::exhibitions();
        $this->line('Exhibitions on /about: '.count($exhibitions).(
            $exhibitions === [] ? '' : ' (first: '.($exhibitions[0]['name'] ?? '?').')'
        ));

        $curated = StorefrontRoutes::shopCategorySlugs();
        $shoppable = Schema::hasTable('categories') ? ShopCatalog::categorySlugs() : $curated;
        $extra = array_values(array_diff($shoppable, $curated));

        $this->line('Curated shop categories: '.implode(', ', $curated));
        $this->line('Admin-added shop categories now visible: '.($extra === [] ? '(none)' : implode(', ', $extra)));

        if (Schema::hasTable('products')) {
            $this->line('Products passing the shop scope: '.ShopCatalog::applyShopScope(
                Product::query()->where('is_active', true)
            )->count());
        }
    }
}
