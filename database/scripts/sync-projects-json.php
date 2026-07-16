<?php

$root = dirname(__DIR__, 2);
$site = json_decode(file_get_contents($root . '/public/data/site-content.json'), true);
$catalog = require $root . '/database/data/projects-catalog.php';
$years = [2024, 2025, 2026, 2023, 2025, 2024, 2026, 2025];
foreach ($catalog as $i => &$p) {
    $p['year'] = (string) ($years[$i] ?? 2025);
}
unset($p);
$site['portfolio'] = $catalog;
$site['projects_page'] = require $root . '/config/projects.php';
file_put_contents(
    $root . '/public/data/site-content.json',
    json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
echo "Updated site-content.json\n";
