<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CollectionContent;
use App\Support\ProductCatalog;
use App\Support\Seo\JsonLd;
use App\Support\Seo\PageSeo;
use App\Support\StorefrontRoutes;
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

            if (! $category && StorefrontRoutes::isShopCategory($slug)) {
                $category = new Category([
                    'name' => StorefrontRoutes::shopCategoryLabel($slug),
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

            $pageCategoryLabel = StorefrontRoutes::isShopCategory($slug)
                ? StorefrontRoutes::shopCategoryLabel($slug)
                : $category->name;

            $pageSeo = PageSeo::make([
                'title' => $page['meta_title'] ?? ($category->meta_title ?: ($pageCategoryLabel.' — Vyomika Atelier')),
                'description' => $page['meta_description'] ?? $category->meta_description,
                'canonical' => route('shop.show', $slug),
                'og_image' => $page['og_image'] ?? $category->og_image ?? $category->image,
                'og_type' => 'website',
            ]);

            $breadcrumbs = [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Shop', 'url' => route('shop.index')],
                ['label' => $pageCategoryLabel],
            ];
            $breadcrumbLd = JsonLd::breadcrumbs($breadcrumbs);

            return view('collections.gallery.index', [
                'page' => $page,
                'slug' => $slug,
                'category' => $category,
                'pageCategoryLabel' => $pageCategoryLabel,
                'products' => $products,
                'pageSeo' => $pageSeo,
                'breadcrumbs' => $breadcrumbs,
                'breadcrumbLd' => $breadcrumbLd,
            ]);
        }

        $categories = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->where('is_active', true)
            ->get();

        abort_unless($categories->isNotEmpty(), 404);

        $category = $categories->firstWhere('slug', $slug) ?? $categories->first();

        $products = self::galleryProductsForCategory($category);

        $pageCategoryLabel = StorefrontRoutes::isShopCategory($slug)
            ? StorefrontRoutes::shopCategoryLabel($slug)
            : $category->name;

        $pageSeo = PageSeo::make([
            'title' => $page['meta_title'] ?? ($category->meta_title ?: ($pageCategoryLabel.' — Vyomika Atelier')),
            'description' => $page['meta_description'] ?? $category->meta_description,
            'canonical' => route('shop.show', $slug),
            'og_image' => $page['og_image'] ?? $category->og_image ?? $category->image,
            'og_type' => 'website',
        ]);

        $breadcrumbs = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
            ['label' => $pageCategoryLabel],
        ];
        $breadcrumbLd = JsonLd::breadcrumbs($breadcrumbs);

        return view('collections.gallery.index', [
            'page' => $page,
            'slug' => $slug,
            'category' => $category,
            'pageCategoryLabel' => $pageCategoryLabel,
            'products' => $products,
            'pageSeo' => $pageSeo,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbLd' => $breadcrumbLd,
        ]);
    }

    /**
     * The catalog slug list is a curated running order, not an allow-list: any
     * other product the admin filed under this category is appended after it.
     *
     * @param  list<string>  $catalogSlugs
     */
    public static function galleryProductsForCategory(Category $category, array $catalogSlugs = []): Collection
    {
        $fromCategory = $category->exists
            ? Product::query()
                ->with('category')
                ->where('is_active', true)
                ->where('is_gallery_visible', true)
                ->where('category_id', $category->id)
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->get()
            : collect();

        if ($catalogSlugs === []) {
            return $fromCategory;
        }

        $curated = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_gallery_visible', true)
            ->whereIn('slug', $catalogSlugs)
            ->get()
            ->keyBy('slug');

        return collect($catalogSlugs)
            ->map(fn (string $slug) => $curated->get($slug))
            ->filter()
            ->concat($fromCategory)
            ->unique('id')
            ->values();
    }
}
