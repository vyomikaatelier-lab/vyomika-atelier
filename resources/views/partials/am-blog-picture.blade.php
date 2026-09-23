@php
    /** @var array{jpeg: ?string, webp: ?string, width: int, height: int} $variant */
    $variant = $variant ?? [];
    $alt = $alt ?? '';
    $lazy = $lazy ?? true;
    $attrs = $attrs ?? '';
    $position = $position ?? 'center';
@endphp
@if(!empty($variant['jpeg']))
<div class="blog-image-frame" style="--image-position: {{ $position }};">
    <picture>
        @if(!empty($variant['webp']))
        <source type="image/webp" srcset="{{ $variant['webp'] }}">
        @endif
        <img
            src="{{ $variant['jpeg'] }}"
            alt="{{ $alt }}"
            width="{{ $variant['width'] }}"
            height="{{ $variant['height'] }}"
            decoding="async"
            @if($lazy) loading="lazy" @else loading="eager" fetchpriority="high" @endif
            {!! $attrs !!}
        >
    </picture>
</div>
@endif
