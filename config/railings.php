<?php

/**
 * Studio Railings — single-page showcase + quotation form.
 */
return [
    'meta_title' => 'Custom Railings & Balustrades | Vyomika Atelier',
    'meta_description' => 'Glass, fabricated, per-step baluster and wrought iron railings for indoor and exterior staircases — straight, L-shape, U-shape, curved and custom layouts. Request a quotation from Vyomika Atelier.',

    'hero' => [
        'label' => 'Railings',
        'title' => 'Railings & Balustrades',
        'subtitle' => 'Precision-fabricated stair railings, glass balustrades and bespoke metal guards — engineered for Indian residences, hospitality and commercial projects.',
        'highlights' => [
            'Glass & metal systems',
            'Indoor & exterior rated',
            'Pan-India fabrication',
        ],
        'cta_primary' => ['label' => 'Request Quotation', 'href' => '#railing-quote'],
        'cta_secondary' => ['label' => 'View Categories', 'href' => '#railing-categories'],
        'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=1400&q=80',
        'image_alt' => 'Custom staircase railings',
    ],

    'intro' => [
        'title' => 'Metalwork That Defines the Staircase',
        'body' => 'From frameless glass with stainless posts to forged wrought iron and CNC-fabricated balusters, Vyomika Atelier designs, fabricates and delivers railing systems that meet IS safety expectations while elevating your interior or façade.',
    ],

    'categories' => [
        'title' => 'Railing Categories',
        'subtitle' => 'Six core systems we fabricate and install across Mumbai, Pune, Bangalore, Delhi NCR and Pan-India projects.',
        'items' => [
            [
                'title' => 'Glass Railings',
                'text' => 'Toughened glass panels with stainless or black PVD posts and top rails — ideal for modern staircases and balconies with uninterrupted views.',
                'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=800&q=80',
            ],
            [
                'title' => 'Fabricated Railings',
                'text' => 'Custom stainless or mild-steel sections — flat bar, tube and plate assemblies CNC-cut and welded in our Delhi studio.',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
            ],
            [
                'title' => 'Per-Step Baluster Railings',
                'text' => 'Individual balusters anchored per tread — classic for heritage homes, duplexes and grand entrance stairs.',
                'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800&q=80',
            ],
            [
                'title' => 'Wrought Iron Railings',
                'text' => 'Scrollwork, pickets and hammered details with durable powder-coat or patina finishes for villas and boutique hotels.',
                'image' => 'https://images.unsplash.com/photo-1600585154526-990dced4db0d?w=800&q=80',
            ],
            [
                'title' => 'Indoor Railings',
                'text' => 'Low-profile profiles, PVD champagne or matte black finishes, and concealed fixings suited to apartments and offices.',
                'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800&q=80',
            ],
            [
                'title' => 'Exterior Railings',
                'text' => 'Weather-resistant grades, drainage detailing and corrosion-resistant coatings for terraces, ramps and outdoor stairs.',
                'image' => 'https://images.unsplash.com/photo-1600585154526-990dced4db0d?w=800&q=80',
            ],
        ],
    ],

    'layouts' => [
        'title' => 'Staircase & Layout Shapes',
        'subtitle' => 'We template, fabricate and install for common and complex geometries.',
        'items' => [
            ['title' => 'Straight', 'text' => 'Single-run stairs — fastest to template and install.'],
            ['title' => 'L-Shape', 'text' => 'Quarter-turn with landing — custom post angles and glass mitres.'],
            ['title' => 'U-Shape', 'text' => 'Half-turn or dog-leg configurations with continuous handrail options.'],
            ['title' => 'Cutout', 'text' => 'Stairs wrapping a lift core or void — precision site measurement essential.'],
            ['title' => 'Curved', 'text' => 'Helical or sweeping curves — segmented glass or bent metal rails.'],
            ['title' => 'Custom', 'text' => 'Split-level, floating treads or architect-drawn one-offs — share DWG/PDF drawings.'],
        ],
    ],

    'why' => [
        'title' => 'Why Specify Vyomika Atelier',
        'items' => [
            'Site measurement and shop drawings before fabrication',
            'Grade 304/316 stainless, toughened glass and IS-aligned guard heights',
            'PVD, powder-coat and brushed finishes to match doors and partitions',
            'Delhi studio QC with labelled crating for safe Pan-India delivery',
            'Coordination with your contractor or architect on site',
        ],
        'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=900&q=80',
        'image_alt' => 'Staircase railing fabrication',
    ],

    'quote' => [
        'title' => 'Request a Quotation',
        'body' => 'Tell us about your staircase, material preferences and site location. Attach a photo or drawing if you have one — we will respond with timelines and an indicative quote.',
        'bullets' => [
            'Site measurement available in Delhi NCR',
            'Shop drawings shared before fabrication',
            'Glass, stainless, MS and wrought iron systems',
        ],
    ],

    'form' => [
        'customer_types' => [
            'homeowner' => 'Homeowner',
            'architect' => 'Architect',
            'interior_designer' => 'Interior designer',
            'contractor' => 'Contractor',
            'developer' => 'Developer',
            'other' => 'Other',
        ],
        'usage' => [
            'indoor' => 'Indoor',
            'exterior' => 'Exterior',
            'both' => 'Indoor & exterior',
        ],
        'railing_categories' => [
            'glass' => 'Glass railings',
            'fabricated' => 'Fabricated railings',
            'per_step_baluster' => 'Per-step baluster railings',
            'wrought_iron' => 'Wrought iron railings',
            'indoor' => 'Indoor railings',
            'exterior' => 'Exterior railings',
        ],
        'layout_shapes' => [
            'straight' => 'Straight',
            'l_shape' => 'L-shape',
            'u_shape' => 'U-shape',
            'cutout' => 'Cutout',
            'curved' => 'Curved',
            'custom' => 'Custom layout',
        ],
        'materials' => [
            'ss_304' => 'Stainless steel 304',
            'ss_316' => 'Stainless steel 316',
            'mild_steel' => 'Mild steel',
            'wrought_iron' => 'Wrought iron',
            'glass_metal' => 'Glass + metal system',
            'other' => 'Other / unsure',
        ],
        'finishes' => [
            'pvd_champagne' => 'PVD Champagne',
            'pvd_rose_gold' => 'PVD Rose gold',
            'pvd_black' => 'PVD Matte black',
            'powder_coat' => 'Powder coat',
            'brushed_ss' => 'Brushed stainless',
            'corten' => 'Corten / weathering steel',
            'other' => 'Other',
        ],
        'heights' => [
            '900' => '900 mm',
            '1000' => '1000 mm',
            '1100' => '1100 mm',
            'custom' => 'Custom height',
        ],
        'timelines' => [
            'urgent' => 'Urgent — within 3 weeks',
            'standard' => '3–5 weeks',
            'flexible' => '1–2 months',
            'planning' => 'Planning stage only',
        ],
    ],
];
