<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Seo\PageSeo;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with('category')
            ->firstOrFail();

        $related = Product::where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->inRandomOrder()
            ->take(4)
            ->get();

        if ($related->count() < 4) {
            $more = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->take(4 - $related->count())
                ->get();
            $related = $related->concat($more);
        }

        $pageSeo = PageSeo::make([
            'title' => $product->meta_title ?: ($product->name.' — Vyomika Atelier'),
            'description' => $product->meta_description
                ?: (Str::limit(strip_tags((string) $product->description), 155) ?: null),
            'canonical' => route('shop.show', $product->slug),
            'og_image' => $product->og_image ?: $product->image,
            'og_type' => 'product',
        ]);

        return view('shop.show', compact('product', 'related', 'pageSeo'));
    }
}
