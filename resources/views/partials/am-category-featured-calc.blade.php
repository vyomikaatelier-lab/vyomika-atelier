@props([
    'title',
    'summary',
    'image' => null,
    'serviceSlug' => 'partitions',
    'calcTitle' => 'Estimate your partition',
    'rate' => 1800,
])

<section class="am-section am-section--featured am-section--edge">
    <div class="am-section__body">
        <div class="am-featured am-featured--edge am-featured--with-calc">
            <div class="am-featured__image am-featured__image--portrait">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $title }}" loading="lazy">
                @endif
            </div>
            <div class="am-featured__body">
                <p class="am-featured__cat">Custom Fabrication</p>
                <h2 class="am-featured__name">{{ $title }}</h2>
                <p class="am-featured__meta">Sq ft calculator · Pan-India delivery · From ₹{{ number_format($rate, 0) }}/sq ft</p>
                <div class="am-featured__price">
                    <span class="am-featured__price-current">From ₹{{ number_format($rate, 0) }}</span>
                    <span style="font-weight:400;font-size:0.85rem;color:var(--am-muted)">per sq ft</span>
                </div>
                <p class="am-featured__desc">{{ $summary }}</p>
                <p class="am-featured__viewers">Calculate your dimensions below, then Order Now for a studio quote.</p>
            </div>
            <div class="am-pdp__calc-column">
                @include('partials.am-calculator', [
                    'rate' => $rate,
                    'serviceSlug' => $serviceSlug,
                    'serviceName' => $title,
                    'calcTitle' => $calcTitle,
                ])
                @include('partials.am-pdp-checkout-trust')
            </div>
        </div>
    </div>
</section>
