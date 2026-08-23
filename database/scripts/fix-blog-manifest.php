<?php

$m = require dirname(__DIR__).'/content/blog/manifest.php';

foreach ($m as &$entry) {
    if (($entry['cluster'] ?? '') === 'CORTEN STEEL') {
        unset($entry['related_service_slugs']);
    }

    if ($entry['slug'] === 'why-corten-steel-is-perfect-for-modern-facades') {
        $entry['image'] = 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg';
        $entry['hero_image_alt'] = 'Corten steel entrance screen with warm rust patina on a contemporary building facade';
        $entry['excerpt'] = 'Why weathering steel suits modern Indian facades: patina character, design uses, drainage detailing and specification notes for architects.';
    }

    if (isset($entry['excerpt']) && strlen($entry['excerpt']) > 165) {
        $entry['excerpt'] = rtrim(substr($entry['excerpt'], 0, 162), ' ,.;').'…';
    }
}
unset($entry);

$path = dirname(__DIR__).'/content/blog/manifest.php';
$content = "<?php\n\n/**\n * Blog content library manifest — 25 articles for blog:import-content.\n *\n * Body HTML lives in database/content/blog/articles/{slug}.php\n *\n * @return list<array<string, mixed>>\n */\nreturn ".var_export($m, true).";\n";
file_put_contents($path, $content);
echo "Manifest fixed.\n";
