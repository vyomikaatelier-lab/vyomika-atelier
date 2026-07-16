<?php

/**
 * 40 PVD partition gallery products — shared by seeder and static preview JSON sync.
 *
 * @return list<array{name: string, slug: string, category: string, price: int, compare_price: int|null, sku: string, featured: bool, image: string, desc: string}>
 */
return (function () {
    $images = [
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
        'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
        'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
    ];

    $featured = [
        ['name' => 'Champagne Wave Partition', 'slug' => 'champagne-wave-partition', 'category' => 'partitions', 'price' => 28999, 'compare_price' => 38999, 'sku' => 'SSM-WAVE-001', 'image' => $images[0], 'desc' => 'Precision stainless wave partition with champagne PVD finish.'],
        ['name' => 'Veil Fluted Panel', 'slug' => 'veil-fluted-panel', 'category' => 'fluted-panels', 'price' => 24999, 'compare_price' => null, 'sku' => 'SSM-FLUTED-002', 'image' => $images[1], 'desc' => 'Vertical fluted PVD panel with soft light diffusion.'],
        ['name' => 'Rose Gold Room Divider', 'slug' => 'rose-gold-room-divider', 'category' => 'room-dividers', 'price' => 32999, 'compare_price' => 42999, 'sku' => 'SSM-RG-003', 'image' => $images[2], 'desc' => 'Statement rose gold PVD room divider for retail and residences.'],
        ['name' => 'Matte Black PVD Partition', 'slug' => 'matte-black-pvd-partition', 'category' => 'partitions', 'price' => 26999, 'compare_price' => 35999, 'sku' => 'SSM-MB-004', 'image' => $images[3], 'desc' => 'Bold matte black partition with fingerprint-resistant PVD coating.'],
        ['name' => 'Laser-Cut Partition', 'slug' => 'laser-cut-partition', 'category' => 'partitions', 'price' => 31999, 'compare_price' => 41999, 'sku' => 'SSM-LASER-005', 'image' => $images[0], 'desc' => 'Custom laser-cut stainless partition patterns.'],
    ];

    $patterns = ['Wave', 'Fluted', 'Laser-Cut', 'Geometric', 'Herringbone', 'Ripple', 'Arc', 'Linear', 'Mesh', 'Perforated', 'Crescent', 'Zigzag', 'Lattice', 'Slat', 'Pleat'];
    $finishes = ['Champagne', 'Rose Gold', 'Matte Black', 'Gold Mirror', 'Champagne Brush'];
    $types = ['Partition', 'Panel', 'Screen', 'Divider'];
    $categorySlugs = ['partitions', 'fluted-panels', 'room-dividers'];

    $items = [];
    foreach ($featured as $i => $row) {
        $items[] = [...$row, 'featured' => true];
    }

    $i = count($items);
    foreach ($patterns as $pattern) {
        foreach ($finishes as $finish) {
            if (count($items) >= 40) {
                break 2;
            }

            $type = $types[$i % count($types)];
            $name = $finish.' '.$pattern.' '.$type;
            $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            $slug = $baseSlug.'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

            $items[] = [
                'name' => $name,
                'slug' => $slug,
                'category' => $categorySlugs[$i % count($categorySlugs)],
                'price' => 22000 + ($i % 12) * 1500,
                'compare_price' => $i % 3 === 0 ? 32000 + $i * 200 : null,
                'sku' => 'SSM-P'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'featured' => false,
                'image' => $images[$i % count($images)],
                'desc' => 'Custom '.strtolower($pattern).' PVD '.strtolower($type).' in '.strtolower($finish).' finish.',
            ];
            $i++;
        }
    }

    return $items;
})();
