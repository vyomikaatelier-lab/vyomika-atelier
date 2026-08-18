<?php

namespace App\Support\Seo;

use App\Models\Product;
use App\Support\CartGuard;
use App\Support\MediaUrl;

class JsonLd
{
    public static function localBusinessEnabled(): bool
    {
        $seo = config('site.seo', []);

        return filter_var($seo['local_business_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * LocalBusiness schema — only when explicitly enabled in Site Settings
     * and a verified customer-facing address is available.
     *
     * @return array<string, mixed>|null
     */
    public static function localBusiness(): ?array
    {
        if (! self::localBusinessEnabled()) {
            return null;
        }

        $brand = config('site.brand', []);
        $business = config('legal.business', []);
        $address = self::verifiedPublicAddress();
        $name = filled($brand['name'] ?? null)
            ? (string) $brand['name']
            : (filled($business['brand_name'] ?? null) ? (string) $business['brand_name'] : null);
        $phone = filled($business['phone'] ?? null)
            ? (string) $business['phone']
            : (filled($brand['phone'] ?? null) ? (string) $brand['phone'] : null);

        if (! filled($name) || ! filled($address) || ! filled($phone)) {
            return null;
        }

        $social = config('site.social', []);
        $sameAs = array_values(array_filter([
            $social['instagram'] ?? null,
            $social['facebook'] ?? null,
            $social['linkedin'] ?? null,
            $social['youtube'] ?? null,
        ]));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $name,
            'url' => url('/'),
            'telephone' => $phone,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressCountry' => self::countryCode($business['country'] ?? 'India'),
            ],
        ];

        $email = filled($business['email'] ?? null)
            ? (string) $business['email']
            : (filled($brand['email'] ?? null) ? (string) $brand['email'] : null);
        if (filled($email)) {
            $data['email'] = $email;
        }

        if (filled($brand['logo'] ?? null)) {
            $data['image'] = MediaUrl::resolve($brand['logo']) ?? $brand['logo'];
        }

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

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

        $url = filled($product->canonical_url)
            ? (string) $product->canonical_url
            : route('shop.show', $product->slug);
        $image = MediaUrl::resolve($product->image) ?? $product->image;
        $brandName = config('site.brand.name', 'Vyomika Atelier');

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => strip_tags((string) ($product->description ?? $product->name)),
            'sku' => self::normalizeSku($product->sku) ?: $product->slug,
            'url' => $url,
            'brand' => [
                '@type' => 'Brand',
                'name' => $brandName,
            ],
        ];

        if (filled($image)) {
            $data['image'] = [$image];
        }

        if (filled($product->material)) {
            $data['material'] = (string) $product->material;
        }

        if (filled($product->color)) {
            $data['color'] = (string) $product->color;
        }

        if (filled($product->gtin)) {
            $data['gtin'] = (string) $product->gtin;
        }

        if (filled($product->mpn)) {
            $data['mpn'] = (string) $product->mpn;
        }

        $unitPrice = $product->hasSizeOptions()
            ? $product->listingPrice()
            : (float) $product->price;

        if ($unitPrice > 0 && $product->pricing_type !== Product::PRICING_QUOTATION_ONLY) {
            $data['offers'] = [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'INR',
                'price' => number_format($unitPrice, 2, '.', ''),
                'availability' => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $brandName,
                ],
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

    /**
     * ItemList JSON-LD for landing-page gallery cards (railings categories, corten applications).
     *
     * @param  list<array<string, mixed>>  $cards
     * @return array<string, mixed>|null
     */
    public static function designGalleryItemList(array $cards, string $listName, string $titleKey = 'title', ?string $pageUrl = null): ?array
    {
        return GalleryCardSeo::itemList($cards, $listName, $titleKey, $pageUrl);
    }

    /** @param array<string, mixed> $data */
    public static function script(array $data): string
    {
        return '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
    }

    public static function normalizeSku(?string $sku): string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return '';
        }

        return trim((string) preg_replace('/^SKU:\s*/i', '', $sku));
    }

    private static function verifiedPublicAddress(): ?string
    {
        $business = config('legal.business', []);
        $address = trim((string) ($business['address'] ?? ''));

        if ($address === '' || self::isGenericAddress($address)) {
            return null;
        }

        return $address;
    }

    private static function isGenericAddress(string $address): bool
    {
        $lower = strtolower($address);

        return str_contains($lower, 'pan-india')
            || str_contains($lower, 'fabrication & delivery')
            || str_contains($lower, 'nationwide');
    }

    private static function countryCode(string $country): string
    {
        return match (strtolower(trim($country))) {
            'india', 'in' => 'IN',
            default => strtoupper(strlen($country) === 2 ? $country : 'IN'),
        };
    }
}
