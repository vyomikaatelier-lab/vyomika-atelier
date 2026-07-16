@props([
    'service',
    'heading' => 'Choose a Design',
])

@if($service->has_designs && $service->designs->isNotEmpty())
<section class="am-design-gallery">
    <p class="am-card__label">Design Gallery</p>
    <h2 class="am-design-gallery__title">
        {{ $service->slug === 'rack-systems-metal-pvd' ? 'Available Designs' : $heading }}
    </h2>
    <div class="am-design-gallery__grid">
        @foreach($service->designs as $design)
            @php $href = $design->galleryHref($service); @endphp
            @if($href)
            <a href="{{ $href }}" class="am-design-gallery__card">
            @else
            <article class="am-design-gallery__card am-design-gallery__card--static">
            @endif
                @if($design->image)
                <div class="am-design-gallery__media">
                    <img src="{{ $design->image }}" alt="{{ $design->name }}" loading="lazy">
                </div>
                @endif
                <div class="am-design-gallery__body">
                    <h3 class="am-design-gallery__name">{{ $design->name }}</h3>
                    <p class="am-design-gallery__desc">{{ $design->description }}</p>
                    @if($href)
                    <span class="am-design-gallery__cta">{{ $design->galleryCtaLabel($service) }}</span>
                    @endif
                </div>
            @if($href)
            </a>
            @else
            </article>
            @endif
        @endforeach
    </div>
</section>
@endif
