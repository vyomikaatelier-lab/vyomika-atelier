@if($product->hasMirrorDimensions())
@php $dims = $product->mirrorDimensionDisplays(); @endphp
<div class="am-pdp__dimensions" aria-label="Product dimensions">
    <p class="am-pdp__dimensions-label">Dimensions</p>
    <ul class="am-pdp__dimensions-list">
        <li><span class="am-pdp__dimensions-value">{{ $dims['feet'] }}</span></li>
        <li><span class="am-pdp__dimensions-value">{{ $dims['mm'] }}</span></li>
        <li><span class="am-pdp__dimensions-value">{{ $dims['cm'] }}</span></li>
    </ul>
</div>
@endif
