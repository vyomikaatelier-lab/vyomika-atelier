@extends('layouts.store')

@section('title', ($service->meta_title ?? $service->name) . ' — Vyomika Atelier LLP')

@if($service->meta_description)
    @push('styles')
    <meta name="description" content="{{ $service->meta_description }}">
    @endpush
@endif

@section('content')

@if($service->usesGalleryOnlyLayout())
@include('partials.am-page-hero', [
    'label' => 'Service',
    'title' => $service->name,
    'subtitle' => \App\Support\ServiceGallery::galleryHeroSubtitle($service, $galleryProducts->count()),
])

<section class="am-page-body am-page-body--gallery-only">
    <div class="am-container">
        @include('partials.am-service-product-gallery', [
            'products' => $galleryProducts,
            'heading' => \App\Support\ServiceGallery::galleryHeading($service),
            'ctaLabel' => \App\Support\ServiceGallery::galleryCtaLabel($service),
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
