<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BlogPost;
use App\Support\BlogContentImporter;

$legacy = array_keys(BlogContentImporter::LEGACY_SLUG_MAP);
$live = BlogContentImporter::PRESERVE_PUBLISHED_SLUGS;

echo "=== Legacy slug check (must be absent) ===\n";
foreach ($legacy as $slug) {
    $exists = BlogPost::query()->where('slug', $slug)->exists();
    echo "  {$slug}: ".($exists ? 'EXISTS — BLOCKER' : 'absent OK')."\n";
}

echo "\n=== Live pillar slug check ===\n";
foreach ($live as $slug) {
    $post = BlogPost::query()->where('slug', $slug)->first();
    if (! $post) {
        echo "  {$slug}: MISSING\n";
        continue;
    }
    echo "  {$slug}: {$post->status} @ {$post->published_at}\n";
}

echo "\n=== DB counts ===\n";
echo '  Total posts: '.BlogPost::count()."\n";
echo '  Published: '.BlogPost::query()->where('status', BlogPost::STATUS_PUBLISHED)->count()."\n";
echo '  Draft: '.BlogPost::query()->where('status', BlogPost::STATUS_DRAFT)->count()."\n";
