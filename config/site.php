<?php

/**
 * Vyomika Atelier storefront content — Amerce eCommerce structure.
 * Used as fallback when database is empty (local preview / first deploy).
 */
return [
    'brand' => [
        'name' => 'Vyomika Atelier',
        'suffix' => '',
        'tagline' => 'PVD Partitions & Metal Furniture',
        'email' => 'namaste@vyomikaatelier.com',
        'phone' => '+91 9205850254',
        'since' => '2009',
        'address_shop' => 'Pan-India fabrication & delivery',
        'address_office' => '467-483/1, Burgees Park, Dilshad Garden Ind. Area, G.T. Road, Shahdra, Delhi',
    ],

    'announcement' => [
        'text' => 'Festive Offer: 15% Off PVD Partitions — Auto Applied at Checkout',
        'link_label' => 'Shop Now',
        'link_href' => '/shop',
    ],

    'nav' => [
        ['label' => 'Home', 'route' => 'home'],
        [
            'label' => 'Shop',
            'children' => [
                ['label' => 'Mirror Frames', 'route' => 'shop.mirror-frames.index'],
                ['label' => 'Corner Tables', 'route' => 'shop.show', 'params' => ['slug' => 'corner-tables']],
                ['label' => 'Coffee Tables', 'route' => 'shop.show', 'params' => ['slug' => 'coffee-tables']],
                ['label' => 'Glass Tables', 'route' => 'shop.show', 'params' => ['slug' => 'glass-tables']],
                ['label' => 'Door Handles', 'route' => 'shop.show', 'params' => ['slug' => 'door-handles']],
                ['label' => 'Bespoke Metal Furniture', 'route' => 'shop.show', 'params' => ['slug' => 'bespoke-metal-furniture']],
            ],
        ],
        [
            'label' => 'Studio',
            'children' => [
                ['label' => 'PVD Partitions', 'route' => 'studio.show', 'params' => ['slug' => 'pvd-partitions']],
                ['label' => 'Slim Profile Door Systems', 'route' => 'studio.show', 'params' => ['slug' => 'slim-profile-door-systems']],
                ['label' => 'Main Entrance PVD Doors', 'route' => 'studio.show', 'params' => ['slug' => 'main-entrance-pvd-doors']],
                ['label' => 'Metal PVD Rack Systems', 'route' => 'studio.show', 'params' => ['slug' => 'metal-pvd-rack-systems']],
            ],
        ],
        ['label' => 'Railings', 'route' => 'railings.index'],
        ['label' => 'Corten steel', 'route' => 'services.show', 'params' => ['slug' => 'corten-steel-facade']],
        ['label' => 'Projects', 'route' => 'projects.index'],
        ['label' => 'About', 'route' => 'about'],
        ['label' => 'Blog', 'route' => 'blog.index'],
        ['label' => 'Professionals', 'route' => 'professionals.index'],
    ],

    'hero' => [
        'slides' => [
            [
                'kicker' => 'LIMITED TIME OFFER',
                'title' => 'Define Spaces With PVD Partitions',
                'description' => 'Champagne gold, rose gold, and matte black finishes — precision stainless partitions crafted for modern Indian interiors.',
                'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
                'cta_label' => 'View All Products',
                'cta_href' => '/shop',
            ],
            [
                'kicker' => 'BESPOKE FABRICATION',
                'title' => 'Wave & Fluted Metal Dividers',
                'description' => 'Statement room dividers in wave, fluted, and laser-cut patterns — engineered for offices, showrooms, and luxury homes.',
                'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
                'cta_label' => 'Explore Collection',
                'cta_href' => '/studio/pvd-partitions',
            ],
            [
                'kicker' => 'METAL FURNITURE',
                'title' => 'Bespoke Tables & Rack Systems',
                'description' => 'Coffee tables, console tables, and PVD rack systems — custom sizes with Pan-India delivery from our Delhi studio.',
                'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
                'cta_label' => 'Shop Furniture',
                'cta_href' => '/shop/coffee-tables',
            ],
        ],
    ],

    'best_sellers' => [
        'title' => 'Best-Selling Products',
        'subtitle' => 'Our most-loved shop pieces — mirrors, tables, handles, and bespoke metal furniture.',
        'cta_label' => 'View All Products',
        'banner' => [
            'title' => 'Discover Your Signature Finish',
            'subtitle' => 'Handpicked PVD metal furniture for modern interiors',
            'cta' => 'Shop now',
            'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=1400&q=80',
            'href' => '/shop',
        ],
        'products' => [
            [
                'name' => 'Brushed Brass Coffee Table',
                'category' => 'Coffee Tables',
                'price' => 18900,
                'compare_price' => null,
                'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80',
                'slug' => 'brushed-brass-coffee-table',
            ],
            [
                'name' => 'Marble Top Corner Table',
                'category' => 'Corner Tables',
                'price' => 16500,
                'compare_price' => null,
                'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
                'slug' => 'marble-top-corner-table',
            ],
            [
                'name' => 'Rose Gold Glass Side Table',
                'category' => 'Glass Tables',
                'price' => 14200,
                'compare_price' => 18900,
                'image' => 'https://images.unsplash.com/photo-1617806118233-18e1de247200?w=800&q=80',
                'slug' => 'rose-gold-glass-side-table',
            ],
            [
                'name' => 'PVD Door Pull Handle',
                'category' => 'Door Handles',
                'price' => 2400,
                'compare_price' => 3200,
                'badge' => '-25%',
                'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
                'slug' => 'pvd-door-pull-handle',
            ],
            [
                'name' => 'Arched Wall Mirror',
                'category' => 'Mirror Frames',
                'price' => 18500,
                'compare_price' => null,
                'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=800&q=80',
                'slug' => 'arched-wall-mirror',
            ],
            [
                'name' => 'Gold Fluted Console',
                'category' => 'Bespoke Metal Furniture',
                'price' => 22400,
                'compare_price' => 29999,
                'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
                'slug' => 'gold-fluted-console',
            ],
        ],
    ],

    'category_banners' => [
        [
            'title' => 'PVD Partitions',
            'subtitle' => 'Wave, fluted & laser-cut dividers',
            'cta' => 'Shop Now',
            'href' => '/studio/pvd-partitions',
            'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
        ],
        [
            'title' => 'Fluted Panels',
            'subtitle' => 'Up to 20% off bestsellers',
            'cta' => 'Shop Now',
            'href' => '/studio/pvd-partitions',
            'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
        ],
        [
            'title' => 'Metal Furniture',
            'subtitle' => 'Tables, consoles & racks',
            'cta' => 'Shop Now',
            'href' => '/shop/coffee-tables',
            'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80',
        ],
        [
            'title' => 'Custom Fabrication',
            'subtitle' => 'Sq ft calculator & quotes',
            'cta' => 'Get Quote',
            'href' => '/custom-order',
            'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
        ],
    ],

    'trending' => [
        'title' => 'Trending Metal Finds',
        'subtitle' => 'Popular PVD finishes and furniture pieces for offices and homes.',
        'products' => [
            [
                'name' => 'Laser-Cut Partition',
                'price' => 31999,
                'compare_price' => 41999,
                'badge' => '-25%',
                'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
                'slug' => 'laser-cut-partition',
            ],
            [
                'name' => 'Gold Fluted Console',
                'price' => 22400,
                'compare_price' => 29999,
                'badge' => '-25%',
                'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
                'slug' => 'gold-fluted-console',
            ],
            [
                'name' => 'Wall Rack System',
                'price' => 12500,
                'compare_price' => 16500,
                'badge' => '-25%',
                'image' => 'https://images.unsplash.com/photo-1615529182904-896166571fac?w=800&q=80',
                'slug' => 'wall-rack-system',
            ],
            [
                'name' => 'Slim Profile Door',
                'price' => 45999,
                'compare_price' => 59999,
                'badge' => '-25%',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
                'slug' => 'slim-profile-door',
            ],
        ],
    ],

    'spotlights' => [
        'title' => 'PVD Craft, Elevated for Interiors',
        'subtitle' => 'Thoughtfully engineered metalwork that transforms commercial and residential spaces.',
        'items' => [
            [
                'title' => 'Custom Sq Ft Partition Calculator',
                'description' => 'Calculate PVD partition costs by area, finish, and pattern. Get an instant estimate for your project dimensions.',
                'price' => 1800,
                'price_unit' => 'per sq ft',
                'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
                'cta' => 'Calculate Now',
                'href' => '/services',
            ],
            [
                'title' => 'Bespoke Metal Furniture',
                'description' => 'Coffee tables, console tables, and display racks — fabricated to your exact size, finish, and installation requirements.',
                'price' => 18900,
                'price_unit' => 'from',
                'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80',
                'cta' => 'Request Quote',
                'href' => '/custom-order',
            ],
        ],
    ],

    'cta_band' => [
        'title' => 'Shape A Space Filled With Precision Metal Beauty',
        'description' => 'Experience thoughtfully crafted PVD partitions and furniture that elevate your interiors across India.',
        'cta_label' => 'View All Products',
        'cta_href' => '/shop',
    ],

    'testimonials' => [
        [
            'quote' => 'The champagne PVD partition arrived perfectly finished — even more stunning than the renders. Our Mumbai office lobby looks world-class.',
            'client' => 'Rahul Mehta',
            'role' => 'Verified Buyer — Architect',
        ],
        [
            'quote' => 'Vyomika Atelier understood our showroom brief immediately. Packaging was secure, installation was smooth, and the wave divider is a showstopper.',
            'client' => 'Priya Nair',
            'role' => 'Verified Buyer — Interior Designer',
        ],
        [
            'quote' => 'We ordered a custom coffee table and PVD handles for our Pune residence. Quality is exceptional — already planning the next partition.',
            'client' => 'Vikram Singh',
            'role' => 'Verified Buyer — Homeowner',
        ],
    ],

    'featured_product' => [
        'category' => 'PVD Partitions',
        'name' => 'Champagne Wave Room Divider',
        'reviews' => 87,
        'sold_recent' => '24 sold in last 48 hours',
        'sku' => 'SSM-WAVE-001',
        'price' => 28999,
        'compare_price' => 38999,
        'badge' => '-25%',
        'description' => 'Precision stainless wave partition with champagne PVD finish. Ideal for offices, retail showrooms, and luxury residential spaces. Low maintenance with lasting lustre.',
        'viewers' => 32,
        'sizes' => ['Standard (6×8 ft)', 'Large (8×10 ft)', 'Custom Size'],
        'default_size' => 'Standard (6×8 ft)',
        'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
        'slug' => 'champagne-wave-partition',
        'rate_per_sqft' => 1800,
    ],

    'blog' => [
        'title' => 'Ideas, Materials & Projects',
        'subtitle' => 'PVD finishes, partition design, Corten steel, and fabrication insights from our Delhi studio.',
        'posts' => [
            [
                'category' => 'Partitions',
                'date' => '28 June 2026',
                'title' => 'PVD Partition Design Ideas for Modern Offices and Showrooms',
                'excerpt' => 'Wave, fluted, and laser-cut PVD partitions that define zones without blocking light.',
                'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
                'slug' => 'pvd-partition-design-ideas',
            ],
            [
                'category' => 'Doors',
                'date' => '20 June 2026',
                'title' => 'Luxury Stainless-Steel Entrance Doors: Slim Profiles and PVD Finishes',
                'excerpt' => 'How architects specify slim-profile stainless entrance doors with PVD coating.',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
                'slug' => 'luxury-stainless-steel-entrance-doors',
            ],
            [
                'category' => 'Corten Steel',
                'date' => '18 May 2026',
                'title' => 'Corten Steel Façades: Patina, Performance and Project Lessons',
                'excerpt' => 'Weathering steel for entrance screens, cladding, and landscape walls.',
                'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200&q=80',
                'slug' => 'corten-steel-facades-guide',
            ],
        ],
    ],

    'trust_badges' => [
        ['icon' => 'shipping', 'title' => 'Pan-India Shipping', 'text' => 'Fabrication & delivery across major cities.'],
        ['icon' => 'delivery', 'title' => '3–4 Week Delivery', 'text' => 'Made-to-order fabrication from Delhi studio.'],
        ['icon' => 'support', 'title' => 'Expert Support', 'text' => 'Delhi studio team — Mon–Sat, 10am–7pm IST.'],
        ['icon' => 'discount', 'title' => 'Trade Discounts', 'text' => 'Special pricing for architects & designers.'],
    ],

    'footer' => [
        'newsletter' => 'Get 10% off your first order and exclusive trade offers.',
        'shop_links' => [
            ['label' => 'All Products', 'route' => 'shop.index'],
            ['label' => 'Mirror Frames', 'route' => 'shop.mirror-frames.index'],
            ['label' => 'Corner Tables', 'route' => 'shop.show', 'params' => ['slug' => 'corner-tables']],
            ['label' => 'Coffee Tables', 'route' => 'shop.show', 'params' => ['slug' => 'coffee-tables']],
            ['label' => 'Glass Tables', 'route' => 'shop.show', 'params' => ['slug' => 'glass-tables']],
            ['label' => 'Door Handles', 'route' => 'shop.show', 'params' => ['slug' => 'door-handles']],
            ['label' => 'Bespoke Metal Furniture', 'route' => 'shop.show', 'params' => ['slug' => 'bespoke-metal-furniture']],
        ],
        'info_links' => [
            ['label' => 'About', 'route' => 'about'],
            ['label' => 'Team', 'route' => 'team'],
            ['label' => 'Railings', 'route' => 'railings.index'],
            ['label' => 'Blog', 'route' => 'blog.index'],
            ['label' => 'Contact', 'route' => 'contact.index'],
        ],
        'service_links' => [
            ['label' => 'PVD Partitions', 'route' => 'studio.show', 'params' => ['slug' => 'pvd-partitions']],
            ['label' => 'Slim Profile Door Systems', 'route' => 'studio.show', 'params' => ['slug' => 'slim-profile-door-systems']],
            ['label' => 'Main Entrance PVD Doors', 'route' => 'studio.show', 'params' => ['slug' => 'main-entrance-pvd-doors']],
            ['label' => 'Metal PVD Rack Systems', 'route' => 'studio.show', 'params' => ['slug' => 'metal-pvd-rack-systems']],
        ],
    ],

    // Legacy keys kept for inner pages / fallbacks
    'shop' => [
        ['name' => 'Champagne Wave Partition', 'category' => 'PVD Partitions', 'price' => 28999, 'compare_price' => 38999, 'badge' => '-25%', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg', 'slug' => 'champagne-wave-partition'],
        ['name' => 'Veil Fluted Panel', 'category' => 'Fluted Panels', 'price' => 24999, 'compare_price' => null, 'badge' => 'NEW', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg', 'slug' => 'veil-fluted-panel'],
        ['name' => 'Rose Gold Room Divider', 'category' => 'Room Dividers', 'price' => 32999, 'compare_price' => 42999, 'badge' => '-25%', 'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg', 'slug' => 'rose-gold-room-divider'],
        ['name' => 'Matte Black PVD Partition', 'category' => 'PVD Partitions', 'price' => 26999, 'compare_price' => 35999, 'badge' => '-25%', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80', 'slug' => 'matte-black-pvd-partition'],
        ['name' => 'Brushed Brass Coffee Table', 'category' => 'Coffee Tables', 'price' => 18900, 'compare_price' => null, 'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80', 'slug' => 'brushed-brass-coffee-table'],
        ['name' => 'PVD Door Pull Handle', 'category' => 'Door Handles', 'price' => 2400, 'compare_price' => 3200, 'badge' => '-25%', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80', 'slug' => 'pvd-door-pull-handle'],
    ],
    'services' => [
        ['name' => 'PVD Partitions', 'summary' => 'Custom wave, fluted, and laser-cut partition systems with online sq ft calculator.'],
        ['name' => 'Metal Furniture', 'summary' => 'Bespoke coffee tables, console tables, and rack systems in PVD finishes.'],
        ['name' => 'Door Systems', 'summary' => 'Slim profile doors and PVD entrance systems in brass, black, and rose gold.'],
        ['name' => 'Custom Fabrication', 'summary' => 'End-to-end metal fabrication for architects, designers, and developers.'],
    ],
    'portfolio' => (static function () {
        $catalog = require database_path('data/projects-catalog.php');
        $years = [2024, 2025, 2026, 2023, 2025, 2024, 2026, 2025];
        foreach ($catalog as $i => &$p) {
            $p['year'] = (string) ($years[$i] ?? 2025);
        }
        unset($p);

        return $catalog;
    })(),
    'team' => [
        ['name' => 'Arjun Mehta', 'role' => 'Studio Director', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&q=80'],
        ['name' => 'Priya Nair', 'role' => 'Lead Designer', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&q=80'],
        ['name' => 'Vikram Singh', 'role' => 'Fabrication Head', 'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&q=80'],
    ],
];
