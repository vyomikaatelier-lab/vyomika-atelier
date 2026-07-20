<?php

/**
 * Sync finish swatch config + admin overrides into public/data/finishes.json
 * for static preview (preview.html + preview-router.js).
 */
$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payload = [
    'swatches' => \App\Support\FinishSwatches::forJsonExport(),
    'generated_at' => date('c'),
];

file_put_contents(
    $root.'/public/data/finishes.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'Exported finishes.json ('.count($payload['swatches'])." swatches)\n";
