@props([
    'products',
    'sectionTitle' => 'Design Gallery',
    'galleryTitle' => 'Designs',
    'parentCategoryName' => null,
    'shopPageSlug' => null,
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
                $showUrl = \App\Support\StorefrontRoutes::productUrl(
                    $product,
                    \App\Support\StorefrontRoutes::isShopCategory((string) $shopPageSlug) ? $shopPageSlug : null
                );
                $cardCategoryLabel = $parentCategoryName
                    ?: (\App\Support\StorefrontRoutes::isShopCategory((string) $shopPageSlug)
                        ? \App\Support\StorefrontRoutes::shopCategoryLabel($shopPageSlug)
                        : null);
            @endphp
            @include('partials.am-design-gallery-card', [
                'showUrl' => $showUrl,
                'title' => $product->name,
                'description' => $product->description,
                'image' => $product->imageUrl(),
                'product' => $product,
                'categoryName' => $cardCategoryLabel,
                'useCheckout' => $product->usesCheckoutFlow(),
            ])
            @endforeach
        </div>
    </div>
</section>
@endif
