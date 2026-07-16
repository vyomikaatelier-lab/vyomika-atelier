@props([
    'products',
    'heading' => 'Design Gallery',
    'ctaLabel' => 'Order Now',
])

@if($products->isNotEmpty())
<section class="am-design-gallery am-design-gallery--service">
    <p class="am-card__label">Design Gallery</p>
    <h2 class="am-design-gallery__title">{{ $heading }}</h2>
    <p class="am-design-gallery__count">{{ $products->count() }} designs · click any to order</p>
    <div class="am-design-gallery__grid am-design-gallery__grid--dense">
        @foreach($products as $product)
        <a href="{{ route('shop.show', $product->slug) }}" class="am-design-gallery__card">
            @if($product->imageUrl())
            <div class="am-design-gallery__media">
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
            </div>
            @endif
            <div class="am-design-gallery__body">
                <h3 class="am-design-gallery__name">{{ $product->name }}</h3>
                @if($product->category)
                <p class="am-design-gallery__cat">{{ $product->category->name }}</p>
                @endif
                <span class="am-design-gallery__cta">{{ $ctaLabel }}</span>
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif
