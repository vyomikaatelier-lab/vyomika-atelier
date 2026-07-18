<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceDesign;
use App\Support\CatalogData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CatalogSyncSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncCategories();
        $this->syncProducts();
        $this->syncServices();
    }

    private function syncCategories(): void
    {
        $categories = [
            ['name' => 'PVD Partitions', 'slug' => 'partitions'],
            ['name' => 'Fluted Panels', 'slug' => 'fluted-panels'],
            ['name' => 'Room Dividers', 'slug' => 'room-dividers'],
            ['name' => 'Coffee Tables', 'slug' => 'coffee-tables'],
            ['name' => 'Corner Tables', 'slug' => 'corner-tables'],
            ['name' => 'Glass Tables', 'slug' => 'glass-tables'],
            ['name' => 'Door Handles', 'slug' => 'door-handles'],
            ['name' => 'Mirror Frames', 'slug' => 'mirror-frames'],
            ['name' => 'Metal Furniture', 'slug' => 'metal-furniture'],
            ['name' => 'Bespoke Metal Furniture', 'slug' => 'bespoke-metal-furniture'],
        ];

        foreach ($categories as $index => $cat) {
            Category::query()->firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function syncProducts(): void
    {
        $cat = fn (string $slug) => Category::query()->where('slug', $slug)->first();

        $partitionGallery = require database_path('data/partition-gallery-products.php');
        $serviceCatalog = CatalogData::serviceGallery();
        $mirrorFramesCatalog = require database_path('data/mirror-frames-catalog.php');

        $productsBySlug = [];
        foreach ($partitionGallery as $item) {
            $productsBySlug[$item['slug']] = $item;
        }
        foreach ($serviceCatalog as $items) {
            foreach ($items as $item) {
                $productsBySlug[$item['slug']] = $item;
            }
        }
        foreach ($mirrorFramesCatalog as $item) {
            $productsBySlug[$item['slug']] = $item;
        }

        foreach (array_values($productsBySlug) as $item) {
            Product::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $cat($item['category'])?->id,
                    'name' => $item['name'],
                    'description' => $item['desc'],
                    'price' => $item['price'],
                    'compare_price' => $item['compare_price'],
                    'sku' => $item['sku'],
                    'stock' => 25,
                    'image' => $item['image'],
                    'gallery' => $item['gallery'] ?? null,
                    'is_featured' => $item['featured'] ?? false,
                    'is_active' => true,
                ]
            );
        }
    }

    private function syncServices(): void
    {
        $services = [
            [
                'name' => 'PVD Partitions',
                'slug' => 'partitions',
                'summary' => 'Custom wave, fluted, and laser-cut PVD partition systems with online sq ft calculator.',
                'content' => '<p>Engineered stainless partitions in champagne gold, rose gold, matte black, and bespoke finishes. Each system is fabricated to your dimensions with Pan-India delivery and installation support.</p>',
                'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Wave Partition', 'slug' => 'wave-partition', 'product_slug' => 'champagne-wave-partition', 'description' => 'Sculptural wave profile with champagne or rose gold PVD finish.', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg'],
                    ['name' => 'Fluted Panel', 'slug' => 'fluted-panel', 'product_slug' => 'veil-fluted-panel', 'description' => 'Vertical fluting for light diffusion and acoustic softening.', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg'],
                    ['name' => 'Laser-Cut Screen', 'slug' => 'laser-cut-screen', 'product_slug' => 'laser-cut-partition', 'description' => 'Custom patterns cut in stainless with precision CNC finishing.', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg'],
                    ['name' => 'Frameless Glass + Metal', 'slug' => 'frameless-glass-metal', 'product_slug' => 'rose-gold-room-divider', 'description' => 'Hybrid partition combining PVD metal frames with glass infill.', 'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg'],
                ],
            ],
            [
                'name' => 'Corten steel',
                'slug' => 'corten-steel-facade',
                'summary' => 'Weathering steel facades, screens, planters and custom metalwork with a natural rust finish.',
                'content' => '<p>Corten cladding, screens, and entrance features that develop a protective patina. Ideal for commercial entrances, landscape walls, and architectural statements.</p>',
                'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200&q=80',
                'has_calculator' => false,
                'has_designs' => false,
                'lead_form' => 'inline',
                'meta_title' => 'Corten Steel Facades, Screens & Custom Fabrication — Vyomika Atelier LLP',
                'meta_description' => 'Explore custom Corten steel facades, decorative screens, planters, gates and architectural metalwork designed and fabricated by Vyomika Atelier LLP.',
            ],
            [
                'name' => 'Slim Profile Door System',
                'slug' => 'slim-profile-door-system',
                'summary' => 'Ultra-slim PVD door frames with premium glass and concealed hardware.',
                'content' => '<p>Pivot, sliding, and hinged door systems with minimal sightlines. PVD-coated frames in brass, black, and rose gold tones.</p>',
                'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Pivot Entrance', 'slug' => 'pivot-entrance', 'description' => 'Statement pivot doors with concealed hardware.'],
                    ['name' => 'Sliding Patio Door', 'slug' => 'sliding-patio', 'description' => 'Slim-track sliding systems for indoor-outdoor flow.'],
                    ['name' => 'Hinged Suite Door', 'slug' => 'hinged-suite', 'description' => 'Premium hinged doors for hotel suites and residences.'],
                ],
            ],
            [
                'name' => 'Bespoke Metal Furniture',
                'slug' => 'bespoke-metal-furniture',
                'summary' => 'Custom coffee tables, consoles, and display furniture in PVD finishes.',
                'content' => '<p>From coffee tables to consoles and shelving — fabricated to your exact dimensions with welding, powder coating, and PVD finishing.</p>',
                'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=1200&q=80',
                'has_calculator' => false,
                'has_designs' => false,
                'lead_form' => 'inline',
                'is_active' => false,
            ],
            [
                'name' => 'Main Entrance PVD Doors',
                'slug' => 'main-entrance-pvd-doors',
                'summary' => 'Grand entrance doors with scratch-resistant PVD metal finishes.',
                'content' => '<p>Custom main entrance doors in brass, matte black, bronze, and rose gold PVD. Engineered for security, weather sealing, and lasting lustre.</p>',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => false,
                'lead_form' => 'popup',
            ],
            [
                'name' => 'Rack Systems, Metal PVD',
                'slug' => 'rack-systems-metal-pvd',
                'summary' => 'Display and storage rack systems in premium PVD metal finishes.',
                'content' => '<p>Wall-mounted and freestanding rack systems for retail, wine storage, and residential display. Modular configurations available.</p>',
                'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Wall Display Rack', 'slug' => 'wall-display', 'description' => 'Floating wall racks for art, wine, or retail display.'],
                    ['name' => 'Freestanding Shelf', 'slug' => 'freestanding-shelf', 'description' => 'Modular freestanding shelving in PVD metal.'],
                    ['name' => 'Wine Storage Rack', 'slug' => 'wine-rack', 'description' => 'Horizontal bottle storage with custom capacity.'],
                ],
            ],
        ];

        foreach ($services as $data) {
            $designs = $data['designs'] ?? [];
            $isActive = $data['is_active'] ?? true;
            unset($data['designs'], $data['is_active']);

            $service = Service::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'is_active' => $isActive,
                    'rate_per_sqft' => 1800,
                ]
            );

            foreach ($designs as $design) {
                if (! Schema::hasColumn('service_designs', 'product_slug')) {
                    unset($design['product_slug']);
                }

                ServiceDesign::query()->updateOrCreate(
                    [
                        'service_id' => $service->id,
                        'slug' => $design['slug'],
                    ],
                    [
                        ...$design,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
