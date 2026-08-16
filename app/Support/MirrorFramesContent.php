<?php

namespace App\Support;

use App\Http\Controllers\CollectionGalleryController;
use App\Models\Category;
use App\Models\Product;

class MirrorFramesContent
{
    public static function all(): array
    {
        $page = config('mirror-frames', []);
        $collectionPage = CollectionContent::page('mirror-frames');

        if (is_array($collectionPage)) {
            foreach (['meta_title', 'meta_description', 'intro', 'gallery_title'] as $key) {
                if (array_key_exists($key, $collectionPage)) {
                    $page[$key] = $collectionPage[$key];
                }
            }

            if (isset($collectionPage['hero']) && is_array($collectionPage['hero'])) {
                $page['hero'] = $collectionPage['hero'];
            }
        }

        $page['designs'] = self::galleryDesigns();

        return LandingPageContent::withResolvedImages($page);
    }

    /**
     * Gallery rows driven by admin products in the mirror-frames category,
     * enriched with config design metadata (badges, highlights) when available.
     *
     * @return list<array<string, mixed>>
     */
    public static function galleryDesigns(): array
    {
        $configDesigns = self::configDesigns();
        $configByProductSlug = collect($configDesigns)->keyBy(
            fn (array $design) => (string) ($design['product_slug'] ?? $design['slug'] ?? '')
        );

        $category = Category::query()->where('slug', 'mirror-frames')->first();

        if (! $category) {
            return self::designsFromConfigWithProducts($configDesigns);
        }

        $catalogSlugs = ProductCatalog::productSlugsForShopPage('mirror-frames');
        $products = CollectionGalleryController::galleryProductsForCategory($category, $catalogSlugs);

        if ($products->isEmpty()) {
            return self::designsFromConfigWithProducts($configDesigns);
        }

        return $products
            ->map(function (Product $product) use ($configByProductSlug) {
                $configDesign = $configByProductSlug->get($product->slug);
                $designSlug = is_array($configDesign)
                    ? (string) ($configDesign['slug'] ?? $product->slug)
                    : $product->slug;

                return array_merge(is_array($configDesign) ? $configDesign : [], [
                    'slug' => $designSlug,
                    'product_slug' => $product->slug,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->imageUrl(),
                    'product' => $product,
                ]);
            })
            ->values()
            ->all();
    }

    public static function design(string $slug): ?array
    {
        foreach (self::configDesigns() as $design) {
            if (($design['slug'] ?? '') === $slug) {
                return $design;
            }
        }

        $productSlug = self::resolveProduct($slug)?->slug ?? $slug;
        $product = self::resolveProduct($productSlug);

        if (! $product) {
            return null;
        }

        return [
            'slug' => $slug,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
        ];
    }

    public static function resolveProduct(string $productSlug, bool $forListing = false): ?Product
    {
        $query = Product::query()
            ->where('slug', $productSlug)
            ->where('is_active', true)
            ->with('category');

        if ($forListing) {
            $query->where('is_gallery_visible', true)
                ->unlessHiddenForStock();
        }

        return $query->first();
    }

    /** @return list<array<string, mixed>> */
    private static function configDesigns(): array
    {
        $designs = config('mirror-frames.designs', []);

        return is_array($designs) ? $designs : [];
    }

    /**
     * Fallback when no category or products exist yet (fresh install / preview).
     *
     * @param  list<array<string, mixed>>  $configDesigns
     * @return list<array<string, mixed>>
     */
    private static function designsFromConfigWithProducts(array $configDesigns): array
    {
        return collect($configDesigns)
            ->map(function (array $design) {
                $productSlug = $design['product_slug'] ?? $design['slug'] ?? null;
                $product = is_string($productSlug) ? self::resolveProduct($productSlug, true) : null;

                if (! $product) {
                    return null;
                }

                $design['product'] = $product;
                $design['name'] = $product->name;
                $design['description'] = $product->description;
                $design['image'] = $product->imageUrl();

                return $design;
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{title: string, description: ?string, image: ?string, product: ?Product} */
    public static function galleryCardFromDesign(array $design): array
    {
        $product = $design['product'] ?? null;
        if (! $product instanceof Product) {
            $productSlug = $design['product_slug'] ?? $design['slug'] ?? null;
            $product = is_string($productSlug) ? self::resolveProduct($productSlug, true) : null;
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
