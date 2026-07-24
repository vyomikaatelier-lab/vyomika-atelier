@php
    use App\Support\MediaUrl;

    $desktop = MediaUrl::resolve($slide['image'] ?? null) ?? ($slide['image'] ?? '');
    $tablet = MediaUrl::resolve($slide['image_tablet'] ?? null) ?? ($slide['image_tablet'] ?? '');
    $mobile = MediaUrl::resolve($slide['image_mobile'] ?? null) ?? ($slide['image_mobile'] ?? '');
    $alt = $slide['title'] ?? '';
@endphp
@if(filled($desktop) || filled($tablet) || filled($mobile))
<picture>
    @if(filled($mobile))
        <source media="(max-width: 767px)" srcset="{{ $mobile }}">
    @endif
    @if(filled($tablet))
        <source media="(max-width: 1023px)" srcset="{{ $tablet }}">
    @endif
    <img
        src="{{ filled($desktop) ? $desktop : ($tablet ?: $mobile) }}"
        alt="{{ $alt }}"
        @if($priority) fetchpriority="high" @else loading="lazy" @endif
    >
</picture>
@endif
