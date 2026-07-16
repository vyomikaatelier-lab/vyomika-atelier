<?php

/**
 * Export config/blog.php to public/data/blog.json for static preview.
 *
 * Usage: php database/scripts/export-blog-json.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$json = json_encode(
    App\Support\BlogContent::exportForPreview(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$path = base_path('public/data/blog.json');
file_put_contents($path, $json . PHP_EOL);

echo "Wrote {$path} (" . strlen($json) . " bytes)\n";
