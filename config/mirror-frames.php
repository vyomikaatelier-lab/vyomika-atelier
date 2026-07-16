<?php

/**
 * Mirror Frames collection — landing grid + design PDPs with fixed-price checkout.
 */
return [
    'meta_title' => 'Mirror Frames Collection | Vyomika Atelier LLP',
    'meta_description' => 'PVD-framed wall mirrors, floor mirrors, backlit bathroom mirrors and bespoke vanity designs — fixed prices with Pan-India delivery from Vyomika Atelier LLP.',

    'hero' => [
        'label' => 'Collections',
        'title' => 'Mirror Frames',
        'subtitle' => 'Architectural mirror frames in champagne, rose gold and matte black PVD — from arched wall mirrors to LED dressing mirrors and retail display systems.',
        'highlights' => [
            'PVD stainless frames',
            'Fixed-price checkout',
            'Pan-India delivery',
        ],
        'cta_primary' => ['label' => 'Browse Designs', 'href' => '#mirror-designs'],
        'cta_secondary' => ['label' => 'Shop All Collections', 'href' => '/shop'],
        'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=1400&q=80',
    ],

    'intro' => [
        'title' => 'Reflective Metalwork, Engineered to Frame',
        'body' => 'Each mirror frame is fabricated in our Mumbai studio from grade 304/316 stainless with PVD coating — brushed or mirror finishes that resist tarnish in humid bathrooms and high-traffic retail environments.',
    ],

    'finishes' => [
        'title' => 'PVD Frame Finishes',
        'subtitle' => 'Select your frame finish at checkout. Black Mirror and Black Brush carry a +30% premium on custom sq ft orders — fixed-price designs include finish selection in the quoted price.',
        'items' => [
            ['name' => 'Champagne Mirror', 'image' => 'images/finishes/champagne-mirror.svg'],
            ['name' => 'Rose Gold Brush', 'image' => 'images/finishes/rose-gold-brush.svg'],
            ['name' => 'Matte Black', 'image' => 'images/finishes/black-mirror.svg'],
        ],
    ],

    'designs' => [
        [
            'slug' => 'arched-wall-mirror',
            'name' => 'Arched Wall Mirror',
            'description' => 'Soft-arch profile wall mirror with slim PVD frame — ideal for entryways, bedrooms and boutique hotel corridors.',
            'image' => 'https://images.unsplash.com/photo-1615874959473-d97dfea35062?w=800&q=80',
            'product_slug' => 'arched-wall-mirror',
            'highlights' => ['900 × 1200 mm standard', 'Toughened mirror glass', 'Concealed wall fixings'],
        ],
        [
            'slug' => 'full-length-floor-mirror',
            'name' => 'Full-Length Floor Mirror',
            'description' => 'Freestanding floor mirror with weighted PVD base and anti-tip bracket — dressing rooms, walk-in closets and retail fitting areas.',
            'image' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&q=80',
            'product_slug' => 'full-length-floor-mirror',
            'highlights' => ['1800 × 600 mm', 'Weighted base plate', 'Anti-tip safety bracket'],
            'badge' => 'Popular',
        ],
        [
            'slug' => 'backlit-bathroom-mirror',
            'name' => 'Backlit Bathroom Mirror',
            'description' => 'IP-rated LED backlit mirror with demister pad option — even illumination for vanity and wet-area installations.',
            'image' => 'https://images.unsplash.com/photo-1620626011761-996317b8d101?w=800&q=80',
            'product_slug' => 'backlit-bathroom-mirror',
            'highlights' => ['IP44 rated LED', 'Demister pad ready', 'Touch dimmer switch'],
        ],
        [
            'slug' => 'fluted-frame-mirror',
            'name' => 'Fluted Frame Mirror',
            'description' => 'Vertical fluted PVD frame wrapping toughened glass — a statement piece for living rooms and hospitality lobbies.',
            'image' => 'https://images.unsplash.com/photo-1600210492494-03fe69c9aeda?w=800&q=80',
            'product_slug' => 'fluted-frame-mirror',
            'highlights' => ['CNC fluted profile', 'Champagne or black PVD', 'Custom widths available'],
        ],
        [
            'slug' => 'round-vanity-mirror',
            'name' => 'Round Vanity Mirror',
            'description' => 'Circular vanity mirror with brushed PVD bezel — compact format for powder rooms and ensuite basins.',
            'image' => 'https://images.unsplash.com/photo-1507089947368-24c3dfe42ee6?w=800&q=80',
            'product_slug' => 'round-vanity-mirror',
            'highlights' => ['600 mm diameter', 'Bevelled edge glass', 'Wall-mount or pivot arm'],
        ],
        [
            'slug' => 'led-dressing-mirror',
            'name' => 'LED Dressing Mirror',
            'description' => 'Full-height dressing mirror with perimeter LED and CRI 90+ lighting — boutique retail and master wardrobe suites.',
            'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=800&q=80',
            'product_slug' => 'led-dressing-mirror',
            'highlights' => ['Perimeter LED strip', 'CRI 90+ warm white', 'Dimmer & memory function'],
            'badge' => 'NEW',
        ],
        [
            'slug' => 'retail-display-mirror',
            'name' => 'Retail Display Mirror',
            'description' => 'Large-format display mirror with reinforced PVD frame for showrooms, salons and fashion retail environments.',
            'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=800&q=80',
            'product_slug' => 'retail-display-mirror',
            'highlights' => ['2000 × 900 mm', 'Reinforced corner joints', 'Crated Pan-India shipping'],
        ],
        [
            'slug' => 'custom-profile-mirror',
            'name' => 'Custom Profile Mirror',
            'description' => 'Bespoke mirror frame profiles — ogee, bullnose, shadow-gap and architect-specified sections with shop drawings.',
            'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=800&q=80',
            'product_slug' => 'custom-profile-mirror',
            'highlights' => ['Custom profile CNC', 'Shop drawings included', 'Architect coordination'],
            'badge' => 'Bespoke',
        ],
    ],
];
