@extends('layouts.store')

@section('title', $design->name . ' — ' . $service->name . ' — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', [
    'label' => $service->name,
    'title' => $design->name,
    'subtitle' => $design->description,
])

@if($service->usesCalculatorPageLayout())
    @include('partials.am-service-featured-calc', ['service' => $service, 'design' => $design])

    <section class="am-page-body">
        <div class="am-container">
            @include('partials.am-product-tabs', [
                'title' => $design->name,
                'descriptionHtml' => $design->content ?: '<p>' . e($design->description) . '</p>',
                'careItems' => $service->careGuidelines(),
                'related' => $related,
            ])
        </div>
    </section>
@else
<section class="am-page-body">
    <div class="am-container">
        <div class="am-split">
            <div>
                @if($design->image)
                    <img src="{{ $design->image }}" alt="{{ $design->name }}" style="width:100%;aspect-ratio:16/10;object-fit:cover;margin-bottom:2rem;border-radius:var(--am-radius-lg)">
                @endif
                @if($design->content)
                    <div class="am-prose">{!! $design->content !!}</div>
                @else
                    <p class="am-prose">{{ $design->description }}</p>
                @endif
            </div>
            <aside>
                @if($service->has_calculator)
                    @include('partials.am-calculator', [
                        'rate' => $service->rate_per_sqft,
                        'serviceSlug' => $service->slug,
                        'designSlug' => $design->slug,
                        'serviceName' => $service->name . ' — ' . $design->name,
                        'calcTitle' => 'Estimate your ' . $service->calculatorEstimateLabel(),
                    ])
                @endif
            </aside>
        </div>
    </div>
</section>
@endif
@endsection
