<?php

/**
 * One-off generator for database/content/blog/articles/*.php
 * Run: php database/scripts/generate-blog-articles.php
 */

$articlesDir = dirname(__DIR__).'/content/blog/articles';
$snippets = require dirname(__DIR__).'/content/blog/_snippets.php';
$cta = $snippets['cta_contact'];

if (! is_dir($articlesDir)) {
    mkdir($articlesDir, 0755, true);
}

function section(string $title, array $paras): string
{
    $html = '<h2>'.$title.'</h2>';
    foreach ($paras as $p) {
        $html .= '<p>'.$p.'</p>';
    }

    return $html;
}

function article(string $intro, array $sections, string $conclusion, array $links): string
{
    global $cta;
    $html = '<p>'.$intro.'</p>';
    foreach ($sections as $s) {
        $html .= section($s[0], $s[1]);
    }
    $html .= '<h2>Conclusion</h2><p>'.$conclusion.'</p>';
    foreach ($links as $l) {
        $html .= '<p>'.$l.'</p>';
    }
    $html .= $cta;

    return $html;
}

function writeArticle(string $slug, array $data): void
{
    global $articlesDir;
    $export = var_export([
        'content' => $data['content'],
        'faq' => $data['faq'] ?? [],
    ], true);

    $php = "<?php\n\nreturn {$export};\n";
    file_put_contents("{$articlesDir}/{$slug}.php", $php);
    echo "Wrote {$slug}.php\n";
}

// ── Article definitions ────────────────────────────────────────────────

writeArticle('glass-partitions-open-plan-without-compromise', [
    'content' => article(
        'Open-plan layouts are standard in contemporary Indian apartments, co-working floors and boutique retail — yet most briefs still need quiet zones, visual privacy and acoustic separation without building full-height masonry walls. Glass partitions framed in PVD-coated stainless offer a practical middle path: they preserve daylight, maintain sightlines and define circulation while keeping the room feeling generous.',
        [
            ['Why glass partitions suit open-plan Indian interiors', [
                'In Delhi NCR, Mumbai and Bangalore, developers often deliver large uninterrupted floor plates. Homeowners want living and dining to read as one volume; offices need meeting pockets within agile layouts. A fixed glass and metal screen lets you <strong>zone without boxing in</strong> the space.',
                'The frame carries structural loads and finish language; the glass carries transparency, diffusion or pattern. Together they behave like furniture at architectural scale — movable in concept even when fixed in place.',
            ]],
            ['Frame materials and PVD finishes', [
                'Grade 304 stainless remains the default for interior partitions in humid climates. PVD (Physical Vapour Deposition) adds champagne, rose gold, matte black or brushed tones without the wear issues common to lacquered brass. Match the partition finish to <a href="/studio/pvd-partitions">PVD partition systems</a>, entrance doors and railings on the same project for a coherent palette.',
                'Powder-coated frames suit budget-driven back-of-house zones; PVD is often specified where touch surfaces and visual quality matter in client-facing areas.',
            ]],
            ['Glass types: clear, fluted, tinted and laminated', [
                '<strong>Clear tempered glass</strong> maximises openness between living and dining or between reception and lounge seating.',
                '<strong>Fluted or reeded glass</strong> softens views — useful between a home office and family area, or outside a conference room. See our guide to <a href="/blog/fluted-glass-slim-profile-doors-design-privacy-guide">fluted glass with slim profiles</a> for privacy behaviour.',
                '<strong>Tinted or bronze glass</strong> reduces glare where western sun hits a partition.',
                '<strong>Laminated glass</strong> improves acoustic performance and safety; specify thickness with your fabricator early.',
            ]],
            ['Planning height, width and fixing methods', [
                'Partial-height partitions (1.2–1.8 m) define zones while keeping ceiling continuity — popular in lofts and double-height lobbies. Full-height systems to the soffit improve acoustic separation for cabins and bedrooms.',
                'Fixings include floor channels, ceiling tracks, wall anchors or freestanding weighted bases for flexible co-working. Confirm slab penetration rules with your structural engineer before core drilling in multi-storey buildings.',
                'Allow 900–1 050 mm clear width for primary passages; narrower gaps feel restrictive in Indian family homes where two people may pass with trays or luggage.',
            ]],
            ['Acoustic expectations and limitations', [
                'Glass partitions improve privacy visually but are not acoustic walls unless you increase mass, air gaps and edge sealing. Pair laminated glass with acoustic gaskets where confidential conversations matter.',
                'Fluted textures scatter sound reflections — helpful in restaurants — but do not replace dedicated acoustic panels.',
            ]],
            ['Maintenance in Indian conditions', [
                'Interior glass needs periodic cleaning with non-abrasive products; hard-water spots are common in NCR and Hyderabad — consider easy-access gaps for squeegee reach.',
                'PVD frames wipe clean with a soft cloth; avoid chlorine-based cleaners on stainless. Inspect floor tracks quarterly in dusty cities to prevent roller drag on sliding panels.',
            ]],
        ],
        'Glass partitions let you keep the generosity of open planning while introducing structure, finish and privacy where the brief demands it. The specification succeeds when frame finish, glass type and fixing method are decided together — not treated as separate trades.',
        [
            'Explore pattern and finish options on our <a href="/studio/pvd-partitions">PVD partitions studio page</a>.',
            'For living-room proportions, read <a href="/blog/how-to-select-metal-partition-for-living-room">how to select a metal partition for a living room</a>.',
            'Compare finish durability in <a href="/blog/pvd-coating-explained-durable-metal-finishes">PVD coating explained</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Are glass partitions safe for homes with children?', 'answer' => 'Use tempered or laminated safety glass with rounded frame edges. Fixed panels are generally safer than sliding units in very active zones unless soft-close hardware is maintained.'],
        ['question' => 'Can a glass partition be relocated later?', 'answer' => 'Freestanding or channel-fixed systems can sometimes be repositioned. Ceiling-embedded tracks require new finishing if moved — discuss modularity at drawing stage.'],
        ['question' => 'Do glass partitions reduce air-conditioning efficiency?', 'answer' => 'They do not seal conditioned zones like full walls. For strong thermal separation, combine with ceiling bulkheads or pair with hinged doors in the partition opening.'],
        ['question' => 'What PVD colour works best with neutral interiors?', 'answer' => 'Champagne PVD adds warmth without dominating beige or greige palettes. Matte black suits graphic contemporary schemes. Request samples under your actual lighting.'],
        ['question' => 'How long does custom fabrication take?', 'answer' => 'Lead time depends on size, glass type and finish. Share dimensions early for a project-specific programme — avoid assuming catalogue furniture timelines for bespoke metal-and-glass systems.'],
    ],
]);

writeArticle('pvd-coating-explained-durable-metal-finishes', [
    'content' => article(
        'If you specify metal partitions, entrance doors or furniture for Indian interiors, you will encounter the term <strong>PVD coating</strong> repeatedly. It describes a vacuum-deposited finish on stainless steel that delivers gold, champagne, rose gold or black tones with high hardness and consistent colour. Understanding how PVD differs from plating and powder coat helps you write clearer specifications and set realistic maintenance expectations.',
        [
            ['What PVD coating is', [
                'Physical Vapour Deposition (PVD) bonds a thin, hard decorative layer to a prepared stainless substrate inside a vacuum chamber. Metal vapour condenses on the surface, producing finishes that read as jewellery-grade rather than paint-like.',
                'Unlike electroplated brass that can tarnish in humid bathrooms, PVD on grade 304/316 stainless is specified for high-touch architectural metalwork — handles, partition frames, door profiles and table bases.',
            ]],
            ['PVD compared with powder coating', [
                'Powder coat applies a thicker organic layer that hides substrate imperfections and suits bold RAL colours on mild steel. PVD preserves the metallic clarity of stainless — brush direction, crisp edges and subtle reflectivity.',
                'For partitions visible on three sides, PVD often wins on edge wear. Powder coat may chip on heavily knocked corners unless impact zones are protected. Read our comparison of <a href="/blog/pvd-partitions-vs-powder-coated-metal-partitions">PVD vs powder-coated partitions</a> for application guidance.',
            ]],
            ['PVD compared with electroplating and lacquer', [
                'Traditional brass plating on steel can corrode at cut edges if not fully sealed. Lacquered brass discolours when cleaners strip the protective film. PVD integrates with the stainless surface preparation and is harder than many organic clear coats.',
                'PVD is not magic: dissimilar-metal contact, chloride-rich cleaners and abrasive pads can still damage appearance. Specify compatible fixings and cleaning protocols in handover documents.',
            ]],
            ['Common PVD colours in architectural metalwork', [
                '<strong>Champagne</strong> — warm neutral that pairs with stone, oak and beige upholstery; widely used in office lobbies and residential living rooms.',
                '<strong>Rose gold</strong> — softer pink-gold tone popular in retail fit-outs and boutique hospitality.',
                '<strong>Gold mirror / bright gold</strong> — higher reflectivity; use sparingly on large planes to avoid glare.',
                '<strong>Matte black</strong> — graphic contrast for contemporary façades and minimalist interiors.',
                'Our <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">PVD finish selection guide</a> compares how these read under Indian LED lighting.',
            ]],
            ['Specifying PVD on drawings', [
                'State substrate grade (typically 304 for interiors), surface preparation (hairline, mirror or satin), PVD colour name from an approved sample, and visible face orientation.',
                'Batch consistency matters on large projects — request partitions, doors and handles from the same finish batch where possible.',
                'Note repair limitations: field touch-up of PVD is not like repainting; damaged panels may need factory refinishing.',
            ]],
            ['Care and maintenance', [
                'Wipe with soft microfibre and pH-neutral cleaners. Avoid steel wool and chloride bleach on coastal projects.',
                'Entrance pulls and partition frames in high-traffic malls may show polish wear over years — not structural failure. Schedule periodic inspection rather than aggressive abrasion.',
            ]],
        ],
        'PVD coating gives architects a durable decorative language on stainless without pretending to be solid brass. Specify it with clear colour samples, compatible substrates and realistic cleaning guidance — then coordinate partitions, doors and hardware on one finish schedule.',
        [
            'See applications on <a href="/studio/pvd-partitions">PVD partitions</a> and <a href="/shop/door-handles">door handles</a>.',
            'For entrance systems, visit <a href="/studio/main-entrance-pvd-doors">main entrance PVD doors</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Does PVD peel like paint?', 'answer' => 'Properly applied PVD on prepared stainless does not peel like thick paint. Damage usually appears as scratches through the coating rather than sheet delamination.'],
        ['question' => 'Can PVD be used outdoors?', 'answer' => 'Exterior use depends on exposure, grade and detailing. Many PVD partition and door projects are interior; exterior specifications need project-specific review.'],
        ['question' => 'Is PVD the same as titanium nitride (TiN)?', 'answer' => 'TiN is one PVD coating family. Architectural finishes may use other vapour-deposited layers to achieve gold or black tones — ask your fabricator for the exact process name on samples.'],
        ['question' => 'How do I match PVD to tapware?', 'answer' => 'Bring physical samples together on site. Phone screens distort warm tones. Champagne PVD may differ from plated brass tapware even when both are labelled gold.'],
    ],
]);

writeArticle('why-corten-steel-is-perfect-for-modern-facades', [
    'content' => article(
        'Modern Indian architecture increasingly favours materials that age with character rather than demanding repainting every few monsoons. Corten steel — a weathering alloy that develops a stable rust-coloured patina — has moved from industrial vocabulary into boutique villas, hospitality façades and retail frontages from Delhi to Bangalore. For architects and developers seeking a warm, textural metal skin without the maintenance cycle of conventional painted steel, Corten offers a compelling proposition when detailing and drainage are handled correctly.',
        [
            ['What makes Corten different from ordinary steel', [
                'Corten (COR-TEN, or weathering steel) contains copper, chromium and nickel alloying that accelerates early surface oxidation, then slows further corrosion once the patina stabilises. Unlike mild steel that rusts through, properly detailed Corten forms a protective layer that reads as intentional design rather than neglect.',
                'The colour shifts from raw mill finish through orange-brown to deep umber over months — faster in humid coastal cities, slower in dry Rajasthan summers. This evolution is the material\'s signature; specify it when the brief accepts natural variation rather than uniform factory colour.',
            ]],
            ['Why contemporary façades suit Corten', [
                'Indian modernism often pairs raw concrete, stone and large glazing. Corten adds warmth that stainless or aluminium can lack, without the green patina anxiety of bare copper in humid climates.',
                'Vertical fins, perforated screens and flat cladding panels create shadow lines that read strongly in harsh midday sun. Corten works well on feature walls, entrance canopies and garden pavilions where the scale is human and the rust tone complements planting.',
                'For a deeper material primer, see <a href="/blog/what-is-corten-steel-and-how-does-it-weather">what Corten steel is and how it weathers</a>.',
            ]],
            ['Design integration with Indian building types', [
                'In Delhi NCR, Corten feature walls contrast crisp white render on farmhouse plots and studio extensions. In Goa and Kochi, architects use perforated Corten to filter sea breeze while screening service areas from street view.',
                'Pair Corten with glass railings or slim metal doors for a coherent contemporary language — the warm rust tone balances cool stainless and clear glazing. Explore our <a href="/corten-steel">Corten steel studio page</a> for application references.',
                'Avoid using Corten as the sole weatherproof layer on habitable envelopes without consulting a façade consultant; many projects use it as rain-screen cladding over a sealed substrate.',
            ]],
            ['Drainage, run-off and neighbour considerations', [
                'The patina is desirable; rust-stained paving below drip lines is not. Slope sills outward, provide drip edges and consider catch trays on balconies where Corten overhangs stone or tile.',
                'Discuss run-off behaviour with your landscape architect early — stained sandstone plinths are difficult to reverse. Our guide on <a href="/blog/reduce-rust-run-off-staining-around-corten-steel">reducing rust run-off around Corten</a> covers practical detailing.',
            ]],
            ['Specification and procurement in India', [
                'Source weathering-grade plate from reputable mills; confirm alloy designation and mill certificates with your fabricator. Cutting, folding and welding expose fresh steel — field-applied patina accelerators can help blend welds, but colour matching remains approximate.',
                'Vyomika Atelier fabricates Corten screens, planters and cladding elements from our Delhi studio, with pan-India delivery and site installation support on qualified projects. Share elevation drawings early for gauge and fixing advice.',
            ]],
            ['Maintenance expectations', [
                'Corten is low maintenance relative to painted steel, not zero maintenance. Remove debris from horizontal ledges where water ponds; wash adjacent stone if run-off appears during the first two wet seasons while the patina matures.',
                'Do not specify Corten in continuously submerged or splash zones without specialist review. Interior feature walls cut from Corten plate need no weathering but may still release fine dust if abraded — seal adjacent fabrics during installation.',
            ]],
        ],
        'Corten steel earns its place on modern Indian façades when the design team embraces patina as finish, controls drainage and sets client expectations about early weathering. Used as a feature material rather than a default envelope, it delivers warmth, texture and long-term resilience that painted metal struggles to match.',
        [
            'Browse façade applications on our <a href="/corten-steel">Corten steel page</a>.',
            'Compare alternatives in <a href="/blog/corten-steel-cladding-vs-conventional-painted-steel">Corten cladding vs painted steel</a>.',
            'For drainage detail, read <a href="/blog/corten-steel-facades-design-drainage-weathering">Corten façades: design, drainage and weathering</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Does Corten rust through like normal steel?', 'answer' => 'Properly detailed weathering steel forms a stable patina that slows further corrosion. Through-thickness failure is not expected in typical cladding thicknesses when drainage is correct and the environment is suitable.'],
        ['question' => 'How long until Corten looks mature in Delhi?', 'answer' => 'Visible patina often begins within weeks of exposure; a settled tone may take one to two monsoon cycles depending on orientation and washing from rain.'],
        ['question' => 'Can Corten be used on the coast?', 'answer' => 'Coastal salt accelerates weathering and run-off. Many architects limit Corten to sheltered feature zones near the sea and rely on more corrosion-resistant metals for primary exposed structure.'],
        ['question' => 'Will Corten stain my driveway?', 'answer' => 'Run-off during early weathering can stain porous stone and concrete. Detail drip edges, slope surfaces away from Corten and plan landscaping accordingly.'],
        ['question' => 'Is Corten suitable for gated community façades?', 'answer' => 'Yes, as feature cladding or entry portals when the society accepts natural colour variation and initial run-off management during installation.'],
    ],
]);

writeArticle('pvd-partitions-materials-finishes-applications-cost-factors', [
    'content' => article(
        'PVD partitions have become a shorthand for premium interior zoning in Indian homes, offices and hospitality fit-outs. The term covers more than glass: architects specify metal-framed screens in stainless with Physical Vapour Deposition finishes — champagne, rose gold, matte black — to divide space without losing light. Understanding materials, finish behaviour and application context helps you brief fabricators accurately and avoid surprises on site.',
        [
            ['Core materials in PVD partition systems', [
                'Grade 304 stainless steel is standard for interior partitions in air-conditioned spaces across India. Grade 316 may be considered for humid zones or coastal projects where chloride exposure is higher.',
                'Glass infills range from clear tempered to fluted, tinted and laminated. Metal-only laser-cut screens suit feature walls where transparency is not required. The frame carries loads; the infill carries the privacy and acoustic story.',
            ]],
            ['PVD finishes and visual behaviour', [
                'PVD deposits a hard decorative layer in a vacuum chamber, producing metallic tones that read richer than powder coat on stainless. Champagne and rose gold dominate residential living and dining divides in Delhi and Mumbai; matte black suits loft and industrial-luxe interiors.',
                'Hairline brushing before PVD adds directionality that hides minor fingerprints better than mirror polish. Request physical samples under your project lighting — warm LEDs shift rose gold toward copper.',
                'Coordinate with <a href="/studio/pvd-partitions">PVD partition systems</a> on the same floor as entrance doors and railings for batch consistency.',
            ]],
            ['Typical applications in Indian projects', [
                '<strong>Living and dining</strong> — partial-height or full-height screens that preserve daylight while defining zones.',
                '<strong>Home offices</strong> — fluted glass partitions for visual privacy during video calls without building a full cabin.',
                '<strong>Corporate reception</strong> — branded metal screens with logo cut-outs in PVD gold or black.',
                '<strong>Retail</strong> — display backdrops and trial-room boundaries that photograph well for social media.',
            ]],
            ['Structural and fixing considerations', [
                'Floor-fixed channels, ceiling anchors and side wall connections each affect cost and reversibility. Double-height lobbies may need stiffening bars invisible from the primary approach.',
                'Sliding PVD partitions add hardware cost and maintenance; hinged or fixed panels suit most residential briefs. Confirm slab penetration rules before core drilling in apartments.',
            ]],
            ['Cost factors without fixed price lists', [
                'Final cost depends on overall dimensions, glass type, finish colour, number of panels, hardware brand and site access. Imported sliding systems and acoustic laminated glass increase budget relative to a single fixed screen.',
                'Labour for measurement, fabrication, transport and installation in high-rise buildings adds complexity. Share accurate drawings early — rework on site is expensive for bespoke metal and glass.',
                'For pricing logic, read <a href="/blog/pvd-partition-price-in-india-what-determines-final-cost">what determines PVD partition cost in India</a>.',
            ]],
            ['Working with Vyomika Atelier', [
                'We fabricate custom PVD partitions from our Delhi studio, serving architects, interior designers and homeowners pan-India. Typical workflow: brief review, site measurement or plan dimensions, shop drawings, fabrication, delivery and installation coordination.',
                'Professionals may begin from our <a href="/professionals">collaboration page</a> with finish schedules and reflected ceiling plans.',
            ]],
        ],
        'PVD partitions succeed when material grade, finish sample, glass type and fixing method are locked before fabrication. Treat cost as a function of size, complexity and site conditions rather than a per-square-foot catalogue figure — and coordinate the finish across all visible metal on the floor.',
        [
            'Explore systems on <a href="/studio/pvd-partitions">PVD partitions</a>.',
            'Compare with powder coat in <a href="/blog/pvd-partitions-vs-powder-coated-metal-partitions">PVD vs powder-coated partitions</a>.',
            'For living rooms, see <a href="/blog/how-to-select-metal-partition-for-living-room">selecting a metal partition</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Is PVD better than powder coat for partitions?', 'answer' => 'PVD preserves metallic clarity and suits high-visibility frames. Powder coat suits bold colours on mild steel and budget-sensitive back-of-house zones. Context and touch frequency guide the choice.'],
        ['question' => 'Can PVD partitions be floor-to-ceiling?', 'answer' => 'Yes, with appropriate fixing and glass thickness. Full-height systems improve visual separation but require confirmed ceiling and floor substrate strength.'],
        ['question' => 'Do you supply only the frame or complete installation?', 'answer' => 'Project scope varies. Many clients request supply and install; others coordinate installation through their contractor. Confirm scope in the quotation.'],
        ['question' => 'How do I match partition PVD to door handles?', 'answer' => 'Order handles and partitions from the same finish batch where possible. Always compare physical samples together on site.'],
        ['question' => 'Are PVD partitions suitable for co-working?', 'answer' => 'Yes — fluted glass and partial-height screens define pods while keeping the floor feeling open. Plan acoustic expectations separately from visual privacy.'],
    ],
]);

writeArticle('pvd-partition-price-in-india-what-determines-final-cost', [
    'content' => article(
        'Homeowners and project managers often ask for a single square-foot rate for PVD partitions. In practice, bespoke metal-and-glass screens are priced as assemblies: frame profile, finish, glass specification, hardware, transport and installation each move the total. This guide explains the variables Indian fabricators use so you can prepare budgets and compare quotations fairly — without relying on generic online price tables that omit site reality.',
        [
            ['Why catalogue pricing rarely applies', [
                'PVD partitions are made to opening dimensions, not pulled from warehouse stock. A 2.4 m wide living-room divide and a 900 mm wide study pocket share fabrication methods but not material quantities or hardware.',
                'Finish, glass edge treatment and number of panels affect labour more than raw steel cost. Quotations should itemise scope rather than hide assumptions.',
            ]],
            ['Dimension and panel count', [
                'Height and width drive steel length, glass area and structural stiffening. Taller panels may need thicker glass or intermediate horizontal bars.',
                'Multi-panel sliding systems cost more than a single fixed screen due to tracks, rollers and alignment labour. Curved or trapezoidal openings add template and wastage.',
            ]],
            ['Glass specification', [
                'Clear tempered glass is the baseline. Fluted, reeded, tinted or laminated glass adds material and processing cost. Acoustic laminated layers increase weight and hinge or track capacity requirements.',
                'Full-height partitions with minimal visible frame demand tighter tolerances — fabrication time rises even if square metres look similar to a framed grid system.',
            ]],
            ['PVD finish and surface preparation', [
                'Champagne, rose gold, gold mirror and matte black PVD sit on prepared stainless — hairline, satin or mirror. Mirror polish before PVD increases preparation labour.',
                'Batch consistency across multiple partitions on one floor may constrain scheduling; rushing separate batches risks visible colour shift under daylight.',
                'Understand finish options in our <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">PVD finish selection guide</a>.',
            ]],
            ['Hardware, logistics and site conditions', [
                'Sliding hardware quality varies widely; specify brand and load rating. Freight to tier-2 cities and crane or manual carry to high floors affects installation quotes.',
                'Site measurement errors lead to remakes — the most expensive line item. Provide final dimensions after flooring and ceiling finishes are complete where possible.',
            ]],
            ['Getting a useful quotation', [
                'Share plan dimensions, elevation sketches, preferred finish sample, glass type and photos of adjacent metalwork. Note lift access and working hours restrictions in gated communities.',
                'Vyomika Atelier issues project-specific quotations from our Delhi studio for pan-India delivery. Compare like with like: fixed vs sliding, glass type and installation scope must match across vendors.',
                'Review application context on <a href="/studio/pvd-partitions">PVD partitions</a> and material background in <a href="/blog/pvd-partitions-materials-finishes-applications-cost-factors">materials and finishes guide</a>.',
            ]],
        ],
        'PVD partition price in India reflects bespoke fabrication, not commodity shelving. Budget with explicit assumptions about size, glass, finish, hardware and site access — then treat the lowest headline rate with suspicion if scope is undefined.',
        [
            'Begin specifications on <a href="/studio/pvd-partitions">PVD partitions</a>.',
            'Compare finish durability in <a href="/blog/pvd-coating-explained-durable-metal-finishes">PVD coating explained</a>.',
            'For living-room sizing, read <a href="/blog/how-to-select-metal-partition-for-living-room">metal partition selection</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Is there a standard per-square-foot rate for PVD partitions?', 'answer' => 'No reliable universal rate exists. Height, fixings, glass type and finish change cost non-linearly. Request itemised quotations for your opening sizes.'],
        ['question' => 'Does sliding cost double a fixed partition?', 'answer' => 'Not always double, but sliding adds track, rollers and alignment labour. The premium depends on width, panel count and hardware grade.'],
        ['question' => 'When should site measurement happen?', 'answer' => 'After floor and ceiling levels are final, or from accurate as-built dimensions on renovation projects. Early estimates can use plan dimensions with a tolerance note.'],
        ['question' => 'Can I reduce cost without changing finish?', 'answer' => 'Simplifying geometry, reducing panel count, choosing clear tempered glass and fixed panels typically help more than compromising PVD quality on visible faces.'],
        ['question' => 'Do you quote from photos alone?', 'answer' => 'Photos help for initial discussion, but formal quotes need dimensions, finish selection and scope confirmation. Complex openings need site measurement.'],
    ],
]);

writeArticle('pvd-partitions-vs-powder-coated-metal-partitions', [
    'content' => article(
        'Partition specifications in India often list either PVD-coated stainless or powder-coated steel without explaining the trade-off. Both can produce striking screens; they differ in substrate, colour behaviour, edge durability and maintenance. Choosing between them early prevents finish clashes with doors and handles already on the project.',
        [
            ['Substrate and where each system lives', [
                'PVD partitions typically use stainless steel frames — grade 304 for interiors — with a vacuum-deposited decorative layer. Powder-coated partitions often use mild steel or aluminium with a thicker baked-on organic coating in RAL colours.',
                'Stainless resists corrosion at cut edges better than painted mild steel, which needs careful edge sealing. In humid bathrooms or coastal fit-outs, substrate choice matters as much as colour.',
            ]],
            ['Appearance and touch surfaces', [
                'PVD reads as metal: brush direction, crisp arrises and subtle reflectivity remain visible. Powder coat reads as a uniform colour skin that can hide minor substrate blemishes.',
                'For champagne or gold tones adjacent to real brass tapware, PVD on stainless usually matches more convincingly than gold-tinted powder on steel. Matte black PVD and black powder both suit contemporary interiors — compare samples under your LED temperature.',
            ]],
            ['Durability at edges and high-traffic zones', [
                'Partition corners and door handles receive knocks. Powder coat can chip on impact, exposing base metal. PVD is thin and hard — damage tends to show as scratches rather than chips, but deep impacts still mark the surface.',
                'Sliding partition tracks accumulate grit in Delhi and Rajasthan; clean tracks regularly regardless of finish. Neither system tolerates abrasive cleaners or steel wool.',
            ]],
            ['Colour range and batch consistency', [
                'Powder coat wins on arbitrary RAL colours and bold brand hues for corporate fit-outs. PVD excels at metallic champagne, rose gold, gold and black families on stainless.',
                'Large projects should batch PVD partitions with <a href="/studio/pvd-partitions">matching door and handle finishes</a> from one production run where possible.',
            ]],
            ['Cost and specification context', [
                'Powder-coated mild steel partitions may cost less on paper for large commercial grids. PVD stainless often wins on premium residential and hospitality projects where finish continuity with entrance systems justifies the investment.',
                'Cost drivers for both include glass type, size and installation — see <a href="/blog/pvd-partition-price-in-india-what-determines-final-cost">partition pricing factors</a>.',
            ]],
            ['Decision framework for Indian projects', [
                'Choose PVD when the partition sits beside PVD entrance doors, slim profiles or furniture and the brief demands metallic warmth. Choose powder coat when you need a specific RAL brand colour on a large mild-steel grid and touch is moderate.',
                'Vyomika Atelier specialises in PVD stainless partitions from Delhi with pan-India project support. Share your floor plan and adjacent finish schedule for a recommendation.',
            ]],
        ],
        'PVD and powder-coated partitions serve different aesthetic and substrate goals. Specify PVD for metallic coherence on stainless in premium interiors; specify powder coat when RAL colour on steel grids drives the design — and always align the choice with doors, handles and railings on the same sightline.',
        [
            'See PVD systems on <a href="/studio/pvd-partitions">PVD partitions</a>.',
            'Learn about PVD in <a href="/blog/pvd-coating-explained-durable-metal-finishes">PVD coating explained</a>.',
            'For open-plan glass options, read <a href="/blog/glass-partitions-open-plan-without-compromise">glass partitions guide</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can powder coat look like gold PVD?', 'answer' => 'Metallic powder coats exist but read more paint-like than PVD on stainless. Compare samples next to tapware and lighting before deciding.'],
        ['question' => 'Which is easier to repair on site?', 'answer' => 'Both are difficult to invisibly repair. Powder coat may allow local touch-up on low-visibility areas; PVD damage usually needs factory refinishing of the panel.'],
        ['question' => 'Is powder-coated steel OK for bathrooms?', 'answer' => 'Only with correct pretreatment and edge sealing. Many designers prefer stainless or aluminium in wet zones.'],
        ['question' => 'Does PVD work on aluminium partitions?', 'answer' => 'PVD is most common on stainless in architectural work. Aluminium partitions typically use powder coat or anodising — confirm with your fabricator.'],
        ['question' => 'Which lasts longer?', 'answer' => 'Lifespan depends on environment, cleaning and impact. Well-maintained PVD stainless and quality powder-coated steel both serve for years in interior conditions.'],
    ],
]);

writeArticle('how-to-select-metal-partition-for-living-room', [
    'content' => article(
        'The living room is where Indian families entertain, watch television and pass through to dining and kitchen zones. A metal partition — often PVD-framed glass or a decorative steel screen — can define space without the heaviness of a masonry wall. Selection succeeds when proportion, privacy, finish and fixing suit how the room actually works, not just how it photographs.',
        [
            ['Clarify what the partition must achieve', [
                'Separate dining without blocking light? Hide a study desk from the sofa sightline? Create a vestibule between entrance and living? Each goal suggests different height, opacity and position.',
                'Acoustic privacy for a home office needs different glass mass than a purely visual divide between sofa and dining table. Be honest about noise — glass alone is not a sound wall.',
            ]],
            ['Height and proportion in Indian living rooms', [
                'Ceiling heights of 2.75–3.0 m are common in urban apartments; villas may reach 3.6 m or double volume. Partial-height partitions (1.2–1.5 m) keep rooms connected; full-height screens suit formal living where separation is stronger.',
                'Width should align with furniture rhythm — aligning a screen edge with a sofa back or column avoids awkward leftover gaps. Allow comfortable passage width (900 mm minimum) if people route through the opening daily.',
            ]],
            ['Glass and metal finish choices', [
                'Clear glass suits open visual connection. Fluted or reeded glass softens views — popular between living and passage where privacy from the main door matters. Tinted glass helps west-facing rooms in Ahmedabad and Delhi.',
                'Champagne PVD frames warm neutral interiors; matte black suits graphic contemporary schemes. Match or deliberately contrast with <a href="/shop/door-handles">door handles</a> and entrance metalwork on the same floor.',
            ]],
            ['Fixed, sliding or pivot options', [
                'Fixed panels are simplest and often sufficient between living and dining. Sliding stacks suit wider openings when you occasionally need full width open. Avoid over-engineering sliding hardware in dusty cities unless tracks are accessible for cleaning.',
                'Explore slim systems on <a href="/studio/pvd-partitions">PVD partitions</a> and compare door movement types in <a href="/blog/slim-profile-doors-hinged-sliding-telescopic-compared">slim profile doors compared</a> for hardware familiarity.',
            ]],
            ['Coordination with lighting and flooring', [
                'Downlights above a partition highlight frame lines — plan ceiling wiring before fixing ceiling anchors. Floor tracks for sliding units must align with tile joints; inform your tiler before laying.',
                'Rugs and partition bases should not create trip hazards. Recessed floor channels look cleaner but complicate future floor changes.',
            ]],
            ['Briefing your fabricator', [
                'Provide a plan with furniture layout, photos of adjacent walls, preferred finish sample and notes on children or pets. Vyomika Atelier measures or works from plans for custom living-room partitions from our Delhi studio, delivered pan-India.',
                'Review completed interiors on our <a href="/projects">projects page</a> for proportion references.',
            ]],
        ],
        'A living-room metal partition should solve a daily spatial problem — light, privacy or circulation — while matching the finish language of the home. Decide height, glass opacity and fixing method from how the family uses the room, then coordinate metal with handles and doors on the same level.',
        [
            'Specify on <a href="/studio/pvd-partitions">PVD partitions</a>.',
            'For glass privacy patterns, see <a href="/blog/fluted-glass-slim-profile-doors-design-privacy-guide">fluted glass guide</a>.',
            'Understand finishes in <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">PVD finish selection</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Will a partition make my living room feel smaller?', 'answer' => 'Glass partitions with slim frames usually preserve openness. Solid metal screens reduce perceived size — use them where partial enclosure is intentional.'],
        ['question' => 'What height for living-dining divide?', 'answer' => 'Many homes use 1.2–1.8 m partial height or full height to soffit. Match the choice to how enclosed you want dining to feel.'],
        ['question' => 'Can I install after flooring is done?', 'answer' => 'Yes, with surface-mounted channels. Recessed tracks need coordination before final floor finish.'],
        ['question' => 'Is fluted glass enough privacy from entrance?', 'answer' => 'Fluted glass obscures detail while passing light — usually adequate for visual screening from main door to sofa, not for acoustic privacy.'],
        ['question' => 'Do you help with 3BHK layouts?', 'answer' => 'Yes. Share your plan and brief; we advise proportion and finish for typical urban apartment living rooms.'],
    ],
]);

writeArticle('slim-profile-doors-hinged-sliding-telescopic-compared', [
    'content' => article(
        'Slim-profile door systems trade bulky frames for minimal metal lines — often PVD-coated stainless with glass infills. In Indian apartments and villas, the choice between hinged, sliding and telescopic opening affects daily use, maintenance and how much wall length you sacrifice. This comparison helps architects and homeowners align hardware with room size, traffic and dust conditions common across the country.',
        [
            ['What slim-profile doors are', [
                'Slim profiles use narrow vertical stiles and top tracks (for sliding) or concealed hinges (for hinged) to maximise glass area. They suit contemporary interiors where standard 100 mm aluminium frames feel visually heavy.',
                'PVD finishes in champagne, black or rose gold align with partitions and entrance systems from the same fabricator. See our <a href="/studio/slim-profile-door-systems">slim profile door systems</a> for typical configurations.',
            ]],
            ['Hinged slim doors', [
                'Single or double hinged doors suit standard openings where swing clearance exists. A 900 mm hinged door needs clear floor space for the arc — problematic in tight Mumbai bedrooms unless furniture is fixed.',
                'Concealed hinges preserve clean jamb lines. Maintenance is straightforward: hinges and handles only, no floor track. Best for ensuite bathrooms, study entries and secondary rooms where swing space is available.',
            ]],
            ['Sliding slim doors', [
                'Top-hung sliding panels save swing space and suit wider openings between living and balcony or master bedroom and walk-in wardrobe. Panel weight limits apply — confirm glass thickness with the fabricator.',
                'Tracks need periodic cleaning in dusty cities; specify soft-close where budget allows. Sliding excels on 1.8–3.0 m openings where a hinged pair would feel cumbersome.',
            ]],
            ['Telescopic and multi-panel stacks', [
                'Telescopic systems stack two or three panels behind one another, opening nearly the full width. Useful for pantry, utility or conference pockets that must fully disappear.',
                'Hardware complexity and alignment tolerance rise with panel count. Specify only where full opening width is used regularly — otherwise a two-panel sliding stack may suffice at lower cost and simpler maintenance.',
            ]],
            ['Choosing for Indian conditions', [
                'Dust: sliding and telescopic tracks need accessible cleaning gaps; hinged doors avoid track grit entirely.',
                'Monsoon humidity: use appropriate stainless grade and hardware; avoid water pooling in floor tracks on terrace-adjacent openings without drainage slope.',
                'For privacy glass options, read <a href="/blog/fluted-glass-slim-profile-doors-design-privacy-guide">fluted glass with slim profiles</a>.',
            ]],
            ['Specification checklist', [
                'Opening width and height, headroom for track, wall structure for anchors, preferred PVD finish, glass type and handover to flooring contractor for track recess timing.',
                'Vyomika Atelier supplies custom slim-profile doors from Delhi with pan-India project coordination. Share reflected ceiling plans if downlights conflict with head tracks.',
            ]],
        ],
        'Hinged slim doors simplify maintenance where swing space allows. Sliding suits wider openings and tight furniture layouts. Telescopic stacks maximise clear width for frequently opened large pockets — at the cost of more hardware care. Match the opening type to daily traffic, dust exposure and wall length before choosing glass and PVD finish.',
        [
            'Explore systems on <a href="/studio/slim-profile-door-systems">slim profile doors</a>.',
            'Compare entrance scale in <a href="/blog/how-to-choose-luxury-main-entrance-door">luxury main entrance guide</a>.',
            'For partitions nearby, see <a href="/studio/pvd-partitions">PVD partitions</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can slim sliding doors be floor-to-ceiling?', 'answer' => 'Yes, with engineered head support and appropriate glass thickness. Confirm structural head detail before ordering.'],
        ['question' => 'Are telescopic doors worth it for homes?', 'answer' => 'Worth it when you regularly need full opening width — large pantry or wardrobe fronts. For occasional use, simpler sliding may suffice.'],
        ['question' => 'Do slim profiles reduce acoustic privacy?', 'answer' => 'Acoustic performance depends on glass mass, seals and surrounding wall construction — not profile width alone.'],
        ['question' => 'How often should tracks be cleaned?', 'answer' => 'In dusty urban areas, inspect monthly and vacuum tracks lightly. Grit causes noisy or stiff sliding.'],
        ['question' => 'Can hinged and sliding mix on one floor?', 'answer' => 'Yes, with consistent PVD finish and glass type for visual coherence.'],
    ],
]);

writeArticle('fluted-glass-slim-profile-doors-design-privacy-guide', [
    'content' => article(
        'Fluted — or reeded — glass has returned to Indian interiors as a privacy layer that still feels luminous. Paired with slim PVD metal profiles, it defines bedrooms, studies and bathroom entries without the opacity of sandblasted panels. Understanding how fluting direction, depth and lighting affect privacy helps you specify doors that work in real homes, not just renderings.',
        [
            ['How fluted glass behaves', [
                'Vertical or horizontal ridges refract sightlines. Viewers see blurred form and movement rather than sharp detail — adequate for many living-to-passage and bedroom scenarios.',
                'Light still passes; rooms feel less boxed than with solid panels. The effect is stronger when fluting faces the side you want to shield, and when backlighting does not silhouette occupants sharply.',
            ]],
            ['Design pairing with slim metal profiles', [
                'Narrow champagne or black PVD frames keep focus on the glass texture. Wide stiles defeat the purpose of slim systems — specify minimum structurally safe profile width.',
                'Coordinate with <a href="/studio/slim-profile-door-systems">slim profile door systems</a> and adjacent <a href="/studio/pvd-partitions">PVD partitions</a> using the same fluting pitch for visual continuity.',
            ]],
            ['Privacy expectations by room', [
                '<strong>Bedroom entry from passage</strong> — fluted glass usually sufficient for visual screening; pair with solid lower panel if floor-level sightlines matter.',
                '<strong>Bathroom</strong> — adequate for guest bath; master suites sometimes prefer fuller opacity or frosted lower zones.',
                '<strong>Home office</strong> — reduces distraction on video calls without full cabin construction.',
                'Fluted glass is not acoustic insulation — specify laminated glass if sound separation matters.',
            ]],
            ['Lighting and orientation', [
                'Strong backlight from one side increases silhouette risk. In Delhi west-facing rooms, consider tinted fluted glass or layered curtains for evening privacy.',
                'Sample doors on site before batch ordering — phone cameras exaggerate clarity through fluting compared to naked eye at normal distance.',
            ]],
            ['Maintenance in Indian homes', [
                'Flutes trap dust; wipe vertically along ridges with soft cloth. Hard-water spots show on clear fluted glass in NCR — establish cleaning access if panels are tall.',
                'Avoid abrasive pads that dull ridge tips. PVD frames clean as standard stainless — no chloride bleach.',
            ]],
            ['Ordering and fabrication notes', [
                'Confirm fluting pitch (narrow vs wide reed), tempered safety requirement and hinge or track load. Vyomika Atelier fabricates fluted slim doors from Delhi for pan-India projects — share opening schedule and finish sample early.',
                'Cross-reference <a href="/blog/slim-profile-doors-hinged-sliding-telescopic-compared">hinged vs sliding slim doors</a> for hardware choice.',
            ]],
        ],
        'Fluted glass with slim PVD profiles delivers privacy without sacrificing daylight — when pitch, orientation and lighting are considered. Use it for visual screening between living zones; add mass or seals when acoustic separation is equally important.',
        [
            'See door systems on <a href="/studio/slim-profile-door-systems">slim profile doors</a>.',
            'For open-plan divides, read <a href="/blog/glass-partitions-open-plan-without-compromise">glass partitions</a>.',
            'For living-room placement, see <a href="/blog/how-to-select-metal-partition-for-living-room">metal partition selection</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can people see through fluted glass at night?', 'answer' => 'With bright interior light and dark exterior, silhouettes may show. Use curtains or adjust lighting where full privacy is needed after dark.'],
        ['question' => 'Is fluted the same as reeded?', 'answer' => 'Both describe linear ridges; terminology varies by supplier. Confirm sample appearance rather than name alone.'],
        ['question' => 'Does fluting weaken glass?', 'answer' => 'Tempered fluted safety glass is standard for doors. Thickness and tempering address strength — confirm with your fabricator.'],
        ['question' => 'Can fluted glass be used on main entrance?', 'answer' => 'Usually interior or secondary doors; main entrances often need different security and weather performance. See main entrance guides for exterior options.'],
        ['question' => 'How do I clean fluted panels?', 'answer' => 'Soft cloth, mild glass cleaner, wipe along flutes. Avoid abrasive tools.'],
    ],
]);

writeArticle('how-to-choose-luxury-main-entrance-door', [
    'content' => article(
        'The main entrance door sets the first material impression of a home or office in India — scale, security subtext, finish and lighting all register before anyone crosses the threshold. Luxury here means intentional specification: PVD stainless or composite systems sized to the opening, coordinated with handles and adjacent glazing, not merely an expensive catalogue leaf.',
        [
            ['Scale and proportion for Indian openings', [
                'Villa and farmhouse main doors often span 1.5–2.4 m wide and 2.4–3.0 m tall, sometimes double-leaf. Apartment entries are narrower but benefit from full-height treatment within the lobby frame.',
                'Oversized doors need structural head support and careful hinge or pivot rating. Undersized doors on grand façades look timid — match door size to wall composition and canopy height.',
            ]],
            ['Material and finish direction', [
                'PVD-coated stainless doors offer durable champagne, rose gold or black tones without solid-brass maintenance. Etched, layered or glass-infill panels add depth for boutique hospitality and high-end residential lobbies.',
                'Review options on <a href="/studio/main-entrance-pvd-doors">main entrance PVD doors</a> and compare inset treatments in <a href="/blog/stainless-steel-glass-etched-entrance-doors-compared">steel, glass and etched doors compared</a>.',
            ]],
            ['Security and performance without overclaiming', [
                'Specify lock quality, frame anchoring and threshold detail with your security consultant. Metal doors are one layer in a system — wall construction, locks and vision panels all matter.',
                'Weather performance depends on threshold, gaskets and overhang — critical for exposed entries in Mumbai monsoon or Delhi dust storms. Avoid promising specific ratings unless tested for your exact assembly.',
            ]],
            ['Handles and pull length', [
                'Pull handle length and position affect ergonomics and visual balance on wide leaves. Long vertical pulls suit tall doors; offset pairs suit double-leaf symmetry.',
                'See <a href="/blog/select-pull-handle-length-for-main-door">pull handle length guide</a> and browse <a href="/shop/door-handles">door handles</a> for finish coordination.',
            ]],
            ['Lighting and landing design', [
                'Recessed ceiling slots or wall grazers highlight PVD grain and etched patterns. Floor landing material should slope slightly away from the threshold; stone plinths stain if water ponds against the frame.',
                'Coordinate electrical conduits before frame installation — surface wiring after the fact mars premium entries.',
            ]],
            ['Project workflow with Vyomika Atelier', [
                'We work from architectural elevations and site measurements at our Delhi studio, fabricating custom entrance systems for pan-India installation. Early involvement lets us align hinge side, floor level and handle centreline before civil work completes.',
                'Architects may start from our <a href="/professionals">professionals page</a> with door schedules and finish samples.',
            ]],
        ],
        'A luxury main entrance door is proportionate, structurally considered and finish-coordinated with handles and adjacent metalwork. Choose material and infill for the façade story, then resolve security, weather and lighting as a system — not as afterthoughts.',
        [
            'Explore <a href="/studio/main-entrance-pvd-doors">main entrance PVD doors</a>.',
            'Compare door types in <a href="/blog/stainless-steel-glass-etched-entrance-doors-compared">steel, glass and etched compared</a>.',
            'Browse references on <a href="/projects">projects</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Single or double leaf for villa entrance?', 'answer' => 'Double leaves suit openings above roughly 1.6 m clear width and formal symmetrical façades. Single oversize leaves simplify hardware but need heavier hinges or pivots.'],
        ['question' => 'Can PVD entrance doors face direct sun?', 'answer' => 'Exterior exposure needs project-specific review of grade, orientation and maintenance. Many PVD entrance projects use covered porches.'],
        ['question' => 'How do I coordinate handle finish?', 'answer' => 'Order handles from the same PVD batch as the door where possible. Compare samples on site under exterior lighting.'],
        ['question' => 'Should the door be installed before flooring?', 'answer' => 'Install after rough floor level is set; protect finished stone during frame fixing. Threshold detail depends on final floor height.'],
        ['question' => 'Do you supply frames only or full door?', 'answer' => 'Scope is project-specific — share your requirement for leaf, frame, hardware and installation on enquiry.'],
    ],
]);

writeArticle('stainless-steel-glass-etched-entrance-doors-compared', [
    'content' => article(
        'Main entrance doors in premium Indian projects combine stainless structure with decorative infills — clear glass, etched panels, layered metal or solid PVD faces. Each approach reads differently under daylight and lobby lighting. Comparing them helps architects write door schedules that match security, privacy and maintenance expectations.',
        [
            ['Solid PVD stainless doors', [
                'Full stainless faces in hairline champagne or matte black present a monolithic, contemporary statement. No glass breakage risk on the field; scratches are the primary long-term concern on high-touch zones.',
                'Suits minimalist villas and corporate entries where transparency is not required. Explore configurations on <a href="/studio/main-entrance-pvd-doors">main entrance PVD doors</a>.',
            ]],
            ['Glass-infill stainless doors', [
                'Steel frames with tempered or laminated glass panels introduce daylight into deep lobbies and allow visual connection to interior art or reception desks.',
                'Privacy depends on glass treatment — clear, tinted, frosted or fluted. Security detailing must address glass type and fixing method; coordinate with your security adviser rather than assuming thickness alone deters intrusion.',
            ]],
            ['Etched and patterned stainless', [
                'Chemical or laser etching adds texture, logos or geometric patterns to stainless faces without separate appliqués. Backlighting reveals depth; unlit, etching reads subtly in daylight.',
                'Etched zones hide fingerprints better than mirror polish. Match etch depth and pattern scale to door size — fine patterns disappear on very large leaves unless viewed closely.',
            ]],
            ['Combined treatments', [
                'Many Vyomika Atelier doors mix PVD frames, etched lower panels and upper glass for balance of privacy and light. Double-leaf entries often use etching on one leaf and glass on the other for asymmetry.',
                'Compare pull hardware scale in <a href="/blog/select-pull-handle-length-for-main-door">pull handle length guide</a>.',
            ]],
            ['Maintenance and Indian climate', [
                'Glass infills need periodic cleaning; etched stainless wipes like standard PVD — avoid abrasive pads on polished zones adjacent to etch.',
                'Coastal projects: specify appropriate stainless grade and rinsing protocol for salt deposit on exterior faces. Covered entries reduce exposure significantly.',
            ]],
            ['Choosing for your brief', [
                'Choose solid PVD for maximum metal presence and simplest glass-free maintenance. Choose glass-infill for lobby brightness. Choose etching for brand identity and tactile depth without full transparency.',
                'Share façade photos and lobby plans with our Delhi studio for pan-India fabrication advice.',
            ]],
        ],
        'Stainless entrance doors succeed when structure, infill and finish serve the lobby story. Solid PVD reads bold and calm; glass brings light; etching adds pattern and brand. Specify each layer with security, weather and cleaning in mind.',
        [
            'See <a href="/studio/main-entrance-pvd-doors">main entrance PVD doors</a>.',
            'Read <a href="/blog/how-to-choose-luxury-main-entrance-door">how to choose a luxury main entrance door</a>.',
            'Coordinate handles via <a href="/shop/door-handles">door handles</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Is etched stainless slippery when wet?', 'answer' => 'Etched faces are not typically used on floor plates. Door faces are vertical — normal hardware grip applies on handles.'],
        ['question' => 'Can etched patterns match company logo?', 'answer' => 'Yes, from vector artwork with minimum line width suitable for door scale. Review shop drawing before fabrication.'],
        ['question' => 'Glass-infill vs solid for security?', 'answer' => 'Security is system-dependent — locks, frame fixings and glass type together. Consult your security specialist for threat-appropriate specification.'],
        ['question' => 'Does etching affect PVD colour?', 'answer' => 'Etching removes material from the surface layer; discuss whether etching occurs before or after PVD with your fabricator for colour consistency.'],
        ['question' => 'Can I mix champagne PVD with clear glass?', 'answer' => 'Common combination — warm frame against clear or low-iron glass. Request sample corner mock-up.'],
    ],
]);

writeArticle('stainless-steel-railings-types-finishes-selection-guide', [
    'content' => article(
        'Stainless steel railings anchor safety on staircases, balconies and mezzanines across Indian residential and commercial projects. Beyond basic guard height, the specification covers profile shape, infill type, finish and fixing — decisions that affect cost, cleaning and how the railing reads against stone, tile or glass architecture.',
        [
            ['Common railing types', [
                '<strong>Post and horizontal rail</strong> — classic, economical, suits traditional and transitional interiors.',
                '<strong>Post and vertical balusters</strong> — tighter spacing for child safety codes on family homes.',
                '<strong>Cable or rod infill</strong> — minimal visual weight; needs tension maintenance.',
                '<strong>Glass infill with stainless posts</strong> — transparent guard; see our <a href="/blog/glass-railings-staircases-balconies-planning-checklist">glass railing checklist</a>.',
                '<strong>Custom PVD-coated frames</strong> — champagne or black metal with glass or metal infill for premium lobbies.',
            ]],
            ['Stainless grades and environments', [
                'Grade 304 serves most interior staircases and sheltered balconies. Exterior exposed railings in coastal Chennai or Mumbai may warrant 316 discussion with your fabricator.',
                'Interior vs exterior expectations differ — read <a href="/blog/interior-vs-exterior-railings-material-finish">interior vs exterior railings</a> before copying an interior spec to a terrace.',
            ]],
            ['Finishes: hairline, mirror and PVD', [
                'Hairline stainless hides minor scratches on high-touch handrails. Mirror polish suits statement stair cores but shows fingerprints.',
                'PVD champagne or black aligns railings with <a href="/studio/pvd-partitions">partitions</a> and entrance doors on the same project. Powder coat on mild steel is an alternative for exterior budget rails — different maintenance profile.',
            ]],
            ['Code and safety planning', [
                'Follow applicable local building rules for height, opening size and load. Typical guard heights around 900–1050 mm appear in many Indian residential briefs — confirm with your architect and authority.',
                'Do not rely on this article for statutory compliance; use project-specific structural and regulatory advice.',
            ]],
            ['Fixing to concrete, steel and stone', [
                'Core-drilled base plates, side-mounted brackets on stringers and under-rail fixings each suit different stair constructions. Share structural drawings before fabrication.',
                'Expansion in direct sun can affect very long exterior runs — allow movement details where engineer specifies.',
            ]],
            ['Procurement with Vyomika Atelier', [
                'We fabricate custom stainless and PVD railings from our Delhi studio for pan-India sites. Provide stair sections, landing dimensions and finish schedule early.',
                'Browse <a href="/railings">railings</a> and <a href="/projects">projects</a> for reference details.',
            ]],
        ],
        'Select stainless railings by environment, infill type and finish coordination with the rest of the metalwork. Interior hairline or PVD suits most premium homes; exterior and coastal projects need grade and fixing review — glass infill adds transparency but demands correct glass and post engineering.',
        [
            'Explore <a href="/railings">railings</a>.',
            'Plan glass guards via <a href="/blog/glass-railings-staircases-balconies-planning-checklist">glass railing checklist</a>.',
            'Compare environments in <a href="/blog/interior-vs-exterior-railings-material-finish">interior vs exterior guide</a>.',
        ]
    ),
    'faq' => [
        ['question' => '304 or 316 for terrace railing?', 'answer' => '304 is common on sheltered terraces; open coastal exposure often prompts 316 discussion. Confirm with your fabricator for your site.'],
        ['question' => 'Can railings match PVD door finish?', 'answer' => 'Yes — specify the same PVD colour sample and batch where possible for posts and handrails.'],
        ['question' => 'Are horizontal cables child-safe?', 'answer' => 'Cable spacing must meet applicable safety rules for your building type. Many family homes prefer vertical infill or glass for tighter openings.'],
        ['question' => 'Do you site-measure stairs?', 'answer' => 'Yes for bespoke projects, or work from accurate architectural sections when provided.'],
        ['question' => 'Can existing masonry stairs get new railings?', 'answer' => 'Usually yes, with core drilling or side fixings suited to existing structure. Site survey confirms anchor feasibility.'],
    ],
]);

writeArticle('glass-railings-staircases-balconies-planning-checklist', [
    'content' => article(
        'Glass railings deliver transparency on staircases, internal balconies and terrace edges — popular in Indian villas and duplex apartments where views and daylight matter. Planning must cover glass type, post structure, fixing, cleaning access and safety expectations before fabrication starts. Use this checklist during design development and before signing off shop drawings.',
        [
            ['Define location: interior, semi-open or fully exterior', [
                'Interior glass guards on stair wells see less UV and thermal swing. Exterior balcony glass faces monsoon, dust and direct sun — glass thickness, hardware and base detail intensify.',
                'Semi-open double-height living rooms need height and opening rules suitable for family use. Cross-read <a href="/blog/interior-vs-exterior-railings-material-finish">interior vs exterior railings</a>.',
            ]],
            ['Glass type and thickness', [
                'Tempered safety glass is baseline; laminated layers add post-breakage behaviour valued on elevated balconies. Thickness depends on span, fixing method and load assumptions — confirm with fabricator engineering, not guesswork.',
                'Low-iron glass reduces green tint on premium stair cores. Tinted glass reduces glare on west-facing terrace edges in Rajasthan and Gujarat.',
            ]],
            ['Post, channel and structural backup', [
                'Stainless posts, minimal spigots or base channels each change appearance and slab penetration. Side-mounted systems suit where floor drainage must stay uninterrupted.',
                'Structure must resist guard loads — coordinate with structural engineer before core drilling slab edges on cantilever balconies.',
            ]],
            ['Handrail and cap details', [
                'Many codes and clients expect a continuous handrail even with glass infill. PVD-coated stainless caps match <a href="/railings">railings</a> elsewhere in the home.',
                'Handrail profile comfort matters on long flights — oval or flat oval suits grip better than sharp square tube for daily use.',
            ]],
            ['Cleaning and maintenance access', [
                'Interior glass accumulates fingerprints at child height; exterior glass collects dust and rain spots. Ensure safe access for cleaning terrace panels — awkward cantilevers often get neglected and look tired within a year.',
                'Avoid abrasive cleaners; hard-water staining is common in NCR borewell areas — squeegee after monsoon helps.',
            ]],
            ['Coordination checklist before order', [
                'Stair section drawings, finished floor levels, glass type, post finish, handrail height, fixing method, sealant colour and timeline relative to stone or tile completion.',
                'Vyomika Atelier fabricates glass and stainless railing systems from Delhi for pan-India projects. Share this checklist completed with your enquiry.',
            ]],
        ],
        'Glass railings reward early planning: location class, laminated vs tempered, structure, handrail continuity and cleaning access. Resolve engineering and finish with stainless posts before site work locks slab edges — surprises at installation are costly on custom glass.',
        [
            'See <a href="/railings">railings</a>.',
            'For stainless options, read <a href="/blog/stainless-steel-railings-types-finishes-selection-guide">stainless railing guide</a>.',
            'Browse <a href="/projects">projects</a> for stair references.',
        ]
    ),
    'faq' => [
        ['question' => 'Is laminated glass mandatory on balconies?', 'answer' => 'Requirements vary by authority and project type. Many designers specify laminated glass on elevated exterior guards for added post-breakage behaviour — confirm for your project.'],
        ['question' => 'Can glass railings be frameless?', 'answer' => 'Spigot or channel systems minimise visible metal. Structural glass guardrails need engineered design — not all spans suit frameless detail.'],
        ['question' => 'Do glass stairs need different glass than balconies?', 'answer' => 'Stair tread glass and guard glass serve different loads. Guard specification follows railing engineering; do not reuse tread specs blindly.'],
        ['question' => 'Will glass get hot on terrace?', 'answer' => 'Glass and metal handrails heat in direct sun. Plan shading or handrail material comfort for south and west terraces.'],
        ['question' => 'Can PVD posts match my door finish?', 'answer' => 'Yes — specify PVD sample on posts and handrail caps to align with entrance and partition metalwork.'],
    ],
]);

writeArticle('interior-vs-exterior-railings-material-finish', [
    'content' => article(
        'Copying an interior railing specification to an exterior terrace is a common mistake in Indian residential projects. Interior guards enjoy stable humidity and no UV; exterior railings face monsoon, dust, thermal expansion and sometimes coastal salt. Material grade, infill choice and finish must respond to exposure — not just aesthetics.',
        [
            ['Environmental differences across India', [
                'Interior stair railings in air-conditioned Delhi apartments see mild variation. Sea-facing balconies in Mumbai or Chennai encounter salt aerosol and driving rain.',
                'Terrace railings in Bangalore may fog overnight then dry quickly — condensation cycles affect fixings and sealants differently than fully interior runs.',
            ]],
            ['Material selection', [
                '<strong>Stainless steel</strong> — 304 common interior; discuss 316 for harsh exterior or coastal exposure with your fabricator.',
                '<strong>Glass infill</strong> — popular both sides; exterior needs laminated safety glass and correct edge protection. See <a href="/blog/glass-railings-staircases-balconies-planning-checklist">glass railing checklist</a>.',
                '<strong>Mild steel with powder coat</strong> — sometimes used on exterior budget projects; touch-up and edge corrosion need active maintenance.',
                'Corten is generally not a handrail material — reserve weathering steel for cladding and features per <a href="/corten-steel">Corten applications</a>.',
            ]],
            ['Finish behaviour outdoors', [
                'Mirror polish stainless shows rain spots and dust; hairline or PVD champagne weathers more gracefully visually. PVD on exterior posts is project-specific — confirm suitability with fabricator for your exposure.',
                'Powder coat chalking and UV fade appear after years on some colours; dark colours heat more in sun — consider handrail comfort on rooftop bars.',
            ]],
            ['Fixing and drainage', [
                'Exterior base plates must not trap standing water. Seal penetrations properly; inspect after first monsoon for loosening due to thermal movement.',
                'Interior side-fixed brackets on stringers avoid floor ponding issues entirely — often preferred on stone stair treads.',
            ]],
            ['Design codes and user safety', [
                'Exterior guard height and opening limits may differ from interior mezzanine rules in your jurisdiction. Confirm with architect.',
                'Child-safe spacing matters on family terraces — vertical balusters or glass with minimal gaps where codes require.',
            ]],
            ['Specification workflow', [
                'Split schedules: interior PVD glass railings for stair and balcony overlooking living; exterior 316 or protected 304 with laminated glass for open terrace.',
                'Vyomika Atelier reviews exposure class from our Delhi studio for pan-India jobs. Share photos and orientation with your <a href="/railings">railing enquiry</a>.',
            ]],
        ],
        'Interior and exterior railings share visual language but not identical specifications. Match grade, glass build-up, finish and fixing to exposure class — and document both schedules clearly so site teams do not swap interior panels outdoors.',
        [
            'Explore <a href="/railings">railings</a>.',
            'Read <a href="/blog/stainless-steel-railings-types-finishes-selection-guide">stainless railing types</a>.',
            'For glass planning, see <a href="/blog/glass-railings-staircases-balconies-planning-checklist">glass railing checklist</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can interior PVD posts be used outside?', 'answer' => 'Only where exposure is sheltered and fabricator confirms suitability. Open terraces usually need exterior-rated detailing and grade review.'],
        ['question' => 'Does exterior glass need to be thicker?', 'answer' => 'Often yes, due to wind and guard load assumptions. Engineering determines thickness — not appearance alone.'],
        ['question' => 'How often to inspect exterior railings?', 'answer' => 'Visual inspection after each monsoon season is prudent — check base plates, glass chips and handrail firmness.'],
        ['question' => 'Powder coat or stainless outside?', 'answer' => 'Stainless avoids repainting cycles; powder-coated steel can work with maintenance budget for touch-up.'],
        ['question' => 'Should terrace and interior railings match visually?', 'answer' => 'They can match in profile and PVD colour while differing in glass build-up and grade — note differences on drawings.'],
    ],
]);

writeArticle('what-is-corten-steel-and-how-does-it-weather', [
    'content' => article(
        'Corten steel appears on Indian façades, garden screens and feature walls as a warm, rust-toned metal that looks centuries old within months. Architects specify it for texture and honesty of material — but successful use starts with understanding what weathering steel actually is and how the patina forms in our climate zones, from dry North India to humid coasts.',
        [
            ['Definition and alloy behaviour', [
                'Weathering steel — often branded COR-TEN — is a low-alloy steel containing copper, chromium, nickel and phosphorus. Exposure to wetting and drying cycles forms a dense oxide layer that slows further corrosion compared with uncoated mild steel.',
                'The surface moves through shades of orange, brown and deep umber. This is intentional oxidation, not structural failure, when thickness and detailing are appropriate for the application.',
            ]],
            ['The weathering timeline in India', [
                'Delhi and Rajasthan: patina may develop visibly over weeks in open air, settling through one or two monsoon seasons.',
                'Mumbai and Kochi: higher humidity accelerates early colour but also increases run-off staining risk on adjacent stone.',
                'Bangalore: moderate cycles; north-facing panels weather slower than rain-washed south faces.',
            ]],
            ['Where Corten is used architecturally', [
                'Feature cladding, entry portals, planter boxes, pergola fins and decorative screens — often as rain-screen over sealed backup walls rather than sole weather barrier.',
                'Explore application photos on our <a href="/corten-steel">Corten steel page</a> and modern façade context in <a href="/blog/why-corten-steel-is-perfect-for-modern-facades">why Corten suits modern façades</a>.',
            ]],
            ['What weathering is not', [
                'Corten is not maintenance-free in all locations. Ponded water on horizontal surfaces, embedded contact with dissimilar metals and chloride-rich environments can cause atypical corrosion.',
                'Interior feature pieces cut from Corten plate do not need outdoor weathering but may shed fine oxide dust if abraded during install — protect finishes nearby.',
            ]],
            ['Fabrication effects on patina', [
                'Cutting, welding and grinding expose bright steel that weather differently from mill scale. Weld seams may stay lighter longer unless blended or treated per fabricator method.',
                'Vyomika Atelier fabricates Corten elements from our Delhi studio with pan-India delivery. Share orientation drawings so we advise on drip edges and visible weld placement.',
            ]],
            ['Client communication', [
                'Set expectation: colour will not be uniform on day one. Photographs in brochures show mature patina; new installs look raw initially.',
                'Discuss run-off with landscape design — link to <a href="/blog/reduce-rust-run-off-staining-around-corten-steel">reducing rust run-off</a> before paving is laid.',
            ]],
        ],
        'Corten steel weathers by design: alloy chemistry and wet-dry cycles build a protective rust skin. In India, rate and tone vary by climate and orientation — specify it when variation is acceptable, drainage is resolved and clients understand the maturation process.',
        [
            'See <a href="/corten-steel">Corten steel</a> applications.',
            'Read <a href="/blog/corten-steel-facades-design-drainage-weathering">façade drainage and weathering</a>.',
            'Compare with paint in <a href="/blog/corten-steel-cladding-vs-conventional-painted-steel">Corten vs painted steel</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Is Corten the same as rusty mild steel?', 'answer' => 'No. Weathering steel forms a stabilising patina; mild steel rust continues without that alloy behaviour unless painted.'],
        ['question' => 'Can weathering be accelerated?', 'answer' => 'Some fabricators apply patina treatments to blend welds. Results vary and remain approximate versus natural weathering.'],
        ['question' => 'Does Corten work indoors?', 'answer' => 'As decorative plate yes, without further weathering. Ensure edges are deburred and consider sealing if dust is a concern.'],
        ['question' => 'Will monsoon restart rust every year?', 'answer' => 'After patina stabilises, seasonal colour deepening is subtle — not repeated structural rusting through the section.'],
        ['question' => 'What thickness for screens?', 'answer' => 'Depends on span, wind and fixing — structural advice from fabricator required. Share panel sizes for recommendation.'],
    ],
]);

writeArticle('corten-steel-facades-design-drainage-weathering', [
    'content' => article(
        'Corten façades succeed or fail on details invisible in hero renders: drip edges, backup wall sealing, panel joint orientation and how rainwater carries oxide onto stone below. For Indian projects with intense monsoon bursts and months of dry dust, drainage design is as important as the Corten pattern itself.',
        [
            ['Rain-screen vs structural skin', [
                'Most Corten façade projects use ventilated rain-screen cladding over waterproofed backup — masonry, AAC or metal deck. Corten panels provide appearance and some environmental separation; they should not be assumed to be the only moisture line without engineering review.',
                'Maintain an air cavity where detail allows to dry panels after rain. Trapped moisture behind flat panels on north façades in Pune can slow patina evenly but prolong damp contact at fixings.',
            ]],
            ['Panel orientation and joint logic', [
                'Vertical panels shed water faster than wide horizontal bands. Lap joints should direct water outward and downward, never inward toward structure.',
                'Perforated screens reduce wind load and allow partial transparency but still need top capping and bottom drip detail to avoid staining on plinths.',
            ]],
            ['Drip edges and catch details', [
                'Continuous drip noses on sill projections throw run-off away from glass and stone. On balconies with Corten soffits, slope minimum 2% toward outer edge where architecturally acceptable.',
                'Early weathering produces more oxide-rich run-off — plan landscaping and paving after cladding install or protect surfaces temporarily. See <a href="/blog/reduce-rust-run-off-staining-around-corten-steel">run-off reduction guide</a>.',
            ]],
            ['Fixings and thermal movement', [
                'Use compatible stainless or weathering-grade fixings; avoid trapped galvanic pairs without isolation. Long panels expand in summer sun — slot holes and concealed brackets accommodate movement on south and west elevations.',
                'Hide fixings from primary approach where possible; exposed screws on Corten age differently from plate face.',
            ]],
            ['Integration with other materials', [
                'Corten pairs well with glass railings, concrete and native stone. Contrast with <a href="/studio/main-entrance-pvd-doors">PVD entrance doors</a> for warm-cool balance on villa entries.',
                'Material primer: <a href="/blog/what-is-corten-steel-and-how-does-it-weather">what Corten is and how it weathers</a>.',
            ]],
            ['Vyomika Atelier façade support', [
                'From our Delhi studio we develop Corten screen and cladding packages for pan-India sites — shop drawings, fabrication and coordination with site cladding contractors. Submit elevations and section details early.',
            ]],
        ],
        'Corten façades in India need rain-screen thinking, outward-directed joints and drip discipline through monsoon. Design the weathering path as carefully as the panel pattern — oxide run-off during maturation is predictable and manageable when paving and drainage are planned together.',
        [
            'Browse <a href="/corten-steel">Corten steel</a>.',
            'Read <a href="/blog/why-corten-steel-is-perfect-for-modern-facades">Corten for modern façades</a>.',
            'Manage staining via <a href="/blog/reduce-rust-run-off-staining-around-corten-steel">run-off reduction</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Should Corten touch the ground?', 'answer' => 'Avoid direct contact with absorbent paving. Use raised kerb, planter edge or drip gap so run-off and splash do not wick upward.'],
        ['question' => 'Can Corten span floor to floor?', 'answer' => 'Large panels need structural backing and wind calculation. Perforated screens reduce weight but need frame support.'],
        ['question' => 'Do I seal Corten to stop run-off?', 'answer' => 'Clear sealants change appearance and may fail unevenly outdoors. Most architects manage run-off with geometry rather than sealing the patina.'],
        ['question' => 'What backup wall suits rain-screen?', 'answer' => 'Any properly waterproofed substrate with ventilated cavity — project engineer defines membrane and insulation stack.'],
        ['question' => 'When to install landscaping near Corten?', 'answer' => 'After initial weathering period or with surface protection — reduces staining on new stone and decking.'],
    ],
]);

writeArticle('corten-steel-cladding-vs-conventional-painted-steel', [
    'content' => article(
        'Façade designers in India often choose between weathering Corten steel and conventional painted steel cladding. Both are steel; lifecycle, appearance and maintenance diverge sharply. Painted systems offer colour control and predictable factory finish; Corten offers evolving patina and reduced repainting — with run-off and variation trade-offs.',
        [
            ['Initial appearance and client expectation', [
                'Painted steel arrives in exact RAL colour — uniform from day one. Corten arrives mill-finish, weathering toward rust tones over months. Client education prevents mismatch with marketing renders showing mature patina immediately.',
            ]],
            ['Maintenance cycles', [
                'Painted cladding needs periodic inspection for chip, fade and recoat — coastal and industrial atmospheres shorten intervals. Well-detailed Corten avoids repaint but needs run-off management and occasional washing of adjacent materials.',
                'Compare weathering behaviour in <a href="/blog/what-is-corten-steel-and-how-does-it-weather">how Corten weathers</a>.',
            ]],
            ['Cost structure', [
                'Painted mild steel systems may win on upfront material cost for large industrial façades. Corten plate and careful detailing can cost more initially but saves repaint labour over decades on feature zones — full lifecycle costing is project-specific, not universal.',
                'Avoid fake price comparisons; request quotations for your elevation area and fixing system.',
            ]],
            ['Design character', [
                'Painted steel suits corporate blue, white minimalist commercial parks and colour-brand headquarters. Corten suits boutique hospitality, farm retreats and residential feature walls seeking warmth.',
                'See <a href="/corten-steel">Corten applications</a> versus crisp PVD metal on entries via <a href="/studio/main-entrance-pvd-doors">main entrance doors</a> for mixed-material façades.',
            ]],
            ['Environmental and staining considerations', [
                'Painted systems can chalk and flake — environmental disposal of recoating matters. Corten run-off stains stone if ignored — neither is consequence-free.',
                'Drainage detail guide: <a href="/blog/corten-steel-facades-design-drainage-weathering">Corten façades and drainage</a>.',
            ]],
            ['When to combine both', [
                'Many projects use painted steel on rear and service elevations while Corten marks public face — budget and narrative aligned.',
                'Vyomika Atelier advises on Corten feature fabrication from Delhi with pan-India site coordination; painted systems may remain with separate cladding contractors.',
            ]],
        ],
        'Choose painted steel when uniform colour and factory predictability dominate. Choose Corten when patina, texture and long-term repaint avoidance justify run-off planning and colour variation. Mixed façades often use each where it performs best visually and economically.',
        [
            'Explore <a href="/corten-steel">Corten steel</a>.',
            'Read <a href="/blog/why-corten-steel-is-perfect-for-modern-facades">Corten for modern façades</a>.',
            'Plan drainage via <a href="/blog/corten-steel-facades-design-drainage-weathering">façade weathering guide</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can painted steel mimic Corten colour?', 'answer' => 'RAL metallic browns approximate patina but stay static — they do not weather organically. Some clients prefer that predictability.'],
        ['question' => 'Which lasts longer?', 'answer' => 'Depends on environment and maintenance. Corten avoids repaint; paint systems fail if maintenance stops. Both can serve decades when detailed correctly.'],
        ['question' => 'Is Corten heavier than painted panels?', 'answer' => 'Weight depends on gauge and profile, not finish type. Compare structural loads on your specification.'],
        ['question' => 'Can I switch from paint to Corten mid-project?', 'answer' => 'Possible on feature zones but requires revisiting detailing, backup wall and client expectation on weathering timeline.'],
        ['question' => 'Does Vyomika supply painted cladding?', 'answer' => 'Our focus is custom Corten and architectural metalwork — painted industrial cladding may sit with other suppliers on mixed projects.'],
    ],
]);

writeArticle('reduce-rust-run-off-staining-around-corten-steel', [
    'content' => article(
        'The rich patina that makes Corten desirable also produces iron oxide run-off during early weathering — particularly through the first monsoon cycles in India. Stained sandstone, white marble plinths and light concrete pavers below Corten can look neglected even when the metal itself is performing correctly. Mitigation is geometric and procedural, not a mystery chemical.',
        [
            ['Why run-off happens', [
                'Until the patina stabilises, rain washes loose oxide particles off the surface. Vertical façades shed downward; canopies drip concentrated streams at edges. This is normal — not a sign of defective steel if detailing follows weathering steel guidance.',
            ]],
            ['Detailing prevention', [
                'Drip grooves, outward-sloping sills and kerb gaps prevent run-off from tracking across sensitive stone. Avoid Corten overhangs directly above porous paving without drip edge throw.',
                'Elevate planters and panel bases above finished grade on hidden supports so splash does not soak into tile joints.',
                'Full façade guidance: <a href="/blog/corten-steel-facades-design-drainage-weathering">Corten façades design and drainage</a>.',
            ]],
            ['Landscape and paving sequencing', [
                'Install final paving after Corten has initial weathering where programme allows — or mask and wash paving during first season. Light granite and limestone show stains fastest.',
                'Dark basalt hides run-off better but is not a substitute for drip detail. Direct downpipes away from mixed-material junctions.',
            ]],
            ['Maintenance during maturation', [
                'Gentle water rinse of stained paving early may reduce deep absorption — avoid aggressive acid cleaners on natural stone without specialist advice.',
                'Do not wire-brush Corten to stop run-off — you reset patina and worsen short-term shedding.',
            ]],
            ['Material pairings', [
                'Pair Corten with tolerant materials at ground zone — concrete, dark aggregate, gravel strips — and keep delicate stone higher or set back.',
                'Upper façades in Corten above glass railings or <a href="/railings">stainless railings</a> reduce ground staining while keeping warmth at eye level.',
            ]],
            ['Project communication', [
                'Tell clients run-off eases after patina matures — typically one to two monsoon cycles in many North Indian sites, variable elsewhere.',
                'Vyomika Atelier includes drip recommendations on shop drawings from our Delhi studio for pan-India Corten installs.',
            ]],
        ],
        'Reduce Corten run-off staining with drip edges, sensible paving sequence and realistic client briefing — not by fighting the weathering process. Plan ground-level materials to tolerate early oxide wash, then maintain paving lightly through the maturation period.',
        [
            'See <a href="/corten-steel">Corten steel</a>.',
            'Understand patina in <a href="/blog/what-is-corten-steel-and-how-does-it-weather">how Corten weathers</a>.',
            'Design façades via <a href="/blog/why-corten-steel-is-perfect-for-modern-facades">modern façade guide</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can sealant stop all run-off?', 'answer' => 'Exterior sealants alter appearance and may fail unevenly. Geometric drip detail is the primary architectural solution.'],
        ['question' => 'How to clean stained sandstone?', 'answer' => 'Consult stone specialist — wrong acids etch stone. Prevention through detail beats remediation.'],
        ['question' => 'Does run-off harm plants?', 'answer' => 'Iron oxide runoff is mostly cosmetic for paving; soil chemistry effects vary — avoid direct drip on delicate planted borders if concerned.'],
        ['question' => 'Is first monsoon the worst?', 'answer' => 'Often yes for visible staining, then decreases as patina stabilises.'],
        ['question' => 'Should gutters be metal matched to Corten?', 'answer' => 'Compatible drainage metals or concealed PVC with careful colour choice avoids galvanic issues — detail with fabricator.'],
    ],
]);

writeArticle('pvd-door-handles-finishes-sizes-selection-guide', [
    'content' => article(
        'Door handles are the most touched architectural metal on any project — entrance pulls, lever sets on bedrooms, flush plates on sliding panels. PVD-coated stainless handles deliver champagne, rose gold and black tones aligned with partitions and doors without solid-brass tarnish. Selecting finish, size and backset early prevents mismatches against doors fabricated elsewhere.',
        [
            ['PVD finishes on handles', [
                'Champagne PVD suits warm neutral interiors common in Indian luxury apartments. Rose gold reads softer under warm LED downlights. Matte black gives graphic contrast on white doors and fluted glass.',
                'Deep dive: <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">PVD finish selection guide</a> and <a href="/blog/pvd-coating-explained-durable-metal-finishes">PVD coating explained</a>.',
            ]],
            ['Handle types by application', [
                '<strong>Pull handles</strong> — entrance and tall sliding doors; length affects ergonomics and proportion.',
                '<strong>Lever handles</strong> — hinged room doors; specify backset compatible with lock body.',
                '<strong>Flush pulls</strong> — sliding and pocket doors; recess depth must match door thickness.',
                'Browse ranges on <a href="/shop/door-handles">door handles</a>.',
            ]],
            ['Sizing and ergonomics', [
                'Entrance pulls often span 600–1200 mm on tall doors — see <a href="/blog/select-pull-handle-length-for-main-door">pull length guide</a>. Lever height typically 900–1050 mm AFF depending on door height and user profile.',
                'Children and elderly users benefit from consistent lever height across the home — note on door schedule.',
            ]],
            ['Coordination with doors', [
                'Order handles from same PVD batch as <a href="/studio/main-entrance-pvd-doors">entrance doors</a> and <a href="/studio/slim-profile-door-systems">slim profile doors</a> when possible. Mixed batches may show slight tone shift in daylight.',
                'Specify fixing centres and rose diameter before door prep — misaligned bore holes are costly to fix in stainless doors.',
            ]],
            ['Care and replacement', [
                'Wipe with soft cloth and pH-neutral cleaner; avoid abrasive pads. Individual handles can be replaced if damaged without remaking entire door — keep spare model reference on handover sheet.',
            ]],
            ['Ordering from Vyomika Atelier', [
                'We supply PVD handles matched to our door and partition projects from Delhi, shipping pan-India. Share door thickness, lock type and finish sample with enquiry.',
            ]],
        ],
        'Select PVD door handles by finish sample under project lighting, correct length for entrance scale and lock compatibility for levers. Coordinate batch and bore centres with door fabricator before production — handles are small but visually decisive.',
        [
            'Shop <a href="/shop/door-handles">door handles</a>.',
            'Match entrance scale in <a href="/blog/how-to-choose-luxury-main-entrance-door">luxury entrance guide</a>.',
            'Compare finishes in <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">finish selection guide</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Pull or lever on main door?', 'answer' => 'Large entrance leaves usually use pulls; levers suit standard hinged interior and service doors. Some entries combine pull outside and lever inside.'],
        ['question' => 'Does PVD wear on handles?', 'answer' => 'High-touch areas may show polish over years. PVD remains structural; appearance can be refreshed or replaced per handle.'],
        ['question' => 'Can handles match tapware exactly?', 'answer' => 'Compare physical samples — plated brass tapware may differ from PVD stainless even when both labelled gold.'],
        ['question' => 'What backset for Indian locks?', 'answer' => 'Confirm with lock supplier — common backsets exist but brand varies. Share lock model with handle supplier.'],
        ['question' => 'Are long pulls harder to install?', 'answer' => 'Need accurate hole alignment and sufficient door core strength. Through-bolted pulls suit heavy entrance doors.'],
    ],
]);

writeArticle('select-pull-handle-length-for-main-door', [
    'content' => article(
        'Pull handle length on a main entrance door affects grip comfort, visual balance and perceived luxury. Too short on a 2.7 m tall leaf looks like an afterthought; too long on a modest apartment door overwhelms the panel. Use door height, leaf width and symmetry rules to choose length before holes are cut in PVD stainless.',
        [
            ['Proportion to door height', [
                'A common starting point: pull length roughly 25–35% of door height for vertical centre-mounted pulls on single leaves. A 2400 mm door often suits 600–850 mm pulls depending on design boldness.',
                'Double-leaf entries may use two vertical pulls of equal length or one long pull on active leaf only — symmetry vs function.',
            ]],
            ['Single vs offset double pulls', [
                'One centred vertical pull suits minimalist doors. Offset pairs (upper and lower) suit very tall hotel doors for ergonomic reach — less common in residential India.',
                'Horizontal pulls across door width appear on contemporary commercial entries; ensure structural backing for torque on wide leaves.',
            ]],
            ['Handedness and daily use', [
                'Active leaf pull should fall naturally at push/pull side — confirm swing direction on shop drawings. Family members of varied height benefit from pulls mounted slightly below geometric centre.',
                'Coordinate with <a href="/studio/main-entrance-pvd-doors">main entrance PVD doors</a> fabrication schedule.',
            ]],
            ['Finish and section profile', [
                'Round section pulls feel classic; square or rectangular profiles read architectural. Larger diameter improves grip for gloved security staff on commercial projects.',
                'Match PVD finish to door frame via <a href="/shop/door-handles">door handles</a> from same batch where possible.',
            ]],
            ['Installation constraints', [
                'Through-bolts need door core or reinforcement plate. Glass-infill doors require pull mounting into solid frame zones only — never glass alone.',
                'Lock height and vision panel location must clear pull backplate — share elevation with fabricator early.',
            ]],
            ['Reference and ordering', [
                'See adjacent projects on <a href="/projects">projects page</a> for proportion cues.',
                'Vyomika Atelier advises pull length with door orders from our Delhi studio for pan-India clients — send door elevation PDF with enquiry.',
            ]],
        ],
        'Choose main door pull length from door height, leaf count and visual weight of the façade. Centre-mounted vertical pulls near 25–35% of door height are a reliable starting range — then adjust with mock-up tape on site before drilling.',
        [
            'Browse <a href="/shop/door-handles">door handles</a>.',
            'Read <a href="/blog/how-to-choose-luxury-main-entrance-door">luxury entrance door guide</a>.',
            'Compare door faces in <a href="/blog/stainless-steel-glass-etched-entrance-doors-compared">steel, glass and etched doors</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Same pull length on double doors?', 'answer' => 'Symmetry usually demands matching lengths on both leaves; active-only pulls are acceptable in some commercial layouts.'],
        ['question' => 'Minimum pull length for villa door?', 'answer' => 'Very short pulls under 400 mm on tall doors look disproportionate — most villa entries start near 600 mm unless deliberately minimal.'],
        ['question' => 'Can pull be wider than door stile?', 'answer' => 'Pull mounts to door face, not stile width — ensure backplate fits solid zone away from glass joints.'],
        ['question' => 'Does longer pull cost much more?', 'answer' => 'Cost rises with length and section size — request quotation for two length options if budget is tight.'],
        ['question' => 'Pull on push side only?', 'answer' => 'Common on entries with interior lever — specify pull outside, lever or flush inside.'],
    ],
]);

writeArticle('pvd-furniture-care-finishes-customization', [
    'content' => article(
        'PVD-coated metal furniture — coffee tables, consoles, custom benches — brings the same champagne and black language as partitions and doors into the furnished layer. In Indian homes with staff cleaning, children and occasional monsoon humidity through open windows, care and finish choice matter as much as the design sketch.',
        [
            ['What PVD furniture includes', [
                'Table bases, frame legs, metal aprons and decorative inserts in stainless with PVD finish. Tops may be stone, glass or wood — metal carries the durable decorative surface.',
                'Explore <a href="/shop/bespoke-metal-furniture">bespoke metal furniture</a> and <a href="/shop/coffee-tables">coffee tables</a> for starting points.',
            ]],
            ['Finish selection for furniture', [
                'Hairline champagne hides fine scratches better than mirror gold. Matte black suits high-touch family rooms. Rose gold fits boutique bedrooms with lower abrasion.',
                'Compare tones in <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">PVD finish guide</a> under your actual downlights.',
            ]],
            ['Daily care in Indian homes', [
                'Dust with soft dry cloth; damp microfibre for fingerprints. Avoid steel wool, scouring powder and chloride bleach cleaners common in some housekeeping cupboards.',
                'Coasters on metal-framed stone tops prevent ring marks on stone, not PVD — but acidic drinks on unsealed stone need usual stone care.',
            ]],
            ['Humidity and placement', [
                'Interior air-conditioned spaces suit 304 stainless PVD furniture standard. Avoid placing metal furniture in direct monsoon splash zones on open terraces without exterior-grade review.',
                'Keep metal legs away from floor mopping pools — standing water affects stone tops and can wick into joint details over time.',
            ]],
            ['Customization workflow', [
                'Share room dimensions, sofa height and preferred top material. Vyomika Atelier develops custom sizes from our Delhi studio with pan-India freight — lead time depends on stone sourcing and finish batch.',
                'Coordinate with <a href="/studio/pvd-partitions">partition finishes</a> on open-plan living for one metal palette.',
            ]],
            ['Repair and refresh', [
                'Deep scratches in PVD may need factory refinishing of component — not field spray paint. Document finish code on delivery for future matching.',
            ]],
        ],
        'PVD furniture stays beautiful with gentle cleaning, sensible placement and finish chosen for touch level in each room. Customize dimensions and tops to the interior, but keep finish samples aligned with architectural metal elsewhere for a coherent Vyomika Atelier home.',
        [
            'See <a href="/shop/bespoke-metal-furniture">bespoke metal furniture</a>.',
            'Browse <a href="/shop/coffee-tables">coffee tables</a>.',
            'Read <a href="/blog/pvd-coating-explained-durable-metal-finishes">PVD coating explained</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Can PVD tables go on terrace?', 'answer' => 'Interior PVD furniture is not typically specified for open terrace exposure — discuss exterior-grade options if outdoor use is required.'],
        ['question' => 'Does marble top need special base care?', 'answer' => 'Support evenly; avoid dragging topped table across floor — stress can flex frame joints.'],
        ['question' => 'Custom size lead time?', 'answer' => 'Depends on design complexity and stone availability — share dimensions for project-specific programme.'],
        ['question' => 'Will cleaning staff damage PVD?', 'answer' => 'Train staff to avoid abrasive powders and steel scrubbers — same as PVD doors and partitions.'],
        ['question' => 'Match furniture to door handles?', 'answer' => 'Yes — specify same PVD colour sample name on furniture and handle orders.'],
    ],
]);

writeArticle('choosing-metal-coffee-table-luxury-interior', [
    'content' => article(
        'A metal coffee table anchors the living room seating group — scale, finish and top material signal the interior\'s ambition before anyone sits down. In Indian luxury homes, PVD stainless bases with stone or glass tops bridge architectural metalwork from entrance and partitions into furnished comfort.',
        [
            ['Scale relative to sofa and room', [
                'Table length often ½ to ⅔ of sofa length; height typically 400–450 mm to match seat cushion level. Oversized tables dominate compact Mumbai living rooms; undersized look lost in Gurgaon villa lounges.',
                'Leave 450–500 mm clearance between table edge and sofa for passage — tighter in small flats.',
            ]],
            ['Base design and visual weight', [
                'Sculptural PVD bases in champagne or black create focal points on neutral rugs. Minimal frame bases suit rooms already rich in pattern — marble floors, feature walls.',
                'Browse <a href="/shop/coffee-tables">coffee tables</a> and custom options on <a href="/shop/bespoke-metal-furniture">bespoke metal furniture</a>.',
            ]],
            ['Top materials', [
                '<strong>Marble and quartzite</strong> — popular in North India; book-match for statement.',
                '<strong>Tempered glass</strong> — light visual weight; shows base detail.',
                '<strong>Wood</strong> — warmth contrast to PVD metal; specify edge sealing for humidity.',
            ]],
            ['Finish coordination', [
                'Match PVD base to <a href="/studio/pvd-partitions">living room partition</a> and <a href="/shop/door-handles">handles</a> for cohesive open plans.',
                'Finish guide: <a href="/blog/pvd-finish-selection-guide-gold-rose-gold-champagne-black">gold, rose gold, champagne, black</a>.',
            ]],
            ['Practical Indian living', [
                'Tables receive trays, laptops and children\'s homework — avoid sharp glass corners in active family rooms or choose rounded tops.',
                'Liftable tops or open base design help robot vacuums common in new urban homes pass underneath.',
            ]],
            ['Ordering custom tables', [
                'Vyomika Atelier builds to your dimensions from Delhi with pan-India delivery. Share sofa spec sheet, rug size and floor photos for proportion advice.',
            ]],
        ],
        'Choose a luxury metal coffee table by sofa proportion, base finish aligned with architectural metal, and top material suited to daily Indian living. The right table feels inevitable in the room — neither cramped nor floating in empty space.',
        [
            'Shop <a href="/shop/coffee-tables">coffee tables</a>.',
            'Explore <a href="/shop/bespoke-metal-furniture">bespoke metal furniture</a>.',
            'Care tips in <a href="/blog/pvd-furniture-care-finishes-customization">PVD furniture care</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Round or rectangular for small living room?', 'answer' => 'Round tables ease circulation in tight layouts; rectangular align with standard sofa symmetry.'],
        ['question' => 'Glass top with children?', 'answer' => 'Tempered glass is standard; consider rounded corners and avoid sharp edge profiles in play zones.'],
        ['question' => 'Can table match partition finish exactly?', 'answer' => 'Specify same PVD sample code on both orders for best match.'],
        ['question' => 'Marble maintenance?', 'answer' => 'Seal per stone supplier advice; wipe spills promptly — metal base care is separate from stone top care.'],
        ['question' => 'Typical custom lead time?', 'answer' => 'Varies with stone procurement and size — enquire with dimensions for programme.'],
    ],
]);

writeArticle('architects-specify-custom-architectural-metalwork', [
    'content' => article(
        'Custom architectural metalwork — PVD partitions, entrance doors, railings, Corten screens and furniture — requires clearer specifications than catalogue joinery. Indian architects who document finish samples, fixing assumptions and coordination milestones reduce site conflict and change orders. This guide outlines what to put on drawings and when to involve the fabricator.',
        [
            ['Early engagement vs late procurement', [
                'Involve metal fabricator during schematic design when floor plates, double-height volumes and entrance axes are set. Late addition of full-height PVD partitions often conflicts with already cast slab edges or MEP drops.',
                'Vyomika Atelier collaborates with architects from our Delhi studio on pan-India projects via the <a href="/professionals">professionals page</a>.',
            ]],
            ['Drawing set essentials', [
                'Plan dimensions to finished surfaces, elevation heights, opening widths, swing directions, glass type notes, PVD finish sample reference, fixing method (floor/ceiling/wall), and coordination with electrical for door interfaces.',
                'Reference studio pages for scope language: <a href="/studio/pvd-partitions">PVD partitions</a>, <a href="/studio/main-entrance-pvd-doors">entrance doors</a>, <a href="/railings">railings</a>, <a href="/corten-steel">Corten</a>.',
            ]],
            ['Finish schedules', [
                'One line per visible metal element: location, substrate, PVD colour name from approved sample, surface prep (hairline/mirror), orientation of brush direction on adjacent panels.',
                'Cross-link hardware on <a href="/shop/door-handles">door handles</a> with same finish code.',
            ]],
            ['Structural and MEP coordination', [
                'Confirm slab penetration policy with structural engineer. Ceiling-mounted partition tracks need clear services path in BOQ stage.',
                'Floor drain locations near entrance frames affect threshold detail — note on door schedule.',
            ]],
            ['Performance language without overclaiming', [
                'State required glass safety type, guard height intent and exposure class (interior/exterior) without inventing test certificates. Refer compliance to project consultant of record.',
            ]],
            ['Tender and substitution', [
                'Define approved fabricator or performance spec to prevent low bids that omit PVD batch matching or correct glass thickness. Substitution clause should require sample approval.',
                'Process overview: <a href="/blog/drawing-to-installation-custom-metal-fabrication-process">drawing to installation</a>.',
            ]],
        ],
        'Architects specify custom metalwork successfully with early fabricator input, finish schedules tied to physical samples, and drawings that show fixing and glass — not just elevation aesthetics. Treat metal as primary architecture, not a shop drawing afterthought.',
        [
            'Start at <a href="/professionals">professionals</a>.',
            'See process in <a href="/blog/drawing-to-installation-custom-metal-fabrication-process">fabrication process guide</a>.',
            'Browse <a href="/projects">projects</a> for specification references.',
        ]
    ),
    'faq' => [
        ['question' => 'When to issue finish sample to client?', 'answer' => 'Before bulk fabrication — typically at shop drawing approval with 300 mm sample or project-specific mock-up.'],
        ['question' => 'Who measures site?', 'answer' => 'Fabricator site measure after finishes are near complete, or architect provides as-built dimensions with tolerance notes.'],
        ['question' => 'Can one spec cover all metal?', 'answer' => 'Use one finish schedule; separate sheets for partitions, doors, railings and Corten with item-specific fixing notes.'],
        ['question' => 'How to specify Corten without run-off surprise?', 'answer' => 'Include drip detail reference and paving note — link landscape architect to metal shop drawings.'],
        ['question' => 'Do you accept NBS-style specs?', 'answer' => 'Share your template — we map to our fabrication scope and clarify exclusions in quotation.'],
    ],
]);

writeArticle('drawing-to-installation-custom-metal-fabrication-process', [
    'content' => article(
        'Custom metal fabrication at Vyomika Atelier follows a predictable sequence from first brief to site handover — whether the item is a PVD partition in Gurgaon, a main entrance door in Hyderabad or Corten screens in Goa. Understanding the process helps architects programme interiors realistically and homeowners know when to lock dimensions.',
        [
            ['Enquiry and brief review', [
                'Share plans, photos, inspiration references and finish preferences via <a href="/contact">contact</a> or <a href="/professionals">professionals</a> for trade enquiries. We confirm scope fit — partitions, doors, railings, Corten or furniture.',
            ]],
            ['Concept and budget alignment', [
                'Initial guidance on system type, glass and finish narrows cost drivers without promising catalogue rates. Complex items may need paid site visit before firm quote in distant cities.',
            ]],
            ['Site measurement or dimension confirmation', [
                'Final dimensions taken after floor screed and ceiling finish where possible. Renovation projects measure existing openings with laser level notes.',
                'Partition and door guides: <a href="/studio/pvd-partitions">PVD partitions</a>, <a href="/studio/main-entrance-pvd-doors">entrance doors</a>.',
            ]],
            ['Shop drawings and sample approval', [
                'Detailed drawings show panel splits, hardware, fixing plates and glass make-up. PVD finish sample approved before batch production.',
                'Architect sign-off recommended on commercial projects; residential clients approve via marked PDF.',
            ]],
            ['Fabrication at Delhi studio', [
                'CNC cutting, welding, grinding, PVD chamber batch and glass assembly under QC check. Corten items may weather partially before dispatch depending on scope.',
            ]],
            ['Delivery, installation and handover', [
                'Packed transport pan-India; installation team or client contractor fixes per drawing. Handover includes care notes for PVD and glass — not generic guarantees.',
                'See completed work on <a href="/projects">projects</a>.',
            ]],
        ],
        'From drawing to installation, custom metalwork needs measured dimensions, approved shop drawings and finish samples before fabrication begins. Programme site readiness before ordering — rework costs more than waiting one week for final floor levels.',
        [
            'Begin at <a href="/contact">contact</a>.',
            'For architects, see <a href="/blog/architects-specify-custom-architectural-metalwork">specification guide</a>.',
            'Explore <a href="/studio/pvd-partitions">PVD partitions</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'How long from approval to delivery?', 'answer' => 'Programme is project-specific — size, finish batch and glass type drive duration. Share scope for estimated timeline.'],
        ['question' => 'Can installation be client contractor?', 'answer' => 'Yes with approved fixing drawings; we remain available for technical clarification on site.'],
        ['question' => 'What if site dimensions change after order?', 'answer' => 'Minor tolerance exists; major changes may require redraw and remanufacture — confirm dimensions at approval stage.'],
        ['question' => 'Do you ship outside major cities?', 'answer' => 'Yes pan-India; freight and handling vary by size and access.'],
        ['question' => 'Is PVD done before or after welding?', 'answer' => 'Typically components are finished in chamber after welding and surface prep — field welding on finished PVD is avoided.'],
    ],
]);

writeArticle('pvd-finish-selection-guide-gold-rose-gold-champagne-black', [
    'content' => article(
        'Choosing among gold, rose gold, champagne and black PVD finishes shapes every metal element in a coordinated interior — partitions, doors, handles, railings and furniture. Under Indian LED lighting and warm daylight, tones read differently than supplier swatches photographed in neutral studios. Use this guide to shortlist finishes with samples on site, not screen colour alone.',
        [
            ['Champagne PVD', [
                'Warm neutral between gold and silver; pairs with beige stone, oak flooring and linen upholstery common in Delhi and Mumbai luxury apartments. Less assertive than bright gold on large door planes.',
                'Hairline champagne hides minor scratches on partitions and handles — popular default for whole-home metal palettes.',
            ]],
            ['Rose gold PVD', [
                'Softer pink-gold tone favoured in retail fit-outs and master suites. Can read coppery under 3000K warm LEDs — verify in evening lighting.',
                'Works with blush plaster, terrazzo and walnut; avoid clash with orange-toned teak if tone is too pink.',
            ]],
            ['Gold mirror and bright gold PVD', [
                'Higher reflectivity suited to accent features — entrance door bands, table bases — rather than entire floor of mirror panels unless brief demands glamour.',
                'Fingerprints show on mirror gold; specify satin or hairline gold variants for high-touch zones.',
            ]],
            ['Matte black PVD', [
                'Graphic contrast on white walls and fluted glass doors. Collects dust visibly in dry cities — acceptable in low-touch vertical planes.',
                'Pairs with Corten feature walls externally for warm-cool juxtaposition on villa entries.',
            ]],
            ['Sampling protocol', [
                'Place 300 mm samples against floor tile, wall paint and window light at 10:00 and 18:00. Photograph with phone but decide with eye.',
                'Batch order <a href="/studio/pvd-partitions">partitions</a>, <a href="/studio/main-entrance-pvd-doors">doors</a> and <a href="/shop/door-handles">handles</a> together when colour uniformity matters.',
            ]],
            ['Technical background', [
                'PVD is vacuum-deposited on prepared stainless — not paint. Read <a href="/blog/pvd-coating-explained-durable-metal-finishes">PVD coating explained</a> for durability expectations vs powder coat.',
                'Vyomika Atelier maintains finish samples at our Delhi studio for pan-India project courier on request.',
            ]],
        ],
        'Gold, rose gold, champagne and black PVD each suit different palettes and touch levels. Champagne is the versatile warm neutral for whole-home coordination; rose gold and bright gold accent; black structures contemporary contrast — always confirm with physical samples under project lighting.',
        [
            'See <a href="/studio/pvd-partitions">PVD partitions</a>.',
            'Shop <a href="/shop/door-handles">door handles</a>.',
            'Furniture coordination in <a href="/blog/pvd-furniture-care-finishes-customization">PVD furniture care</a>.',
        ]
    ),
    'faq' => [
        ['question' => 'Most popular finish in India projects?', 'answer' => 'Champagne hairline leads for residential coordination; matte black strong in contemporary commercial lobbies.'],
        ['question' => 'Does rose gold suit traditional interiors?', 'answer' => 'It can, with stone and wood tones tested — may fight heavy red teak unless sampled carefully.'],
        ['question' => 'Can two PVD colours mix on one door?', 'answer' => 'Yes — frame black with champagne pull is common; limit to two tones per room for clarity.'],
        ['question' => 'Will gold PVD tarnish like brass?', 'answer' => 'PVD on stainless does not tarnish like solid brass; maintain with non-abrasive cleaning.'],
        ['question' => 'Sample courier outside Delhi?', 'answer' => 'Yes — request samples with project enquiry for pan-India review before batch order.'],
    ],
]);

echo "Generated all 25 blog articles.\n";
