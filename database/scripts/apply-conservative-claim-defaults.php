<?php

/** Apply conservative claim defaults to 25 global blog articles. Run once. */

$articlesDir = dirname(__DIR__).'/content/blog/articles';
$manifest = require dirname(__DIR__).'/content/blog/manifest.php';

$globalSlugs = [];
foreach ($manifest as $entry) {
    if (empty($entry['locale'])) {
        $globalSlugs[] = $entry['slug'];
    }
}

$replacements = [
    'Request material certificates and gauge confirmation on delivery — thickness affects stiffness on tall fins.' => 'Confirm gauge and material specification on delivery documentation where provided — thickness affects stiffness on tall fins.',
    'Request material certificates' => 'Confirm material specification where documentation is provided',
    'Do not cite UK Building Regulations, UAE Civil Defence or similar unless project-specific documentation exists.' => 'Refer statutory compliance to the project consultant of record — do not cite regional regulations unless project-specific documentation exists.',
    'Do not cite UK Building Regulations' => 'Refer UK compliance to the project consultant of record',
    'Do not cite UK' => 'Refer UK compliance to the project consultant of record — do not cite',
    'UK install coordination are project-specific' => 'international coordination is reviewed project by project',
    'UK install coordination' => 'international coordination reviewed project by project',
    'installation team or client contractor fixes per drawing' => 'client contractor or agreed installer fixes per drawing',
    'installation team or client contractor' => 'client contractor or agreed installer',
    'We supply PVD handles matched to our door and partition projects from Delhi, shipping pan-India.' => 'We supply PVD handles matched to our door and partition projects from our Delhi, India studio. International enquiries are reviewed project by project.',
    'Vyomika Atelier builds to your dimensions from Delhi with pan-India delivery.' => 'Vyomika Atelier builds to your dimensions from our Delhi, India studio with delivery across India. International enquiries are reviewed project by project.',
    'Vyomika Atelier advises pull length with door orders from our Delhi studio for pan-India clients' => 'Vyomika Atelier advises pull length with door orders from our Delhi, India studio',
    'Vyomika Atelier includes drip recommendations on shop drawings from our Delhi studio for pan-India Corten installs.' => 'Vyomika Atelier includes drip recommendations on shop drawings from our Delhi, India studio.',
    'Vyomika Atelier advises on Corten feature fabrication from Delhi with pan-India site coordination' => 'Vyomika Atelier advises on Corten feature fabrication from our Delhi, India studio',
    'lead time depends on stone sourcing and finish batch.' => 'programme depends on stone sourcing and finish batch — share scope for project-specific timing.',
    'lead time depends on stone sourcing' => 'programme depends on stone sourcing',
    'without promising catalogue rates' => 'without promising fixed catalogue rates',
    'without generic guarantees' => 'without open-ended guarantees beyond documented scope',
];

foreach ($globalSlugs as $slug) {
    $path = "{$articlesDir}/{$slug}.php";
    if (! file_exists($path)) {
        continue;
    }
    $data = require $path;
    $content = $data['content'] ?? '';
    $original = $content;
    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    if ($content !== $original) {
        $data['content'] = $content;
        file_put_contents($path, "<?php\n\nreturn ".var_export($data, true).";\n");
        echo "Claim-softened: {$slug}\n";
    }
}

echo "Done.\n";
