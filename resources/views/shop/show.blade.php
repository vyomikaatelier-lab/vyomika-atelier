@extends('layouts.store')

@php
    use App\Support\Seo\JsonLd;
@endphp

@section('title', $pageSeo['title'])

@if($productLd)
@push('jsonld')
{!! JsonLd::script($productLd) !!}
@endpush
@endif

@if($breadcrumbLd)
@push('jsonld')
{!! JsonLd::script($breadcrumbLd) !!}
@endpush
@endif

@section('content')
@php
    use App\Models\Service;
    use App\Support\ProductCatalog;
    use App\Support\Seo\ProductSeo;
    use App\Support\StorefrontRoutes;
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
        @include('partials.am-breadcrumbs', ['items' => $breadcrumbs ?? StorefrontRoutes::productBreadcrumbs($product)])

        <div class="am-pdp">
            <div class="am-pdp__gallery" data-pdp-gallery>
                <div class="am-pdp__gallery-inner">
                    <div class="am-pdp__main">
                        @if($product->image)
                            @include('partials.am-product-image', [
                                'path' => $product->image,
                                'alt' => ProductSeo::imageAlt($product),
                                'context' => 'pdp',
                                'priority' => true,
                                'class' => 'am-pdp__main-img',
                                'id' => 'pdp-main-image',
                            ])
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

                @php $hasSizeOptions = $product->hasSizeOptions(); @endphp

                @if($showCalculator || ! $hasSizeOptions)
                <div class="am-featured__price {{ $showCalculator ? 'am-featured__price--sqft' : '' }}">
                    @if($showCalculator)
                    <div class="am-pdp__sqft-price">
                        <span class="am-pdp__sqft-price-current" data-sqft-rate-display>₹{{ number_format($calcRate, 0) }}</span>
                        <span class="am-pdp__sqft-price-unit">/ sq ft</span>
                    </div>
                    <p class="am-pdp__sqft-price-note" data-sqft-black-note hidden>Black finish selected — ₹{{ number_format($blackRate, 0) }}/sq ft (+30%)</p>
                    @else
                    <span class="am-featured__price-current">{{ $product->formattedPrice() }}</span>
                    @if($product->hasDisplayComparePrice())
                    <span class="am-featured__price-old">₹{{ number_format($product->compare_price, 0) }}</span>
                    <span class="am-featured__badge">-{{ $discount }}%</span>
                    @endif
                    @endif
                </div>
                @endif

                <ul class="am-pdp__trust">
                    <li>✓ PVD stainless fabrication</li>
                    <li>✓ Secure packaging</li>
                    <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
                </ul>

                @include('partials.am-mirror-dimensions', ['product' => $product])

                @include('partials.am-pdp-finish-swatches', [
                    'swatches' => \App\Support\FinishSwatches::forRates($calcRate, $blackRate),
                    'baseRate' => $calcRate,
                    'note' => $product->resolvedSwatchesNote(),
                ])

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
