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

@include('partials.am-service-hero', ['hero' => $hero])

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
