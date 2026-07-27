@if($product->hasMirrorDimensions())
@php $dims = $product->mirrorDimensionDisplays(); @endphp
<div class="am-pdp__dimensions" aria-label="Product dimensions">
    <p class="am-pdp__dimensions-label">Dimensions</p>
    <ul class="am-pdp__dimensions-list">
        <li class="am-pdp__dimensions-item">
            <span class="am-pdp__dimensions-unit">ft</span>
            <span class="am-pdp__dimensions-value">{{ $dims['feet'] }}</span>
        </li>
        <li class="am-pdp__dimensions-item">
            <span class="am-pdp__dimensions-unit">mm</span>
            <span class="am-pdp__dimensions-value">{{ $dims['mm'] }}</span>
        </li>
        <li class="am-pdp__dimensions-item">
            <span class="am-pdp__dimensions-unit">cm</span>
            <span class="am-pdp__dimensions-value">{{ $dims['cm'] }}</span>
        </li>
    </ul>
</div>
@endif
