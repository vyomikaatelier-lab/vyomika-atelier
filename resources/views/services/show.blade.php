@extends('layouts.app')

@section('title', ($service->meta_title ?? $service->name) . ' — VYOMIKA ATELIER')

@if($service->meta_description)
    @push('styles')
    <meta name="description" content="{{ $service->meta_description }}">
    @endpush
@endif

@section('content')
<div class="relative">
    @if($service->image)
    <div class="h-[50vh] min-h-[320px] bg-brand-900">
        <img src="{{ $service->image }}" alt="{{ $service->name }}" class="w-full h-full object-cover opacity-80">
    </div>
    @endif
    <div class="max-w-7xl mx-auto px-5 {{ $service->image ? '-mt-24 relative z-10' : 'pt-16' }}">
        <div class="bg-brand-50 border border-brand-200 p-8 md:p-12 mb-12">
            <p class="va-label mb-3">Service</p>
            <h1 class="font-serif text-4xl md:text-5xl text-brand-900 mb-4">{{ $service->name }}</h1>
            <p class="text-brand-600 leading-relaxed max-w-3xl">{{ $service->summary }}</p>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-5 pb-20">
    <div class="grid lg:grid-cols-3 gap-12">
        <div class="lg:col-span-2">
            @if($service->content)
                <div class="prose-brand text-brand-700 leading-relaxed mb-12">{!! $service->content !!}</div>
            @endif

            @if($service->has_designs && $service->designs->isNotEmpty())
                <div class="mb-12">
                    <p class="va-label mb-4">Designs</p>
                    <h2 class="font-serif text-3xl text-brand-900 mb-8">
                        @if($service->slug === 'rack-systems-metal-pvd')
                            Available Designs
                        @else
                            Choose a Design
                        @endif
                    </h2>
                    <div class="grid sm:grid-cols-2 gap-6">
                        @foreach($service->designs as $design)
                            @if($service->slug === 'rack-systems-metal-pvd')
                                <div class="border border-brand-200 bg-white p-5">
                                    @if($design->image)
                                        <img src="{{ $design->image }}" alt="{{ $design->name }}" class="w-full aspect-[4/3] object-cover mb-4">
                                    @endif
                                    <h3 class="font-serif text-xl text-brand-900">{{ $design->name }}</h3>
                                    <p class="text-sm text-brand-500 mt-2">{{ $design->description }}</p>
                                </div>
                            @else
                                <a href="{{ route('services.design', [$service->slug, $design->slug]) }}" class="va-card group block border border-brand-200 bg-white p-5 hover:border-brand-400 transition">
                                    @if($design->image)
                                        <img src="{{ $design->image }}" alt="{{ $design->name }}" class="w-full aspect-[4/3] object-cover mb-4">
                                    @endif
                                    <h3 class="font-serif text-xl text-brand-900 group-hover:text-brand-500 transition">{{ $design->name }}</h3>
                                    <p class="text-sm text-brand-500 mt-2">{{ $design->description }}</p>
                                    <span class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mt-4 inline-block">View &amp; calculate →</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="lg:col-span-1">
            @if($service->has_calculator)
                <x-price-calculator
                    :service-slug="$service->slug"
                    :service-name="$service->name"
                    :rate="$service->rate_per_sqft" />
            @elseif($service->usesPopupForm())
                <div class="bg-white border border-brand-200 p-8 text-center">
                    <p class="va-label mb-3">Get a Quote</p>
                    <p class="text-brand-500 text-sm mb-6">Contact us for a detailed quotation tailored to your project.</p>
                    <a href="{{ route('contact.index') }}" class="va-btn-outline">Contact Us</a>
                </div>
            @endif

            @if(!$service->usesPopupForm())
                <div class="mt-8 bg-white border border-brand-200 p-8">
                    <p class="va-label mb-3">Enquire</p>
                    <h3 class="font-serif text-2xl text-brand-900 mb-6">Request Information</h3>
                    <x-lead-form-inline
                        :service-slug="$service->slug"
                        :subject="$service->name . ' enquiry'" />
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
