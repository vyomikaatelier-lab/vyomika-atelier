<?php

namespace App\Http\Controllers;

use App\Models\Product;

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

        return view('shop.show', compact('product', 'related'));
    }
}
