<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Support\ShopCatalog;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('category')) {
            $categorySlug = $request->category;

            if ($redirect = ShopCatalog::studioCategoryRedirectUrl($categorySlug)) {
                return redirect($redirect);
            }

            if ($categorySlug === 'mirror-frames') {
                return redirect()->route('collections.mirror-frames.index');
            }

            if (in_array($categorySlug, CollectionGalleryController::slugs(), true)) {
                return redirect()->route('collections.gallery.index', $categorySlug);
            }
        }

        $query = ShopCatalog::applyShopScope(
            Product::where('is_active', true)->with('category')
        );

        $activeCategory = null;
        if ($request->filled('category')) {
            $activeCategory = Category::where('slug', $request->category)
                ->where('is_active', true)
                ->whereIn('slug', ShopCatalog::categorySlugs())
                ->first();

            if ($activeCategory) {
                $query->where('category_id', $activeCategory->id);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        match ($request->get('sort', 'newest')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('slug', ShopCatalog::categorySlugs())
            ->withCount(['products' => fn ($q) => ShopCatalog::applyShopScope(
                $q->where('is_active', true)
            )])
            ->having('products_count', '>', 0)
            ->orderBy('name')
            ->get();

        $pageTitle = $activeCategory?->name ?? ($request->filled('search') ? 'Search' : 'Shop');
        $pageSubtitle = $activeCategory
            ? 'Browse '.$activeCategory->name.' from Vyomika Atelier LLP.'
            : 'Mirror frames, tables, and door hardware — order through our collection galleries.';

        return view('shop.index', compact('products', 'categories', 'activeCategory', 'pageTitle', 'pageSubtitle'));
    }
}
