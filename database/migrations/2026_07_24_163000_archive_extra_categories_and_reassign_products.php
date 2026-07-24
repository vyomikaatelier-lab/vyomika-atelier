<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasTable('products')) {
            return;
        }

        DB::transaction(function (): void {
            ProductCatalog::syncCanonicalCategories();

            $id = fn (string $slug): ?int => Category::query()->where('slug', $slug)->value('id');

            $partitionsId = $id('partitions');
            $bespokeId = $id('bespoke-metal-furniture');
            $railingsId = $id('railings');

            // fluted-panels, room-dividers → partitions (studio / enquiry / sq ft)
            if ($partitionsId) {
                Product::query()
                    ->whereHas('category', fn ($q) => $q->whereIn('slug', ['fluted-panels', 'room-dividers']))
                    ->update([
                        'category_id' => $partitionsId,
                        'section' => Product::SECTION_STUDIO,
                        'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
                        'pricing_type' => Product::PRICING_SQUARE_FOOT,
                    ]);
            }

            // home-decor → bespoke-metal-furniture (shop)
            if ($bespokeId) {
                Product::query()
                    ->whereHas('category', fn ($q) => $q->where('slug', 'home-decor'))
                    ->update([
                        'category_id' => $bespokeId,
                        'section' => Product::SECTION_SHOP,
                        'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                        'pricing_type' => Product::PRICING_FIXED,
                    ]);
            }

            // metal-furniture → service inference / bespoke
            Product::query()
                ->with('category')
                ->whereHas('category', fn ($q) => $q->where('slug', 'metal-furniture'))
                ->orderBy('id')
                ->each(function (Product $product) use ($id): void {
                    $targetSlug = ProductCatalog::categorySlugForProduct($product->slug)
                        ?? ProductCatalog::categorySlugForMetalFurnitureProduct($product->slug);

                    $categoryId = $id($targetSlug);
                    if (! $categoryId) {
                        return;
                    }

                    $section = in_array($targetSlug, ProductCatalog::studioCategorySlugs(), true)
                        ? Product::SECTION_STUDIO
                        : Product::SECTION_SHOP;

                    $product->update([
                        'category_id' => $categoryId,
                        'section' => $section,
                        'purchase_mode' => Product::SECTION_PURCHASE_MODE_MAP[$section] ?? null,
                        'pricing_type' => match ($section) {
                            Product::SECTION_SHOP => Product::PRICING_FIXED,
                            Product::SECTION_STUDIO => Product::PRICING_SQUARE_FOOT,
                            default => $product->pricing_type,
                        },
                    ]);
                });

            // railings category products: keep section/purchase_mode; keep archived FK (nullable allowed)
            if ($railingsId) {
                Product::query()
                    ->where('category_id', $railingsId)
                    ->update([
                        'section' => Product::SECTION_RAILINGS,
                        'purchase_mode' => Product::PURCHASE_MODE_QUOTE,
                        'pricing_type' => Product::PRICING_QUOTATION_ONLY,
                    ]);
            }

            Category::query()
                ->whereIn('slug', ProductCatalog::obsoleteCategorySlugs())
                ->update(['is_active' => false]);
        });
    }

    public function down(): void
    {
        // Irreversible data reassignment — obsolete categories remain inactive.
    }
};
