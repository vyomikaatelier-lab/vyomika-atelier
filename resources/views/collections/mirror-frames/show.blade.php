@extends('layouts.store')

@section('title', ($design['name'] ?? $product->name) . ' — Mirror Frames — Vyomika Atelier LLP')

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
    $highlights = $design['highlights'] ?? [];
@endphp

<section class="am-page-body am-page-body--pdp am-page-body--mirror-frames">
    <div class="am-container">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Collections', 'url' => route('shop.index')],
            ['label' => 'Mirror Frames', 'url' => route('collections.mirror-frames.index')],
            ['label' => $design['name'] ?? $product->name],
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
                <p class="am-featured__cat">Mirror Frames</p>
                <h1 class="am-pdp__title">{{ $design['name'] ?? $product->name }}</h1>
                <p class="am-featured__meta">
                    @if($product->sku) SKU: {{ $product->sku }} · @endif
                    Pan-India shipping
                </p>

                <div class="am-featured__price">
                    <span class="am-featured__price-current">{{ $product->formattedPrice() }}</span>
                    @if($product->compare_price)
                    <span class="am-featured__price-old">₹{{ number_format($product->compare_price, 0) }}</span>
                    @endif
                    @if($discount)
                    <span class="am-featured__badge">-{{ $discount }}%</span>
                    @elseif(!empty($design['badge']))
                    <span class="am-featured__badge">{{ $design['badge'] }}</span>
                    @endif
                </div>

                <ul class="am-pdp__trust">
                    <li>✓ PVD stainless frame fabrication</li>
                    <li>✓ Secure crated packaging</li>
                    <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
                </ul>

                @if(count($highlights))
                <ul class="am-mirror-frames-highlights">
                    @foreach($highlights as $item)
                    <li>{{ $item }}</li>
                    @endforeach
                </ul>
                @endif

                @include('partials.am-pdp-finish-swatches')

                @if(!empty($design['description']) || $product->description)
                <div class="am-prose am-pdp__desc">{{ $design['description'] ?? $product->description }}</div>
                @endif

                <div class="am-pdp__buy-inline" id="buy">
                    @include('partials.am-pdp-buy-actions', ['product' => $product])
                    @include('partials.am-pdp-checkout-trust')
                </div>
            </div>
        </div>

        @include('partials.am-product-tabs', [
            'title' => $design['name'] ?? $product->name,
            'descriptionHtml' => $product->description ? '<div>' . $product->description . '</div>' : '',
            'careItems' => Service::careGuidelinesForCategory($product->category?->slug),
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
