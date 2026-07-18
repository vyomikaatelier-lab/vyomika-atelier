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
            $this->classifyProductBySlugMap($productSlug, $meta, $updated, $skipped, $changedSlugs);
        }

        // Products in DB but not in the static slug map (e.g. procedurally generated
        // gallery SKUs) — classify from category slug when inference is unambiguous.
        $inferred = 0;
        Product::query()
            ->with('category')
            ->whereNotIn('slug', array_keys($slugMap))
            ->orderBy('slug')
            ->each(function (Product $product) use (&$inferred, &$changedSlugs): void {
                if ($this->classifyProductByInference($product, $changedSlugs)) {
                    $inferred++;
                }
            });

        $unclassified = Product::query()
            ->unclassified()
            ->pluck('slug')
            ->values()
            ->all();

        $deactivated = 0;
        Product::query()
            ->active()
            ->unclassified()
            ->each(function (Product $product) use (&$deactivated, &$unclassified): void {
                $product->update([
                    'is_active' => false,
                    'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
                ]);
                $deactivated++;
                $this->command?->warn("Deactivated unclassified active product: {$product->slug}");
            });

        $this->command?->info("Catalog classification complete: {$updated} updated from slug map, {$inferred} inferred from category, {$skipped} skipped, {$deactivated} active unclassified deactivated.");

        if ($unclassified !== []) {
            $this->command?->warn('Still unclassified (no slug-map entry and category could not be inferred): '.implode(', ', $unclassified));
        }

        $this->writeReport($changedSlugs, $unclassified, $updated + $inferred, $skipped, $deactivated);
    }

    /**
     * @param array{section: string, service_slug: ?string, shop_category: ?string, category: string} $meta
     * @param list<string> $changedSlugs
     */
    private function classifyProductBySlugMap(string $productSlug, array $meta, int &$updated, int &$skipped, array &$changedSlugs): void
    {
        $category = Category::query()->where('slug', $meta['category'])->first();

        if (! $category) {
            $this->command?->warn("Category not found for {$productSlug}: {$meta['category']}");
            $skipped++;

            return;
        }

        $product = Product::query()->where('slug', $productSlug)->first();

        if (! $product) {
            $this->command?->warn("Product not found: {$productSlug}");
            $skipped++;

            return;
        }

        if ($this->applyClassification($product, $category, $meta['section'], $changedSlugs)) {
            $updated++;
            $this->command?->line("Reassigned {$productSlug} → section={$meta['section']}, category={$meta['category']}");
        }
    }

    /** @param list<string> $changedSlugs */
    private function classifyProductByInference(Product $product, array &$changedSlugs): bool
    {
        $categorySlug = $product->category?->slug;
        $section = ProductCatalog::sectionFor($product->slug, $categorySlug);

        if ($section === 'unknown' || ! in_array($section, Product::SECTIONS, true)) {
            return false;
        }

        $category = $product->category;

        if (! $category) {
            $inferredCategorySlug = ProductCatalog::categorySlugForProduct($product->slug)
                ?? ProductCatalog::inferShopCategoryFromSlug($product->slug);

            if ($inferredCategorySlug) {
                $category = Category::query()->where('slug', $inferredCategorySlug)->first();
            }
        }

        if (! $category) {
            return false;
        }

        if ($this->applyClassification($product, $category, $section, $changedSlugs)) {
            $this->command?->line("Inferred {$product->slug} → section={$section}, category={$category->slug}");

            return true;
        }

        return false;
    }

    /** @param list<string> $changedSlugs */
    private function applyClassification(Product $product, Category $category, string $section, array &$changedSlugs): bool
    {
        $desired = [
            'category_id' => $category->id,
            'section' => $section,
            'purchase_mode' => self::PURCHASE_MODE_BY_SECTION[$section] ?? Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => self::PRICING_TYPE_BY_SECTION[$section] ?? Product::PRICING_FIXED,
        ];

        foreach ($desired as $field => $value) {
            if ((string) $product->getAttribute($field) !== (string) $value) {
                $product->update($desired);
                $changedSlugs[] = $product->slug;

                return true;
            }
        }

        return false;
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
    private function writeReport(array $changedSlugs, array $unclassifiedSlugs, int $updated, int $skipped, int $deactivated = 0): void
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
            'unclassified_slugs' => $unclassified,
            'active_unclassified_deactivated' => $deactivated,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->command?->info("Classification report written to {$path}");
    }
}
