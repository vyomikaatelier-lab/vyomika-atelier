<?php

/**
 * Seed production-like blog state for LOCAL SIMULATION staging verification.
 * Creates 25 global manifest slugs: 3 published pillars + 22 drafts.
 *
 * Usage: php database/scripts/staging-blog-simulation-seed.php
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BlogPost;
use App\Support\BlogContentImporter;
use Carbon\Carbon;

$importer = new BlogContentImporter(database_path('content/blog'));
$manifest = require database_path('content/blog/manifest.php');
$articles = $importer->filterArticles($manifest);

$pillarDates = [
    'glass-partitions-open-plan' => Carbon::parse('2017-11-03 09:41:22'),
    'pvd-coating-explained' => Carbon::parse('2018-04-27 16:08:55'),
    'corten-steel-modern-facades' => Carbon::parse('2019-09-14 11:23:07'),
];

$created = 0;

foreach ($articles as $article) {
    $slug = (string) $article['slug'];
    $isPillar = in_array($slug, BlogContentImporter::PRESERVE_PUBLISHED_SLUGS, true);

    BlogPost::query()->create([
        'title' => (string) ($article['title'] ?? $slug),
        'slug' => $slug,
        'content' => '<p>Legacy simulation content for '.$slug.'</p>',
        'excerpt' => str_repeat('x', 150),
        'status' => $isPillar ? BlogPost::STATUS_PUBLISHED : BlogPost::STATUS_DRAFT,
        'published_at' => $isPillar ? $pillarDates[$slug] : null,
        'hero_image_alt' => (string) ($article['hero_image_alt'] ?? 'Alt'),
        'is_active' => true,
    ]);

    $created++;
}

echo "Seeded {$created} global blog posts (3 published pillars, ".($created - 3)." drafts).\n";
