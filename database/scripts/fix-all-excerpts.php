<?php

$manifestPath = __DIR__.'/../content/blog/manifest.php';

$excerpts = [
    'glass-partitions-open-plan' => 'Zone open-plan Indian homes and offices with glass and PVD metal partitions that keep daylight, flow and privacy in balance — materials, fixings and maintenance.',
    'pvd-coating-explained' => 'What PVD coating is on stainless metalwork, how it differs from powder coat and plating, and how architects specify durable champagne, gold and black finishes.',
    'corten-steel-modern-facades' => 'Why weathering steel suits modern Indian facades: patina character, design uses, drainage detailing and specification notes for architects and developers.',
    'pvd-partitions-materials-finishes-applications-cost-factors' => 'Specify PVD metal partitions with confidence: materials, finish options, living-room and office applications, and factors that shape a project quotation in India.',
    'pvd-partition-price-in-india-what-determines-final-cost' => 'Understand PVD partition pricing in India — size, glass, hardware, finish and site conditions — without misleading online list prices for bespoke metalwork.',
    'pvd-partitions-vs-powder-coated-metal-partitions' => 'Compare PVD and powder-coated metal partitions for appearance, touch durability and maintenance when specifying interior metalwork for Indian homes and offices.',
    'how-to-select-metal-partition-for-living-room' => 'Plan a living-room metal partition for privacy, light and proportion — glass choices, PVD finishes and circulation clearances for Indian apartments and villas.',
    'slim-profile-doors-hinged-sliding-telescopic-compared' => 'Compare slim profile hinged, sliding and telescopic door systems for Indian homes — space planning, glass options and hardware selection for interior openings.',
    'fluted-glass-slim-profile-doors-design-privacy-guide' => 'Use fluted and reeded glass with slim profile doors for privacy and light diffusion in bathrooms, bedrooms and office cabins across Indian interior projects.',
    'how-to-choose-luxury-main-entrance-door' => 'Choose a luxury main entrance door for Indian residences — proportion, PVD finishes, security hardware and coordination with the building facade and landscape.',
    'stainless-steel-glass-etched-entrance-doors-compared' => 'Compare stainless, glass-forward and etched entrance doors for Indian homes — visual weight, privacy levels and long-term maintenance in varied climates.',
    'stainless-steel-railings-types-finishes-selection-guide' => 'Select stainless steel railings for staircases and balconies — glass, bar and panel systems with finish guidance for Indian residential and commercial projects.',
    'glass-railings-staircases-balconies-planning-checklist' => 'Plan glass staircase and balcony railings with a practical checklist — site measurements, posts, glass type and installation sequencing for Indian homes.',
    'interior-vs-exterior-railings-material-finish' => 'Interior and exterior railings face different exposure in Indian climates — grades, coatings and detailing compared for a coherent specification on one project.',
    'what-is-corten-steel-and-how-does-it-weather' => 'Learn what Corten weathering steel is, how its protective patina forms, and what to expect during the first seasons on Indian architectural and landscape projects.',
    'corten-steel-facades-design-drainage-weathering' => 'Design Corten steel facades for Indian sites with correct drainage, panel joints and runoff control — practical notes for architects, contractors and developers.',
    'corten-steel-cladding-vs-conventional-painted-steel' => 'Corten cladding versus painted steel — compare appearance, upkeep, runoff behaviour and when each suits Indian architectural metalwork on facades and features.',
    'reduce-rust-run-off-staining-around-corten-steel' => 'Reduce early rust run-off staining near Corten steel with drip edges, landscape buffers and temporary protection on stone, paving and adjacent finishes.',
    'pvd-door-handles-finishes-sizes-selection-guide' => 'Select PVD door handles for main doors and interiors — finish colours, pull lengths, backplate options and daily maintenance in Indian homes and offices.',
    'select-pull-handle-length-for-main-door' => 'Match pull handle length to main door height and visual balance — ergonomic grip zones and proportion rules for Indian entrance doors and villa gates.',
    'pvd-furniture-care-finishes-customization' => 'Care for PVD metal furniture and brief custom consoles and tables — finish handling, cleaning and customisation options from our Delhi manufacturing studio.',
    'choosing-metal-coffee-table-luxury-interior' => 'Choose a metal coffee table for luxury living rooms — scale, PVD finish, glass tops and circulation clearances in Indian homes and hospitality lounges.',
    'pvd-finish-selection-guide-gold-rose-gold-champagne-black' => 'Compare gold, rose gold, champagne and black PVD finishes for partitions, doors and furniture — keep colour consistent across one Indian interior project.',
    'architects-specify-custom-architectural-metalwork' => 'A specification workflow for architects briefing custom metal fabrication in India — drawings, finishes, tolerances, site coordination and handover documentation.',
    'drawing-to-installation-custom-metal-fabrication-process' => 'From approved drawings to site installation — how custom metal packages move through measurement, fabrication, QC and delivery from our Delhi studio.',
    'india-pvd-partition-prices-materials-size-installation' => 'Understand PVD partition pricing in India: materials, panel size, glass specification, PVD finish and installation factors that shape a fair project quotation.',
    'india-glass-railing-price-quotation-factors' => 'Glass railing prices in India depend on run length, glass type, post system, PVD finish and site access — factors to prepare before requesting a project quote.',
    'india-choosing-metal-partitions-homes-apartments' => 'Select metal partitions for Indian homes and apartments: open-plan zoning, PVD finishes, glass privacy and circulation clearances that suit local apartment layouts.',
    'uk-metal-room-dividers-interiors-specification-guide' => 'Design and specify metal room dividers for UK interiors — glazed partition walls, PVD finishes and coordination notes for India-manufactured bespoke metalwork.',
    'uk-slimline-internal-glass-doors-hinged-sliding-fixed' => 'Compare slimline internal glass doors for UK homes — hinged, sliding and fixed glazed panels with minimal PVD metal frames from an India-based fabricator.',
    'uk-corten-steel-cladding-weathering-drainage-detailing' => 'Corten steel cladding for UK projects: patina development, drainage detailing and runoff control — notes from an India-based fabricator, subject to export review.',
    'uae-pvd-stainless-steel-interiors-finishes-applications' => 'PVD stainless steel for UAE interiors: finish options, high-touch applications and specification notes for India-manufactured metalwork, subject to project review.',
    'uae-glass-metal-partitions-dubai-offices-villas' => 'Glass and metal partitions for Dubai offices and villas — open-plan zoning, PVD frames and export supply considerations from an India-based manufacturer.',
    'uae-corten-steel-heat-humidity-coastal-considerations' => 'Specify Corten steel for UAE projects with realistic expectations for heat, humidity, coastal salt and patina — subject to project review and export terms.',
];

$manifest = require $manifestPath;
$bad = 0;
foreach ($manifest as &$entry) {
    $slug = $entry['slug'];
    if (isset($excerpts[$slug])) {
        $entry['excerpt'] = $excerpts[$slug];
    }
    $len = strlen($entry['excerpt'] ?? '');
    if ($len < 140 || $len > 165) {
        echo "BAD {$slug}: {$len} — {$entry['excerpt']}\n";
        $bad++;
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
echo $bad === 0 ? "All excerpts valid.\n" : "{$bad} excerpts still invalid.\n";
