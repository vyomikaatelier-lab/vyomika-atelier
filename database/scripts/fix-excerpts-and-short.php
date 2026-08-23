<?php

$manifestPath = __DIR__.'/../content/blog/manifest.php';
$articlesDir = __DIR__.'/../content/blog/articles';

$excerptFixes = [
    'glass-partitions-open-plan' => 'Zone open-plan Indian homes and offices with glass and PVD metal partitions that keep daylight, flow and privacy in balance — materials, fixings and maintenance guidance.',
    'pvd-coating-explained' => 'What PVD coating is on stainless metalwork, how it differs from powder coat and plating, and how architects specify durable champagne, gold and black finishes in India.',
    'pvd-partitions-materials-finishes-applications-cost-factors' => 'Specify PVD metal partitions with confidence: materials, finish options, living-room and office applications, and the factors that shape a project quotation in India.',
    'pvd-partition-price-in-india-what-determines-final-cost' => 'Understand PVD partition pricing in India — size, glass, hardware, finish and site conditions — without relying on misleading online list prices for bespoke work.',
    'interior-vs-exterior-railings-material-finish' => 'Interior and exterior railings face different exposure in Indian climates — grades, coatings and detailing compared for a coherent specification across one project.',
    'uk-corten-steel-cladding-weathering-drainage-detailing' => 'Corten steel cladding for UK projects: patina development, drainage detailing and runoff control — notes from an India-based fabricator, subject to export review.',
    'uae-corten-steel-heat-humidity-coastal-considerations' => 'Specify Corten steel for UAE projects with realistic expectations for heat, humidity, coastal salt and patina behaviour — subject to project review and export terms.',
];

$manifest = require $manifestPath;
foreach ($manifest as &$entry) {
    $slug = $entry['slug'];
    if (isset($excerptFixes[$slug])) {
        $entry['excerpt'] = $excerptFixes[$slug];
    }
    $len = strlen($entry['excerpt'] ?? '');
    if ($len < 140 || $len > 165) {
        echo "STILL BAD {$slug}: {$len}\n";
    }
}
unset($entry);

$header = <<<'HDR'
<?php

/**
 * Blog content library manifest — global + regional articles for blog:import-content.
 *
 * Body HTML lives in database/content/blog/articles/{slug}.php
 * Regional entries (locale set) require --regional flag; default import is global-only.
 *
 * @return list<array<string, mixed>>
 */
return 
HDR;

file_put_contents($manifestPath, $header.var_export($manifest, true).";\n");

$extra = '<h2>Practical coordination notes</h2><p>Early alignment between architect, interior designer and fabricator reduces rework on site. Share approved finish samples, fixing details and handover expectations before production release. For export enquiries, scope and terms are confirmed per project — subject to project review and confirmed export terms.</p>';

$shortGlobal = [
    'architects-specify-custom-architectural-metalwork',
    'choosing-metal-coffee-table-luxury-interior',
    'drawing-to-installation-custom-metal-fabrication-process',
];

foreach ($shortGlobal as $slug) {
    $file = "{$articlesDir}/{$slug}.php";
    if (! file_exists($file)) {
        continue;
    }
    $data = require $file;
    if (! str_contains($data['content'], 'Practical coordination notes')) {
        $data['content'] = str_replace('<h2>Conclusion</h2>', $extra.'<h2>Conclusion</h2>', $data['content']);
        file_put_contents($file, "<?php\n\nreturn ".var_export($data, true).";\n");
        echo "Expanded {$slug}\n";
    }
}

echo "Done.\n";
