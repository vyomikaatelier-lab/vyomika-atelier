@props([
    'showUrl',
    'title',
    'description' => null,
    'image' => null,
    'imageAlt' => null,
    'badge' => null,
    'product' => null,
    'categoryName' => null,
    'serviceSlug' => '',
])

<article class="am-design-gallery__card am-design-gallery__card--split am-collection-card">
    <a href="{{ $showUrl }}" class="am-design-gallery__media">
        @if($image)
        <img src="{{ $image }}" alt="{{ $imageAlt ?? $title }}" loading="lazy">
        @if($badge)
        <span class="am-mirror-frames-card__badge">{{ $badge }}</span>
        @endif
        @endif
    </a>
    <div class="am-design-gallery__body">
        <h3 class="am-design-gallery__name">
            <a href="{{ $showUrl }}">{{ $title }}</a>
        </h3>
        @if($categoryName)
        <p class="am-design-gallery__cat">{{ $categoryName }}</p>
        @endif
        @if($description)
        <p class="am-design-gallery__desc">{{ $description }}</p>
        @endif
        <div class="am-design-gallery__actions">
            <a href="{{ $showUrl }}" class="am-btn am-btn--card-view">View</a>
            @if($product)
            <button type="button"
                class="am-btn am-btn--card-primary"
                data-open-order-popup
                data-product-name="{{ $title }}"
                data-product-slug="{{ $product->slug }}"
                data-service-slug="{{ $serviceSlug ?: \App\Models\Service::serviceSlugForProduct($product->slug, $product->category?->slug) }}">
                Order Now
            </button>
            @else
            <a href="{{ $showUrl }}" class="am-btn am-btn--card-primary">Order Now</a>
            @endif
        </div>
    </div>
</article>
