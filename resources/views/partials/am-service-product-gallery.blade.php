@props([
    'products',
    'heading' => 'Design Gallery',
    'ctaLabel' => 'Order Now',
    'serviceSlug' => '',
    'categoryLabel' => '',
])

@if($products->isNotEmpty())
<section class="am-design-gallery am-design-gallery--service" id="studio-gallery">
    <p class="am-card__label">Design Gallery</p>
    <h2 class="am-design-gallery__title">{{ $heading }}</h2>
    <div class="am-design-gallery__grid am-design-gallery__grid--studio">
        @foreach($products as $product)
        @php
            $productUrl = \App\Support\StorefrontRoutes::productUrl($product);
            $resolvedService = $serviceSlug ?: (\App\Models\Service::serviceSlugForProduct($product->slug, $product->category?->slug) ?? '');
            $itemLabel = $categoryLabel ?: \App\Support\StorefrontRoutes::productSectionLabel($product);
        @endphp
        <article class="am-design-gallery__card am-design-gallery__card--split">
            @include('partials.am-gallery-media', [
                'image' => $product->imageUrl(),
                'alt' => $product->name,
                'href' => $productUrl,
            ])
            <div class="am-design-gallery__body">
                <h3 class="am-design-gallery__name">
                    <a href="{{ $productUrl }}">{{ $product->name }}</a>
                </h3>
                @if($itemLabel)
                <p class="am-design-gallery__cat">{{ $itemLabel }}</p>
                @endif
                @if($product->description)
                <p class="am-design-gallery__desc">{{ $product->description }}</p>
                @endif
                <div class="am-design-gallery__actions">
                    <a href="{{ $productUrl }}" class="am-btn am-btn--card-view">View</a>
                    @if($ctaLabel === 'Request Quote')
                    <a href="{{ route('leads.create') }}" class="am-btn am-btn--card-primary">Request Quote</a>
                    @else
                    @include('partials.am-gallery-order-now-btn', [
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'serviceSlug' => $resolvedService,
                        'category' => $itemLabel,
                        'price' => $product->price,
                    ])
                    @endif
                </div>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif
