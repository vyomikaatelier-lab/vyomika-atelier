@props([
    'items' => [],
    'heading' => 'Design Gallery',
    'sectionLabel' => 'Design Gallery',
    'serviceSlug' => '',
    'categoryLabel' => '',
])

@if(!empty($items))
<section class="am-design-gallery am-design-gallery--service" id="studio-gallery">
    <p class="am-card__label">{{ $sectionLabel }}</p>
    <h2 class="am-design-gallery__title">{{ $heading }}</h2>
    <div class="am-design-gallery__grid am-design-gallery__grid--studio">
        @foreach($items as $item)
        @php
            $title = $item['title'] ?? $item['name'] ?? '';
            $slug = $item['slug'] ?? \Illuminate\Support\Str::slug($title);
            $description = $item['text'] ?? $item['description'] ?? null;
            $image = $item['image'] ?? null;
            $category = $item['category'] ?? $categoryLabel;
        @endphp
        <article class="am-design-gallery__card am-design-gallery__card--split">
            @include('partials.am-gallery-media', [
                'image' => $image,
                'alt' => $title,
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
                    @include('partials.am-gallery-order-now-btn', [
                        'name' => $title,
                        'slug' => $slug,
                        'serviceSlug' => $serviceSlug,
                        'category' => $category,
                        'price' => $item['price'] ?? null,
                    ])
                </div>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif
