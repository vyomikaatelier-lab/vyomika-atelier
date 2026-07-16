<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\MirrorFramesContent;
use Illuminate\View\View;

class MirrorFramesController extends Controller
{
    public function index(): View
    {
        return view('collections.mirror-frames.index', [
            'page' => MirrorFramesContent::all(),
        ]);
    }

    public function show(string $designSlug): View
    {
        $design = MirrorFramesContent::design($designSlug);

        abort_unless($design, 404);

        $productSlug = $design['product_slug'] ?? $designSlug;
        $product = MirrorFramesContent::resolveProduct($productSlug);

        abort_unless($product, 404);

        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->inRandomOrder()
            ->take(4)
            ->get();

        if ($related->count() < 4) {
            $more = Product::query()
                ->where('is_active', true)
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->take(4 - $related->count())
                ->get();
            $related = $related->concat($more);
        }

        return view('collections.mirror-frames.show', [
            'page' => MirrorFramesContent::all(),
            'design' => $design,
            'product' => $product,
            'related' => $related,
        ]);
    }
}
