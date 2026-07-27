<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CollectionContent;
use App\Support\ProductCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CollectionGalleryController extends Controller
{
    /** @return list<string> */
    public static function slugs(): array
    {
        return CollectionContent::slugs();
    }

    public function index(string $slug): View|RedirectResponse
    {
        if ($slug === 'mirror-frames') {
            return redirect()->route('shop.mirror-frames.index');
        }

        abort_unless(in_array($slug, self::slugs(), true), 404);

        $page = CollectionContent::withResolvedImages(CollectionContent::page($slug));
        abort_unless(is_array($page), 404);

        $categorySlugs = $page['category_slugs'] ?? [$slug];

        $catalogSlugs = ProductCatalog::productSlugsForShopPage($slug);

        if ($catalogSlugs !== []) {
            $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();

            if (! $category && \App\Support\StorefrontRoutes::isShopCategory($slug)) {
                $category = new Category([
                    'name' => \App\Support\StorefrontRoutes::shopCategoryLabel($slug),
                    'slug' => $slug,
                    'is_active' => true,
                ]);
            }

            if (! $category) {
                $categorySlugs = $page['category_slugs'] ?? [$slug];
                $fallbackSlug = $categorySlugs[0] ?? $slug;
                $category = Category::query()->where('slug', $fallbackSlug)->where('is_active', true)->first();
            }

            abort_unless($category, 404);

            $products = self::galleryProductsForCategory($category, $catalogSlugs);

            $pageCategoryLabel = \App\Support\StorefrontRoutes::isShopCategory($slug)
                ? \App\Support\StorefrontRoutes::shopCategoryLabel($slug)
                : $category->name;

            return view('collections.gallery.index', [
                'page' => $page,
                'slug' => $slug,
                'category' => $category,
                'pageCategoryLabel' => $pageCategoryLabel,
                'products' => $products,
            ]);
        }

        $categories = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->where('is_active', true)
            ->get();

        abort_unless($categories->isNotEmpty(), 404);

        $category = $categories->firstWhere('slug', $slug) ?? $categories->first();

        $products = self::galleryProductsForCategory($category);

        return view('collections.gallery.index', [
            'page' => $page,
            'slug' => $slug,
            'category' => $category,
            'pageCategoryLabel' => \App\Support\StorefrontRoutes::isShopCategory($slug)
                ? \App\Support\StorefrontRoutes::shopCategoryLabel($slug)
                : $category->name,
            'products' => $products,
        ]);
    }

    /** @param  list<string>  $catalogSlugs */
    public static function galleryProductsForCategory(Category $category, array $catalogSlugs = []): Collection
    {
        if ($catalogSlugs !== []) {
            $products = Product::query()
                ->with('category')
                ->where('is_active', true)
                ->where('is_gallery_visible', true)
                ->whereIn('slug', $catalogSlugs)
                ->get()
                ->keyBy('slug');

            return collect($catalogSlugs)
                ->map(fn (string $slug) => $products->get($slug))
                ->filter()
                ->values();
        }

        return Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_gallery_visible', true)
            ->where('category_id', $category->id)
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();
    }
}
