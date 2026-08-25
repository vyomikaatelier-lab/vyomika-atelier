<?php

namespace App\Support\Seo;

use App\Models\Product;
use App\Support\ProductPublicationPolicy;
use Illuminate\Support\Str;

class ProductSeo
{
    /** @return array<string, mixed> */
    public static function pageData(Product $product): array
    {
        $canonical = filled($product->canonical_url)
            ? (string) $product->canonical_url
            : route('shop.show', $product->slug);

        $robots = ProductPublicationPolicy::robotsMeta($product);

        return PageSeo::make([
            'title' => $product->meta_title ?: ($product->name.' — Vyomika Atelier'),
            'description' => $product->meta_description
                ?: (Str::limit(strip_tags((string) $product->description), 155) ?: null),
            'canonical' => $canonical,
            'robots' => $robots,
            'og_image' => $product->og_image ?: $product->image,
            'og_type' => 'product',
        ]);
    }

    public static function imageAlt(Product $product): string
    {
        if (filled($product->image_alt)) {
            return trim((string) $product->image_alt);
        }

        return $product->name;
    }
}
