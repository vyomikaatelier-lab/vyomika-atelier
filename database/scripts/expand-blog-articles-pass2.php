<?php

/**
 * Second expansion pass — adds slug-aware depth sections before Conclusion.
 */

$articlesDir = dirname(__DIR__).'/content/blog/articles';

function insertBeforeConclusion(string $html, string $insert): string
{
    $marker = '<h2>Conclusion</h2>';

    return str_contains($html, $marker)
        ? str_replace($marker, $insert.$marker, $html)
        : $html.$insert;
}

function block(string $title, array $paras): string
{
    $html = '<h2>'.$title.'</h2>';
    foreach ($paras as $p) {
        $html .= '<p>'.$p.'</p>';
    }

    return $html;
}

$clusterSections = [
    'PVD PARTITIONS' => block('Typical project scenarios in India', [
        'Boutique retail on high streets often uses PVD partitions to create VIP consultation zones without losing window display continuity. Office reception desks use half-height glass with champagne frames to separate waiting seating from the corridor.',
        'Residential open kitchens benefit from grease-resistant stainless frames paired with easy-clean glass; specify clearance to hob zones and confirm whether the partition sits in a fire-rated path with your consultant.',
        'Mandir and puja room screening appears frequently in apartment briefs — combine fluted glass with solid lower panels if daily rituals require visual privacy without darkening the adjacent living area.',
    ]).block('Handover checklist for partition packages', [
        'Receive labelled panels, fixings and a layout drawing keyed to room names. Confirm glass is tempered where required and edges are arrised safely.',
        'Review cleaning instructions for PVD faces and glass coatings before contractor snagging closes access.',
        'Schedule a walk-through with the client to demonstrate track adjustment on sliding leaves where applicable.',
    ]),
    'SLIM PROFILE/ENTRANCE DOORS' => block('Climate and exposure considerations', [
        'North India dust storms deposit fine grit in door tracks — specify removable track covers for terrace sliders and plan quarterly cleaning in handover manuals.',
        'Monsoon driving rain tests threshold drainage on entrance doors; coordinate drip grooves with civil works before stone thresholds are cut.',
        'Coastal projects should confirm hardware corrosion class and glass edge sealing against salt mist — even inland cities like Mumbai benefit from conservative specifications on sea-facing apartments.',
    ]).block('Security and hardware coordination', [
        'Main entrance doors should be briefed alongside lock bodies, cylinders and digital access if applicable. Pull handles must clear knuckle space when multipoint locks throw.',
        'Fire-rated corridors may restrict glass area — verify with local fire consultants before approving full-height glazed entrances in commercial lobbies.',
        'Soft-close and self-closing devices need seasonal adjustment; include maintenance contacts in handover packs.',
    ]),
    'RAILINGS' => block('Compliance and safety planning', [
        'Railings must satisfy applicable building codes for height, climbability and glass impact requirements. Share staircase sections early so guard heights suit tread geometry on winders.',
        'Child-safe gaps are a priority in family homes — horizontal cables may be restricted in some jurisdictions; vertical bars or glass panels are common alternatives.',
        'Load assumptions for crowded terraces should be stated on shop drawings; event venues may need higher design loads than residential balconies.',
    ]).block('Finishing adjacent trades', [
        'Glass railing templates often dictate stone floor cutting — sequence site work so flooring contractors attend template visits.',
        'Paint touch-up near weld zones should happen after railing installation to avoid overspray on stainless or PVD handrails.',
        'Exterior railings may require earthing coordination when integrated with lightning protection on high-rise towers — confirm with MEP consultants.',
    ]),
    'CORTEN STEEL' => block('Client communication during weathering', [
        'Set expectations that Corten will look uneven for the first season. Share reference photos of early, mid and mature patina rather than only hero shots of fully weathered European projects.',
        'Explain that runoff staining on paving is temporary if detailing is correct — but not zero-risk on porous sandstone.',
        'Interior Corten feature walls do not need full weathering cycles but may still carry metallic odour briefly after installation; ventilate during snagging.',
    ]).block('Fabrication quality markers', [
        'Look for clean fold radii without micro-cracking on outer faces, consistent weld grinding and labelled panel orientation for site crews.',
        'Request material certificates and gauge confirmation on delivery — thickness affects stiffness on tall fins.',
        'Pre-weathered panels may be specified for visible courtyards where adjacent finishes cannot tolerate any run-off period.',
    ]),
    'PVD HARDWARE/FURNITURE' => block('Coordination with adjacent finishes', [
        'PVD handles should be sampled against door lacquer, stone and curtain fabric under project lighting. Warm LEDs shift champagne toward gold; cool LEDs read greyer.',
        'Furniture PVD legs contact floor protectors — specify felt or adjustable feet to avoid scratching engineered wood and large-format tile.',
        'Open shelving in humid cities benefits from ventilation gaps behind units; do not trap moisture against walls.',
    ]).block('Customisation boundaries', [
        'Custom furniture succeeds when overall dimensions, top material and finish are fixed before structural welding. Late changes to stone thickness alter leg heights and bracket positions.',
        'Batch production of identical hotel room pieces requires prototype approval before full run — photograph QC panels for client records.',
        'Replacement parts policy should be agreed for high-traffic hospitality — handles and edge guards are wear items over years, not manufacturing defects.',
    ]),
    'ARCHITECTURAL METALWORK' => block('Drawing packages that fabricators need', [
        'Plan, elevation and section at meaningful scale plus interface details to adjacent trades. Photographs help but do not replace dimensions on irregular existing buildings.',
        'Note tolerances realistically — site-built openings in older Delhi colonies may be out of plumb; allow adjustment shims in design.',
        'Identify visible versus concealed faces so grinding effort focuses where occupants see quality.',
    ]).block('Professional collaboration workflow', [
        'Vyomika Atelier supports architects through drawing review, finish sampling and labelled delivery from our Delhi studio to sites across India.',
        'Early involvement reduces RFIs during civil works — especially for embedded tracks, conduits behind Corten screens and floor inserts for pivot doors.',
        'Use the <a href="/professionals">professionals page</a> to share practice details and receive specification support on active tenders.',
    ]),
];

$slugCluster = [];
$manifest = require dirname(__DIR__).'/content/blog/manifest.php';
foreach ($manifest as $entry) {
    $slugCluster[$entry['slug']] = $entry['cluster'] ?? 'ARCHITECTURAL METALWORK';
}

foreach (glob("{$articlesDir}/*.php") as $file) {
    $slug = basename($file, '.php');
    $cluster = $slugCluster[$slug] ?? 'ARCHITECTURAL METALWORK';
    $insert = $clusterSections[$cluster] ?? $clusterSections['ARCHITECTURAL METALWORK'];
    $data = require $file;
    $data['content'] = insertBeforeConclusion($data['content'], $insert);
    file_put_contents($file, "<?php\n\nreturn ".var_export($data, true).";\n");
    echo basename($file).': '.str_word_count(strip_tags($data['content']))." words\n";
}
