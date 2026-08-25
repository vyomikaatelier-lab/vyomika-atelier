<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CollectionContent;
use App\Support\ProductCatalog;
use App\Support\Seo\JsonLd;
use App\Support\Seo\PageSeo;
use App\Support\StorefrontRoutes;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $dbCategory = Category::query()->where('slug', $slug)->where('is_active', true)->first();
        $isDbShopCategory = $dbCategory !== null && $dbCategory->resolvedSection() === Product::SECTION_SHOP;

        abort_unless(
            in_array($slug, self::slugs(), true)
                || StorefrontRoutes::isShopCategory($slug)
                || $isDbShopCategory,
            404
        );

        $page = CollectionContent::page($slug);
        if (! is_array($page)) {
            abort_unless($isDbShopCategory && $dbCategory, 404);
            $page = $this->fallbackPage($dbCategory);
        }
        $page = CollectionContent::withResolvedImages($page);

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

            $products = $this->paginateGallery(self::galleryProductsForCategory($category, $catalogSlugs));

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
                ['label' => 'Shop', 'url' => StorefrontRoutes::primaryShopUrl()],
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

        $products = $this->paginateGallery(self::galleryProductsForCategory($category));

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
            ['label' => 'Shop', 'url' => StorefrontRoutes::primaryShopUrl()],
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
     * Products for a collection gallery page, ordered by admin sort_order.
     * When catalog slugs are provided they extend the category scope (legacy
     * rows filed outside the category) but no longer dictate display order.
     *
     * @param  list<string>  $catalogSlugs
     */
    public static function galleryProductsForCategory(Category $category, array $catalogSlugs = []): Collection
    {
        if (! $category->exists && $catalogSlugs === []) {
            return collect();
        }

        $query = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_gallery_visible', true)
            ->unlessHiddenForStock();

        if ($catalogSlugs !== []) {
            $query->where(function ($q) use ($category, $catalogSlugs) {
                if ($category->exists) {
                    $q->where('category_id', $category->id);
                }

                if ($category->exists) {
                    $q->orWhereIn('slug', $catalogSlugs);
                } else {
                    $q->whereIn('slug', $catalogSlugs);
                }
            });
        } elseif ($category->exists) {
            $query->where('category_id', $category->id);
        } else {
            return collect();
        }

        return $query->orderedForDisplay()->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackPage(Category $category): array
    {
        return [
            'meta_title' => ($category->meta_title ?: $category->name).' — Vyomika Atelier',
            'meta_description' => $category->meta_description,
            'og_image' => $category->og_image ?? $category->image,
            'hero' => [
                'title' => $category->name,
                'image' => $category->image,
            ],
            'intro' => [
                'title' => $category->name,
                'body' => $category->description,
            ],
            'gallery_title' => $category->name,
            'category_slugs' => [$category->slug],
        ];
    }

    private function paginateGallery(Collection $products): LengthAwarePaginator
    {
        $perPage = max(1, (int) config('shop.gallery_per_page', 12));
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $products->forPage($page, $perPage)->values(),
            $products->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
