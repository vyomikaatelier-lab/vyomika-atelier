@props([
    'path' => null,
    'alt' => '',
    'context' => 'card',
    'priority' => false,
    'class' => '',
    'id' => null,
])

@php
    use App\Services\ProductImageDerivativeService;

    $sources = app(ProductImageDerivativeService::class)->responsiveSources($path, $context);
@endphp

@if($sources)
<picture>
    @if($sources['webp_srcset'] !== '')
    <source type="image/webp" srcset="{{ $sources['webp_srcset'] }}" sizes="{{ $sources['sizes'] }}">
    @endif
    <img
        @if($id) id="{{ $id }}" @endif
        src="{{ $sources['src'] }}"
        @if(str_contains($sources['srcset'], ' '))
        srcset="{{ $sources['srcset'] }}"
        sizes="{{ $sources['sizes'] }}"
        @endif
        alt="{{ $alt }}"
        width="{{ $sources['width'] }}"
        height="{{ $sources['height'] }}"
        class="{{ $class }}"
        decoding="async"
        @if($priority) fetchpriority="high" @else loading="lazy" @endif
    >
</picture>
@endif
