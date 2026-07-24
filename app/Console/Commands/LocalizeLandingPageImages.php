<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Models\SiteSetting;
use App\Support\LandingPageContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Download legacy external landing-page images into public storage.
 * Idempotent: skips non-http sources and paths that already exist locally.
 * Does not overwrite administrator-edited local image paths.
 */
class LocalizeLandingPageImages extends Command
{
    protected $signature = 'landing-pages:localize-images {--dry-run : Report only}';

    protected $description = 'Download external Railings/Corten landing images into managed storage';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if (! Schema::hasTable('site_settings')) {
            $this->error('site_settings table is missing. Run migrations first.');

            return self::FAILURE;
        }

        $pages = SiteSetting::getValue('landing_pages', []) ?? [];
        if (! is_array($pages)) {
            $pages = [];
        }

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach (LandingPageContent::slugs() as $slug) {
            $page = LandingPageContent::page($slug);
            $changed = false;
            $localized = $this->walk($page, $slug, $dry, $downloaded, $skipped, $failed, $changed);

            if ($changed && ! $dry) {
                $pages[$slug] = $localized;
            }
        }

        if (! $dry) {
            SiteSetting::setValue('landing_pages', $pages);
        }

        $this->info("Downloaded: {$downloaded}; skipped: {$skipped}; failed: {$failed}".($dry ? ' (dry-run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function walk(array $node, string $slug, bool $dry, int &$downloaded, int &$skipped, int &$failed, bool &$changed): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->walk($value, $slug, $dry, $downloaded, $skipped, $failed, $changed);

                continue;
            }

            if (! is_string($value) || ! in_array($key, ['image', 'mobile_image', 'og_image'], true)) {
                continue;
            }

            if (! preg_match('#^https?://#i', $value)) {
                $skipped++;

                continue;
            }

            $local = $this->download($value, $slug, $dry);
            if ($local === null) {
                $this->warn("Failed: {$value}");
                $failed++;

                continue;
            }

            if ($local !== $value) {
                $node[$key] = $local;
                $changed = true;
                $downloaded++;
                $this->line(($dry ? '[dry] ' : '')."{$value} -> {$local}");
            } else {
                $skipped++;
            }
        }

        return $node;
    }

    private function download(string $url, string $slug, bool $dry): ?string
    {
        $hash = sha1($url);
        $ext = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $ext = Str::lower(preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $path = "landing-pages/imported/{$slug}/{$hash}.{$ext}";

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        if ($dry) {
            return $path;
        }

        try {
            $response = Http::timeout(30)->withHeaders([
                'User-Agent' => 'VyomikaAtelierLandingImport/1.0',
            ])->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > 8 * 1024 * 1024) {
                return null;
            }

            Storage::disk('public')->put($path, $body);
            MediaFile::query()->firstOrCreate(
                ['path' => $path],
                [
                    'disk' => 'public',
                    'filename' => basename($path),
                    'mime' => $response->header('Content-Type') ?: 'image/jpeg',
                    'size' => strlen($body),
                    'is_private' => false,
                ]
            );

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
