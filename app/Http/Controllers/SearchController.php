<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Seo\PageSeo;
use App\Support\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', $request->query('search', '')));

        $products = collect();
        if ($q !== '') {
            $products = ShopCatalog::applyListingScope(
                Product::query()->where('is_active', true)->with('category')
            )
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('description', 'like', '%'.$q.'%')
                        ->orWhere('sku', 'like', '%'.$q.'%');
                })
                ->orderedForDisplay()
                ->paginate(12)
                ->withQueryString();
        }

        $pageSeo = PageSeo::make([
            'title' => $q !== '' ? 'Search: '.$q.' — Vyomika Atelier' : 'Search — Vyomika Atelier',
            'description' => 'Search Vyomika Atelier shop collections.',
            'canonical' => route('search', $q !== '' ? ['q' => $q] : []),
            'robots' => 'noindex,nofollow',
            'og_type' => 'website',
        ]);

        return view('search.index', [
            'q' => $q,
            'products' => $products,
            'pageSeo' => $pageSeo,
        ]);
    }
}
