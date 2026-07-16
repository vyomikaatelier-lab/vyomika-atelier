<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;

class MirrorFramesContent
{
    public static function all(): array
    {
        return config('mirror-frames', []);
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
        $product = Product::query()
            ->where('slug', $productSlug)
            ->where('is_active', true)
            ->with('category')
            ->first();

        if ($product) {
            return $product;
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

        return Product::query()->updateOrCreate(
            ['slug' => $productSlug],
            [
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
            ]
        )->load('category');
    }
}
