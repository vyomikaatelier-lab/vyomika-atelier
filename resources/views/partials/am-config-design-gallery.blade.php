@php
    $items = $items ?? [];
    $heading = $heading ?? 'Design Gallery';
    $sectionLabel = $sectionLabel ?? 'Design Gallery';
    $serviceSlug = $serviceSlug ?? '';
    $categoryLabel = $categoryLabel ?? '';
    $quoteAnchor = $quoteAnchor ?? null;
    $quoteLabel = $quoteLabel ?? 'Order Now';
    $darkSection = $darkSection ?? false;
    $gallerySectionClass = 'am-design-gallery am-design-gallery--service'.($darkSection ? ' am-design-gallery--on-dark' : '');
@endphp

@if(!empty($items))
<section class="{{ $gallerySectionClass }}" id="studio-gallery">
    <p class="am-card__label">{{ $sectionLabel }}</p>
    <h2 class="am-design-gallery__title">{{ $heading }}</h2>
    <div class="am-design-gallery__grid am-design-gallery__grid--studio">
        @foreach($items as $item)
        @php
            $title = $item['title'] ?? $item['name'] ?? '';
            $slug = $item['slug'] ?? \Illuminate\Support\Str::slug($title);
            $description = $item['text'] ?? $item['description'] ?? null;
            $image = $item['image'] ?? null;
            $imageAlt = $item['image_alt'] ?? $title;
            $category = $item['category'] ?? $categoryLabel;
            $ctaHref = $item['cta_href'] ?? null;
            $ctaLabel = $item['cta_label'] ?? 'Learn more';
        @endphp
        <article class="am-design-gallery__card am-design-gallery__card--split">
            @include('partials.am-gallery-media', [
                'image' => $image,
                'alt' => $imageAlt,
            ])
            <div class="am-design-gallery__body">
                <h3 class="am-design-gallery__name">{{ $title }}</h3>
                @if($category)
                <p class="am-design-gallery__cat">{{ $category }}</p>
                @endif
                @if($description)
                <p class="am-design-gallery__desc">{{ $description }}</p>
                @endif
                <div class="am-design-gallery__actions am-design-gallery__actions--solo">
                    @if(filled($quoteAnchor))
                    @include('partials.am-gallery-order-now-btn', [
                        'href' => $quoteAnchor,
                        'label' => $quoteLabel,
                        'name' => $title,
                        'slug' => $slug,
                        'serviceSlug' => $serviceSlug,
                        'category' => $category,
                        'price' => $item['price'] ?? null,
                    ])
                    @elseif(!empty($ctaHref))
                    <a href="{{ $ctaHref }}" class="am-btn am-btn--card-primary">{{ $ctaLabel }}</a>
                    @else
                    @include('partials.am-gallery-order-now-btn', [
                        'name' => $title,
                        'slug' => $slug,
                        'serviceSlug' => $serviceSlug,
                        'category' => $category,
                        'price' => $item['price'] ?? null,
                    ])
                    @endif
                </div>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif
