<?php

/**
 * About page — brand story, capabilities, exhibitions, values.
 */
return [
    'meta_title' => 'About Vyomika Atelier | Architectural Metalwork & Exhibitions',
    'meta_description' => 'Discover Vyomika Atelier, our custom metalwork expertise and exhibition journey across Delhi and London.',

    'hero' => [
        'label' => 'About',
        'title' => 'About Vyomika Atelier',
        'subtitle' => 'Bespoke architectural metalwork, premium finishes and custom fabrication for distinctive interiors.',
        'image' => '/images/exhibitions/hero-studio.svg',
    ],

    'brand_story' => [
        'title' => 'Crafted Beyond Convention',
        'paragraphs' => [
            'Vyomika Atelier is a design-led metal fabrication studio based in Delhi. We translate architectural intent into precision-built partitions, doors, furniture and feature metalwork — finished in PVD, brushed metal and Corten steel.',
            'Every piece is custom-manufactured in our studio with documented QC, secure packaging and Pan-India delivery. We collaborate closely with architects, interior designers, contractors and dealers to deliver metalwork that meets exacting briefs.',
            'From concept drawings to site-ready installation, we bring material expertise and reliable execution to residential, hospitality and commercial projects.',
        ],
        'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80',
    ],

    'capabilities' => [
        'title' => 'Capabilities',
        'items' => [
            [
                'name' => 'PVD Partitions',
                'text' => 'Wave, fluted and laser-cut room dividers in champagne, rose gold and matte black PVD.',
                'route' => 'studio.show',
                'params' => ['slug' => 'pvd-partitions'],
                'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
            ],
            [
                'name' => 'Designer Doors',
                'text' => 'Slim profile systems and main entrance PVD doors engineered for premium entrances.',
                'route' => 'studio.show',
                'params' => ['slug' => 'slim-profile-door-systems'],
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
            ],
            [
                'name' => 'Mirrors',
                'text' => 'Framed and frameless metal mirror systems with integrated lighting and custom profiles.',
                'route' => 'shop.mirror-frames.index',
                'image' => 'https://images.unsplash.com/photo-1618220179428-22790b461013?w=800&q=80',
            ],
            [
                'name' => 'Furniture',
                'text' => 'Bespoke coffee tables, consoles, racks and display systems in PVD and brushed metal.',
                'route' => 'shop.show',
                'params' => ['slug' => 'bespoke-metal-furniture'],
                'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80',
            ],
            [
                'name' => 'Railings',
                'text' => 'Stair and balcony railings, balustrades and handrails in stainless and PVD finishes.',
                'route' => 'railings.index',
                'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=800&q=80',
            ],
            [
                'name' => 'Corten Steel',
                'text' => 'Weathering steel façades, screens and landscape features with controlled patina.',
                'route' => 'services.show',
                'params' => ['slug' => 'corten-steel-facade'],
                'image' => 'https://images.unsplash.com/photo-1600585154526-990dced4db0d?w=800&q=80',
            ],
            [
                'name' => 'Custom Metal Fabrication',
                'text' => 'End-to-end CNC cutting, welding and finishing for one-off architectural metalwork.',
                'route' => 'leads.create',
                'image' => 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=800&q=80',
            ],
        ],
    ],

    'exhibitions' => [
        'title' => 'Our Exhibition Journey',
        'subtitle' => 'Showcasing architectural metalwork at leading design and construction fairs across India and the UK.',
        'events' => [
            [
                'slug' => 'index-2023',
                'name' => 'INDEX 2023',
                'location' => 'Mumbai',
                'year' => 2023,
                'summary' => 'Our debut at INDEX Mumbai — presenting PVD partitions and bespoke metal furniture to architects and interior designers.',
                'images' => [
                    '/images/exhibitions/index-2023/01.svg',
                    '/images/exhibitions/index-2023/02.svg',
                    '/images/exhibitions/index-2023/03.svg',
                ],
            ],
            [
                'slug' => 'ice-expo-2023',
                'name' => 'ICE Expo',
                'location' => 'Mumbai',
                'year' => 2023,
                'summary' => 'ICE Expo Mumbai — connecting with contractors and developers on Corten features and entrance systems.',
                'images' => [
                    '/images/exhibitions/ice-expo-2023/01.svg',
                    '/images/exhibitions/ice-expo-2023/02.svg',
                    '/images/exhibitions/ice-expo-2023/03.svg',
                ],
            ],
            [
                'slug' => 'index-2024',
                'name' => 'INDEX 2024',
                'location' => 'Mumbai',
                'year' => 2024,
                'summary' => 'Return to INDEX with expanded collections — fluted panels, slim profile doors and live finish sampling.',
                'images' => [
                    '/images/exhibitions/index-2024/01.svg',
                    '/images/exhibitions/index-2024/02.svg',
                    '/images/exhibitions/index-2024/03.svg',
                ],
            ],
            [
                'slug' => 'uk-construction-week-2025',
                'name' => 'UK Construction Week',
                'location' => 'London',
                'year' => 2025,
                'summary' => 'International debut at UK Construction Week — introducing Vyomika Atelier metalwork to the UK specification market.',
                'images' => [
                    '/images/exhibitions/uk-construction-week-2025/01.svg',
                    '/images/exhibitions/uk-construction-week-2025/02.svg',
                    '/images/exhibitions/uk-construction-week-2025/03.svg',
                ],
            ],
        ],
    ],

    'values' => [
        'title' => 'What We Stand For',
        'items' => [
            [
                'title' => 'Custom Design',
                'text' => 'Every project starts with your drawings and vision — we engineer metalwork to your exact dimensions and finish.',
            ],
            [
                'title' => 'Precision Fabrication',
                'text' => 'CNC cutting, welded assemblies and documented QC from our Delhi studio.',
            ],
            [
                'title' => 'Premium Finishes',
                'text' => 'PVD coatings, brushed metal and Corten patina applied with lasting lustre and low maintenance.',
            ],
            [
                'title' => 'Project Collaboration',
                'text' => 'Dedicated support for architects, designers, contractors and dealers through every project phase.',
            ],
            [
                'title' => 'Reliable Execution',
                'text' => 'Clear timelines, secure packaging and Pan-India delivery — typically 3–4 weeks from drawing approval.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Let\'s Create Something Distinctive',
        'body' => 'Whether you are specifying a single partition or a full lobby programme, our studio team is ready to collaborate.',
        'cta_primary' => ['label' => 'View Projects', 'route' => 'projects.index'],
        'cta_secondary' => ['label' => 'Contact Us', 'route' => 'contact.index'],
    ],
];
