<?php

namespace App\Http\Controllers;

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

        if (StorefrontRoutes::isShopCategory($slug)) {
            if ($slug === 'mirror-frames') {
                return redirect()->route('shop.mirror-frames.index', [], 301);
            }

            return app(CollectionGalleryController::class)->index($slug);
        }

        return app(ProductController::class)->show($slug);
    }
}
