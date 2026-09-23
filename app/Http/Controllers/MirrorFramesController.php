<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\MirrorFramesContent;
use App\Support\ProductPublicationPolicy;
use Illuminate\View\View;

class MirrorFramesController extends Controller
{
    public function index(): View
    {
        $page = MirrorFramesContent::all();

        return view('collections.mirror-frames.index', [
            'page' => $page,
        ]);
    }

    public function show(string $designSlug): View
    {
        $design = MirrorFramesContent::design($designSlug);

        abort_unless($design, 404);

        $productSlug = $design['product_slug'] ?? $designSlug;
        $product = MirrorFramesContent::resolveProductOrSeed($productSlug);

        abort_unless($product, 404);

        $relatedQuery = ProductPublicationPolicy::applyGalleryScope(
            Product::query()->unlessHiddenForStock()
        )->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id));

        $related = (clone $relatedQuery)->inRandomOrder()->take(4)->get();

        if ($related->count() < 4) {
            $more = (clone $relatedQuery)
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
