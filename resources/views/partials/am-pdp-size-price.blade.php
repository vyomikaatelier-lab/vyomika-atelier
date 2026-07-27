@props(['selectedSize'])

@php
    $compare = $selectedSize['compare_price'] ?? null;
    $discount = $selectedSize['discount_percent'] ?? null;
    $showCompare = $compare && (float) $compare > (float) $selectedSize['price'];
@endphp

<div class="am-pdp-buy__price am-featured__price am-featured__price--size-selected">
    <span class="am-featured__price-current" data-pdp-price-display>₹{{ number_format($selectedSize['price'], 0) }}</span>
    <span class="am-featured__price-old" data-pdp-compare-display @unless($showCompare) hidden @endunless>₹{{ number_format($compare ?? 0, 0) }}</span>
    <span class="am-featured__badge" data-pdp-discount-display @unless($showCompare && $discount) hidden @endunless>-{{ $discount }}%</span>
</div>
