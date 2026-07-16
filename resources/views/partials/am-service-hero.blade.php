@props(['hero' => []])

<section class="am-mirror-frames-hero am-service-hero" style="--mirror-frames-hero-img: url('{{ $hero['image'] ?? '' }}')">
    <div class="am-container am-mirror-frames-hero__inner">
        <p class="am-page-hero__label">{{ $hero['label'] ?? 'Studio' }}</p>
        <h1 class="am-mirror-frames-hero__title">{{ $hero['title'] ?? '' }}</h1>
        <p class="am-mirror-frames-hero__subtitle">{{ $hero['subtitle'] ?? '' }}</p>
        @if(!empty($hero['highlights']))
        <ul class="am-pro-hero__highlights">
            @foreach($hero['highlights'] as $item)
            <li>{{ $item }}</li>
            @endforeach
        </ul>
        @endif
        <div class="am-pro-hero__actions">
            @if(!empty($hero['cta_primary']['href']))
            <a href="{{ $hero['cta_primary']['href'] }}" class="am-btn am-btn--primary">{{ $hero['cta_primary']['label'] }}</a>
            @endif
            @if(!empty($hero['cta_secondary']['href']))
            <a href="{{ $hero['cta_secondary']['href'] }}" class="am-btn am-btn--outline am-btn--light">{{ $hero['cta_secondary']['label'] }}</a>
            @endif
        </div>
    </div>
</section>
