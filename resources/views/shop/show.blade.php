@extends('layouts.store')

@php
    use App\Support\Seo\JsonLd;
    use App\Support\Seo\PageSeo;
    use App\Support\MediaUrl;

    $pageSeo = PageSeo::make([
        'title' => $product->meta_title ?: ($product->name.' — Vyomika Atelier'),
        'description' => $product->meta_description
            ?: (\Illuminate\Support\Str::limit(strip_tags((string) $product->description), 155) ?: null),
        'canonical' => route('shop.show', $product->slug),
        'og_image' => $product->og_image ?: $product->image,
        'og_type' => 'product',
    ]);
    $productLd = JsonLd::product($product);
@endphp

@section('title', $pageSeo['title'])

@if($productLd)
@push('jsonld')
{!! JsonLd::script($productLd) !!}
@endpush
@endif

@section('content')
@php
    use App\Models\Service;
    use App\Support\ProductCatalog;
    use App\Support\StorefrontRoutes;
    $mainImage = $product->imageUrl();
    $discount = $product->discountPercent();
    $categorySlug = $product->category?->slug;
    $sectionLabel = StorefrontRoutes::productSectionLabel($product);
    $isStudio = $product->isStudioItem();
    $showCalculator = $isStudio;
    $showCheckoutBuy = $product->usesCheckoutFlow();
    $calcServiceSlug = Service::serviceSlugForProduct($product->slug, $categorySlug) ?? '';
    $calcLabel = Service::estimateLabelForProduct($product->slug, $categorySlug);
    $calcRate = $product->sqFtRate();
    $blackRate = $product->blackSqFtRateForProduct();
@endphp

<section class="am-page-body am-page-body--pdp">
    <div class="am-container">
        @include('partials.am-breadcrumbs', ['items' => StorefrontRoutes::productBreadcrumbs($product)])

        <div class="am-pdp">
            <div class="am-pdp__gallery" data-pdp-gallery>
                <div class="am-pdp__gallery-inner">
                    <div class="am-pdp__main">
                        @if($mainImage)
                            <img src="{{ $mainImage }}" alt="{{ $product->name }}" id="pdp-main-image" class="am-pdp__main-img">
                        @else
                            <div class="am-pdp__placeholder">VA</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="am-pdp__info">
                @if($sectionLabel)
                    <p class="am-featured__cat">{{ $sectionLabel }}</p>
                @endif
                <h1 class="am-pdp__title">{{ $product->name }}</h1>
                @if(filled($headlineMeta = $product->resolvedHeadlineText()))
                <p class="am-featured__meta">{{ $headlineMeta }}</p>
                @endif

                @include('partials.am-mirror-dimensions', ['product' => $product])

                @php $hasSizeOptions = $product->hasSizeOptions(); @endphp
                @php $selectedSize = $hasSizeOptions ? $product->resolveSizeOption(null) : null; @endphp

                {{-- Door handles: size rows carry per-size price/discount; show selector before the price line --}}
                @if($hasSizeOptions)
                @include('partials.am-pdp-size-options', ['product' => $product])
                @endif

                <div class="am-featured__price {{ $showCalculator ? 'am-featured__price--sqft' : '' }}{{ $hasSizeOptions ? ' am-featured__price--size-selected' : '' }}">
                    @if($showCalculator)
                    <div class="am-pdp__sqft-price">
                        <span class="am-pdp__sqft-price-current" data-sqft-rate-display>₹{{ number_format($calcRate, 0) }}</span>
                        <span class="am-pdp__sqft-price-unit">/ sq ft</span>
                    </div>
                    <p class="am-pdp__sqft-price-note" data-sqft-black-note hidden>Black finish selected — ₹{{ number_format($blackRate, 0) }}/sq ft (+30%)</p>
                    @else
                    <span class="am-featured__price-current" data-pdp-price-display>@if($selectedSize)₹{{ number_format($selectedSize['price'], 0) }}@else{{ $product->formattedPrice() }}@endif</span>
                    @if(! $hasSizeOptions && $product->hasDisplayComparePrice())
                    <span class="am-featured__price-old">₹{{ number_format($product->compare_price, 0) }}</span>
                    <span class="am-featured__badge">-{{ $discount }}%</span>
                    @endif
                    @endif
                </div>

                <ul class="am-pdp__trust">
                    <li>✓ PVD stainless fabrication</li>
                    <li>✓ Secure packaging</li>
                    <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
                </ul>

                @include('partials.am-pdp-finish-swatches', [
                    'swatches' => \App\Support\FinishSwatches::forRates($calcRate, $blackRate),
                    'baseRate' => $calcRate,
                    'note' => $product->resolvedSwatchesNote(),
                ])

                @unless($hasSizeOptions)
                @include('partials.am-pdp-size-options', ['product' => $product])
                @endunless

                @if($product->description)
                <div class="am-prose am-pdp__desc">{{ $product->description }}</div>
                @endif

                @if($showCalculator)
                <div class="am-pdp__calc-inline" id="buy">
                    @include('partials.am-calculator', [
                        'rate' => $calcRate,
                        'serviceSlug' => $calcServiceSlug,
                        'serviceName' => $product->name,
                        'calcTitle' => 'Estimate your ' . $calcLabel,
                    ])
                    @include('partials.am-pdp-checkout-trust')
                </div>
                @elseif($showCheckoutBuy)
                <div class="am-pdp__buy-inline" id="buy">
                    @include('partials.am-pdp-buy-actions', ['product' => $product])
                    @include('partials.am-pdp-checkout-trust')
                </div>
                @else
                <div class="am-pdp__quote-cta" id="buy">
                    @include('partials.am-gallery-order-now-btn', [
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'serviceSlug' => $calcServiceSlug,
                        'category' => $sectionLabel ?? '',
                        'price' => $product->price,
                        'class' => 'am-btn am-btn--primary am-btn--lg am-btn--full',
                    ])
                    @include('partials.am-pdp-checkout-trust')
                </div>
                @endif
            </div>
        </div>

        @include('partials.am-product-tabs', [
            'title' => $product->name,
            'descriptionHtml' => $product->description ? '<div>' . $product->description . '</div>' : '',
            'specificationsHtml' => $product->tab_specifications,
            'packagingHtml' => $product->tab_packaging,
            'shippingHtml' => $product->tab_shipping,
            'careItems' => ProductCatalog::careGuidelinesForProduct($product->slug, $categorySlug),
            'related' => $related,
            'product' => $product,
            'categoryLabel' => $sectionLabel,
        ])
    </div>
</section>
@endsection
