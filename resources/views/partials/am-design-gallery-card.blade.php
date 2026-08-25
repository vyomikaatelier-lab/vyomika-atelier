@props([
    'showUrl',
    'title',
    'image' => null,
    'imageAlt' => null,
    'badge' => null,
    'product' => null,
    'serviceSlug' => '',
    'finish' => '',
    'price' => null,
    'useCheckout' => false,
])

@php
    use App\Support\CartGuard;
    use App\Support\StorefrontPrice;

    $canBuyNow = $product instanceof \App\Models\Product
        ? CartGuard::canDisplayBuyNow($product)
        : (bool) $useCheckout;

    $priceLabel = $product instanceof \App\Models\Product
        ? StorefrontPrice::listingLabel($product)
        : StorefrontPrice::formatInr($price);

    if ($priceLabel === null && $product instanceof \App\Models\Product && $product->usesCheckoutFlow()) {
        $priceLabel = 'Price on request';
    }

    $compareLabel = $product instanceof \App\Models\Product
        ? StorefrontPrice::compareLabel($product)
        : null;
@endphp

<article class="am-design-gallery__card am-design-gallery__card--split am-collection-card">
    <div class="am-design-gallery__media-wrap">
        @include('partials.am-gallery-media', [
            'image' => $image,
            'alt' => $imageAlt ?? $title,
            'href' => $showUrl,
        ])
        @if($badge)
        <span class="am-mirror-frames-card__badge">{{ $badge }}</span>
        @endif
    </div>
    <div class="am-design-gallery__body">
        <h3 class="am-design-gallery__name">
            <a href="{{ $showUrl }}">{{ $title }}</a>
        </h3>
        @if($product instanceof \App\Models\Product)
            @include('partials.am-product-rating', ['product' => $product])
        @endif
        @if($priceLabel)
        <p class="am-design-gallery__price">
            <span class="am-design-gallery__price-current">{{ $priceLabel }}</span>
            @if($compareLabel)
            <span class="am-design-gallery__price-old">{{ $compareLabel }}</span>
            @endif
        </p>
        @endif
        <div class="am-design-gallery__actions">
            <a href="{{ $showUrl }}" class="am-btn am-btn--card-view">View Details</a>
            @if($canBuyNow)
            @include('partials.am-gallery-buy-now-btn', ['product' => $product])
            @elseif($product instanceof \App\Models\Product && ! $product->usesCheckoutFlow())
            @include('partials.am-gallery-order-now-btn', [
                'name' => $title,
                'slug' => $product->slug,
                'serviceSlug' => $serviceSlug ?: \App\Models\Service::serviceSlugForProduct($product->slug, $product->category?->slug),
                'category' => '',
                'finish' => $finish,
                'price' => $price ?? $product->price,
                'label' => 'Request Quote',
            ])
            @endif
        </div>
    </div>
</article>
