@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
@endphp

@section('title', $page['meta_title'] ?? ($category->name.' — Vyomika Atelier LLP'))

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
<link rel="canonical" href="{{ route('collections.gallery.index', $slug) }}">
@endpush

@section('content')

<section class="am-mirror-frames-hero" style="--mirror-frames-hero-img: url('{{ $hero['image'] ?? '' }}')">
    <div class="am-container am-mirror-frames-hero__inner">
        <p class="am-page-hero__label">{{ $hero['label'] ?? 'Collections' }}</p>
        <h1 class="am-mirror-frames-hero__title">{{ $hero['title'] ?? $category->name }}</h1>
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

@include('partials.am-collection-gallery-grid', [
    'products' => $products,
    'galleryTitle' => $page['gallery_title'] ?? $category->name,
])

@endsection
