<?php

/**
 * Expands blog article bodies toward 1000+ words and adds manifest excerpts.
 * Run: php database/scripts/expand-blog-articles.php
 */

$articlesDir = dirname(__DIR__).'/content/blog/articles';
$manifestPath = dirname(__DIR__).'/content/blog/manifest.php';

$extraSections = [
    'glass-partitions-open-plan-without-compromise' => [
        ['Coordinating with MEP and ceiling design', [
            'Open-plan partitions often sit beneath exposed ductwork or feature ceilings. Confirm headroom for top tracks and whether sprinklers need repositioning before fixing ceiling anchors.',
            'Lighting designers frequently graze PVD frames with linear LED — coordinate diffuser placement so you do not cast harsh stripes across fluted glass at night.',
            'In apartments with VRV cassettes, avoid placing full-height glass directly under supply diffusers; condensation streaks are common in monsoon months.',
        ]],
        ['Working with interior designers and contractors', [
            'Issue partition shop drawings showing floor and ceiling fixings before civil finishes complete. Last-minute core drilling through newly laid marble is expensive and risky.',
            'Share edge profiles with your flooring contractor so expansion gaps align with channel widths.',
            'Photograph mock-up panels on site under daytime and evening light before batch fabrication — PVD and glass both shift appearance with colour temperature.',
        ]],
        ['When to choose sliding versus fixed glass panels', [
            'Sliding glass partitions suit pantry closures and flexible meeting rooms. Hinged doors within a glass wall improve acoustic separation for bedrooms.',
            'Telescopic stacking is rarely needed indoors at residential scale but appears in large penthouse living rooms opening to terraces — coordinate with our <a href="/studio/slim-profile-door-systems">slim profile door systems</a> when indoor-outdoor continuity matters.',
        ]],
    ],
    'default' => [
        ['Site measurement and documentation', [
            'Accurate field dimensions prevent costly remakes. For Delhi NCR projects, Vyomika Atelier can coordinate site visits when scope includes measurement-dependent metalwork.',
            'Photograph adjacent finishes — stone, paint, veneer — alongside PVD samples so clients approve contrast in real conditions, not only on screen.',
            'Record floor levelness and ceiling plane deviations; slim frames show gaps quickly if substrates are out of tolerance.',
        ]],
        ['Packaging, delivery and installation sequencing', [
            'Custom metalwork ships labelled by room and orientation. Plan installation after primary wet trades and before final soft furnishings where possible.',
            'Protect finished PVD faces with film until handover; construction dust in Indian sites is abrasive.',
            'Pan-India delivery is routine from our Delhi studio — allow realistic lead time for fabrication, QC photography and crating rather than assuming ready-stock timelines.',
        ]],
        ['Questions to ask your fabricator early', [
            'Which substrate grade and finish sample batch applies to the order?',
            'Are shop drawings included and how many revision rounds are anticipated?',
            'What installation support is available in your city versus supply-only delivery?',
            'How are transport damage and transit insurance handled for large glass-and-metal panels?',
        ]],
    ],
];

$excerpts = [
    'glass-partitions-open-plan-without-compromise' => 'Zone open-plan Indian homes and offices with glass and PVD metal partitions that keep daylight, flow and privacy in balance — materials, fixings and maintenance guidance.',
    'pvd-coating-explained-durable-metal-finishes' => 'What PVD coating is on stainless metalwork, how it differs from powder coat and plating, and how architects specify durable champagne, gold and black finishes in India.',
    'why-corten-steel-is-perfect-for-modern-facades' => 'Why weathering steel suits contemporary Indian façades — patina character, design applications, drainage detailing and specification notes for architects and developers.',
    'pvd-partitions-materials-finishes-applications-cost-factors' => 'Specify PVD metal partitions with confidence: materials, finish options, living-room and office applications, and the factors that shape a project quotation in India.',
    'pvd-partition-price-in-india-what-determines-final-cost' => 'Understand PVD partition pricing in India — size, glass, hardware, finish and site conditions — without relying on misleading online list prices.',
    'pvd-partitions-vs-powder-coated-metal-partitions' => 'Compare PVD and powder-coated metal partitions for appearance, touch durability and maintenance when specifying Indian interior metalwork.',
    'how-to-select-metal-partition-for-living-room' => 'Plan a living-room metal partition for privacy, light and proportion — glass choices, PVD finishes and circulation clearances for Indian apartments and villas.',
    'slim-profile-doors-hinged-sliding-telescopic-compared' => 'Compare slim profile hinged, sliding and telescopic door systems for Indian homes — space planning, glass options and hardware selection.',
    'fluted-glass-slim-profile-doors-design-privacy-guide' => 'Use fluted and reeded glass with slim profile doors for privacy and light diffusion in bathrooms, bedrooms and office cabins across Indian interiors.',
    'how-to-choose-luxury-main-entrance-door' => 'Choose a luxury main entrance door for Indian residences — proportion, PVD finishes, security hardware and coordination with the building façade.',
    'stainless-steel-glass-etched-entrance-doors-compared' => 'Compare stainless, glass-forward and etched entrance doors for Indian homes — visual weight, privacy levels and long-term maintenance.',
    'stainless-steel-railings-types-finishes-selection-guide' => 'Select stainless steel railings for staircases and balconies — glass, bar and panel systems with finish guidance for Indian residential and commercial projects.',
    'glass-railings-staircases-balconies-planning-checklist' => 'Plan glass staircase and balcony railings with a practical checklist — site measurements, posts, glass type and installation sequencing for Indian homes.',
    'interior-vs-exterior-railings-material-finish' => 'Interior and exterior railings face different exposure in Indian climates — grades, coatings and detailing compared for a coherent specification.',
    'what-is-corten-steel-and-how-does-it-weather' => 'Learn what Corten weathering steel is, how its protective patina forms, and what to expect during the first seasons on Indian architectural projects.',
    'corten-steel-facades-design-drainage-weathering' => 'Design Corten steel façades for Indian sites with correct drainage, panel joints and runoff control — practical notes for architects and contractors.',
    'corten-steel-cladding-vs-conventional-painted-steel' => 'Corten cladding versus painted steel — compare appearance, upkeep, runoff behaviour and when each suits Indian architectural metalwork.',
    'reduce-rust-run-off-staining-around-corten-steel' => 'Reduce early rust run-off staining near Corten steel with drip edges, landscape buffers and temporary protection on stone and paving.',
    'pvd-door-handles-finishes-sizes-selection-guide' => 'Select PVD door handles for main doors and interiors — finish colours, pull lengths, backplate options and daily maintenance in Indian homes.',
    'select-pull-handle-length-for-main-door' => 'Match pull handle length to main door height and visual balance — ergonomic grip zones and proportion rules for Indian entrance doors.',
    'pvd-furniture-care-finishes-customization' => 'Care for PVD metal furniture and brief custom consoles and tables — finish handling, cleaning and customisation options from our Delhi studio.',
    'choosing-metal-coffee-table-luxury-interior' => 'Choose a metal coffee table for luxury living rooms — scale, PVD finish, glass tops and circulation clearances in Indian homes.',
    'pvd-finish-selection-guide-gold-rose-gold-champagne-black' => 'Compare gold, rose gold, champagne and black PVD finishes for partitions, doors and furniture — keep colour consistent across one Indian project.',
    'architects-specify-custom-architectural-metalwork' => 'A specification workflow for architects briefing custom metal fabrication in India — drawings, finishes, tolerances and site coordination.',
    'drawing-to-installation-custom-metal-fabrication-process' => 'From approved drawings to site installation — how custom metal packages move through measurement, fabrication, QC and delivery in India.',
];

function insertBeforeConclusion(string $html, string $insert): string
{
    $marker = '<h2>Conclusion</h2>';

    if (! str_contains($html, $marker)) {
        return $html.$insert;
    }

    return str_replace($marker, $insert.$marker, $html);
}

function renderSections(array $sections): string
{
    $html = '';
    foreach ($sections as [$title, $paras]) {
        $html .= '<h2>'.$title.'</h2>';
        foreach ($paras as $p) {
            $html .= '<p>'.$p.'</p>';
        }
    }

    return $html;
}

foreach (glob("{$articlesDir}/*.php") as $file) {
    $slug = basename($file, '.php');
    $data = require $file;
    $sections = $extraSections[$slug] ?? $extraSections['default'];
    $data['content'] = insertBeforeConclusion($data['content'], renderSections($sections));
    $export = var_export($data, true);
    file_put_contents($file, "<?php\n\nreturn {$export};\n");
    $words = str_word_count(strip_tags($data['content']));
    echo "Expanded {$slug}: {$words} words\n";
}

$manifest = require $manifestPath;
foreach ($manifest as &$entry) {
    $slug = $entry['slug'];
    if (isset($excerpts[$slug])) {
        $entry['excerpt'] = $excerpts[$slug];
    }
}
unset($entry);

$export = var_export($manifest, true);
file_put_contents($manifestPath, "<?php\n\n/**\n * Blog content library manifest — 25 articles for blog:import-content.\n *\n * Body HTML lives in database/content/blog/articles/{slug}.php\n *\n * @return list<array<string, mixed>>\n */\nreturn {$export};\n");

echo "Updated manifest excerpts.\n";
