<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryPublicationPolicy;
use App\Support\ShopCatalog;
use App\Support\StorefrontRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShopPageController extends Controller
{
    public function show(string $slug): View|RedirectResponse
    {
        if ($redirect = ShopCatalog::studioCategoryRedirectUrl($slug)) {
            return redirect($redirect, 301);
        }

        if (StorefrontRoutes::isStudioUrl($slug)) {
            return redirect()->route('studio.show', $slug, 301);
        }

        if ($studioUrl = StorefrontRoutes::studioUrlForService($slug)) {
            return redirect()->route('studio.show', $studioUrl, 301);
        }

        if (StorefrontRoutes::isShopCategory($slug) || $this->isActiveShopCategory($slug)) {
            if (! CategoryPublicationPolicy::isSitemapListedBySlug($slug)) {
                abort(404);
            }

            if ($slug === 'mirror-frames') {
                return redirect()->route('shop.mirror-frames.index', [], 301);
            }

            return app(CollectionGalleryController::class)->index($slug);
        }

        return app(ProductController::class)->show($slug);
    }

    private function isActiveShopCategory(string $slug): bool
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();

        return $category !== null && $category->resolvedSection() === Product::SECTION_SHOP;
    }
}
