@props([
    'service',
    'design' => null,
])

@php
    $image = $design?->image ?? $service->image;
    $name = $design?->name ?? $service->name;
    $summary = $design?->description ?? $service->summary;
    $rate = $service->rate_per_sqft ?? 1800;
    $estimateLabel = $service->calculatorEstimateLabel();
@endphp

<section class="am-section am-section--featured am-section--edge">
    <div class="am-section__body">
        <div class="am-featured am-featured--edge am-featured--with-calc">
            <div class="am-featured__image am-featured__image--portrait">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
                @endif
            </div>
            <div class="am-featured__body">
                <p class="am-featured__cat">{{ $service->name }}</p>
                <h2 class="am-featured__name">{{ $name }}</h2>
                <p class="am-featured__meta">Custom fabrication · Pan-India delivery · From ₹{{ number_format($rate, 0) }}/sq ft</p>
                <div class="am-featured__price">
                    <span class="am-featured__price-current">From ₹{{ number_format($rate, 0) }}</span>
                    <span style="font-weight:400;font-size:0.85rem;color:var(--am-muted)">per sq ft</span>
                </div>
                <p class="am-featured__desc">{{ $summary }}</p>
                <p class="am-featured__viewers">Use the calculator to estimate your project — then Order Now for a studio quote.</p>
                @if($design)
                    <a href="{{ route('services.show', $service->slug) }}" class="am-featured__view-link">← All {{ $service->name }} designs</a>
                @elseif($service->has_designs && $service->designs->isNotEmpty())
                    <p class="am-featured__meta" style="margin-top:0.5rem">{{ $service->designs->count() }} design{{ $service->designs->count() === 1 ? '' : 's' }} available below</p>
                @endif
            </div>
            <div class="am-pdp__calc-column">
                @include('partials.am-calculator', [
                    'rate' => $rate,
                    'serviceSlug' => $service->slug,
                    'designSlug' => $design?->slug ?? '',
                    'serviceName' => $name,
                    'calcTitle' => 'Estimate your ' . $estimateLabel,
                ])
                @include('partials.am-pdp-checkout-trust')
            </div>
        </div>
    </div>
</section>
