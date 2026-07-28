<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Support\CmsSettings;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Pinpoints the layer that turns a saved value into stale HTML.
 *
 * vyomika:sync-doctor answers "is the database right?" but it calls
 * CmsSettings::hydrate() before reporting resolved values, so it reports what a
 * healthy boot *would* produce and cannot see a boot-time hydration that failed
 * — the exact failure that makes every page fall back to config seed content.
 *
 * This command reads the boot-time value *before* hydrating anything, so each
 * layer is measured independently:
 *
 *   database -> boot-time config -> rendered HTML
 */
class SyncTraceCommand extends Command
{
    protected $signature = 'vyomika:sync-trace {--expect= : Fail unless this string reaches the rendered HTML}';

    protected $description = 'Trace one saved value from the database through boot-time config into rendered homepage HTML';

    public function handle(): int
    {
        // Must be read before anything in this command hydrates: this is what
        // AppServiceProvider::boot() produced, which is what a web request uses.
        $bootValue = $this->announcementFromConfig();
        $bootStatus = CmsSettings::hydrationStatus();
        $bootHtml = $this->renderHomepage();

        $seedValue = $this->announcementFromSeedFile();
        $dbValue = $this->announcementFromDatabase();

        CmsSettings::hydrate();
        $hydratedValue = $this->announcementFromConfig();
        $hydratedHtml = $this->renderHomepage();

        $this->section('Layer 1 — database (source of truth)');
        $this->line('site_settings table: '.(Schema::hasTable('site_settings') ? 'present' : 'MISSING'));
        $this->line('homepage.announcement.text: '.$this->show($dbValue));

        $this->section('Layer 2 — config seed (fallback in config/site.php)');
        $this->line('site.announcement.text: '.$this->show($seedValue));

        $this->section('Layer 3 — boot-time config (what a web request sees)');
        $this->line('Boot hydration ran: '.($bootStatus['ran'] ? 'yes' : 'NO'));
        $this->line('site_settings reachable at boot: '.($bootStatus['table_found'] ? 'yes' : 'NO'));
        $this->line('Keys applied at boot: '.($bootStatus['applied'] === [] ? '(none)' : implode(', ', $bootStatus['applied'])));
        if (! empty($bootStatus['error'])) {
            $this->line('Boot hydration error: '.$bootStatus['error']);
        }
        $this->line('config(site.announcement.text): '.$this->show($bootValue));

        $this->section('Layer 4 — rendered homepage HTML');
        $this->line('Rendered with boot-time config: '.$this->htmlVerdict($bootHtml, $dbValue, $seedValue));
        $this->line('Rendered after explicit hydrate(): '.$this->htmlVerdict($hydratedHtml, $dbValue, $seedValue));
        $this->line('config after explicit hydrate(): '.$this->show($hydratedValue));

        $this->section('Layer 5 — the copy of the code that is running');
        $this->reportServedCopy();

        $this->section('Verdict');
        $this->reportVerdict($bootValue, $dbValue, $seedValue, $hydratedValue, $bootHtml);

        $expected = $this->option('expect');
        if (filled($expected)) {
            $this->section('Expectation');
            if (is_string($bootHtml) && str_contains($bootHtml, (string) $expected)) {
                $this->info('PASS: rendered homepage HTML contains '.$expected);

                return self::SUCCESS;
            }

            $this->error('FAIL: rendered homepage HTML does not contain '.$expected);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function announcementFromConfig(): ?string
    {
        $value = config('site.announcement.text');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Read config/site.php off disk so the seed can be told apart from a stale save. */
    private function announcementFromSeedFile(): ?string
    {
        $path = config_path('site.php');

        if (! is_file($path)) {
            return null;
        }

        try {
            $seed = require $path;
        } catch (\Throwable) {
            return null;
        }

        $value = data_get($seed, 'announcement.text');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function announcementFromDatabase(): ?string
    {
        if (! Schema::hasTable('site_settings')) {
            return null;
        }

        $value = data_get(SiteSetting::getValue('homepage'), 'announcement.text');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Render the real route through the real middleware, controller, view and
     * layout. Anything short of this cannot prove the HTML is correct.
     */
    private function renderHomepage(): ?string
    {
        try {
            $kernel = $this->laravel->make(HttpKernel::class);
            $response = $kernel->handle(Request::create('/', 'GET'));

            return (string) $response->getContent();
        } catch (\Throwable $e) {
            $this->warn('Homepage render failed: '.$e->getMessage());

            return null;
        }
    }

    private function htmlVerdict(?string $html, ?string $dbValue, ?string $seedValue): string
    {
        if ($html === null) {
            return 'render failed';
        }

        if ($dbValue !== null && str_contains($html, $dbValue)) {
            return 'contains the DATABASE value';
        }

        if ($seedValue !== null && str_contains($html, $seedValue)) {
            return 'contains the CONFIG SEED value — database value never reached the HTML';
        }

        return 'contains neither the database nor the seed value';
    }

    private function reportServedCopy(): void
    {
        $this->line('Application path: '.base_path());
        $this->line('Public path: '.public_path());
        $this->line('Public path resolves to: '.(realpath(public_path()) ?: '(unresolvable)'));

        if (is_link(public_path())) {
            $this->line('Public path is a symlink to: '.(readlink(public_path()) ?: '(unreadable)'));
        }

        $entry = public_path('index.php');
        $this->line('Front controller: '.(is_file($entry)
            ? $entry.' (modified '.date('Y-m-d H:i:s', (int) filemtime($entry)).')'
            : 'MISSING at '.$entry));

        $autoload = base_path('vendor/autoload.php');
        $this->line('Autoloader: '.(is_file($autoload)
            ? $autoload.' (modified '.date('Y-m-d H:i:s', (int) filemtime($autoload)).')'
            : 'MISSING at '.$autoload));

        $configCache = base_path('bootstrap/cache/config.php');
        $this->line('Config cache: '.(is_file($configCache)
            ? 'cached '.date('Y-m-d H:i:s', (int) filemtime($configCache))
            : 'not cached'));

        $this->reportCompiledLayout();

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            $enabled = is_array($status) && ($status['opcache_enabled'] ?? false);
            $this->line('OPcache: '.($enabled
                ? 'ENABLED — a web request may still be running pre-deploy bytecode; reload PHP-FPM'
                : 'disabled'));
        } else {
            $this->line('OPcache: not available in this SAPI (the web SAPI may still have it on)');
        }
    }

    /**
     * The announcement is interpolated at render time, so its text must never be
     * baked into a compiled blade. If it is, view:cache is serving old content.
     */
    private function reportCompiledLayout(): void
    {
        $source = resource_path('views/layouts/store.blade.php');
        $this->line('Layout source: '.(is_file($source)
            ? 'modified '.date('Y-m-d H:i:s', (int) filemtime($source))
            : 'MISSING'));

        $compiled = [];
        foreach (glob(storage_path('framework/views/*.php')) ?: [] as $file) {
            $contents = (string) @file_get_contents($file);
            if (str_contains($contents, 'am-announce__track')) {
                $compiled[$file] = $contents;
            }
        }

        if ($compiled === []) {
            $this->line('Compiled layout: not compiled yet');

            return;
        }

        foreach ($compiled as $file => $contents) {
            $baked = preg_match('/am-announce__track.{0,400}?Festive Offer/s', $contents) === 1;
            $this->line(sprintf(
                'Compiled layout %s: compiled %s%s%s',
                basename($file),
                date('Y-m-d H:i:s', (int) filemtime($file)),
                is_file($source) && filemtime($file) < filemtime($source) ? ' — OLDER THAN SOURCE' : '',
                $baked ? ' — announcement text is BAKED IN, run php artisan view:clear' : ''
            ));
        }
    }

    private function reportVerdict(
        ?string $bootValue,
        ?string $dbValue,
        ?string $seedValue,
        ?string $hydratedValue,
        ?string $bootHtml
    ): void {
        if ($dbValue === null) {
            $this->line('No announcement is saved in the database, so the config seed is correct.');
            $this->line('If the admin just saved one, the admin save path is the failing layer.');

            return;
        }

        if ($bootValue !== $dbValue) {
            $this->line('DIVERGENCE AT BOOT-TIME HYDRATION.');
            $this->line('The database holds the new value but boot-time config does not, so every');
            $this->line('web request renders config seed content. '.($hydratedValue === $dbValue
                ? 'Hydrating by hand fixes it, which is why vyomika:sync-doctor looks correct.'
                : 'Hydrating by hand does not fix it either.'));
            $this->line('Check the storefront log for "CmsSettings::hydrate() failed".');

            return;
        }

        if (is_string($bootHtml) && ! str_contains($bootHtml, $dbValue)) {
            $this->line('DIVERGENCE AT THE VIEW LAYER.');
            $this->line('Boot-time config holds the database value but the rendered HTML does not.');
            $this->line('Run php artisan view:clear.');

            return;
        }

        $this->line('NO DIVERGENCE INSIDE PHP.');
        $this->line('Database, boot-time config and rendered HTML all agree on:');
        $this->line('  '.$dbValue);
        $this->line('If the live page still shows different text, the request is not reaching this');
        $this->line('code or this database. Compare the paths in "Layer 5" against the document root');
        $this->line('your domain actually serves, reload PHP-FPM to drop OPcache, then look at a CDN.');

        if ($seedValue !== null && $seedValue !== $dbValue) {
            $this->line('Old seed text to search the live HTML for: '.$seedValue);
        }
    }

    private function show(?string $value): string
    {
        return $value ?? '(unset)';
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('== '.$title.' ==');
    }
}
