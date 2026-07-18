@extends('layouts.store')

@section('title', 'Studio — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', [
    'title' => 'Custom Architectural Solutions',
    'subtitle' => 'PVD partitions, door systems, rack systems and bespoke metal fabrication — engineered to your drawings.',
    'showLabel' => false,
])

<section class="am-page-body">
    <div class="am-container">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Studio'],
        ]])
        <div class="am-grid-3">
            @foreach($services as $service)
            @php
                $studioSlug = \App\Support\StorefrontRoutes::studioUrlForService($service->slug);
            @endphp
            @if($studioSlug)
            <a href="{{ route('studio.show', $studioSlug) }}" class="am-card">
                <div class="am-card__thumb">
                    @if($service->image)
                    <img src="{{ $service->image }}" alt="{{ $service->name }}" loading="lazy">
                    @endif
                </div>
                <div class="am-card__body">
                    <h2 class="am-card__title">{{ $service->name }}</h2>
                    <p class="am-card__text">{{ $service->summary }}</p>
                    <span class="am-card__text" style="margin-top:1rem;display:inline-block">Order Now →</span>
                </div>
            </a>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endsection
