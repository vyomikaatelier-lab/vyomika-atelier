@props(['option'])

@php
    $dims = \App\Models\Product::mirrorDimensionDisplaysForOption(is_array($option) ? $option : null);
@endphp

@if($dims)
<div class="am-pdp__dimensions am-pdp__dimensions--selected" data-mirror-dim-display aria-label="Selected size dimensions">
    <p class="am-pdp__dimensions-label">Dimensions</p>
    <dl class="am-pdp__dimensions-grid">
        <div class="am-pdp__dimensions-row">
            <dt class="am-pdp__dimensions-unit">Feet</dt>
            <dd class="am-pdp__dimensions-value" data-mirror-dim-feet>{{ $dims['feet'] }}</dd>
        </div>
        <div class="am-pdp__dimensions-row">
            <dt class="am-pdp__dimensions-unit">mm</dt>
            <dd class="am-pdp__dimensions-value" data-mirror-dim-mm>{{ $dims['mm'] }}</dd>
        </div>
        <div class="am-pdp__dimensions-row">
            <dt class="am-pdp__dimensions-unit">cm</dt>
            <dd class="am-pdp__dimensions-value" data-mirror-dim-cm>{{ $dims['cm'] }}</dd>
        </div>
    </dl>
</div>
@endif
