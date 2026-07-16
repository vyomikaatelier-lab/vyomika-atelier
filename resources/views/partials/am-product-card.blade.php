@php
    use App\Support\StorefrontUrl;

    $isModel = $product instanceof \App\Models\Product;
    $name = $isModel ? $product->name : ($product['name'] ?? '');
    $category = $isModel ? ($product->category?->name ?? 'Product') : ($product['category'] ?? 'Product');
    $price = $isModel ? $product->price : ($product['price'] ?? 0);
    $comparePrice = $isModel ? $product->compare_price : ($product['compare_price'] ?? null);
    $badge = $isModel ? null : ($product['badge'] ?? null);
    if ($isModel && ! $badge && $comparePrice && $comparePrice > $price) {
        $badge = '-' . round((1 - $price / $comparePrice) * 100) . '%';
    }
    $image = $isModel ? ($product->imageUrl() ?: $product->image) : ($product['image'] ?? '');
    $slug = $isModel ? $product->slug : ($product['slug'] ?? '');
    $url = $slug
        ? StorefrontUrl::to('shop.show', ['slug' => $slug], '/shop/'.$slug)
        : StorefrontUrl::to('shop.index', [], '/shop');
@endphp
<article class="am-product-card" data-product-url="{{ $url }}">
    <a href="{{ $url }}" class="am-product-card__thumb">
        @if($badge)
        <span class="am-product-card__badge {{ $badge === 'NEW' ? 'am-product-card__badge--new' : '' }}">{{ $badge }}</span>
        @endif
        @if($image)
        <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
        @endif
        <div class="am-product-card__actions">
            <button type="button" class="am-btn am-btn--primary am-btn--sm am-btn--full" data-order-now data-product-url="{{ $url }}">Order Now</button>
        </div>
    </a>
    <div class="am-product-card__body">
        <p class="am-product-card__cat">{{ $category }}</p>
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
