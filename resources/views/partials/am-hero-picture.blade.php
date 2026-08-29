@php
    use App\Support\ResponsiveHero;

    $picture = ResponsiveHero::picture($slide ?? [], $fallbackDesktop ?? null, $sizes ?? '100vw');
    $priority = $priority ?? false;
    $alt = $slide['title'] ?? '';
@endphp
@if($picture)
<picture>
    @foreach($picture['sources'] as $source)
        <source media="{{ $source['media'] }}" srcset="{{ $source['srcset'] }}"@if(!empty($source['type'])) type="{{ $source['type'] }}" @endif>
    @endforeach
    <img
        src="{{ $picture['src'] }}"
        srcset="{{ $picture['srcset'] }}"
        sizes="{{ $picture['sizes'] }}"
        alt="{{ $alt }}"
        @if($picture['width']) width="{{ $picture['width'] }}" @endif
        @if($picture['height']) height="{{ $picture['height'] }}" @endif
        decoding="async"
        @if($priority) fetchpriority="high" @else loading="lazy" @endif
    >
</picture>
@endif
