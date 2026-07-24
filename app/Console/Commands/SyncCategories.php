<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Console\Command;

class SyncCategories extends Command
{
    protected $signature = 'catalog:sync-categories {--assign-products : Reassign products missing category or wrong section}';

    protected $description = 'Ensure canonical product categories exist with correct section and optionally reassign products';

    public function handle(): int
    {
        $synced = ProductCatalog::syncCanonicalCategories();
        $this->info("Synced {$synced} canonical categories.");

        if ($this->option('assign-products')) {
            $assigned = $this->assignProducts();
            $this->info("Reassigned or updated {$assigned} product(s).");
        }

        return self::SUCCESS;
    }

    private function assignProducts(): int
    {
        $updated = 0;

        Product::query()
            ->with('category')
            ->where(function ($query) {
                $query->whereNull('category_id')
                    ->orWhere(fn ($q) => $q->unclassified());
            })
            ->orderBy('slug')
            ->each(function (Product $product) use (&$updated): void {
                $categorySlug = ProductCatalog::categorySlugForProduct($product->slug)
                    ?? $this->inferCategorySlug($product);

                if ($categorySlug === null) {
                    return;
                }

                $category = Category::query()->where('slug', $categorySlug)->first();
                if ($category === null) {
                    return;
                }

                $resolvedSection = $category->resolvedSection();
                $changes = [];

                if ($product->category_id !== $category->id) {
                    $changes['category_id'] = $category->id;
                }

                if ($resolvedSection !== null && $product->section !== $resolvedSection) {
                    $changes['section'] = $resolvedSection;
                    $changes['purchase_mode'] = Product::SECTION_PURCHASE_MODE_MAP[$resolvedSection] ?? null;
                    $changes['pricing_type'] = match ($resolvedSection) {
                        Product::SECTION_SHOP => Product::PRICING_FIXED,
                        Product::SECTION_STUDIO => Product::PRICING_SQUARE_FOOT,
                        Product::SECTION_RAILINGS => Product::PRICING_QUOTATION_ONLY,
                        default => null,
                    };
                }

                if ($changes !== []) {
                    $product->update($changes);
                    $updated++;
                }
            });

        return $updated;
    }

    private function inferCategorySlug(Product $product): ?string
    {
        $section = ProductCatalog::inferSectionFromSlug($product->slug);

        if ($section === 'railings') {
            return 'railings';
        }

        if ($section === 'shop') {
            return ProductCatalog::inferShopCategoryFromSlug($product->slug);
        }

        if ($section === 'studio') {
            if (preg_match('/(partition|fluted|room-divider)/', $product->slug)) {
                return match (true) {
                    str_contains($product->slug, 'fluted') => 'fluted-panels',
                    str_contains($product->slug, 'room-divider') || str_contains($product->slug, 'divider') => 'room-dividers',
                    default => 'partitions',
                };
            }

            return 'metal-furniture';
        }

        return $product->category?->slug;
    }
}
