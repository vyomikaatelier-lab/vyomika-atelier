@props(['product'])

@php
    $defaultSize = $product->resolveSizeOption(null);
@endphp

@if($product->hasSizeOptions() && $defaultSize)
<div class="am-pdp-size-selector">
    @include('partials.am-pdp-size-options', [
        'product' => $product,
        'compact' => false,
        'showMirrorDimensions' => $product->isMirrorFrameProduct(),
    ])

    @include('partials.am-pdp-mirror-dimensions-selected', [
        'option' => $defaultSize,
    ])

    @include('partials.am-pdp-size-price', ['selectedSize' => $defaultSize])
</div>
@endif
