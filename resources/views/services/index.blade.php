@extends('layouts.app')

@section('title', 'Services — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">What We Do</p>
    <h1 class="font-serif text-5xl text-brand-900">Services</h1>
    <p class="text-brand-500 mt-4 max-w-lg mx-auto">Partitions, façades, door systems, bespoke metalwork, and PVD finishes — engineered and fabricated to specification.</p>
</div>

<div class="max-w-7xl mx-auto px-5 py-16">
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        @foreach($services as $service)
        <a href="{{ route('services.show', $service->slug) }}" class="va-card group block">
            <div class="aspect-[4/3] bg-brand-100 overflow-hidden mb-5">
                @if($service->image)
                    <img src="{{ $service->image }}" alt="{{ $service->name }}" class="w-full h-full object-cover">
                @endif
            </div>
            <h2 class="font-serif text-2xl text-brand-900 group-hover:text-brand-500 transition">{{ $service->name }}</h2>
            <p class="text-brand-500 text-sm mt-2 leading-relaxed">{{ $service->summary }}</p>
            @if($service->has_designs)
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mt-4">{{ $service->designs->count() }} designs →</p>
            @else
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mt-4">View details →</p>
            @endif
        </a>
        @endforeach
    </div>
</div>
@endsection
