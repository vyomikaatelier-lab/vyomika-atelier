@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
    $story = $page['brand_story'] ?? [];
    $capabilities = $page['capabilities'] ?? [];
    $exhibitions = $page['exhibitions'] ?? [];
    $values = $page['values'] ?? [];
    $cta = $page['cta'] ?? [];
@endphp

@section('title', $page['meta_title'] ?? 'About Vyomika Atelier LLP')

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
@endpush

@section('content')

{{-- Hero --}}
<section class="am-about-hero" @if(!empty($hero['image'])) style="--about-hero-img: url('{{ asset(ltrim($hero['image'], '/')) }}')" @endif>
    <div class="am-container am-about-hero__inner am-reveal">
        @if(!empty($hero['label']))
        <p class="am-page-hero__label">{{ $hero['label'] }}</p>
        @endif
        <h1 class="am-about-hero__title">{{ $hero['title'] ?? 'About Vyomika Atelier' }}</h1>
        @if(!empty($hero['subtitle']))
        <p class="am-about-hero__subtitle">{{ $hero['subtitle'] }}</p>
        @endif
    </div>
</section>

{{-- Brand Story --}}
@if(!empty($story['paragraphs']))
<section class="am-section am-section--white">
    <div class="am-container am-about-story">
        <div class="am-about-story__copy am-reveal">
            <h2 class="am-corten-section__title">{{ $story['title'] ?? 'Crafted Beyond Convention' }}</h2>
            @foreach($story['paragraphs'] as $paragraph)
            <p class="am-corten-section__lead">{{ $paragraph }}</p>
            @endforeach
        </div>
        @if(!empty($story['image']))
        <div class="am-about-story__media am-reveal am-reveal--delay">
            <img src="{{ $story['image'] }}" alt="Vyomika Atelier studio" loading="lazy">
        </div>
        @endif
    </div>
</section>
@endif

{{-- Capabilities --}}
@if(!empty($capabilities['items']))
<section class="am-section am-section--cream" id="capabilities">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center am-reveal">{{ $capabilities['title'] ?? 'Capabilities' }}</h2>
        <div class="am-about-caps">
            @foreach($capabilities['items'] as $item)
            <article class="am-about-caps__card am-reveal">
                <a href="{{ isset($item['route']) ? route($item['route'], $item['params'] ?? []) : '#' }}" class="am-about-caps__link">
                    <div class="am-about-caps__media">
                        <img src="{{ $item['image'] ?? '' }}" alt="{{ $item['name'] }}" loading="lazy">
                    </div>
                    <div class="am-about-caps__body">
                        <h3>{{ $item['name'] }}</h3>
                        <p>{{ $item['text'] }}</p>
                    </div>
                </a>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Exhibitions --}}
@if(!empty($exhibitions['events']))
<section class="am-section am-section--white" id="exhibitions">
    <div class="am-container">
        <div class="am-about-exhibitions__head am-reveal">
            <h2 class="am-corten-section__title">{{ $exhibitions['title'] ?? 'Our Exhibition Journey' }}</h2>
            @if(!empty($exhibitions['subtitle']))
            <p class="am-corten-section__lead">{{ $exhibitions['subtitle'] }}</p>
            @endif
        </div>
        <div class="am-about-timeline">
            @foreach($exhibitions['events'] as $event)
            <article class="am-about-timeline__event am-reveal" id="exhibition-{{ $event['slug'] }}">
                <div class="am-about-timeline__meta">
                    <span class="am-about-timeline__year">{{ $event['year'] }}</span>
                    <h3 class="am-about-timeline__name">{{ $event['name'] }}</h3>
                    <p class="am-about-timeline__location">{{ $event['location'] }}</p>
                </div>
                <div class="am-about-timeline__content">
                    @if(!empty($event['summary']))
                    <p class="am-about-timeline__summary">{{ $event['summary'] }}</p>
                    @endif
                    @if(!empty($event['images']))
                    <div class="am-about-gallery" data-about-gallery>
                        @foreach($event['images'] as $i => $img)
                        <button type="button"
                            class="am-about-gallery__item"
                            data-about-lightbox
                            data-src="{{ asset(ltrim($img, '/')) }}"
                            data-caption="{{ $event['name'] }} — {{ $event['location'] }}, {{ $event['year'] }}"
                            aria-label="View {{ $event['name'] }} photo {{ $i + 1 }}">
                            <img src="{{ asset(ltrim($img, '/')) }}" alt="{{ $event['name'] }} — photo {{ $i + 1 }}" loading="lazy">
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Values --}}
@if(!empty($values['items']))
<section class="am-section am-section--dark">
    <div class="am-container">
        <h2 class="am-corten-section__title am-corten-section__title--center am-reveal">{{ $values['title'] ?? 'What We Stand For' }}</h2>
        <div class="am-about-values">
            @foreach($values['items'] as $item)
            <article class="am-about-values__card am-reveal">
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['text'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- CTA --}}
@if(!empty($cta['title']))
<section class="am-section am-section--white am-about-cta">
    <div class="am-container am-about-cta__inner am-reveal">
        <div>
            <h2 class="am-corten-section__title">{{ $cta['title'] }}</h2>
            @if(!empty($cta['body']))
            <p class="am-corten-section__lead">{{ $cta['body'] }}</p>
            @endif
        </div>
        <div class="am-about-cta__actions">
            @if(!empty($cta['cta_primary']['route']))
            <a href="{{ route($cta['cta_primary']['route'], $cta['cta_primary']['params'] ?? []) }}" class="am-btn am-btn--primary am-btn--lg">{{ $cta['cta_primary']['label'] }}</a>
            @endif
            @if(!empty($cta['cta_secondary']['route']))
            <button type="button" class="am-btn am-btn--outline" data-open-contact-studio data-contact-context="About page enquiry">{{ $cta['cta_secondary']['label'] }}</button>
            @endif
        </div>
    </div>
</section>
@endif

{{-- Lightbox --}}
<div class="am-about-lightbox" id="am-about-lightbox" aria-hidden="true" role="dialog" aria-label="Exhibition photo">
    <button type="button" class="am-about-lightbox__close" data-about-lightbox-close aria-label="Close">&times;</button>
    <button type="button" class="am-about-lightbox__nav am-about-lightbox__nav--prev" data-about-lightbox-prev aria-label="Previous">&lsaquo;</button>
    <figure class="am-about-lightbox__figure">
        <img src="" alt="" class="am-about-lightbox__img" id="am-about-lightbox-img">
        <figcaption class="am-about-lightbox__caption" id="am-about-lightbox-caption"></figcaption>
    </figure>
    <button type="button" class="am-about-lightbox__nav am-about-lightbox__nav--next" data-about-lightbox-next aria-label="Next">&rsaquo;</button>
</div>

@endsection
