@extends('layouts.store')

@section('title', 'Services — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'What We Do',
    'title' => 'Services',
    'subtitle' => 'Partitions, façades, door systems, bespoke metalwork, and PVD finishes — engineered and fabricated to specification.',
])

<section class="am-page-body">
    <div class="am-container">
        <div class="am-grid-3">
            @foreach($services as $service)
            <a href="{{ route('services.show', $service->slug) }}" class="am-card">
                <div class="am-card__thumb">
                    @if($service->image)
                        <img src="{{ $service->image }}" alt="{{ $service->name }}">
                    @endif
                </div>
                <div class="am-card__body">
                    <h2 class="am-card__title">{{ $service->name }}</h2>
                    <p class="am-card__text">{{ $service->summary }}</p>
                    <span class="am-card__text" style="margin-top:1rem;display:inline-block">
                        @if($service->slug === 'corten-steel-facade')
                            Request quote →
                        @else
                            Order Now →
                        @endif
                    </span>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endsection
