@php
    use App\Support\ResponsiveHero;

    $picture = ResponsiveHero::picture($slide ?? [], $fallbackDesktop ?? null, $sizes ?? '100vw');
    $priority = $priority ?? false;
    $alt = $slide['title'] ?? '';
@endphp
@if($picture)
<picture>
    @foreach($picture['sources'] as $source)
        <source media="{{ $source['media'] }}" srcset="{{ $source['srcset'] }}"
            @if(!empty($source['type'])) type="{{ $source['type'] }}" @endif
            @if(!empty($source['width'])) width="{{ $source['width'] }}" @endif
            @if(!empty($source['height'])) height="{{ $source['height'] }}" @endif>
    @endforeach
    <img
        src="{{ $picture['src'] }}"
        srcset="{{ $picture['srcset'] }}"
        sizes="{{ $picture['sizes'] }}"
        alt="{{ $alt }}"
        @if($picture['width']) width="{{ $picture['width'] }}" @endif
        @if($picture['height']) height="{{ $picture['height'] }}" @endif
        @if(!$priority) decoding="async" @endif
        @if($priority) fetchpriority="high" @else loading="lazy" @endif
    >
</picture>
@endif
