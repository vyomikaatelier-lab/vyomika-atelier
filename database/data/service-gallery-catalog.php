<?php

/**
 * Curated studio service catalog seeds — classification and first-time sync only.
 * Do not auto-generate filler rows (generate_service_gallery_products polluted production).
 *
 * @return array<string, list<array{name: string, slug: string, category: string, price: int, compare_price: int|null, sku: string, featured: bool, image: string, desc: string, size_options?: list<array<string, mixed>>}>>
 */
return (function () {
    $doorImages = [
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    ];

    $rackImages = [
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    ];

    $furnitureImages = [
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    ];

    return [
        'slim-profile-door-system' => [
            ['name' => 'Slim Profile Pivot Door', 'slug' => 'slim-profile-pivot-door', 'category' => 'slim-profile-door-system', 'price' => 45999, 'compare_price' => 59999, 'sku' => 'SSM-SPD-001', 'featured' => true, 'image' => $doorImages[0], 'desc' => 'Ultra-slim pivot entrance with concealed PVD frame.'],
            ['name' => 'Slim Sliding Patio Door', 'slug' => 'slim-sliding-patio-door', 'category' => 'slim-profile-door-system', 'price' => 52999, 'compare_price' => 64999, 'sku' => 'SSM-SPD-002', 'featured' => true, 'image' => $doorImages[1], 'desc' => 'Minimal-track sliding door for indoor-outdoor flow.'],
            ['name' => 'Slim Hinged Suite Door', 'slug' => 'slim-hinged-suite-door', 'category' => 'slim-profile-door-system', 'price' => 48999, 'compare_price' => null, 'sku' => 'SSM-SPD-003', 'featured' => true, 'image' => $doorImages[0], 'desc' => 'Premium hinged suite door with PVD frame.'],
        ],
        'main-entrance-pvd-doors' => [
            ['name' => 'Slim Profile Door', 'slug' => 'slim-profile-door', 'category' => 'main-entrance-pvd-doors', 'price' => 45999, 'compare_price' => 59999, 'sku' => 'SSM-DOR-001', 'featured' => true, 'image' => $doorImages[0], 'desc' => 'Grand main entrance PVD door system.'],
            [
                'name' => 'PVD Door Pull Handle',
                'slug' => 'pvd-door-pull-handle',
                'category' => 'door-handles',
                'price' => 1800,
                'compare_price' => null,
                'sku' => 'SSM-DOR-002',
                'featured' => true,
                'image' => $doorImages[1],
                'desc' => 'Slim profile pull handle in PVD finishes.',
                'size_options' => [
                    ['label' => '8"', 'price' => 1800, 'compare_price' => 2400, 'size_inches' => 8, 'sku_suffix' => '8IN'],
                    ['label' => '12"', 'price' => 2400, 'compare_price' => 3200, 'size_inches' => 12, 'sku_suffix' => '12IN'],
                    ['label' => '18"', 'price' => 3600, 'compare_price' => 4500, 'size_inches' => 18, 'sku_suffix' => '18IN'],
                    ['label' => '24"', 'price' => 4800, 'compare_price' => 6000, 'size_inches' => 24, 'sku_suffix' => '24IN'],
                ],
            ],
            [
                'name' => 'Brass Entrance Pull',
                'slug' => 'brass-entrance-pull',
                'category' => 'door-handles',
                'price' => 2400,
                'compare_price' => null,
                'sku' => 'SSM-DOR-003',
                'featured' => true,
                'image' => $doorImages[0],
                'desc' => 'Statement entrance pull in brushed brass PVD.',
                'size_options' => [
                    ['label' => '12"', 'price' => 2400, 'compare_price' => 3000, 'size_inches' => 12, 'sku_suffix' => '12IN'],
                    ['label' => '18"', 'price' => 3200, 'compare_price' => 4000, 'size_inches' => 18, 'sku_suffix' => '18IN'],
                    ['label' => '24"', 'price' => 4200, 'compare_price' => 5200, 'size_inches' => 24, 'sku_suffix' => '24IN'],
                ],
            ],
        ],
        'rack-systems-metal-pvd' => [
            ['name' => 'Wall Rack System', 'slug' => 'wall-rack-system', 'category' => 'rack-systems-metal-pvd', 'price' => 12500, 'compare_price' => 16500, 'sku' => 'SSM-RCK-001', 'featured' => true, 'image' => $rackImages[0], 'desc' => 'Modular wall-mounted PVD display rack.'],
            ['name' => 'Freestanding Wine Rack', 'slug' => 'freestanding-wine-rack', 'category' => 'rack-systems-metal-pvd', 'price' => 18900, 'compare_price' => null, 'sku' => 'SSM-RCK-002', 'featured' => true, 'image' => $rackImages[1], 'desc' => 'Freestanding wine storage in PVD metal.'],
            ['name' => 'Retail Display Rack', 'slug' => 'retail-display-rack', 'category' => 'rack-systems-metal-pvd', 'price' => 22400, 'compare_price' => 28900, 'sku' => 'SSM-RCK-003', 'featured' => true, 'image' => $rackImages[0], 'desc' => 'Retail shelving rack with champagne PVD finish.'],
        ],
        'bespoke-metal-furniture' => [
            ['name' => 'Brushed Brass Coffee Table', 'slug' => 'brushed-brass-coffee-table', 'category' => 'coffee-tables', 'price' => 18900, 'compare_price' => null, 'sku' => 'SSM-FUR-001', 'featured' => true, 'image' => $furnitureImages[0], 'desc' => 'Bespoke brass PVD coffee table.'],
            ['name' => 'Marble Top Corner Table', 'slug' => 'marble-top-corner-table', 'category' => 'corner-tables', 'price' => 16500, 'compare_price' => null, 'sku' => 'SSM-FUR-002', 'featured' => true, 'image' => $furnitureImages[1], 'desc' => 'Corner table with marble top and PVD frame.'],
            ['name' => 'Rose Gold Glass Side Table', 'slug' => 'rose-gold-glass-side-table', 'category' => 'glass-tables', 'price' => 14200, 'compare_price' => 18900, 'sku' => 'SSM-FUR-003', 'featured' => true, 'image' => $furnitureImages[0], 'desc' => 'Glass side table with rose gold PVD frame.'],
            ['name' => 'Gold Fluted Console', 'slug' => 'gold-fluted-console', 'category' => 'metal-furniture', 'price' => 22400, 'compare_price' => 29999, 'sku' => 'SSM-FUR-004', 'featured' => true, 'image' => $furnitureImages[1], 'desc' => 'Fluted console table for entryways.'],
        ],
    ];
})();
