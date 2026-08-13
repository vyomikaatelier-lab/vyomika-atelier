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

@endsection
