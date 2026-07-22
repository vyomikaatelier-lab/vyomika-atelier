@extends('layouts.store')

@php
    $hero = $page['hero'] ?? [];
    $pageCategoryLabel = $pageCategoryLabel ?? (
        \App\Support\StorefrontRoutes::isShopCategory($slug)
            ? \App\Support\StorefrontRoutes::shopCategoryLabel($slug)
            : $category->name
    );
@endphp

@section('title', $page['meta_title'] ?? ($category->name.' — Vyomika Atelier'))

@push('meta')
<meta name="description" content="{{ $page['meta_description'] ?? '' }}">
<link rel="canonical" href="{{ route('shop.show', $slug) }}">
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

@include('partials.am-collection-gallery-grid', [
    'products' => $products,
    'galleryTitle' => $page['gallery_title'] ?? $pageCategoryLabel,
    'parentCategoryName' => $pageCategoryLabel,
    'shopPageSlug' => $slug,
])

@endsection
