<?php

/**
 * @param list<array{name: string, slug: string, category: string, price: int, compare_price: int|null, sku: string, image: string, desc: string}> $featured
 * @param list<string> $patterns
 * @param list<string> $finishes
 * @param list<string> $types
 * @param list<string> $categorySlugs
 * @param list<string> $images
 *
 * @return list<array{name: string, slug: string, category: string, price: int, compare_price: int|null, sku: string, featured: bool, image: string, desc: string}>
 */
function generate_service_gallery_products(
    array $featured,
    array $patterns,
    array $finishes,
    array $types,
    array $categorySlugs,
    array $images,
    string $skuPrefix,
    int $target = 40,
): array {
    $items = [];
    foreach ($featured as $i => $row) {
        $items[] = [...$row, 'featured' => true];
    }

    $index = count($items);
    foreach ($patterns as $pattern) {
        foreach ($finishes as $finish) {
            if (count($items) >= $target) {
                break 2;
            }

            $type = $types[$index % count($types)];
            $name = $finish.' '.$pattern.' '.$type;
            $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            $slug = $baseSlug.'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            $items[] = [
                'name' => $name,
                'slug' => $slug,
                'category' => $categorySlugs[$index % count($categorySlugs)],
                'price' => 18000 + ($index % 14) * 2000,
                'compare_price' => $index % 3 === 0 ? 28000 + $index * 250 : null,
                'sku' => $skuPrefix.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'featured' => false,
                'image' => $images[$index % count($images)],
                'desc' => 'Custom '.strtolower($pattern).' '.strtolower($type).' in '.strtolower($finish).' PVD finish.',
            ];
            $index++;
        }
    }

    return $items;
}

$doorImages = [
    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
    'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=800&q=80',
    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
];

$rackImages = [
    'https://images.unsplash.com/photo-1615529182904-896166571fac?w=800&q=80',
    'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=800&q=80',
    'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
];

$furnitureImages = [
    'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80',
    'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
    'https://images.unsplash.com/photo-1617806118233-18e1de247200?w=800&q=80',
];

return [
    'slim-profile-door-system' => generate_service_gallery_products(
        [
            ['name' => 'Slim Profile Pivot Door', 'slug' => 'slim-profile-pivot-door', 'category' => 'metal-furniture', 'price' => 45999, 'compare_price' => 59999, 'sku' => 'SSM-SPD-001', 'image' => $doorImages[0], 'desc' => 'Ultra-slim pivot entrance with concealed PVD frame.'],
            ['name' => 'Slim Sliding Patio Door', 'slug' => 'slim-sliding-patio-door', 'category' => 'metal-furniture', 'price' => 52999, 'compare_price' => 64999, 'sku' => 'SSM-SPD-002', 'image' => $doorImages[1], 'desc' => 'Minimal-track sliding door for indoor-outdoor flow.'],
            ['name' => 'Slim Hinged Suite Door', 'slug' => 'slim-hinged-suite-door', 'category' => 'metal-furniture', 'price' => 48999, 'compare_price' => null, 'sku' => 'SSM-SPD-003', 'image' => $doorImages[2], 'desc' => 'Premium hinged suite door with PVD frame.'],
        ],
        ['Pivot', 'Sliding', 'Hinged', 'Folding', 'Stacking', 'Frameless', 'Glass', 'Double', 'Single', 'Arched'],
        ['Champagne', 'Rose Gold', 'Matte Black', 'Gold Mirror', 'Brushed Brass'],
        ['Door', 'Entrance', 'Portal', 'System', 'Suite'],
        ['metal-furniture'],
        $doorImages,
        'SSM-SD',
    ),
    'main-entrance-pvd-doors' => generate_service_gallery_products(
        [
            ['name' => 'Slim Profile Door', 'slug' => 'slim-profile-door', 'category' => 'metal-furniture', 'price' => 45999, 'compare_price' => 59999, 'sku' => 'SSM-DOR-001', 'image' => $doorImages[0], 'desc' => 'Grand main entrance PVD door system.'],
            ['name' => 'PVD Door Pull Handle', 'slug' => 'pvd-door-pull-handle', 'category' => 'door-handles', 'price' => 2400, 'compare_price' => 3200, 'sku' => 'SSM-DOR-002', 'image' => $doorImages[2], 'desc' => 'Slim profile pull handle in PVD finishes.'],
            ['name' => 'Brass Entrance Pull', 'slug' => 'brass-entrance-pull', 'category' => 'door-handles', 'price' => 3200, 'compare_price' => null, 'sku' => 'SSM-DOR-003', 'image' => $doorImages[3], 'desc' => 'Statement entrance pull in brushed brass PVD.'],
        ],
        ['Entrance', 'Main', 'Grand', 'Pivot', 'Double', 'Single', 'Security', 'Glass', 'Panel', 'Monumental'],
        ['Champagne', 'Rose Gold', 'Matte Black', 'Bronze', 'Gold Mirror'],
        ['Door', 'Entrance', 'Portal', 'Gate', 'System'],
        ['metal-furniture', 'door-handles'],
        $doorImages,
        'SSM-ED',
    ),
    'rack-systems-metal-pvd' => generate_service_gallery_products(
        [
            ['name' => 'Wall Rack System', 'slug' => 'wall-rack-system', 'category' => 'metal-furniture', 'price' => 12500, 'compare_price' => 16500, 'sku' => 'SSM-RCK-001', 'image' => $rackImages[0], 'desc' => 'Modular wall-mounted PVD display rack.'],
            ['name' => 'Freestanding Wine Rack', 'slug' => 'freestanding-wine-rack', 'category' => 'metal-furniture', 'price' => 18900, 'compare_price' => null, 'sku' => 'SSM-RCK-002', 'image' => $rackImages[1], 'desc' => 'Freestanding wine storage in PVD metal.'],
            ['name' => 'Retail Display Rack', 'slug' => 'retail-display-rack', 'category' => 'metal-furniture', 'price' => 22400, 'compare_price' => 28900, 'sku' => 'SSM-RCK-003', 'image' => $rackImages[2], 'desc' => 'Retail shelving rack with champagne PVD finish.'],
        ],
        ['Wall', 'Floating', 'Modular', 'Wine', 'Display', 'Shelf', 'Ladder', 'Grid', 'Boutique', 'Gallery'],
        ['Champagne', 'Rose Gold', 'Matte Black', 'Gold Mirror', 'Brushed Brass'],
        ['Rack', 'Shelf', 'System', 'Unit', 'Storage'],
        ['metal-furniture'],
        $rackImages,
        'SSM-RK',
    ),
    'bespoke-metal-furniture' => generate_service_gallery_products(
        [
            ['name' => 'Brushed Brass Coffee Table', 'slug' => 'brushed-brass-coffee-table', 'category' => 'coffee-tables', 'price' => 18900, 'compare_price' => null, 'sku' => 'SSM-FUR-001', 'image' => $furnitureImages[0], 'desc' => 'Bespoke brass PVD coffee table.'],
            ['name' => 'Marble Top Corner Table', 'slug' => 'marble-top-corner-table', 'category' => 'corner-tables', 'price' => 16500, 'compare_price' => null, 'sku' => 'SSM-FUR-002', 'image' => $furnitureImages[1], 'desc' => 'Corner table with marble top and PVD frame.'],
            ['name' => 'Rose Gold Glass Side Table', 'slug' => 'rose-gold-glass-side-table', 'category' => 'glass-tables', 'price' => 14200, 'compare_price' => 18900, 'sku' => 'SSM-FUR-003', 'image' => $furnitureImages[2], 'desc' => 'Glass side table with rose gold PVD frame.'],
            ['name' => 'Gold Fluted Console', 'slug' => 'gold-fluted-console', 'category' => 'metal-furniture', 'price' => 22400, 'compare_price' => 29999, 'sku' => 'SSM-FUR-004', 'image' => $furnitureImages[1], 'desc' => 'Fluted console table for entryways.'],
        ],
        ['Coffee', 'Console', 'Side', 'Corner', 'Glass', 'Nested', 'Accent', 'Entry', 'Lounge', 'Statement'],
        ['Champagne', 'Rose Gold', 'Matte Black', 'Brass', 'Bronze'],
        ['Table', 'Console', 'Desk', 'Stand', 'Piece'],
        ['coffee-tables', 'corner-tables', 'glass-tables', 'metal-furniture'],
        $furnitureImages,
        'SSM-BF',
    ),
];
