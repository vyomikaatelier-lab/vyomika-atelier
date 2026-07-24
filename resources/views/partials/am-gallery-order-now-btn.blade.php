@php
    $name = $name ?? '';
    $slug = $slug ?? '';
    $serviceSlug = $serviceSlug ?? '';
    $category = $category ?? '';
    $finish = $finish ?? '';
    $price = $price ?? null;
    $href = $href ?? null;
    $label = $label ?? 'Order Now';
    $class = $class ?? 'am-btn am-btn--card-primary';
    $targetId = filled($href) ? ltrim($href, '#') : '';
@endphp

@if(filled($href))
<a
    href="{{ $href }}"
    class="{{ $class }}"
    data-scroll-to-form
    @if($targetId !== '') data-scroll-target="{{ $targetId }}" @endif
    onclick="return window.amScrollToQuoteForm(@js($targetId), @js($href), event)"
>{{ $label }}</a>
@else
<button type="button"
    class="{{ $class }}"
    data-open-order-popup
    data-open-contact-studio
    data-popup-type="order_now"
    data-product-name="{{ $name }}"
    data-product-slug="{{ $slug }}"
    data-service-slug="{{ $serviceSlug }}"
    @if($category) data-category="{{ $category }}" @endif
    @if($finish) data-finish="{{ $finish }}" @endif
    @if($price !== null && $price !== '') data-price="{{ $price }}" @endif>
    {{ $label }}
</button>
@endif
