@if($product->hasMirrorDimensions())
@php $dims = $product->mirrorDimensionDisplays(); @endphp
<div class="am-pdp__dimensions" aria-label="Product dimensions">
    <p class="am-pdp__dimensions-label">Dimensions</p>
    <dl class="am-pdp__dimensions-grid">
        <div class="am-pdp__dimensions-row">
            <dt class="am-pdp__dimensions-unit">Feet</dt>
            <dd class="am-pdp__dimensions-value">{{ $dims['feet'] }}</dd>
        </div>
        <div class="am-pdp__dimensions-row">
            <dt class="am-pdp__dimensions-unit">mm</dt>
            <dd class="am-pdp__dimensions-value">{{ $dims['mm'] }}</dd>
        </div>
        <div class="am-pdp__dimensions-row">
            <dt class="am-pdp__dimensions-unit">cm</dt>
            <dd class="am-pdp__dimensions-value">{{ $dims['cm'] }}</dd>
        </div>
    </dl>
</div>
@endif
