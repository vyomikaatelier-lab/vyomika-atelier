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
    'finish' => '',
    'price' => null,
])

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
        @if($categoryName)
        <p class="am-design-gallery__cat">{{ $categoryName }}</p>
        @endif
        @if($description)
        <p class="am-design-gallery__desc">{{ $description }}</p>
        @endif
        <div class="am-design-gallery__actions">
            <a href="{{ $showUrl }}" class="am-btn am-btn--card-view">View</a>
            @if($product)
            @include('partials.am-gallery-order-now-btn', [
                'name' => $title,
                'slug' => $product->slug,
                'serviceSlug' => $serviceSlug ?: \App\Models\Service::serviceSlugForProduct($product->slug, $product->category?->slug),
                'category' => $categoryName ?? $product->category?->name ?? '',
                'finish' => $finish,
                'price' => $price ?? $product->price,
            ])
            @else
            @include('partials.am-gallery-order-now-btn', [
                'name' => $title,
                'slug' => '',
                'serviceSlug' => $serviceSlug,
                'category' => $categoryName ?? '',
                'finish' => $finish,
                'price' => $price,
            ])
            @endif
        </div>
    </div>
</article>
