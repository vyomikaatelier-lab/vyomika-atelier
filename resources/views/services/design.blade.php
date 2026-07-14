@extends('layouts.app')

@section('title', $design->name . ' — ' . $service->name . ' — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3"><a href="{{ route('services.show', $service->slug) }}" class="hover:text-brand-700">{{ $service->name }}</a></p>
    <h1 class="font-serif text-5xl text-brand-900">{{ $design->name }}</h1>
    @if($design->description)
        <p class="text-brand-500 mt-4 max-w-lg mx-auto">{{ $design->description }}</p>
    @endif
</div>

<div class="max-w-7xl mx-auto px-5 py-16">
    <div class="grid lg:grid-cols-3 gap-12">
        <div class="lg:col-span-2">
            @if($design->image)
                <img src="{{ $design->image }}" alt="{{ $design->name }}" class="w-full aspect-[16/10] object-cover mb-8">
            @endif
            @if($design->content)
                <div class="prose-brand text-brand-700 leading-relaxed">{!! $design->content !!}</div>
            @else
                <p class="text-brand-600 leading-relaxed">{{ $design->description }}</p>
            @endif
        </div>
        <aside>
            @if($service->has_calculator)
                <x-price-calculator
                    :service-slug="$service->slug"
                    :design-slug="$design->slug"
                    :service-name="$service->name . ' — ' . $design->name"
                    :rate="$service->rate_per_sqft" />
            @endif
        </aside>
    </div>
</div>
@endsection
