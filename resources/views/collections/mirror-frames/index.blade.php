@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
    $designs = $page['designs'] ?? [];
@endphp

@section('title', $page['meta_title'] ?? 'Mirror Frames — Vyomika Atelier LLP')

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
<link rel="canonical" href="{{ route('collections.mirror-frames.index') }}">
@endpush

@section('content')

<section class="am-mirror-frames-hero" style="--mirror-frames-hero-img: url('{{ $hero['image'] ?? '' }}')">
    <div class="am-container am-mirror-frames-hero__inner">
        <p class="am-page-hero__label">{{ $hero['label'] ?? 'Collections' }}</p>
        <h1 class="am-mirror-frames-hero__title">{{ $hero['title'] ?? 'Mirror Frames' }}</h1>
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

@if(!empty($page['intro']['body']))
<section class="am-section am-section--white">
    <div class="am-container am-mirror-frames-intro">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['intro']['title'] ?? '' }}</h2>
        <p class="am-corten-section__lead am-corten-section__lead--center">{{ $page['intro']['body'] }}</p>
    </div>
</section>
@endif

@if(!empty($designs))
<section class="am-section am-section--cream am-mirror-frames-designs" id="mirror-designs">
    <div class="am-container">
        <div class="am-mirror-frames-section-head">
            <p class="am-card__label">Design Gallery</p>
            <h2 class="am-corten-section__title">Mirror Frame Designs</h2>
        </div>
        <div class="am-design-gallery__grid am-design-gallery__grid--dense am-mirror-frames-grid">
            @foreach($designs as $design)
            @php
                $showUrl = route('collections.mirror-frames.show', $design['slug']);
            @endphp
            @include('partials.am-design-gallery-card', [
                'showUrl' => $showUrl,
                'title' => $design['name'],
                'description' => $design['description'] ?? null,
                'image' => $design['image'] ?? null,
                'badge' => $design['badge'] ?? null,
                'product' => $design['product'] ?? null,
            ])
            @endforeach
        </div>
    </div>
</section>
@endif

@if(!empty($page['finishes']['items']))
<section class="am-section am-section--white">
    <div class="am-container">
        <div class="am-mirror-frames-section-head am-mirror-frames-section-head--center">
            <h2 class="am-corten-section__title">{{ $page['finishes']['title'] ?? 'PVD Frame Finishes' }}</h2>
            @if(!empty($page['finishes']['subtitle']))
            <p class="am-corten-section__lead">{{ $page['finishes']['subtitle'] }}</p>
            @endif
        </div>
        <div class="am-mirror-frames-finishes">
            @foreach($page['finishes']['items'] as $finish)
            <article class="am-mirror-frames-finish">
                @if(!empty($finish['image']))
                <img src="{{ asset($finish['image']) }}" alt="{{ $finish['name'] }}" loading="lazy">
                @endif
                <p>{{ $finish['name'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
