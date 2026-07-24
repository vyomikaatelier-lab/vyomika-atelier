@php

    use App\Support\ProductCatalog;

    use App\Support\StorefrontRoutes;

    use App\Support\StorefrontUrl;



    $isModel = $product instanceof \App\Models\Product;
    $isObject = is_object($product) && ! $isModel;
    $name = $isModel ? $product->name : ($isObject ? ($product->name ?? '') : ($product['name'] ?? ''));
    $slug = $isModel ? $product->slug : ($isObject ? ($product->slug ?? '') : ($product['slug'] ?? ''));
    $categorySlug = $isModel ? $product->category?->slug : ($isObject ? ($product->category_slug ?? null) : ($product['category_slug'] ?? null));
    $sectionLabel = $isModel
        ? StorefrontRoutes::productSectionLabel($product)
        : ($isObject ? ($product->section_label ?? $product->shop_category ?? '') : ($product['section_label'] ?? $product['shop_category'] ?? ''));
    $price = $isModel ? $product->price : ($isObject ? ($product->price ?? 0) : ($product['price'] ?? 0));
    $comparePrice = $isModel ? $product->compare_price : ($isObject ? ($product->compare_price ?? null) : ($product['compare_price'] ?? null));
    $badge = $isModel ? null : ($isObject ? ($product->badge ?? null) : ($product['badge'] ?? null));

    if ($isModel && ! $badge && $comparePrice && $comparePrice > $price) {

        $badge = '-' . round((1 - $price / $comparePrice) * 100) . '%';

    }

    $image = $isModel
        ? ($product->imageUrl() ?: $product->image)
        : ($isObject ? ($product->image ?? '') : ($product['image'] ?? ''));

    $url = $slug
        ? ($isModel ? StorefrontRoutes::productUrl($product) : StorefrontUrl::to('shop.show', ['slug' => $slug], '/shop/'.$slug))
        : StorefrontUrl::to('shop.index', [], '/shop');

    $orderServiceSlug = $isModel
        ? (\App\Models\Service::serviceSlugForProduct($slug, $categorySlug) ?? '')
        : ($isObject ? ($product->service_slug ?? '') : ($product['service_slug'] ?? ''));

    $useCheckout = $isModel
        ? $product->usesCheckoutFlow()
        : (($isObject ? ($product->section ?? 'shop') : ($product['section'] ?? 'shop')) === 'shop');
@endphp

<article class="am-product-card" data-product-url="{{ $url }}">

    <div class="am-product-card__thumb">
        <a href="{{ $url }}" class="am-product-card__thumb-link">
            @if($badge)
            <span class="am-product-card__badge {{ $badge === 'NEW' ? 'am-product-card__badge--new' : '' }}">{{ $badge }}</span>
            @endif
            @if($image)
            <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
            @endif
        </a>
        <div class="am-product-card__actions">
            @if($isModel && $useCheckout)
            <form action="{{ route('cart.add', $product) }}" method="POST" class="am-product-card__buy-form">
                @csrf
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="buy_now" value="1">
                <button type="submit" class="am-btn am-btn--primary am-btn--sm am-btn--full">Buy Now</button>
            </form>
            @elseif($isModel)
            <button type="button"
                class="am-btn am-btn--primary am-btn--sm am-btn--full"
                data-open-order-popup
                data-product-name="{{ $name }}"
                data-product-slug="{{ $slug }}"
                data-service-slug="{{ $orderServiceSlug }}">
                Order Now
            </button>
            @else
            <button type="button" class="am-btn am-btn--primary am-btn--sm am-btn--full" data-order-now data-product-url="{{ $url }}">Order Now</button>
            @endif
        </div>
    </div>

    <div class="am-product-card__body">

        @if($sectionLabel)

        <p class="am-product-card__cat">{{ $sectionLabel }}</p>

        @endif

        <h3 class="am-product-card__name"><a href="{{ $url }}">{{ $name }}</a></h3>

        <div class="am-product-card__stars" aria-hidden="true">★★★★★</div>

        <div class="am-product-card__price">

            <span class="am-product-card__price-current">{{ \App\Support\SiteContent::formatPrice($price) }}</span>

            @if($comparePrice)

            <span class="am-product-card__price-old">{{ \App\Support\SiteContent::formatPrice($comparePrice) }}</span>

            @endif

        </div>

    </div>

</article>

