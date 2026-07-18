@extends('layouts.store')

@section('title', ($service->meta_title ?? $service->name) . ' — Vyomika Atelier LLP')

@push('meta')
@if($studioUrl = \App\Support\StorefrontRoutes::studioUrlForService($service->slug))
<link rel="canonical" href="{{ route('studio.show', $studioUrl) }}">
@else
<link rel="canonical" href="{{ route('services.show', $service->slug) }}">
@endif
@if($service->meta_description)
<meta name="description" content="{{ $service->meta_description }}">
@endif
@endpush

@section('content')

@php
    $page = config("services.{$service->slug}", []);
    $hero = $page['hero'] ?? null;
@endphp

@if($service->usesGalleryOnlyLayout())
@if(is_array($hero) && !empty($hero['title']))
@include('partials.am-service-hero', ['hero' => $hero])
@else
@include('partials.am-page-hero', [
    'label' => 'Studio',
    'title' => $service->name,
    'subtitle' => \App\Support\ServiceGallery::galleryHeroSubtitle($service, $galleryProducts->count()),
    'showLabel' => false,
])
@endif

@if(!empty($page['intro']['body']))
<section class="am-section am-section--white">
    <div class="am-container am-mirror-frames-intro">
        <h2 class="am-corten-section__title am-corten-section__title--center">{{ $page['intro']['title'] ?? '' }}</h2>
        <p class="am-corten-section__lead am-corten-section__lead--center">{{ $page['intro']['body'] }}</p>
    </div>
</section>
@endif

<section class="am-page-body am-page-body--gallery-only" id="service-gallery">
    <div class="am-container">
        @include('partials.am-service-product-gallery', [
            'products' => $galleryProducts,
            'heading' => \App\Support\ServiceGallery::galleryHeading($service),
            'ctaLabel' => \App\Support\ServiceGallery::galleryCtaLabel($service),
            'serviceSlug' => $service->slug,
            'categoryLabel' => $service->name,
        ])
    </div>
</section>

@else
@include('partials.am-page-hero', [
    'label' => 'Service',
    'title' => $service->name,
    'subtitle' => $service->summary,
])

<section class="am-page-body">
    <div class="am-container">
        <div class="am-split">
            <div>
                @if($service->content)
                    <div class="am-prose" style="margin-bottom:3rem">{!! $service->content !!}</div>
                @endif
            </div>

            <aside>
                <div class="am-card" style="margin-top:0">
                    <div class="am-card__body">
                        <p class="am-card__label" style="margin-bottom:1rem">Enquire</p>
                        <h3 class="am-card__title" style="font-size:1.35rem;margin-bottom:1.5rem">Request Information</h3>
                        <x-lead-form-inline
                            :service-slug="$service->slug"
                            :subject="$service->name . ' enquiry'" />
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
@endif

@endsection
