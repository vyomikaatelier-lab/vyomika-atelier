<?php

/**
 * Curated PVD partition catalog seeds — used for classification and first-time sync only.
 * The studio gallery lists admin-managed products in the partitions category;
 * do not auto-generate filler rows here (they polluted production after catalog:sync).
 *
 * @return list<array{name: string, slug: string, category: string, price: int, compare_price: int|null, sku: string, featured: bool, image: string, desc: string}>
 */
return (function () {
    $images = [
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
        'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    ];

    return [
        ['name' => 'Champagne Wave Partition', 'slug' => 'champagne-wave-partition', 'category' => 'partitions', 'price' => 28999, 'compare_price' => 38999, 'sku' => 'SSM-WAVE-001', 'featured' => true, 'image' => $images[0], 'desc' => 'Precision stainless wave partition with champagne PVD finish.'],
        ['name' => 'Veil Fluted Panel', 'slug' => 'veil-fluted-panel', 'category' => 'fluted-panels', 'price' => 24999, 'compare_price' => null, 'sku' => 'SSM-FLUTED-002', 'featured' => true, 'image' => $images[1], 'desc' => 'Vertical fluted PVD panel with soft light diffusion.'],
        ['name' => 'Rose Gold Room Divider', 'slug' => 'rose-gold-room-divider', 'category' => 'room-dividers', 'price' => 32999, 'compare_price' => 42999, 'sku' => 'SSM-RG-003', 'featured' => true, 'image' => $images[1], 'desc' => 'Statement rose gold PVD room divider for retail and residences.'],
        ['name' => 'Laser-Cut Partition', 'slug' => 'laser-cut-partition', 'category' => 'partitions', 'price' => 31999, 'compare_price' => 41999, 'sku' => 'SSM-LASER-005', 'featured' => true, 'image' => $images[0], 'desc' => 'Custom laser-cut stainless partition patterns.'],
    ];
})();
