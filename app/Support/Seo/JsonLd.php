<?php

namespace App\Support\Seo;

use App\Models\Product;
use App\Support\CartGuard;
use App\Support\MediaUrl;

class JsonLd
{
    /** @return array<string, mixed> */
    public static function organization(): array
    {
        $brand = config('site.brand', []);
        $business = config('legal.business', []);
        $social = config('site.social', []);

        $sameAs = array_values(array_filter([
            $social['instagram'] ?? null,
            $social['facebook'] ?? null,
            $social['linkedin'] ?? null,
            $social['youtube'] ?? null,
        ]));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $brand['name'] ?? 'Vyomika Atelier',
            'url' => url('/'),
            'description' => PageSeo::siteDefaults()['description'] ?? null,
        ];

        if (filled($brand['logo'] ?? null)) {
            $data['logo'] = MediaUrl::resolve($brand['logo']) ?? $brand['logo'];
        }

        if (filled($business['email'] ?? null)) {
            $data['email'] = $business['email'];
        }
        if (filled($business['phone'] ?? null)) {
            $data['telephone'] = $business['phone'];
        }
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('site.brand.name', 'Vyomika Atelier'),
            'url' => url('/'),
        ];
    }

    /**
     * @param  list<array{label: string, url?: string|null}>  $crumbs
     * @return array<string, mixed>|null
     */
    public static function breadcrumbs(array $crumbs): ?array
    {
        $items = [];
        $position = 1;
        foreach ($crumbs as $crumb) {
            $label = trim((string) ($crumb['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $entry = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $label,
            ];
            if (filled($crumb['url'] ?? null)) {
                $entry['item'] = $crumb['url'];
            }
            $items[] = $entry;
        }

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Product schema only for active shop checkout products.
     *
     * @return array<string, mixed>|null
     */
    public static function product(Product $product): ?array
    {
        if (! CartGuard::isEligible($product)) {
            return null;
        }

        $url = route('shop.show', $product->slug);
        $image = MediaUrl::resolve($product->image) ?? $product->image;

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => strip_tags((string) ($product->description ?? $product->name)),
            'sku' => $product->sku ?: $product->slug,
            'url' => $url,
        ];

        if (filled($image)) {
            $data['image'] = [$image];
        }

        $price = (float) $product->price;
        if ($price > 0 && $product->pricing_type !== Product::PRICING_QUOTATION_ONLY) {
            $data['offers'] = [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'INR',
                'price' => number_format($price, 2, '.', ''),
                'availability' => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        return $data;
    }

    /**
     * @param  list<array{q?: string, a?: string, question?: string, answer?: string}>  $faqs
     * @return array<string, mixed>|null
     */
    public static function faqPage(array $faqs): ?array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $q = trim((string) ($faq['q'] ?? $faq['question'] ?? ''));
            $a = trim((string) ($faq['a'] ?? $faq['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function script(array $data): string
    {
        return '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
    }
}
