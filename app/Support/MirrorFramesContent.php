<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;

class MirrorFramesContent
{
    public static function all(): array
    {
        $page = config('mirror-frames', []);
        $page['hero'] = PageHeroContent::heroWithResolvedImages('mirror-frames');

        return $page;
    }

    public static function design(string $slug): ?array
    {
        foreach (self::all()['designs'] ?? [] as $design) {
            if (($design['slug'] ?? '') === $slug) {
                return $design;
            }
        }

        return null;
    }

    public static function resolveProduct(string $productSlug): ?Product
    {
        return Product::query()
            ->where('slug', $productSlug)
            ->where('is_active', true)
            ->with('category')
            ->first();
    }

    /** @return array{title: string, description: ?string, image: ?string, product: ?Product} */
    public static function galleryCardFromDesign(array $design): array
    {
        $product = $design['product'] ?? null;
        if (! $product instanceof Product) {
            $productSlug = $design['product_slug'] ?? $design['slug'] ?? null;
            $product = is_string($productSlug) ? self::resolveProduct($productSlug) : null;
        }

        return [
            'title' => $product?->name ?? ($design['name'] ?? ''),
            'description' => $product?->description ?? ($design['description'] ?? null),
            'image' => $product?->imageUrl() ?? ($design['image'] ?? null),
            'product' => $product,
        ];
    }

    /**
     * Bootstraps a mirror frame product on a fresh install. It must never touch a
     * row that already exists: this runs from a public GET, so reseeding would
     * overwrite the admin's edits and undo a deliberate deactivation.
     */
    public static function resolveProductOrSeed(string $productSlug): ?Product
    {
        $product = self::resolveProduct($productSlug);

        if ($product) {
            return $product;
        }

        if (Product::query()->where('slug', $productSlug)->exists()) {
            return null;
        }

        $catalog = require database_path('data/mirror-frames-catalog.php');
        $item = collect($catalog)->firstWhere('slug', $productSlug);

        if (! $item) {
            return null;
        }

        $category = Category::query()->firstOrCreate(
            ['slug' => $item['category']],
            [
                'name' => 'Mirror Frames',
                'sort_order' => 8,
                'is_active' => true,
            ]
        );

        return Product::query()->create(
            [
                'slug' => $productSlug,
                'category_id' => $category->id,
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
                'dim_width_cm' => $item['dim_width_cm'] ?? null,
                'dim_height_cm' => $item['dim_height_cm'] ?? null,
            ]
        )->load('category');
    }
}
