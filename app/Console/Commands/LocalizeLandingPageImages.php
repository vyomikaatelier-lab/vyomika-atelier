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
            // Browser-like headers: Unsplash and CDNs often block non-browser clients.
            $response = Http::timeout(45)
                ->withOptions(['allow_redirects' => true])
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; VyomikaAtelier/1.0; +https://vyomikaatelier.com)',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://unsplash.com/',
                ])
                ->get($url);

            if (! $response->successful()) {
                $this->warn("HTTP {$response->status()} for {$url}");

                return null;
            }

            $body = $response->body();
            $mime = strtolower((string) ($response->header('Content-Type') ?: ''));
            if ($body === '' || strlen($body) > 8 * 1024 * 1024) {
                return null;
            }
            if ($mime !== '' && ! str_starts_with($mime, 'image/') && ! str_contains($mime, 'octet-stream')) {
                $this->warn("Non-image content-type ({$mime}) for {$url}");

                return null;
            }

            Storage::disk('public')->put($path, $body);
            MediaFile::query()->firstOrCreate(
                ['path' => $path],
                [
                    'disk' => 'public',
                    'filename' => basename($path),
                    'mime' => $mime !== '' ? explode(';', $mime)[0] : 'image/jpeg',
                    'size' => strlen($body),
                    'is_private' => false,
                ]
            );

            return $path;
        } catch (\Throwable $e) {
            $this->warn($e->getMessage());

            return null;
        }
    }
}
