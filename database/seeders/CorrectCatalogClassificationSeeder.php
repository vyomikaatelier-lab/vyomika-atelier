<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Idempotent catalog correction, driven entirely by ProductCatalog::slugMap()
 * (built from database/data/*.php). Safe to run repeatedly:
 *  - Never creates or deletes products (only updates existing rows by slug).
 *  - Exports a JSON backup of every product's classification before touching data.
 *  - Reports which products changed and which are unclassified.
 *
 * Mapping (per task spec):
 *   Shop:   mirror-frames, corner-tables, coffee-tables, glass-tables, door-handles, bespoke-metal-furniture
 *   Studio: pvd-partitions, slim-profile-door-systems, main-entrance-pvd-doors, metal-pvd-rack-systems
 *   Rule:   Slim Hinged Suite Door and all slim hinged/sliding/telescopic slim-profile
 *           designs -> Studio > Slim Profile Door Systems (already resolved by
 *           ProductCatalog::inferMetalFurnitureService()'s pivot/sliding/hinged/... regex).
 */
class CorrectCatalogClassificationSeeder extends Seeder
{
    /** @var array<string, string> section => purchase_mode */
    private const PURCHASE_MODE_BY_SECTION = [
        'shop' => Product::PURCHASE_MODE_CHECKOUT,
        'studio' => Product::PURCHASE_MODE_ENQUIRY,
        'railings' => Product::PURCHASE_MODE_QUOTE,
    ];

    /** @var array<string, string> section => pricing_type */
    private const PRICING_TYPE_BY_SECTION = [
        'shop' => Product::PRICING_FIXED,
        'studio' => Product::PRICING_SQUARE_FOOT,
        'railings' => Product::PRICING_QUOTATION_ONLY,
    ];

    public function run(): void
    {
        $this->exportBackup();

        $slugMap = ProductCatalog::slugMap();
        $updated = 0;
        $skipped = 0;
        $changedSlugs = [];

        foreach ($slugMap as $productSlug => $meta) {
            $category = Category::query()->where('slug', $meta['category'])->first();

            if (! $category) {
                $this->command?->warn("Category not found for {$productSlug}: {$meta['category']}");
                $skipped++;

                continue;
            }

            $product = Product::query()->where('slug', $productSlug)->first();

            if (! $product) {
                $this->command?->warn("Product not found: {$productSlug}");
                $skipped++;

                continue;
            }

            $section = $meta['section'];
            $desired = [
                'category_id' => $category->id,
                'section' => $section,
                'purchase_mode' => self::PURCHASE_MODE_BY_SECTION[$section] ?? Product::PURCHASE_MODE_ENQUIRY,
                'pricing_type' => self::PRICING_TYPE_BY_SECTION[$section] ?? Product::PRICING_FIXED,
            ];

            $changed = false;
            foreach ($desired as $field => $value) {
                if ((string) $product->getAttribute($field) !== (string) $value) {
                    $changed = true;
                    break;
                }
            }

            if (! $changed) {
                continue;
            }

            $product->update($desired);
            $updated++;
            $changedSlugs[] = $productSlug;
            $this->command?->line("Reassigned {$productSlug} → section={$desired['section']}, category={$meta['category']}, purchase_mode={$desired['purchase_mode']}");
        }

        $unclassified = Product::query()
            ->whereNotIn('slug', array_keys($slugMap))
            ->pluck('slug')
            ->values()
            ->all();

        $this->command?->info("Catalog classification complete: {$updated} updated, {$skipped} skipped.");

        if ($unclassified !== []) {
            $this->command?->warn('Unclassified products (not in ProductCatalog slug map, left untouched): '.implode(', ', $unclassified));
        }

        $this->writeReport($changedSlugs, $unclassified, $updated, $skipped);
    }

    private function exportBackup(): void
    {
        $dir = database_path('backups');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/catalog-classification-'.now()->format('Y-m-d-His').'.json';

        $records = Product::query()
            ->with('category:id,slug,name')
            ->orderBy('slug')
            ->get(['id', 'slug', 'name', 'sku', 'category_id', 'section', 'purchase_mode', 'pricing_type'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'sku' => $p->sku,
                'category_id' => $p->category_id,
                'category_slug' => $p->category?->slug,
                'section' => $p->section,
                'purchase_mode' => $p->purchase_mode,
                'pricing_type' => $p->pricing_type,
            ])
            ->values()
            ->all();

        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'product_count' => count($records),
            'products' => $records,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->command?->info("Backup written to {$path}");
    }

    /** @param list<string> $changedSlugs @param list<string> $unclassifiedSlugs */
    private function writeReport(array $changedSlugs, array $unclassifiedSlugs, int $updated, int $skipped): void
    {
        $dir = database_path('backups');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/catalog-classification-report-'.now()->format('Y-m-d-His').'.json';

        File::put($path, json_encode([
            'run_at' => now()->toIso8601String(),
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'changed_slugs' => $changedSlugs,
            'unclassified_slugs' => $unclassifiedSlugs,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->command?->info("Classification report written to {$path}");
    }
}
