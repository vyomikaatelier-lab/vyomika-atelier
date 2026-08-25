<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductCatalog;
use App\Support\ProductPublicationPolicy;
use App\Support\Seo\JsonLd;
use App\Support\Seo\ProductSeo;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->with('category')
            ->first();

        abort_unless(ProductPublicationPolicy::isPubliclyAccessible($product), 404);

        $relatedQuery = ProductPublicationPolicy::applyGalleryScope(
            Product::query()->unlessHiddenForStock()
        )
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id));

        $related = (clone $relatedQuery)->inRandomOrder()->take(4)->get();

        if ($related->count() < 4) {
            $more = (clone $relatedQuery)
                ->whereNotIn('id', $related->pluck('id'))
                ->take(4 - $related->count())
                ->get();
            $related = $related->concat($more);
        }

        $pageSeo = ProductSeo::pageData($product);
        $breadcrumbs = ProductCatalog::breadcrumbsFor($product);
        $breadcrumbLd = JsonLd::breadcrumbs($breadcrumbs);
        $productLd = JsonLd::product($product);

        return view('shop.show', compact('product', 'related', 'pageSeo', 'breadcrumbs', 'breadcrumbLd', 'productLd'));
    }
}
