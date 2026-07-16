@props([
    'products',
    'sectionTitle' => 'Design Gallery',
    'galleryTitle' => 'Designs',
])

@if($products->isNotEmpty())
<section class="am-section am-section--cream am-collection-designs" id="collection-gallery">
    <div class="am-container">
        <div class="am-mirror-frames-section-head">
            <p class="am-card__label">{{ $sectionTitle }}</p>
            <h2 class="am-corten-section__title">{{ $galleryTitle }}</h2>
        </div>
        <div class="am-design-gallery__grid am-design-gallery__grid--dense">
            @foreach($products as $product)
            @php
                $showUrl = \App\Support\StorefrontUrl::to('shop.show', ['slug' => $product->slug], '/shop/'.$product->slug);
            @endphp
            <article class="am-design-gallery__card am-collection-card">
                <a href="{{ $showUrl }}" class="am-design-gallery__media">
                    @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
                    @endif
                </a>
                <div class="am-design-gallery__body">
                    <h3 class="am-design-gallery__name">
                        <a href="{{ $showUrl }}">{{ $product->name }}</a>
                    </h3>
                    @if($product->category)
                    <p class="am-design-gallery__cat">{{ $product->category->name }}</p>
                    @endif
                    @if($product->description)
                    <p class="am-design-gallery__desc">{{ $product->description }}</p>
                    @endif
                    <div class="am-design-gallery__actions">
                        <a href="{{ $showUrl }}" class="am-btn am-btn--card-view">View</a>
                        <form action="{{ route('cart.add', $product) }}" method="POST" class="am-design-gallery__buy-form">
                            @csrf
                            <input type="hidden" name="quantity" value="1">
                            <input type="hidden" name="buy_now" value="1">
                            <button type="submit" class="am-btn am-btn--card-primary">Buy Now</button>
                        </form>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
