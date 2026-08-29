@php
    $variant = \App\Support\StorefrontImage::variant($src ?? null);
    $alt = $alt ?? '';
    $lazy = $lazy ?? true;
@endphp
@if($variant)
    @if(!empty($variant['webp']))
    <picture>
        <source type="image/webp" srcset="{{ $variant['webp'] }}">
        <img
            src="{{ $variant['src'] }}"
            alt="{{ $alt }}"
            @if($variant['width']) width="{{ $variant['width'] }}" @endif
            @if($variant['height']) height="{{ $variant['height'] }}" @endif
            decoding="async"
            @if($lazy) loading="lazy" @endif
        >
    </picture>
    @else
    <img
        src="{{ $variant['src'] }}"
        alt="{{ $alt }}"
        @if($variant['width']) width="{{ $variant['width'] }}" @endif
        @if($variant['height']) height="{{ $variant['height'] }}" @endif
        decoding="async"
        @if($lazy) loading="lazy" @endif
    >
    @endif
@endif
