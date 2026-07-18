<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CollectionGalleryController extends Controller
{
    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(config('collections', []));
    }

    public function index(string $slug): View|RedirectResponse
    {
        if ($slug === 'mirror-frames') {
            return redirect()->route('shop.mirror-frames.index');
        }

        abort_unless(in_array($slug, self::slugs(), true), 404);

        $page = config("collections.{$slug}");
        abort_unless(is_array($page), 404);

        $categorySlugs = $page['category_slugs'] ?? [$slug];

        $catalogSlugs = ProductCatalog::productSlugsForShopPage($slug);

        if ($catalogSlugs !== []) {
            $products = Product::query()
                ->whereIn('slug', $catalogSlugs)
                ->where('is_active', true)
                ->with('category')
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->get();

            $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();

            if (! $category && \App\Support\StorefrontRoutes::isShopCategory($slug)) {
                $category = new Category([
                    'name' => \App\Support\StorefrontRoutes::shopCategoryLabel($slug),
                    'slug' => $slug,
                    'is_active' => true,
                ]);
            }

            if (! $category) {
                $fallbackSlug = $categorySlugs[0] ?? $slug;
                $category = Category::query()->where('slug', $fallbackSlug)->where('is_active', true)->first();
            }

            abort_unless($category, 404);

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

        $products = Product::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('is_active', true)
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

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
}
