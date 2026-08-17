@extends('layouts.store')

@section('title', ($design['name'] ?? $product->name) . ' — Mirror Frames — Vyomika Atelier')

@if($product->description)
@push('meta')
<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($product->description), 155) }}">
@endpush
@endif

@section('content')
@php
    use App\Models\Service;
    $mainImage = $product->imageUrl();
    $discount = $product->discountPercent();
@endphp

<section class="am-page-body am-page-body--pdp am-page-body--mirror-frames">
    <div class="am-container">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
            ['label' => 'Mirror Frames', 'url' => route('shop.mirror-frames.index')],
            ['label' => $design['name'] ?? $product->name],
        ]])

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
                <p class="am-featured__cat">Mirror Frames</p>
                <h1 class="am-pdp__title">{{ $product->name }}</h1>
                @if(filled($headlineMeta = $product->resolvedHeadlineText()))
                <p class="am-featured__meta">{{ $headlineMeta }}</p>
                @endif

                @php $hasSizeOptions = $product->hasSizeOptions(); @endphp

                @if(! $hasSizeOptions)
                <div class="am-featured__price">
                    <span class="am-featured__price-current">{{ $product->formattedPrice() }}</span>
                    @if($product->hasDisplayComparePrice())
                    <span class="am-featured__price-old">₹{{ number_format($product->compare_price, 0) }}</span>
                    <span class="am-featured__badge">-{{ $discount }}%</span>
                    @elseif(!empty($design['badge']))
                    <span class="am-featured__badge">{{ $design['badge'] }}</span>
                    @endif
                </div>
                @elseif(!empty($design['badge']))
                <div class="am-featured__price">
                    <span class="am-featured__badge">{{ $design['badge'] }}</span>
                </div>
                @endif

                <ul class="am-pdp__trust">
                    <li>✓ PVD stainless frame fabrication</li>
                    <li>✓ Secure crated packaging</li>
                    <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
                </ul>

                @php
                    $highlights = $product->linesWithoutDimensionChips($design['highlights'] ?? []);
                @endphp
                @if(count($highlights))
                <ul class="am-mirror-frames-highlights">
                    @foreach($highlights as $item)
                    <li>{{ $item }}</li>
                    @endforeach
                </ul>
                @endif

                @include('partials.am-mirror-dimensions', ['product' => $product])

                @include('partials.am-pdp-finish-swatches', ['note' => $product->resolvedSwatchesNote()])

                @include('partials.am-pdp-size-selector', ['product' => $product])

                <div class="am-pdp__buy-inline" id="buy">
                    @if($product->usesCheckoutFlow())
                    @include('partials.am-pdp-buy-actions', ['product' => $product, 'externalSizeSelector' => $hasSizeOptions])
                    @else
                    @include('partials.am-gallery-order-now-btn', [
                        'name' => $design['name'] ?? $product->name,
                        'slug' => $product->slug,
                        'serviceSlug' => \App\Models\Service::serviceSlugForProduct($product->slug, $product->category?->slug) ?? '',
                        'category' => 'Mirror Frames',
                        'price' => $product->price,
                        'class' => 'am-btn am-btn--primary am-btn--lg am-btn--full',
                    ])
                    @endif
                    @include('partials.am-pdp-checkout-trust')
                </div>
            </div>
        </div>

        @include('partials.am-product-tabs', [
            'title' => $product->name,
            'descriptionHtml' => $product->description ? '<div>' . $product->description . '</div>' : '',
            'specificationsHtml' => $product->tab_specifications,
            'packagingHtml' => $product->tab_packaging,
            'shippingHtml' => $product->tab_shipping,
            'careItems' => Service::careGuidelinesForCategory($product->category?->slug),
            'related' => $related,
            'product' => $product,
        ])
    </div>
</section>
@endsection
