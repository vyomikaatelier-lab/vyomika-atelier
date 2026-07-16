@extends('layouts.store')

@section('title', $product->name . ' — Vyomika Atelier LLP')

@if($product->description)
@push('meta')
<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($product->description), 155) }}">
@endpush
@endif

@section('content')
@php
    use App\Models\Service;
    $images = $product->galleryUrls();
    $discount = $product->discountPercent();
    $categorySlug = $product->category?->slug;
    $showCalculator = $product->showsSqFtCalculator();
    $calcServiceSlug = Service::serviceSlugForProduct($product->slug, $categorySlug);
    $calcLabel = Service::estimateLabelForProduct($product->slug, $categorySlug);
    $calcRate = \App\Models\Product::baseSqFtRate();
    $blackRate = \App\Models\Product::blackSqFtRate();
@endphp

<section class="am-page-body am-page-body--pdp">
    <div class="am-container">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
            ...($product->category ? [['label' => $product->category->name, 'url' => route('shop.index', ['category' => $product->category->slug])]] : []),
            ['label' => $product->name],
        ]])

        <div class="am-pdp">
            <div class="am-pdp__gallery" data-pdp-gallery>
                <div class="am-pdp__gallery-inner">
                    @if(count($images) > 1)
                    <div class="am-pdp__thumbs am-pdp__thumbs--vertical">
                        @foreach($images as $i => $src)
                        <button type="button" class="am-pdp__thumb {{ $i === 0 ? 'is-active' : '' }}" data-pdp-thumb="{{ $src }}" aria-label="View image {{ $i + 1 }}">
                            <img src="{{ $src }}" alt="">
                        </button>
                        @endforeach
                    </div>
                    @endif
                    <div class="am-pdp__main">
                        @if(count($images))
                            <img src="{{ $images[0] }}" alt="{{ $product->name }}" id="pdp-main-image" class="am-pdp__main-img">
                        @else
                            <div class="am-pdp__placeholder">VA</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="am-pdp__info">
                @if($product->category)
                    <p class="am-featured__cat">{{ $product->category->name }}</p>
                @endif
                <h1 class="am-pdp__title">{{ $product->name }}</h1>
                <p class="am-featured__meta">
                    @if($product->sku) SKU: {{ $product->sku }} · @endif
                    Pan-India shipping
                </p>

                <div class="am-featured__price {{ $showCalculator ? 'am-featured__price--sqft' : '' }}">
                    @if($showCalculator)
                    <div class="am-pdp__sqft-price">
                        <span class="am-pdp__sqft-price-current" data-sqft-rate-display>₹{{ number_format($calcRate, 0) }}</span>
                        <span class="am-pdp__sqft-price-unit">/ sq ft</span>
                    </div>
                    <p class="am-pdp__sqft-price-note" data-sqft-black-note hidden>Black finish selected — ₹{{ number_format($blackRate, 0) }}/sq ft (+30%)</p>
                    @else
                    <span class="am-featured__price-current">{{ $product->formattedPrice() }}</span>
                    @if($product->compare_price)
                    <span class="am-featured__price-old">₹{{ number_format($product->compare_price, 0) }}</span>
                    @endif
                    @if($discount)
                    <span class="am-featured__badge">-{{ $discount }}%</span>
                    @endif
                    @endif
                </div>

                <ul class="am-pdp__trust">
                    <li>✓ PVD stainless fabrication</li>
                    <li>✓ Secure packaging</li>
                    <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
                </ul>

                @include('partials.am-pdp-finish-swatches')

                @if($product->description)
                <div class="am-prose am-pdp__desc">{{ $product->description }}</div>
                @endif

                @if($showCalculator)
                <div class="am-pdp__calc-inline">
                    @include('partials.am-calculator', [
                        'rate' => $calcRate,
                        'serviceSlug' => $calcServiceSlug,
                        'serviceName' => $product->name,
                        'calcTitle' => 'Estimate your ' . $calcLabel,
                    ])
                    @include('partials.am-pdp-checkout-trust')
                </div>
                @else
                <div class="am-pdp__quote-cta" id="buy">
                    <button type="button" class="am-btn am-btn--primary am-btn--lg am-btn--full" data-open-order-popup data-product-name="{{ $product->name }}" data-product-slug="{{ $product->slug }}" data-service-slug="{{ $calcServiceSlug }}">Order Now</button>
                </div>
                @include('partials.am-pdp-checkout-trust')
                @endif
            </div>
        </div>

        @include('partials.am-product-tabs', [
            'title' => $product->name,
            'descriptionHtml' => $product->description ? '<div>' . $product->description . '</div>' : '',
            'careItems' => Service::careGuidelinesForCategory($categorySlug),
            'related' => $related,
            'product' => $product,
        ])
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-pdp-thumb]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const src = btn.dataset.pdpThumb;
        const main = document.getElementById('pdp-main-image');
        if (main && src) main.src = src;
        document.querySelectorAll('[data-pdp-thumb]').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
    });
});
</script>
@endpush
