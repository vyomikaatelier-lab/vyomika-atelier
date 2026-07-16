<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('is_active', true)->with('category');

        $activeCategory = null;
        if ($request->filled('category')) {
            $activeCategory = Category::where('slug', $request->category)->where('is_active', true)->first();
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
        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $pageTitle = $activeCategory?->name ?? ($request->filled('search') ? 'Search' : 'Shop');
        $pageSubtitle = $activeCategory
            ? 'Browse '.$activeCategory->name.' from Vyomika Atelier LLP.'
            : 'PVD partitions, fluted panels, metal furniture, and hardware — Pan-India delivery.';

        return view('shop.index', compact('products', 'categories', 'activeCategory', 'pageTitle', 'pageSubtitle'));
    }
}
