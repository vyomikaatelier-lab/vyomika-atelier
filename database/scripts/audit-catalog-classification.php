<?php

/**
 * Audit catalog classification without Laravel bootstrap.
 * Run: php database/scripts/audit-catalog-classification.php
 */
$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

if (! class_exists(\App\Support\ProductCatalog::class)) {
    // Minimal bootstrap for database_path()
    if (! function_exists('database_path')) {
        function database_path(string $path = ''): string
        {
            global $root;

            return $root.'/database'.($path ? '/'.$path : '');
        }
    }

    require_once $root.'/app/Support/StorefrontRoutes.php';
    require_once $root.'/app/Support/ProductCatalog.php';
}

$map = \App\Support\ProductCatalog::slugMap();

$shop = [];
$studio = [];

foreach ($map as $slug => $meta) {
    if ($meta['section'] === 'shop') {
        $shop[$meta['shop_category']][] = $slug;
    } else {
        $studio[$meta['service_slug']][] = $slug;
    }
}

echo "Total products: ".count($map).PHP_EOL.PHP_EOL;

echo "=== SHOP ===".PHP_EOL;
foreach ($shop as $cat => $slugs) {
    echo $cat.': '.count($slugs).PHP_EOL;
}

echo PHP_EOL."=== STUDIO ===".PHP_EOL;
foreach ($studio as $service => $slugs) {
    echo $service.': '.count($slugs).PHP_EOL;
}

echo PHP_EOL."=== SPOT CHECK ===".PHP_EOL;
$checks = [
    'slim-hinged-suite-door' => ['studio', 'slim-profile-door-system'],
    'pvd-door-pull-handle' => ['shop', 'door-handles'],
    'champagne-wave-partition' => ['studio', 'partitions'],
    'brushed-brass-coffee-table' => ['shop', 'coffee-tables'],
];
foreach ($checks as $slug => [$section, $parent]) {
    $m = $map[$slug] ?? null;
    $ok = $m && $m['section'] === $section
        && ($section === 'shop' ? $m['shop_category'] === $parent : $m['service_slug'] === $parent);
    echo ($ok ? 'OK' : 'FAIL')."  {$slug} → ".json_encode($m).PHP_EOL;
}
