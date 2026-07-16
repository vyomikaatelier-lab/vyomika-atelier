@props([
    'products',
    'heading' => 'Design Gallery',
    'ctaLabel' => 'Buy Now',
    'serviceSlug' => '',
])

@if($products->isNotEmpty())
<section class="am-design-gallery am-design-gallery--service">
    <p class="am-card__label">Design Gallery</p>
    <h2 class="am-design-gallery__title">{{ $heading }}</h2>
    <div class="am-design-gallery__grid am-design-gallery__grid--dense">
        @foreach($products as $product)
        @php
            $productUrl = \App\Support\StorefrontUrl::to('shop.show', ['slug' => $product->slug], '/shop/'.$product->slug);
        @endphp
        <article class="am-design-gallery__card">
            <a href="{{ $productUrl }}" class="am-design-gallery__media">
                @if($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
                @endif
            </a>
            <div class="am-design-gallery__body">
                <h3 class="am-design-gallery__name">
                    <a href="{{ $productUrl }}">{{ $product->name }}</a>
                </h3>
                @if($product->category)
                <p class="am-design-gallery__cat">{{ $product->category->name }}</p>
                @endif
                <div class="am-design-gallery__actions">
                    <a href="{{ $productUrl }}" class="am-btn am-btn--card-view">View</a>
                    @if($product->usesCheckoutFlow())
                    <form action="{{ route('cart.add', $product) }}" method="POST" class="am-design-gallery__buy-form">
                        @csrf
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="buy_now" value="1">
                        <button type="submit" class="am-btn am-btn--card-primary">Buy Now</button>
                    </form>
                    @elseif($ctaLabel === 'Request Quote')
                    <a href="{{ route('leads.create') }}" class="am-btn am-btn--card-primary">Request Quote</a>
                    @else
                    <button type="button"
                        class="am-btn am-btn--card-primary"
                        data-open-order-popup
                        data-product-name="{{ $product->name }}"
                        data-product-slug="{{ $product->slug }}"
                        data-service-slug="{{ $serviceSlug ?: \App\Models\Service::serviceSlugForProduct($product->slug, $product->category?->slug) }}">
                        Order Now
                    </button>
                    @endif
                </div>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif
