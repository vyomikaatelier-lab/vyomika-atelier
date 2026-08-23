<?php

/**
 * Revise 25 global blog articles for international English.
 * India-specific pricing article is lightly touched; India examples retained where relevant.
 *
 * Usage: php database/scripts/internationalize-global-blog-articles.php
 */

$articlesDir = dirname(__DIR__).'/content/blog/articles';

$globalReplacements = [
    'If you specify metal partitions, entrance doors or furniture for Indian interiors' => 'If you specify metal partitions, entrance doors or furniture for residential and commercial interiors',
    'Open-plan layouts are standard in contemporary Indian apartments, co-working floors and boutique retail' => 'Open-plan layouts are standard in contemporary apartments, co-working floors and boutique retail',
    'Modern Indian architecture increasingly favours' => 'Modern architecture in warm climates increasingly favours',
    'architects and developers seeking' => 'architects and developers seeking',
    'The weathering timeline in India' => 'The weathering timeline in practice',
    'Typical project scenarios in India' => 'Typical project scenarios',
    'Maintenance in Indian conditions' => 'Maintenance in humid and dusty conditions',
    'Indian family homes' => 'family homes',
    'Indian residential and commercial projects' => 'residential and commercial projects',
    'Indian residential projects' => 'residential projects',
    'Indian homes and hospitality fit-outs' => 'homes and hospitality fit-outs',
    'Indian homes, offices and hospitality fit-outs' => 'homes, offices and hospitality fit-outs',
    'Indian homes, offices and hospitality' => 'homes, offices and hospitality',
    'Indian homes and offices' => 'homes and offices',
    'Indian homes' => 'homes and apartments',
    'Indian apartments and villas' => 'apartments and villas',
    'Indian apartments' => 'apartments',
    'Indian interiors' => 'interior projects',
    'Indian projects' => 'projects',
    'Indian climate zones' => 'regional climate zones',
    'Indian climates' => 'varied climates',
    'Indian conditions' => 'local site conditions',
    'Indian sites' => 'site conditions',
    'Indian LED lighting' => 'project LED lighting',
    'Indian specification market' => 'international specification market',
    'construction dust in Indian sites is abrasive' => 'construction dust on active sites is abrasive',
    'Pan-India delivery is routine from our Delhi studio — allow realistic lead time for fabrication, QC photography and crating rather than assuming ready-stock timelines.' => 'We manufacture at our Delhi, India studio with delivery across India. International supply is arranged case-by-case — share project location via our <a href="/contact">contact page</a>. Allow realistic lead time for fabrication, QC photography and crating rather than assuming ready-stock timelines.',
    'Pan-India delivery from our Delhi studio' => 'delivery across India from our Delhi studio',
    'Pan-India delivery' => 'delivery across India',
    'For Delhi NCR projects, Vyomika Atelier can coordinate site visits when scope includes measurement-dependent metalwork.' => 'For projects in Delhi NCR, Vyomika Atelier can coordinate site visits when scope includes measurement-dependent metalwork. Other regions may share dimensioned surveys and photos for remote quoting.',
    'In Delhi NCR, Mumbai and Bangalore, developers often deliver' => 'In major metropolitan markets — including Delhi NCR, Mumbai and Bangalore — developers often deliver',
    'Partition specifications in India often list' => 'Partition specifications often list',
    'Homeowners and project managers often ask for a single square-foot rate for PVD partitions. In practice, bespoke metal-and-glass screens are priced as assemblies: frame profile, finish, glass specification, hardware, transport and installation each move the total. This guide explains the variables Indian fabricators use so you can prepare budgets and compare quotations fairly — without relying on generic online price tables that omit site reality.' => 'Homeowners and project managers often ask for a single square-foot rate for PVD partitions. In practice, bespoke metal-and-glass screens are priced as assemblies: frame profile, finish, glass specification, hardware, transport and installation each move the total. This guide explains the variables fabricators use in India so you can prepare budgets and compare quotations fairly — without relying on generic online price tables that omit site reality.',
    'under our climate zones, from dry North India to humid coasts' => 'under different climate zones — from dry interiors to humid coasts',
    'through the first monsoon cycles in India' => 'through the first heavy-rain cycles',
    'less common in residential India' => 'less common in typical residential entries',
    'Main entrance doors in premium Indian projects combine' => 'Main entrance doors in premium residential and hospitality projects combine',
    'Slim-profile door systems trade bulky frames for minimal metal lines — often PVD-coated stainless with glass infills. In Indian apartments and villas, the choice between hinged, sliding and telescopic opening affects daily use, maintenance and how much wall length you sacrifice. This comparison helps architects and homeowners align hardware with room size, traffic and dust conditions common across the country.' => 'Slim-profile door systems trade bulky frames for minimal metal lines — often PVD-coated stainless with glass infills. In apartments and villas, the choice between hinged, sliding and telescopic opening affects daily use, maintenance and how much wall length you sacrifice. This comparison helps architects and homeowners align hardware with room size, traffic and dust conditions.',
    'Stainless steel railings anchor safety on staircases, balconies and mezzanines across Indian residential and commercial projects.' => 'Stainless steel railings anchor safety on staircases, balconies and mezzanines across residential and commercial projects.',
    'Copying an interior railing specification to an exterior terrace is a common mistake in Indian residential projects.' => 'Copying an interior railing specification to an exterior terrace is a common mistake in residential projects.',
    'Environmental differences across India' => 'Environmental differences by exposure',
    'Interior stair railings in air-conditioned Delhi apartments see mild variation. Sea-facing balconies in Mumbai or Chennai encounter salt aerosol and driving rain.' => 'Interior stair railings in air-conditioned spaces see mild variation. Sea-facing balconies in coastal cities encounter salt aerosol and driving rain.',
    'Terrace railings in Bangalore may fog overnight then dry quickly — condensation cycles affect fixings and sealants differently than fully interior runs.' => 'Terrace railings in highland or humid cities may fog overnight then dry quickly — condensation cycles affect fixings and sealants differently than fully interior runs.',
    'A practical specification workflow for architects briefing custom metal fabrication in India' => 'A practical specification workflow for architects briefing custom metal fabrication',
    'for custom metalwork projects in India' => 'for custom metalwork projects',
    'how custom metal packages move through measurement, fabrication, QC and delivery in India' => 'how custom metal packages move through measurement, fabrication, QC and delivery from our Delhi studio',
    'Vyomika Atelier supports architects through drawing review, finish sampling and labelled delivery from our Delhi studio to sites across India.' => 'Vyomika Atelier supports architects through drawing review, finish sampling and labelled delivery from our Delhi, India studio to sites across India and selected export projects.',
];

$indiaOnlySlugs = [
    'pvd-partition-price-in-india-what-determines-final-cost',
];

$files = glob($articlesDir.'/*.php');
$updated = 0;

foreach ($files as $file) {
    $slug = basename($file, '.php');
    if (str_starts_with($slug, 'india-') || str_starts_with($slug, 'uk-') || str_starts_with($slug, 'uae-')) {
        continue;
    }

    $data = require $file;
    if (! is_array($data) || ! isset($data['content'])) {
        continue;
    }

    $content = $data['content'];
    $original = $content;

    if (! in_array($slug, $indiaOnlySlugs, true)) {
        foreach ($globalReplacements as $from => $to) {
            $content = str_replace($from, $to, $content);
        }

        if (! str_contains($content, 'Delhi, India studio') && str_contains($content, 'Vyomika Atelier')) {
            $internationalNote = '<p><em>Vyomika Atelier manufactures architectural metalwork at our Delhi, India studio. Technical guidance in this article applies internationally; delivery, pricing and site services vary by region — use our <a href="/contact">contact page</a> for project-specific enquiries.</em></p>';
            if (! str_contains($content, 'manufactures architectural metalwork at our Delhi')) {
                $content = preg_replace('/^(<p>)/', $internationalNote.'$1', $content, 1);
            }
        }
    } else {
        $content = str_replace(
            'This guide explains the variables fabricators use in India',
            'This guide explains the variables Indian fabricators use',
            $content
        );
    }

    if ($content !== $original) {
        $data['content'] = $content;
        $export = var_export($data, true);
        file_put_contents($file, "<?php\n\nreturn {$export};\n");
        $updated++;
        echo "Updated: {$slug}\n";
    }
}

echo "Done. {$updated} article(s) revised.\n";
