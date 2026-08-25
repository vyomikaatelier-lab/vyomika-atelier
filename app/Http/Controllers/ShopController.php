<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ShopCatalog;
use App\Support\StorefrontRoutes;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * The generic all-products listing is retired. /shop permanently redirects
     * to the configured primary shop category. Query-string category shortcuts
     * and search keep working as one-hop redirects.
     */
    public function index(Request $request)
    {
        if ($request->filled('category')) {
            $categorySlug = (string) $request->query('category');

            if ($redirect = ShopCatalog::studioCategoryRedirectUrl($categorySlug)) {
                return redirect($redirect, 301);
            }

            if ($categorySlug === 'mirror-frames') {
                return redirect()->route('shop.mirror-frames.index', [], 301);
            }

            if (StorefrontRoutes::isShopCategory($categorySlug)) {
                return redirect()->route('shop.show', $categorySlug, 301);
            }
        }

        if ($request->filled('search')) {
            return redirect()->route('search', ['q' => $request->query('search')], 301);
        }

        return redirect(StorefrontRoutes::primaryShopUrl(), 301);
    }
}
