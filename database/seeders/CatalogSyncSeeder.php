<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceDesign;
use App\Support\CatalogData;
use App\Support\ProductCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CatalogSyncSeeder extends Seeder
{
    public static bool $dryRun = false;

    public function run(): void
    {
        $dryRun = static::$dryRun || (bool) ($this->command?->option('dry-run') ?? false);

        $this->syncCategories($dryRun);
        $this->syncProducts($dryRun);
        $this->syncServices($dryRun);
    }

    private function syncCategories(bool $dryRun): void
    {
        $result = ProductCatalog::syncCanonicalCategories($dryRun);
        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->command?->info("Categories: {$result['synced']} canonical, {$result['created']} would create, {$result['updated']} would update{$suffix}.");
    }

    private function syncProducts(bool $dryRun): void
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

        $created = 0;
        $backfilled = 0;
        $skipped = 0;

        foreach (array_values($productsBySlug) as $item) {
            $categorySlug = ProductCatalog::categorySlugForProduct($item['slug'])
                ?? (in_array($item['category'] ?? '', ProductCatalog::obsoleteCategorySlugs(), true)
                    ? (in_array($item['category'], ['fluted-panels', 'room-dividers'], true) ? 'partitions' : 'bespoke-metal-furniture')
                    : ($item['category'] ?? null));

            $existing = Product::query()->where('slug', $item['slug'])->first();

            if ($existing !== null) {
                if ($existing->category_id === null && $categorySlug && ($category = $cat($categorySlug))) {
                    if (! $dryRun) {
                        $existing->update(['category_id' => $category->id]);
                    }
                    $backfilled++;
                } else {
                    $skipped++;
                }

                continue;
            }

            $payload = [
                'category_id' => $categorySlug ? $cat($categorySlug)?->id : null,
                'name' => $item['name'],
                'description' => $item['desc'],
                'price' => $item['price'],
                'compare_price' => $item['compare_price'] ?? null,
                'sku' => $item['sku'],
                'stock' => 25,
                'image' => $item['image'],
                'gallery' => $item['gallery'] ?? null,
                'is_featured' => $item['featured'] ?? false,
                'is_active' => true,
                'dim_width_cm' => $item['dim_width_cm'] ?? null,
                'dim_height_cm' => $item['dim_height_cm'] ?? null,
            ];

            if (array_key_exists('size_options', $item)) {
                $sizeOptions = is_array($item['size_options']) && $item['size_options'] !== []
                    ? array_values($item['size_options'])
                    : null;
                $payload['size_options'] = $sizeOptions;
                if ($sizeOptions !== null) {
                    $payload['price'] = min(array_column($sizeOptions, 'price'));
                    $payload['compare_price'] = null;
                }
            }

            if (! $dryRun) {
                Product::query()->create(array_merge(['slug' => $item['slug']], $payload));
            }
            $created++;
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->command?->info("Products: {$created} would create, {$backfilled} category backfill, {$skipped} existing preserved{$suffix}.");
    }

    private function syncServices(bool $dryRun): void
    {
        if (! $dryRun) {
            Service::query()
                ->whereIn('slug', Service::adminHiddenSlugs())
                ->each(function (Service $service) {
                    $service->designs()->delete();
                    $service->delete();
                });
        }

        $services = [
            [
                'name' => 'PVD Partitions',
                'slug' => 'partitions',
                'summary' => 'Custom wave, fluted, and laser-cut PVD partition systems with online sq ft calculator.',
                'content' => '<p>Engineered stainless partitions in champagne gold, rose gold, matte black, and bespoke finishes. Each system is fabricated to your dimensions with Pan-India delivery and installation support.</p>',
                'image' => 'images/blog/heroes/glass-partitions-open-plan-hero-card.jpg',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Wave Partition', 'slug' => 'wave-partition', 'product_slug' => 'champagne-wave-partition', 'description' => 'Sculptural wave profile with champagne or rose gold PVD finish.', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg'],
                    ['name' => 'Fluted Panel', 'slug' => 'fluted-panel', 'product_slug' => 'veil-fluted-panel', 'description' => 'Vertical fluting for light diffusion and acoustic softening.', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg'],
                    ['name' => 'Laser-Cut Screen', 'slug' => 'laser-cut-screen', 'product_slug' => 'laser-cut-partition', 'description' => 'Custom patterns cut in stainless with precision CNC finishing.', 'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg'],
                    ['name' => 'Frameless Glass + Metal', 'slug' => 'frameless-glass-metal', 'product_slug' => 'rose-gold-room-divider', 'description' => 'Hybrid partition combining PVD metal frames with glass infill.', 'image' => 'images/blog/heroes/glass-partitions-open-plan-hero-card.jpg'],
                ],
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

        $created = 0;
        $skipped = 0;

        foreach ($services as $data) {
            $designs = $data['designs'] ?? [];
            $defaultActive = $data['is_active'] ?? true;
            unset($data['designs'], $data['is_active']);

            $existing = Service::query()->where('slug', $data['slug'])->first();

            if ($existing === null) {
                if (! $dryRun) {
                    $service = Service::query()->create([
                        ...$data,
                        'is_active' => $defaultActive,
                        'rate_per_sqft' => 1800,
                    ]);
                    $this->syncServiceDesigns($service, $designs, $dryRun);
                }
                $created++;
            } else {
                $skipped++;
                if (! $dryRun) {
                    $this->syncServiceDesigns($existing, $designs, $dryRun, createOnly: true);
                }
            }
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->command?->info("Services: {$created} would create, {$skipped} existing preserved{$suffix}.");
    }

    /** @param list<array<string, mixed>> $designs */
    private function syncServiceDesigns(Service $service, array $designs, bool $dryRun, bool $createOnly = false): void
    {
        foreach ($designs as $design) {
            if (! Schema::hasColumn('service_designs', 'product_slug')) {
                unset($design['product_slug']);
            }

            $existing = ServiceDesign::query()
                ->where('service_id', $service->id)
                ->where('slug', $design['slug'])
                ->first();

            if ($existing !== null && $createOnly) {
                continue;
            }

            if ($existing === null) {
                if (! $dryRun) {
                    ServiceDesign::query()->create([
                        'service_id' => $service->id,
                        ...$design,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
