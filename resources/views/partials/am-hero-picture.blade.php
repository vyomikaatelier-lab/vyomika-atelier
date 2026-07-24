@php
    use App\Support\ResponsiveHero;

    $urls = ResponsiveHero::urls($slide);
    $desktop = $urls['desktop'];
    $tablet = $urls['tablet'];
    $mobile = $urls['mobile'];
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
