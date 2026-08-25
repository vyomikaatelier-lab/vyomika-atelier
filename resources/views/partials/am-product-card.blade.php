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
    $priceLabel = $isModel
        ? \App\Support\StorefrontPrice::listingLabel($product)
        : \App\Support\StorefrontPrice::formatInr($isObject ? ($product->price ?? null) : ($product['price'] ?? null));
    $compareLabel = $isModel
        ? \App\Support\StorefrontPrice::compareLabel($product)
        : null;
    $comparePrice = $isModel ? $product->compare_price : ($isObject ? ($product->compare_price ?? null) : ($product['compare_price'] ?? null));
    $price = $isModel ? $product->listingPrice() : ($isObject ? ($product->price ?? 0) : ($product['price'] ?? 0));
    $badge = $isModel ? null : ($isObject ? ($product->badge ?? null) : ($product['badge'] ?? null));

    if ($isModel && ! $badge && ! $product->hasSizeOptions() && $comparePrice && $comparePrice > $price) {
        $badge = '-' . round((1 - $price / $comparePrice) * 100) . '%';
    }

    $image = $isModel
        ? ($product->imageUrl() ?: $product->image)
        : ($isObject ? ($product->image ?? '') : ($product['image'] ?? ''));

    $url = $slug
        ? ($isModel ? StorefrontRoutes::productUrl($product) : StorefrontUrl::to('shop.show', ['slug' => $slug], '/shop/'.$slug))
        : \App\Support\StorefrontRoutes::primaryShopUrl();

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
            @if($isModel && filled($product->image))
            @include('partials.am-product-image', [
                'path' => $product->image,
                'alt' => \App\Support\Seo\ProductSeo::imageAlt($product),
                'context' => 'card',
            ])
            @else
            <img src="{{ $image }}" alt="{{ $name }}" width="400" height="500" loading="lazy" decoding="async">
            @endif
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
                Request Quote
            </button>
            @else
            <button type="button" class="am-btn am-btn--primary am-btn--sm am-btn--full" data-order-now data-product-url="{{ $url }}">Request Quote</button>
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

            @if($priceLabel)
            <span class="am-product-card__price-current">{{ $priceLabel }}</span>
            @endif
            @if($compareLabel)
            <span class="am-product-card__price-old">{{ $compareLabel }}</span>
            @endif

        </div>

    </div>

</article>

