@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
    $story = $page['brand_story'] ?? [];
    $exhibitions = $page['exhibitions'] ?? [];
    $values = $page['values'] ?? [];
    $cta = $page['cta'] ?? [];
@endphp

@section('title', $pageSeo['title'] ?? 'About Vyomika Atelier')

@section('content')

{{-- Hero --}}
<section class="am-about-hero am-hero-responsive" @include('partials.am-responsive-hero-style', ['hero' => $hero])>
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
            @php
                $coverImage = $event['cover_image'] ?? null;
                $galleryImages = array_slice($event['gallery'] ?? [], 0, 4);
                $hasMedia = $coverImage || !empty($galleryImages);
                $lightboxCaption = $event['name'].' — '.$event['location'].', '.$event['year'];
                $photoIndex = 0;
            @endphp
            <article class="am-about-timeline__event{{ $hasMedia ? ' am-about-timeline__event--has-media' : '' }} am-reveal" id="exhibition-{{ $event['slug'] }}">
                <div class="am-about-timeline__copy">
                    <span class="am-about-timeline__year">{{ $event['year'] }}</span>
                    <h3 class="am-about-timeline__name">{{ $event['name'] }}</h3>
                    <p class="am-about-timeline__location">{{ $event['location'] }}</p>
                    @if(!empty($event['summary']))
                    <p class="am-about-timeline__summary">{{ $event['summary'] }}</p>
                    @endif
                </div>
                @if($hasMedia)
                <div class="am-about-timeline__visual">
                    <div class="am-about-exhibition-media{{ $coverImage && !empty($galleryImages) ? ' am-about-exhibition-media--split' : '' }}" data-about-gallery>
                        @if($coverImage)
                        @php $photoIndex++; @endphp
                        <button type="button"
                            class="am-about-gallery__featured"
                            data-about-lightbox
                            data-src="{{ $coverImage }}"
                            data-caption="{{ $lightboxCaption }}"
                            aria-label="View {{ $event['name'] }} cover photo">
                            <img src="{{ $coverImage }}" alt="{{ $event['name'] }} — cover" loading="lazy">
                        </button>
                        @endif
                        @if(!empty($galleryImages))
                        <div class="am-about-gallery{{ $coverImage ? ' am-about-gallery--with-featured' : '' }}">
                            @foreach($galleryImages as $img)
                            @php $photoIndex++; @endphp
                            <button type="button"
                                class="am-about-gallery__item"
                                data-about-lightbox
                                data-src="{{ $img }}"
                                data-caption="{{ $lightboxCaption }}"
                                aria-label="View {{ $event['name'] }} photo {{ $photoIndex }}">
                                <img src="{{ $img }}" alt="{{ $event['name'] }} — photo {{ $photoIndex }}" loading="lazy">
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endif
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
