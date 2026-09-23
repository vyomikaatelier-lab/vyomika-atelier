@extends('layouts.store')

@php
    use App\Support\Seo\JsonLd;
@endphp

@section('title', $pageSeo['title'] ?? ($category->name.' — Vyomika Atelier'))

@if(!empty($breadcrumbLd))
@push('jsonld')
{!! JsonLd::script($breadcrumbLd) !!}
@endpush
@endif

@section('content')

@include('partials.am-shop-category-hero', ['hero' => $page['hero'] ?? []])

@if(!empty($page['intro']['body']))
<section class="am-section am-section--white">
    <div class="am-container am-mirror-frames-intro">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['intro']['title'] ?? '' }}</h2>
        <p class="am-corten-section__lead am-corten-section__lead--center">{{ $page['intro']['body'] }}</p>
    </div>
</section>
@endif

@include('partials.am-breadcrumbs', ['items' => $breadcrumbs ?? []])

@include('partials.am-collection-gallery-grid', [
    'products' => $products,
    'galleryTitle' => $page['gallery_title'] ?? $pageCategoryLabel,
    'parentCategoryName' => $pageCategoryLabel,
    'shopPageSlug' => $slug,
])

@if($products->isEmpty())
<section class="am-section am-section--cream">
    <div class="am-container am-empty">
        <h2>No designs in this collection yet</h2>
        <p>Browse another shop collection while we add more pieces here.</p>
        <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--outline">Shop Mirror Frames</a>
    </div>
</section>
@elseif(method_exists($products, 'hasPages') && $products->hasPages())
<div class="am-container am-pagination">{{ $products->links('vendor.pagination.amerce') }}</div>
@endif

@endsection
