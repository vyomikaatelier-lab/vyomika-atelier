<?php

/**
 * Post-import verification for LOCAL SIMULATION staging release.
 * Usage: php database/scripts/staging-post-import-verify.php
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BlogPost;
use App\Support\BlogContentImporter;
use Illuminate\Support\Facades\Route;

$manifest = require database_path('content/blog/manifest.php');
$globalPublished = 0;
$globalDraft = 0;
$regional = 0;

foreach ($manifest as $entry) {
    if (($entry['import_eligible'] ?? true) === false) {
        $regional++;
        continue;
    }
    if (($entry['status'] ?? 'draft') === 'published') {
        $globalPublished++;
    } else {
        $globalDraft++;
    }
}

echo "=== Manifest counts ===\n";
echo "  Global published: {$globalPublished}\n";
echo "  Global draft: {$globalDraft}\n";
echo "  Regional (import_eligible false): {$regional}\n";

echo "\n=== DB counts (global manifest slugs only) ===\n";
$importer = new BlogContentImporter(database_path('content/blog'));
$articles = $importer->filterArticles($manifest);
$slugs = array_map(fn ($a) => $a['slug'], $articles);

$dbPublished = BlogPost::query()->whereIn('slug', $slugs)->where('status', BlogPost::STATUS_PUBLISHED)->count();
$dbDraft = BlogPost::query()->whereIn('slug', $slugs)->where('status', BlogPost::STATUS_DRAFT)->count();
echo "  Published: {$dbPublished}\n";
echo "  Draft: {$dbDraft}\n";

echo "\n=== Pillar published_at preservation ===\n";
$expected = [
    'glass-partitions-open-plan' => '2017-11-03 09:41:22',
    'pvd-coating-explained' => '2018-04-27 16:08:55',
    'corten-steel-modern-facades' => '2019-09-14 11:23:07',
];
foreach ($expected as $slug => $date) {
    $post = BlogPost::query()->where('slug', $slug)->first();
    $actual = $post?->published_at?->format('Y-m-d H:i:s') ?? 'NULL';
    $ok = $actual === $date ? 'OK' : "MISMATCH (got {$actual})";
    echo "  {$slug}: {$ok}\n";
}

echo "\n=== Legacy slugs (must be absent) ===\n";
foreach (array_keys(BlogContentImporter::LEGACY_SLUG_MAP) as $legacy) {
    $exists = BlogPost::query()->where('slug', $legacy)->exists();
    echo '  '.$legacy.': '.($exists ? 'EXISTS — BLOCKER' : 'absent OK')."\n";
}

echo "\n=== Pillar hero paths ===\n";
foreach (BlogContentImporter::PRESERVE_PUBLISHED_SLUGS as $slug) {
    $post = BlogPost::query()->where('slug', $slug)->first();
    echo "  {$slug}:\n";
    echo '    image: '.($post->image ?? 'NULL')."\n";
    echo '    og_image: '.($post->og_image ?? 'NULL')."\n";
    $jpg = public_path(ltrim(str_replace('\\', '/', $post->image ?? ''), '/'));
    $webp = preg_replace('/\.jpe?g$/i', '.webp', $jpg);
    echo '    hero jpg exists: '.(is_file($jpg) ? 'yes' : 'NO')."\n";
    echo '    hero webp exists: '.(is_file($webp) ? 'yes' : 'NO')."\n";
    if ($post->og_image) {
        $og = public_path(ltrim(str_replace('\\', '/', $post->og_image), '/'));
        echo '    og/card exists: '.(is_file($og) ? 'yes' : 'NO')."\n";
    }
}

echo "\n=== Sitemap blog URLs ===\n";
$request = Illuminate\Http\Request::create('/sitemap.xml', 'GET');
$response = app()->handle($request);
$xml = $response->getContent();
preg_match_all('#<loc>([^<]+/blog/[^<]+)</loc>#', $xml, $matches);
$sitemapBlog = $matches[1] ?? [];
echo '  Blog entries in sitemap: '.count($sitemapBlog)."\n";
foreach ($sitemapBlog as $url) {
    echo "    {$url}\n";
}

$draftInSitemap = [];
foreach ($slugs as $slug) {
    $post = BlogPost::query()->where('slug', $slug)->first();
    if ($post && $post->status !== BlogPost::STATUS_PUBLISHED) {
        foreach ($sitemapBlog as $url) {
            if (str_contains($url, '/blog/'.$slug)) {
                $draftInSitemap[] = $slug;
            }
        }
    }
}
echo '  Draft slugs leaked to sitemap: '.($draftInSitemap === [] ? 'none OK' : implode(', ', $draftInSitemap))."\n";

echo "\n=== Backup files ===\n";
$backups = glob(storage_path('app/blog-backups/blog-posts-*.json')) ?: [];
rsort($backups);
echo '  Latest: '.($backups[0] ?? 'none')."\n";
