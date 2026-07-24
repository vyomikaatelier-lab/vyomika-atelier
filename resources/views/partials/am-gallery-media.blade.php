@props([
    'image' => null,
    'alt' => '',
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $imageSrc = \App\Support\MediaUrl::resolve(is_string($image) ? $image : null) ?? $image;
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif class="am-design-gallery__media">
    @if($imageSrc)
    <img src="{{ $imageSrc }}" alt="{{ $alt }}" loading="lazy" decoding="async"
        onerror="this.style.display='none';var f=this.parentElement.querySelector('.am-design-gallery__media-fallback');if(f)f.hidden=false;">
    @endif
    <span class="am-design-gallery__media-fallback" @if($imageSrc) hidden @endif aria-hidden="true">VA</span>
</{{ $tag }}>
