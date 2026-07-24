@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
    $designs = $page['designs'] ?? [];
@endphp

@section('title', $page['meta_title'] ?? 'Mirror Frames — Vyomika Atelier')

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
<link rel="canonical" href="{{ route('shop.mirror-frames.index') }}">
@endpush

@section('content')

@include('partials.am-service-hero', ['hero' => $hero, 'class' => 'am-service-hero--compact-mobile'])

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
                $showUrl = route('shop.mirror-frames.show', $design['slug']);
            @endphp
            @include('partials.am-design-gallery-card', [
                'showUrl' => $showUrl,
                'title' => $design['name'],
                'description' => $design['description'] ?? null,
                'image' => $design['image'] ?? null,
                'badge' => $design['badge'] ?? null,
                'product' => $design['product'] ?? null,
                'useCheckout' => ($design['product'] ?? null)?->usesCheckoutFlow() ?? false,
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
