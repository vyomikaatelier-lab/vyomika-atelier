@props(['hero' => [], 'fallbackDesktop' => null])

@php
    use App\Support\ResponsiveHero;

    $urls = ResponsiveHero::urls($hero ?? [], $fallbackDesktop ?? null);
    $hasImage = filled($urls['desktop']) || filled($urls['tablet']) || filled($urls['mobile']);
    $imagePosition = $hero['image_position'] ?? 'right';
    $heroLayout = $hero['hero_layout'] ?? 'default';
    $isCompact = $heroLayout === 'compact';
    $hasStructuredTitle = filled($hero['title_line1'] ?? null) || filled($hero['title_accent'] ?? null);
    $eyebrow = $hero['eyebrow'] ?? $hero['label'] ?? null;
@endphp

<section @class([
    'am-shop-category-hero',
    'am-shop-category-hero--image-' . $imagePosition,
    'am-shop-category-hero--compact' => $isCompact,
])>
    <div class="am-shop-category-hero__grid">
        @if($imagePosition === 'left')
        <div class="am-shop-category-hero__media">
            @if($hasImage)
                @include('partials.am-hero-picture', ['slide' => $hero, 'priority' => true])
            @endif
        </div>
        @endif

        <div class="am-shop-category-hero__content">
            <div class="am-shop-category-hero__brand" aria-hidden="true">
                <span class="am-shop-category-hero__brand-name">VYOMIKA</span>
                <span class="am-shop-category-hero__brand-tag">ATELIER</span>
            </div>

            @if($eyebrow)
            <p class="am-shop-category-hero__eyebrow">{{ $eyebrow }}</p>
            @endif

            @if($hasStructuredTitle)
            <h1 class="am-shop-category-hero__title am-shop-category-hero__title--structured">
                @if(!empty($hero['title_line1']))
                <span class="am-shop-category-hero__title-line">{{ $hero['title_line1'] }}</span>
                @endif
                @if(!empty($hero['title_accent']))
                <span class="am-shop-category-hero__title-accent">{{ $hero['title_accent'] }}</span>
                @endif
                @if(!empty($hero['title_line2']))
                <span class="am-shop-category-hero__title-sub">{{ $hero['title_line2'] }}</span>
                @endif
            </h1>
            @else
            <h1 class="am-shop-category-hero__title">{{ $hero['title'] ?? '' }}</h1>
            @endif

            @if(!empty($hero['tagline']) || !empty($hero['tagline_accent']))
            <div class="am-shop-category-hero__divider" aria-hidden="true">
                <span class="am-shop-category-hero__divider-line"></span>
                <span class="am-shop-category-hero__divider-diamond">&#9670;</span>
                <span class="am-shop-category-hero__divider-line"></span>
            </div>
            <p class="am-shop-category-hero__tagline">
                @if(!empty($hero['tagline']))
                {{ $hero['tagline'] }}
                @endif
                @if(!empty($hero['tagline_accent']))
                <span class="am-shop-category-hero__tagline-accent">{{ $hero['tagline_accent'] }}</span>
                @endif
            </p>
            @endif

            @if(!empty($hero['subtitle']))
            <p class="am-shop-category-hero__subtitle">{{ $hero['subtitle'] }}</p>
            @endif

            @if(!empty($hero['highlights']))
            <ul class="am-pro-hero__highlights am-pro-hero__highlights--dark am-shop-category-hero__highlights">
                @foreach($hero['highlights'] as $item)
                <li>{{ $item }}</li>
                @endforeach
            </ul>
            @endif

            @if(!empty($hero['cta_primary']['href']) || !empty($hero['cta_secondary']['href']))
            <div class="am-pro-hero__actions">
                @if(!empty($hero['cta_primary']['href']))
                <a href="{{ $hero['cta_primary']['href'] }}" class="am-btn am-btn--primary">{{ $hero['cta_primary']['label'] }}</a>
                @endif
                @if(!empty($hero['cta_secondary']['href']))
                <a href="{{ $hero['cta_secondary']['href'] }}" class="am-btn am-btn--outline">{{ $hero['cta_secondary']['label'] }}</a>
                @endif
            </div>
            @endif

            @if(!empty($hero['footer_tagline']) || !empty($hero['footer_tagline_accent']))
            <p class="am-shop-category-hero__footer-tagline">
                @if(!empty($hero['footer_tagline']))
                {{ $hero['footer_tagline'] }}
                @endif
                @if(!empty($hero['footer_tagline_accent']))
                <span class="am-shop-category-hero__tagline-accent">{{ $hero['footer_tagline_accent'] }}</span>
                @endif
            </p>
            @endif
        </div>

        @if($imagePosition !== 'left')
        <div class="am-shop-category-hero__media">
            @if($hasImage)
                @include('partials.am-hero-picture', ['slide' => $hero, 'priority' => true])
            @endif
        </div>
        @endif
    </div>
</section>
