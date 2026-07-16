<?php

/**
 * Sync config/site.php + catalogs into public/data/site-content.json for static preview.
 * Run: php database/scripts/export-site-json.php
 */
$root = dirname(__DIR__, 2);

$existing = json_decode(file_get_contents($root . '/public/data/site-content.json'), true) ?: [];
$config = require $root . '/config/site.php';

$nav = array_map(function ($item) {
    if (! empty($item['children'])) {
        return [
            'label' => $item['label'],
            'children' => array_map(function ($child) {
                if (isset($child['route'])) {
                    $params = $child['params'] ?? [];
                    $href = match ($child['route']) {
                        'shop.index' => '/shop' . (isset($params['category']) ? '?category=' . $params['category'] : ''),
                        'services.show' => '/services/' . ($params['slug'] ?? ''),
                        'collections.mirror-frames.index' => '/collections/mirror-frames',
                        default => '/' . str_replace('.index', '', str_replace('.', '/', $child['route'])),
                    };

                    return ['label' => $child['label'], 'href' => $href];
                }

                return $child;
            }, $item['children']),
        ];
    }

    if (isset($item['route'])) {
        $params = $item['params'] ?? [];
        $href = match ($item['route']) {
            'shop.index' => '/shop',
            'services.show' => '/services/' . ($params['slug'] ?? ''),
            'services.index' => '/services',
            'projects.index' => '/projects',
            'blog.index' => '/blog',
            'about' => '/about',
            'professionals.index' => '/professionals',
            'studio.railings' => '/studio/railings',
            'collections.mirror-frames.index' => '/collections/mirror-frames',
            default => '/' . str_replace('.index', '', str_replace('.', '/', $item['route'])),
        };

        return ['label' => $item['label'], 'href' => $href];
    }

    return $item;
}, $config['nav'] ?? []);

$catalog = require $root . '/database/data/projects-catalog.php';
$years = [2024, 2025, 2026, 2023, 2025, 2024, 2026, 2025];
foreach ($catalog as $i => &$p) {
    $p['year'] = (string) ($years[$i] ?? 2025);
}
unset($p);

$mergeKeys = [
    'brand', 'announcement', 'hero', 'category_banners', 'trending',
    'spotlights', 'cta', 'testimonials', 'blog', 'trust_badges', 'footer',
    'featured_product', 'shop',
];

foreach ($mergeKeys as $key) {
    if (isset($config[$key])) {
        $existing[$key] = $config[$key];
    }
}

$existing['nav'] = $nav;
$existing['portfolio'] = $catalog;
$existing['team'] = $config['team'] ?? ($existing['team'] ?? []);
$existing['projects_page'] = require $root . '/config/projects.php';

if (file_exists($root . '/public/data/blog.json')) {
    $existing['blog_full'] = json_decode(file_get_contents($root . '/public/data/blog.json'), true);
}

file_put_contents(
    $root . '/public/data/site-content.json',
    json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "Exported site-content.json\n";
