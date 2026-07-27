@props(['product'])

@php
    $options = $product->hasSizeOptions() ? $product->normalizedSizeOptions() : [];
@endphp

@if($options !== [])
<div class="am-pdp-size" data-pdp-size>
    <p class="am-pdp-size__label">Size: <span data-size-label>{{ $options[0]['label'] }}</span></p>
    <div class="am-size-options" role="listbox" aria-label="Select size">
        @foreach($options as $index => $option)
        <button type="button"
            class="am-size-opt {{ $index === 0 ? 'is-active' : '' }}"
            role="option"
            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
            data-size-option
            data-size-label="{{ $option['label'] }}"
            data-size-price="{{ $option['price'] }}">
            {{ $option['label'] }}
        </button>
        @endforeach
    </div>
</div>
@endif
