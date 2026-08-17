@props(['product', 'compact' => false, 'showMirrorDimensions' => false])

@php
    $options = $product->hasSizeOptions() ? $product->normalizedSizeOptions() : [];
@endphp

@if($options !== [])
<div class="am-pdp-size{{ $compact ? ' am-pdp-size--compact' : '' }}" data-pdp-size>
    <p class="am-pdp-size__label">
        @if($compact)
        Size
        <span class="am-sr-only" data-size-label>{{ $options[0]['label'] }}</span>
        @else
        {{ $product->isMirrorFrameProduct() ? 'Select size' : 'Size' }}: <span data-size-label>{{ $options[0]['label'] }}</span>
        @endif
    </p>
    <div class="am-size-options {{ $compact ? 'am-size-options--compact' : 'am-size-options--rows' }}" role="listbox" aria-label="Select size">
        @foreach($options as $index => $option)
        @php
            $optDiscount = $option['discount_percent'] ?? null;
            $optCompare = $option['compare_price'] ?? null;
            $dimDetail = $showMirrorDimensions ? ($option['dimension_detail'] ?? null) : null;
            $dimDisplays = $showMirrorDimensions ? \App\Models\Product::mirrorDimensionDisplaysForOption($option) : null;
        @endphp
        <button type="button"
            class="am-size-opt {{ $compact ? 'am-size-opt--pill' : 'am-size-opt--row' }} {{ $index === 0 ? 'is-active' : '' }}"
            role="option"
            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
            data-size-option
            data-size-label="{{ $option['label'] }}"
            data-size-price="{{ $option['price'] }}"
            @if($optCompare) data-size-compare="{{ $optCompare }}" @endif
            @if($optDiscount) data-size-discount="{{ $optDiscount }}" @endif
            @if($dimDisplays) data-size-dim-feet="{{ $dimDisplays['feet'] }}" @endif
            @if($dimDisplays) data-size-dim-mm="{{ $dimDisplays['mm'] }}" @endif
            @if($dimDisplays) data-size-dim-cm="{{ $dimDisplays['cm'] }}" @endif>
            <span class="am-size-opt__label">{{ $option['label'] }}</span>
            @if($dimDetail && ! $compact)
            <span class="am-size-opt__dims">{{ $dimDetail }}</span>
            @endif
            @unless($compact)
            <span class="am-size-opt__pricing">
                <span class="am-size-opt__price">₹{{ number_format($option['price'], 0) }}</span>
                @if($optDiscount && $optCompare)
                <span class="am-size-opt__compare">₹{{ number_format($optCompare, 0) }}</span>
                <span class="am-size-opt__badge">-{{ $optDiscount }}%</span>
                @endif
            </span>
            @endunless
        </button>
        @endforeach
    </div>
</div>
@endif
